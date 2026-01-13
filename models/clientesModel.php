<?php
// C:\xampp\htdocs\qacrmtaktik\models\clientesModel.php
/**
 * Clientes — TAKTIK (PROD)
 * - Catálogo
 * - DataTables server-side
 * - Borrado lógico (activo)
 * - Unique por empresa (codigo)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class clientesModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }
    private function nullIfEmpty($v) { $v=$this->s($v); return $v===''?null:$v; }

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
        $fActivo = $this->s($q['f_activo'] ?? '');

        $total = (int) Capsule::table('clientes')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('clientes as c')
            ->where('c.empresa_id', $empresaId)
            ->select([
                'c.id',
                'c.codigo',
                'c.nombre',
                'c.email',
                'c.telefono',
                'c.direccion',
                'c.activo',
                Capsule::raw('DATE_FORMAT(c.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
            ]);

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('c.activo', (int)$fActivo);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('c.nombre','like',$like)
                  ->orWhere('c.codigo','like',$like)
                  ->orWhere('c.email','like',$like)
                  ->orWhere('c.telefono','like',$like);
            });
        }

        $filtered = (int) (clone $base)->count();

        $cols = [
            0 => 'c.id',
            1 => 'c.codigo',
            2 => 'c.nombre',
            3 => 'c.email',
            4 => 'c.telefono',
            5 => 'c.activo',
            6 => 'c.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 6);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'c.creado_en';

        $base->orderBy($orderBy, $orderDir);

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
    public function getCli(int $empresaId, int $id): ?array
    {
        $this->empresaOk($empresaId);

        $r = Capsule::table('clientes')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveCli(int $empresaId, array $in): int
    {
        $this->empresaOk($empresaId);

        $id       = $this->i($in['id'] ?? 0);
        $codigo   = $this->nullIfEmpty($in['codigo'] ?? null);
        $nombre   = $this->s($in['nombre'] ?? '');
        $email    = $this->nullIfEmpty($in['email'] ?? null);
        $telefono = $this->nullIfEmpty($in['telefono'] ?? null);
        $direccion= $this->nullIfEmpty($in['direccion'] ?? null);
        $activo   = $this->s($in['activo'] ?? '1');

        if ($nombre === '') throw new Exception('Nombre requerido');
        $activo = ($activo === '0' || $activo === 'false') ? 0 : 1;

        if ($codigo !== null) {
            $dup = Capsule::table('clientes')
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo);
            if ($id > 0) $dup->where('id','<>',$id);
            if ($dup->exists()) throw new Exception('Código duplicado');
        }

        $data = [
            'empresa_id' => $empresaId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'activo' => $activo,
            'actualizado_en' => $this->now(),
        ];

        if ($id > 0) {
            Capsule::table('clientes')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('clientes')->insertGetId($data);
    }

    public function deleteCli(int $empresaId, int $id): void
    {
        $this->empresaOk($empresaId);

        $ok = Capsule::table('clientes')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'activo' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$ok) throw new Exception('No se pudo desactivar');
    }
}
