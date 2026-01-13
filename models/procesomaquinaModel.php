<?php
// C:\xampp\htdocs\qacrmtaktik\models\procesomaquinaModel.php
/**
 * Proceso ↔ Máquina — TAKTIK (PROD)
 * - Asigna procesos permitidos por máquina
 * - DataTables server-side (máquinas + procesos)
 * - Multi-tenant safe aunque proceso_maquina NO tenga empresa_id:
 *   valida máquina pertenece a empresa y procesos pertenecen a empresa
 *   y al guardar borra SOLO mappings ligados a procesos de la empresa.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class procesomaquinaModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }

    private function empresaOk(int $empresaId): void
    {
        if ($empresaId <= 0) throw new Exception('Empresa inválida');
    }

    private function maquinaValida(int $empresaId, int $maquinaId): bool
    {
        return Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->where('id', $maquinaId)
            ->exists();
    }

    // =========================
    // DataTables: Máquinas
    // =========================
    public function dtMaquinas(int $empresaId, array $q): array
    {
        $this->empresaOk($empresaId);

        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search = $this->s($q['search']['value'] ?? '');

        $total = (int) Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('maquinas as m')
            ->where('m.empresa_id', $empresaId)
            ->select([
                'm.id',
                'm.codigo',
                'm.nombre',
                'm.tipo',
                'm.activo',
                Capsule::raw('(SELECT COUNT(*) FROM proceso_maquina pm WHERE pm.maquina_id = m.id) as procesos_count'),
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('m.nombre','like',$like)
                  ->orWhere('m.codigo','like',$like)
                  ->orWhere('m.tipo','like',$like);
            });
        }

        $filtered = (int) (clone $base)->count();

        $cols = [
            0 => 'm.id',
            1 => 'm.codigo',
            2 => 'm.nombre',
            3 => 'm.tipo',
            4 => 'm.activo',
            5 => 'procesos_count',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 0);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy  = $cols[$orderCol] ?? 'm.id';

        if ($orderBy === 'procesos_count') $base->orderByRaw('procesos_count '.$orderDir);
        else $base->orderBy($orderBy, $orderDir);

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
    // DataTables: Procesos (con flag asignado)
    // =========================
    public function dtProcesos(int $empresaId, int $maquinaId, array $q): array
    {
        $this->empresaOk($empresaId);

        if ($maquinaId <= 0) {
            return [
                'draw' => $this->i($q['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ];
        }

        // valida que la máquina sea de la empresa (multi-tenant)
        if (!$this->maquinaValida($empresaId, $maquinaId)) {
            throw new Exception('Máquina inválida');
        }

        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search = $this->s($q['search']['value'] ?? '');
        $fActivo = $this->s($q['f_activo'] ?? '1'); // default solo activos

        $total = (int) Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->count();

        $base = Capsule::table('procesos as p')
            ->leftJoin('proceso_maquina as pm', function($j) use ($maquinaId) {
                $j->on('pm.proceso_id', '=', 'p.id')
                  ->where('pm.maquina_id', '=', $maquinaId);
            })
            ->where('p.empresa_id', $empresaId)
            ->select([
                'p.id',
                'p.nombre',
                'p.setup_minutos',
                'p.frecuencia_setup',
                'p.activo',
                Capsule::raw('CASE WHEN pm.proceso_id IS NULL THEN 0 ELSE 1 END as asignado'),
            ]);

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('p.activo', (int)$fActivo);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('p.nombre','like',$like)
                  ->orWhere('p.frecuencia_setup','like',$like);
            });
        }

        $filtered = (int) (clone $base)->count();

        $cols = [
            0 => 'asignado',
            1 => 'p.id',
            2 => 'p.nombre',
            3 => 'p.setup_minutos',
            4 => 'p.frecuencia_setup',
            5 => 'p.activo',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 2);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy  = $cols[$orderCol] ?? 'p.nombre';

        if ($orderBy === 'asignado') $base->orderByRaw('asignado '.$orderDir);
        else $base->orderBy($orderBy, $orderDir);

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
    // Guardar asignación (batch)
    // =========================
    public function saveAsignacion(int $empresaId, int $uid, int $maquinaId, array $procesosIds): array
    {
        $this->empresaOk($empresaId);

        if ($maquinaId <= 0) throw new Exception('Máquina inválida');
        if (!$this->maquinaValida($empresaId, $maquinaId)) throw new Exception('Máquina inválida');

        // normaliza ids
        $ids = [];
        foreach ($procesosIds as $x) {
            $v = (int)$x;
            if ($v > 0) $ids[$v] = true;
        }
        $ids = array_keys($ids);

        // valida que todos los procesos sean de la empresa (y opcional: activos)
        if (!empty($ids)) {
            $validCount = (int) Capsule::table('procesos')
                ->where('empresa_id', $empresaId)
                ->whereIn('id', $ids)
                ->count();

            if ($validCount !== count($ids)) {
                throw new Exception('Uno o más procesos son inválidos');
            }
        }

        Capsule::beginTransaction();
        try {
            // BORRA SOLO mappings ligados a procesos de ESTA empresa
            $empresaProcIds = Capsule::table('procesos')
                ->where('empresa_id', $empresaId)
                ->pluck('id')
                ->toArray();

            if (!empty($empresaProcIds)) {
                // chunk delete para no reventar placeholders
                $chunks = array_chunk($empresaProcIds, 800);
                foreach ($chunks as $ch) {
                    Capsule::table('proceso_maquina')
                        ->where('maquina_id', $maquinaId)
                        ->whereIn('proceso_id', $ch)
                        ->delete();
                }
            } else {
                // no hay procesos en empresa, nada que borrar
            }

            // inserta nuevos
            $ins = [];
            foreach ($ids as $pid) {
                $ins[] = [
                    'proceso_id' => $pid,
                    'maquina_id' => $maquinaId,
                ];
            }

            if (!empty($ins)) {
                // insert masivo
                Capsule::table('proceso_maquina')->insert($ins);
            }

            Capsule::commit();

            return [
                'ok' => true,
                'asignados' => count($ids),
            ];
        } catch (Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }
}
