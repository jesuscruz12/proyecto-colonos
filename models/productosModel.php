<?php
// C:\xampp\htdocs\qacrmtaktik\models\productosModel.php
/**
 * Productos — TAKTIK (PROD)
 * - Catálogo
 * - DataTables server-side + filtros
 * - Borrado lógico (activo=0)
 * - Multiempresa
 * - NO usar métodos save()/update() que choquen con Eloquent Model
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class productosModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Helpers
    // =========================
    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }

    private function nullIfEmpty($v)
    {
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

        $search = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fActivo = $this->s($q['f_activo'] ?? ''); // '', '1','0'

        $total = (int) Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('productos as p')
            ->where('p.empresa_id', $empresaId)
            ->select([
                'p.id',
                'p.nombre',
                'p.descripcion',
                'p.activo',
                Capsule::raw('DATE_FORMAT(p.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(p.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
            ]);

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('p.activo', (int)$fActivo);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('p.nombre','like',$like)
                  ->orWhere('p.descripcion','like',$like);
            });
        }

        // orden DT
        $cols = [
            0 => 'p.id',
            1 => 'p.nombre',
            2 => 'p.descripcion',
            3 => 'p.activo',
            4 => 'p.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 4);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'p.creado_en';

        $base->orderBy($orderBy, $orderDir);

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
    // CRUD
    // =========================
    public function getProd(int $empresaId, int $id): ?array
    {
        $this->empresaOk($empresaId);

        $r = Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveProd(int $empresaId, array $in): int
    {
        $this->empresaOk($empresaId);

        $id    = $this->i($in['id'] ?? 0);
        $nom   = $this->s($in['nombre'] ?? '');
        $desc  = $this->nullIfEmpty($in['descripcion'] ?? null);
        $activo = $this->s($in['activo'] ?? '1');

        if ($nom === '') throw new Exception('Nombre requerido');

        $activo = ($activo === '0' || $activo === 'false') ? 0 : 1;

        // unique nombre por empresa
        $dup = Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nom);

        if ($id > 0) $dup->where('id','<>',$id);
        if ($dup->exists()) throw new Exception('Ya existe un producto con ese nombre');

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $nom,
            'descripcion' => $desc,
            'activo' => $activo,
            'actualizado_en' => $this->now(),
        ];

        if ($id > 0) {
            Capsule::table('productos')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('productos')->insertGetId($data);
    }

    public function deleteProd(int $empresaId, int $id): void
    {
        $this->empresaOk($empresaId);

        $updated = Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'activo' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$updated) throw new Exception('No se pudo desactivar');
    }
}
