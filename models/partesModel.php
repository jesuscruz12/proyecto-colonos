<?php
// C:\xampp\htdocs\qacrmtaktik\models\partesModel.php
/**
 * Partes — TAKTIK (PROD)
 * - Catálogo multi-empresa
 * - DataTables server-side
 * - Borrado lógico (activo=0)
 * - cliente_id: validar pertenezca a empresa y esté activo
 * - NO usar save()/update() que choquen con Eloquent Model
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class partesModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Helpers
    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }

    private function nullIfEmpty($v) {
        $v = $this->s($v);
        return $v === '' ? null : $v;
    }

    private function empresaOk(int $empresaId): void
    {
        if ($empresaId <= 0) throw new Exception('Empresa inválida');
    }

    // =========================
    // DataTables
    // =========================
    public function dtList(int $empresaId, array $q): array
    {
        $this->empresaOk($empresaId);

        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search  = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fActivo   = $this->s($q['f_activo'] ?? '');     // '', '1','0'
        $fCliente  = $this->i($q['f_cliente_id'] ?? 0);  // 0 = todos

        $total = (int) Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('partes as p')
            ->leftJoin('clientes as c', function($j){
                $j->on('c.id', '=', 'p.cliente_id')
                  ->on('c.empresa_id', '=', 'p.empresa_id');
            })
            ->where('p.empresa_id', $empresaId)
            ->select([
                'p.id',
                'p.numero',
                'p.descripcion',
                'p.material',
                'p.unidad',
                'p.activo',
                'p.cliente_id',
                Capsule::raw('COALESCE(c.nombre,"") as cliente_nombre'),
                Capsule::raw('DATE_FORMAT(p.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(p.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
            ]);

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('p.activo', (int)$fActivo);
        }

        if ($fCliente > 0) {
            $base->where('p.cliente_id', $fCliente);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('p.numero','like',$like)
                  ->orWhere('p.descripcion','like',$like)
                  ->orWhere('p.material','like',$like)
                  ->orWhere('p.unidad','like',$like)
                  ->orWhere(Capsule::raw('COALESCE(c.nombre,"")'),'like',$like);
            });
        }

        // Orden DataTables
        $cols = [
            0 => 'p.id',
            1 => 'p.numero',
            2 => 'p.descripcion',
            3 => 'cliente_nombre',
            4 => 'p.material',
            5 => 'p.unidad',
            6 => 'p.activo',
            7 => 'p.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 7);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'p.creado_en';

        if ($orderBy === 'cliente_nombre') {
            $base->orderByRaw('cliente_nombre '.$orderDir);
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
            'data' => $rows
        ];
    }

    // =========================
    // Catálogos auxiliares
    // =========================
    public function clientesList(int $empresaId): array
    {
        $this->empresaOk($empresaId);

        return Capsule::table('clientes')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->select('id','nombre')
            ->orderBy('nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function unidadesList(int $empresaId): array
    {
        $this->empresaOk($empresaId);

        // unidades existentes (para sugerir)
        return Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('unidad')
            ->where('unidad','<>','')
            ->select('unidad')
            ->distinct()
            ->orderBy('unidad','asc')
            ->pluck('unidad')
            ->toArray();
    }

    // =========================
    // CRUD
    // =========================
    public function getParte(int $empresaId, int $id): ?array
    {
        $this->empresaOk($empresaId);

        $r = Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveParte(int $empresaId, array $in): int
    {
        $this->empresaOk($empresaId);

        $id          = $this->i($in['id'] ?? 0);
        $clienteId   = $this->i($in['cliente_id'] ?? 0);
        $numero      = $this->s($in['numero'] ?? '');
        $desc        = $this->nullIfEmpty($in['descripcion'] ?? null);
        $material    = $this->nullIfEmpty($in['material'] ?? null);
        $unidad      = $this->s($in['unidad'] ?? 'pza');
        $activo      = $this->s($in['activo'] ?? '1');

        if ($numero === '') throw new Exception('Número requerido');
        if ($unidad === '') $unidad = 'pza';

        $activo = ($activo === '0' || $activo === 'false') ? 0 : 1;
        $clienteId = $clienteId > 0 ? $clienteId : null;

        // unique numero por empresa
        $dup = Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->where('numero', $numero);

        if ($id > 0) $dup->where('id','<>',$id);
        if ($dup->exists()) throw new Exception('Ya existe una parte con ese número');

        // valida cliente si viene
        if ($clienteId !== null) {
            $okCli = Capsule::table('clientes')
                ->where('empresa_id', $empresaId)
                ->where('id', $clienteId)
                ->where('activo', 1)
                ->exists();
            if (!$okCli) throw new Exception('Cliente inválido');
        }

        $data = [
            'empresa_id'     => $empresaId,
            'cliente_id'     => $clienteId,
            'numero'         => $numero,
            'descripcion'    => $desc,
            'material'       => $material,
            'unidad'         => $unidad,
            'activo'         => $activo,
            'actualizado_en' => $this->now(),
        ];

        if ($id > 0) {
            Capsule::table('partes')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('partes')->insertGetId($data);
    }

    public function deleteParte(int $empresaId, int $id): void
    {
        $this->empresaOk($empresaId);

        $updated = Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'activo' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$updated) throw new Exception('No se pudo desactivar');
    }
}
