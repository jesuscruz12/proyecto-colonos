<?php
// C:\xampp\htdocs\qacrmtaktik\models\embarquesModel.php
/**
 * Embarques — TAKTIK (PROD)
 * - DataTables server-side
 * - CRUD Embarque + Items
 * - Multi-tenant safe (empresa_id)
 * - NO usa save()/update() para no chocar con Eloquent Model
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class embarquesModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // -------------------------
    // Helpers
    // -------------------------
    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }

    private function empresaOk(int $empresaId): void
    {
        if ($empresaId <= 0) throw new Exception('Empresa inválida');
    }

    private function parseDt(?string $v): ?string
    {
        $v = $this->s($v);
        if ($v === '') return null;

        // acepta "YYYY-MM-DDTHH:MM" o "YYYY-MM-DD HH:MM"
        $v = str_replace('T', ' ', $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $v)) $v .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $v)) {
            throw new Exception('Fecha/hora inválida');
        }
        return $v;
    }

    // -------------------------
    // DataTables
    // -------------------------
    public function dtList(int $empresaId, array $q): array
    {
        $this->empresaOk($empresaId);

        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fEstado = $this->s($q['f_estado'] ?? ''); // '', preparando, liberado_calidad, enviado, entregado
        $fDesde  = $this->s($q['f_desde'] ?? '');  // YYYY-MM-DD
        $fHasta  = $this->s($q['f_hasta'] ?? '');  // YYYY-MM-DD

        $total = (int) Capsule::table('embarques')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('embarques as e')
            ->join('ordenes_trabajo as ot', function($j){
                $j->on('ot.id', '=', 'e.orden_trabajo_id')
                  ->on('ot.empresa_id', '=', 'e.empresa_id');
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'e.creado_por')
            ->leftJoin('clientes as c', function($j){
                $j->on('c.id','=','ot.cliente_id')
                  ->on('c.empresa_id','=','ot.empresa_id');
            })
            ->where('e.empresa_id', $empresaId)
            ->select([
                'e.id',
                'e.folio',
                'e.estado',
                Capsule::raw('DATE_FORMAT(e.fecha_envio,"%Y-%m-%d %H:%i") as fecha_envio'),
                Capsule::raw('DATE_FORMAT(e.fecha_entrega,"%Y-%m-%d %H:%i") as fecha_entrega'),
                'e.notas',
                'e.orden_trabajo_id',
                'ot.folio_ot',
                'ot.estado as ot_estado',
                'ot.prioridad as ot_prioridad',
                Capsule::raw('COALESCE(c.nombre,"") as cliente_nombre'),
                Capsule::raw('COALESCE(u.nombre,"") as creado_por_nombre'),
                Capsule::raw('DATE_FORMAT(e.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('(SELECT COUNT(*) FROM embarque_items ei WHERE ei.embarque_id = e.id) as items_count'),
                Capsule::raw('(SELECT COALESCE(SUM(ei.cantidad),0) FROM embarque_items ei WHERE ei.embarque_id = e.id) as qty_total'),
            ]);

        if ($fEstado !== '') {
            $base->where('e.estado', $fEstado);
        }

        if ($fDesde !== '') {
            // usa creado_en como “rango” por defecto (no fecha_envio porque puede ser null)
            $base->whereDate('e.creado_en', '>=', $fDesde);
        }
        if ($fHasta !== '') {
            $base->whereDate('e.creado_en', '<=', $fHasta);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('e.folio','like',$like)
                  ->orWhere('ot.folio_ot','like',$like)
                  ->orWhere('e.notas','like',$like)
                  ->orWhere(Capsule::raw('COALESCE(c.nombre,"")'),'like',$like);
            });
        }

        $cols = [
            0 => 'e.id',
            1 => 'e.folio',
            2 => 'ot.folio_ot',
            3 => 'cliente_nombre',
            4 => 'e.estado',
            5 => 'e.fecha_envio',
            6 => 'e.fecha_entrega',
            7 => 'items_count',
            8 => 'qty_total',
            9 => 'e.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 9);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'e.creado_en';

        if (in_array($orderBy, ['items_count','qty_total','cliente_nombre'], true)) {
            $base->orderByRaw($orderBy.' '.$orderDir);
        } else {
            $base->orderBy($orderBy, $orderDir);
        }

        $filtered = (int) (clone $base)->count();

        $rows = $base->offset($start)->limit($length)->get()
            ->map(fn($r)=>(array)$r)->toArray();

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ];
    }

    // -------------------------
    // Meta
    // -------------------------
    public function meta(int $empresaId): array
    {
        $this->empresaOk($empresaId);

        return [
            'estados' => [
                ['id'=>'preparando','nombre'=>'Preparando'],
                ['id'=>'liberado_calidad','nombre'=>'Liberado calidad'],
                ['id'=>'enviado','nombre'=>'Enviado'],
                ['id'=>'entregado','nombre'=>'Entregado'],
            ],
        ];
    }

    // -------------------------
    // GET
    // -------------------------
    public function getEmb(int $empresaId, int $id): ?array
    {
        $this->empresaOk($empresaId);

        $h = Capsule::table('embarques as e')
            ->join('ordenes_trabajo as ot', function($j){
                $j->on('ot.id','=','e.orden_trabajo_id')
                  ->on('ot.empresa_id','=','e.empresa_id');
            })
            ->where('e.empresa_id', $empresaId)
            ->where('e.id', $id)
            ->select([
                'e.*',
                'ot.folio_ot',
                'ot.estado as ot_estado',
                'ot.prioridad as ot_prioridad',
            ])
            ->first();

        if (!$h) return null;

        $items = Capsule::table('embarque_items as ei')
            ->leftJoin('partes as p', 'p.id', '=', 'ei.parte_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'ei.producto_id')
            ->where('ei.embarque_id', $id)
            ->select([
                'ei.id',
                'ei.tipo_item',
                'ei.parte_id',
                'ei.producto_id',
                'ei.lote_id',
                'ei.cantidad',
                Capsule::raw('COALESCE(p.nombre,"") as parte_nombre'),
                Capsule::raw('COALESCE(pr.nombre,"") as producto_nombre'),
            ])
            ->orderBy('ei.id','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();

        return [
            'header' => (array)$h,
            'items'  => $items,
        ];
    }

    // -------------------------
    // SEARCH OTs / Partes / Productos
    // -------------------------
    public function searchOts(int $empresaId, string $q, int $limit=25): array
    {
        $this->empresaOk($empresaId);
        $q = $this->s($q);
        $limit = max(1, min(50, $limit));

        $sql = Capsule::table('ordenes_trabajo as ot')
            ->leftJoin('clientes as c', function($j){
                $j->on('c.id','=','ot.cliente_id')
                  ->on('c.empresa_id','=','ot.empresa_id');
            })
            ->where('ot.empresa_id', $empresaId)
            ->select([
                'ot.id',
                'ot.folio_ot',
                'ot.estado',
                'ot.prioridad',
                Capsule::raw('COALESCE(ot.descripcion,"") as descripcion'),
                Capsule::raw('COALESCE(c.nombre,"") as cliente_nombre'),
            ])
            ->orderBy('ot.id','desc')
            ->limit($limit);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $sql->where(function($w) use ($like){
                $w->where('ot.folio_ot','like',$like)
                  ->orWhere('ot.descripcion','like',$like)
                  ->orWhere(Capsule::raw('COALESCE(c.nombre,"")'),'like',$like);
            });
        }

        return $sql->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function searchPartes(int $empresaId, string $q, int $limit=25): array
    {
        $this->empresaOk($empresaId);
        $q = $this->s($q);
        $limit = max(1, min(50, $limit));

        $sql = Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->select(['id','codigo','nombre'])
            ->orderBy('nombre','asc')
            ->limit($limit);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $sql->where(function($w) use ($like){
                $w->where('nombre','like',$like)->orWhere('codigo','like',$like);
            });
        }

        return $sql->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function searchProductos(int $empresaId, string $q, int $limit=25): array
    {
        $this->empresaOk($empresaId);
        $q = $this->s($q);
        $limit = max(1, min(50, $limit));

        $sql = Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->select(['id','codigo','nombre'])
            ->orderBy('nombre','asc')
            ->limit($limit);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $sql->where(function($w) use ($like){
                $w->where('nombre','like',$like)->orWhere('codigo','like',$like);
            });
        }

        return $sql->get()->map(fn($r)=>(array)$r)->toArray();
    }

    // -------------------------
    // SAVE (header + items)
    // -------------------------
    public function saveEmb(int $empresaId, int $uid, array $in): int
    {
        $this->empresaOk($empresaId);

        $id   = $this->i($in['id'] ?? 0);
        $otId = $this->i($in['orden_trabajo_id'] ?? 0);
        $folio = $this->s($in['folio'] ?? '');
        $estado = $this->s($in['estado'] ?? 'preparando');
        $notas  = $this->s($in['notas'] ?? '');

        $fechaEnvio    = $this->parseDt($in['fecha_envio'] ?? null);
        $fechaEntrega  = $this->parseDt($in['fecha_entrega'] ?? null);

        if ($otId <= 0) throw new Exception('OT requerida');

        $okOt = Capsule::table('ordenes_trabajo')
            ->where('empresa_id', $empresaId)
            ->where('id', $otId)
            ->exists();
        if (!$okOt) throw new Exception('OT inválida');

        $validEstados = ['preparando','liberado_calidad','enviado','entregado'];
        if (!in_array($estado, $validEstados, true)) throw new Exception('Estado inválido');

        if ($fechaEntrega !== null && $fechaEnvio === null) {
            throw new Exception('Si hay entrega, captura fecha de envío');
        }
        if ($fechaEnvio !== null && $fechaEntrega !== null) {
            if (strtotime($fechaEntrega) < strtotime($fechaEnvio)) {
                throw new Exception('Entrega no puede ser menor que envío');
            }
        }

        // items vienen como JSON string en 'items_json'
        $itemsJson = $this->s($in['items_json'] ?? '');
        if ($itemsJson === '') throw new Exception('Items requeridos');

        $items = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) <= 0) throw new Exception('Items inválidos');

        // valida items
        $clean = [];
        foreach ($items as $it) {
            $tipo = $this->s($it['tipo_item'] ?? '');
            $cant = (float)($it['cantidad'] ?? 0);

            $parteId = $this->i($it['parte_id'] ?? 0);
            $prodId  = $this->i($it['producto_id'] ?? 0);
            $loteId  = $this->i($it['lote_id'] ?? 0);

            if (!in_array($tipo, ['parte','producto'], true)) throw new Exception('Tipo item inválido');
            if ($cant <= 0) throw new Exception('Cantidad inválida');

            if ($tipo === 'parte') {
                if ($parteId <= 0) throw new Exception('Parte requerida');
                $ok = Capsule::table('partes')->where('empresa_id',$empresaId)->where('id',$parteId)->exists();
                if (!$ok) throw new Exception('Parte inválida');
                $prodId = null;
            } else {
                if ($prodId <= 0) throw new Exception('Producto requerido');
                $ok = Capsule::table('productos')->where('empresa_id',$empresaId)->where('id',$prodId)->exists();
                if (!$ok) throw new Exception('Producto inválido');
                $parteId = null;
            }

            $clean[] = [
                'tipo_item' => $tipo,
                'parte_id' => $parteId ?: null,
                'producto_id' => $prodId ?: null,
                'lote_id' => $loteId > 0 ? $loteId : null,
                'cantidad' => $cant,
            ];
        }

        return Capsule::connection()->transaction(function() use ($empresaId,$uid,$id,$otId,$folio,$estado,$fechaEnvio,$fechaEntrega,$notas,$clean){
            $data = [
                'empresa_id' => $empresaId,
                'orden_trabajo_id' => $otId,
                'folio' => ($folio !== '' ? $folio : null),
                'estado' => $estado,
                'fecha_envio' => $fechaEnvio,
                'fecha_entrega' => $fechaEntrega,
                'notas' => ($notas !== '' ? $notas : null),
                'actualizado_en' => $this->now(),
            ];

            if ($id > 0) {
                $ok = Capsule::table('embarques')
                    ->where('empresa_id',$empresaId)
                    ->where('id',$id)
                    ->exists();
                if (!$ok) throw new Exception('Embarque no encontrado');

                Capsule::table('embarques')
                    ->where('empresa_id',$empresaId)
                    ->where('id',$id)
                    ->update($data);

                // reemplaza items (simple y seguro)
                Capsule::table('embarque_items')->where('embarque_id',$id)->delete();
            } else {
                $data['creado_por'] = $uid > 0 ? $uid : null;
                $data['creado_en']  = $this->now();
                $id = (int) Capsule::table('embarques')->insertGetId($data);
            }

            foreach ($clean as $it) {
                Capsule::table('embarque_items')->insert([
                    'embarque_id' => $id,
                    'tipo_item' => $it['tipo_item'],
                    'parte_id' => $it['parte_id'],
                    'producto_id' => $it['producto_id'],
                    'lote_id' => $it['lote_id'],
                    'cantidad' => $it['cantidad'],
                ]);
            }

            return $id;
        });
    }

    // -------------------------
    // DELETE (borra items y header)
    // -------------------------
    public function deleteEmb(int $empresaId, int $id): void
    {
        $this->empresaOk($empresaId);
        if ($id <= 0) throw new Exception('ID inválido');

        $h = Capsule::table('embarques')
            ->where('empresa_id',$empresaId)
            ->where('id',$id)
            ->select('estado')
            ->first();

        if (!$h) throw new Exception('No encontrado');

        // regla simple: solo borrar si sigue "preparando"
        if (($h->estado ?? '') !== 'preparando') {
            throw new Exception('Solo se puede eliminar en estado "preparando"');
        }

        Capsule::connection()->transaction(function() use ($empresaId,$id){
            Capsule::table('embarque_items')->where('embarque_id',$id)->delete();
            Capsule::table('embarques')->where('empresa_id',$empresaId)->where('id',$id)->delete();
        });
    }
}
