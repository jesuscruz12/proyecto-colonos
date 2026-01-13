<?php
// C:\xampp\htdocs\qacrmtaktik\models\procesosModel.php
/**
 * Procesos — TAKTIK
 * - Catálogo + Asignación a máquinas (proceso_maquina)
 * - DataTables server-side
 * - OJO: NO usar métodos save()/update() (chocan con Eloquent Model)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class procesosModel extends Model
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

    private function normFrecuencia(string $v): string
    {
        $v = $this->s($v);
        return in_array($v, ['orden','lote','pieza'], true) ? $v : 'orden';
    }

    // =========================
    // DataTables
    // =========================
    public function dtList(int $empresaId, array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fActivo = $this->s($q['f_activo'] ?? ''); // '', '1', '0'

        $base = Capsule::table('procesos as p')
            ->where('p.empresa_id', $empresaId)
            ->select([
                'p.id',
                'p.nombre',
                'p.setup_minutos',
                'p.frecuencia_setup',
                'p.activo',
                Capsule::raw('DATE_FORMAT(p.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(p.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
                Capsule::raw('(SELECT COUNT(*) FROM proceso_maquina pm WHERE pm.proceso_id = p.id) as maquinas_count'),
            ]);

        $total = (int) Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->count();

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('p.activo', (int)$fActivo);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('p.nombre','like',$like)
                  ->orWhere('p.frecuencia_setup','like',$like)
                  ->orWhere('p.setup_minutos','like',$like);
            });
        }

        // orden DT
        $cols = [
            0 => 'p.id',
            1 => 'p.nombre',
            2 => 'p.setup_minutos',
            3 => 'p.frecuencia_setup',
            4 => 'p.activo',
            5 => 'maquinas_count',
            6 => 'p.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 6);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'p.creado_en';

        if ($orderBy === 'maquinas_count') {
            $base->orderByRaw('maquinas_count '.$orderDir);
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
    // CRUD Proceso
    // =========================
    public function getProc(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    // ✅ NO se llama save() para no chocar con Eloquent
    public function saveProc(int $empresaId, array $in): int
    {
        $id      = $this->i($in['id'] ?? 0);
        $nombre  = $this->s($in['nombre'] ?? '');
        $setup   = $this->i($in['setup_minutos'] ?? 0);
        $freq    = $this->normFrecuencia($in['frecuencia_setup'] ?? 'orden');
        $activo  = $this->s($in['activo'] ?? '1');

        if ($nombre === '') throw new Exception('Nombre requerido');

        $setup  = max(0, $setup);
        $activo = ($activo === '0' || $activo === 'false') ? 0 : 1;

        // unique por empresa (nombre)
        $dup = Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nombre);

        if ($id > 0) $dup->where('id','<>',$id);

        if ($dup->exists()) throw new Exception('Ya existe un proceso con ese nombre');

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'setup_minutos' => $setup,
            'frecuencia_setup' => $freq,
            'activo' => $activo,
            'actualizado_en' => $this->now()
        ];

        if ($id > 0) {
            Capsule::table('procesos')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('procesos')->insertGetId($data);
    }

    public function deleteProc(int $empresaId, int $id): void
    {
        // ✅ BORRADO LÓGICO: desactivar
        // NO borra pivote: si luego reactivas, conserva asignaciones
        $updated = Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'activo' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$updated) {
            throw new Exception('No se pudo desactivar (ID inválido)');
        }
    }

    // =========================
    // Máquinas / Asignación
    // =========================
    public function maquinasActivas(int $empresaId): array
    {
        return Capsule::table('maquinas')
            ->select('id','codigo','nombre','tipo','activo')
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function maquinasAsignadas(int $empresaId, int $procesoId): array
    {
        $ok = Capsule::table('procesos')
            ->where('empresa_id',$empresaId)
            ->where('id',$procesoId)
            ->exists();
        if (!$ok) return [];

        return Capsule::table('proceso_maquina as pm')
            ->join('maquinas as m','m.id','=','pm.maquina_id')
            ->where('m.empresa_id', $empresaId)
            ->where('pm.proceso_id', $procesoId)
            ->select('m.id','m.codigo','m.nombre','m.tipo','m.activo')
            ->orderBy('m.nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function setAsignacionMaquinas(int $empresaId, int $procesoId, array $maquinaIds): void
    {
        $ok = Capsule::table('procesos')
            ->where('empresa_id',$empresaId)
            ->where('id',$procesoId)
            ->exists();
        if (!$ok) throw new Exception('Proceso inválido');

        $validIds = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $maquinaIds ?: [-1])
            ->pluck('id')
            ->toArray();

        Capsule::beginTransaction();
        try {
            Capsule::table('proceso_maquina')->where('proceso_id', $procesoId)->delete();

            $rows = [];
            foreach ($validIds as $mid) {
                $rows[] = ['proceso_id' => $procesoId, 'maquina_id' => (int)$mid];
            }
            if (!empty($rows)) Capsule::table('proceso_maquina')->insert($rows);

            Capsule::commit();
        } catch(Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }
}
