<?php
/**
 * calendarioslaboralesModel — TAKTIK (PROD)
 * Tabla: calendarios_laborales
 * - Multi-tenant (empresa_id)
 * - Soft delete: activo=0 + eliminado_en
 * - JSON validado: dias_laborales, pausas
 * - DataTables server-side (activos / eliminados)
 * - CRUD + calendarConfig
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class calendarioslaboralesModel extends Model
{
    protected $table = 'calendarios_laborales';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Helpers
    // =========================
    private function empresaIdOk(int $empresaId): void
    {
        if ($empresaId <= 0) throw new Exception('Empresa inválida');
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function normTime(string $t, string $def): string
    {
        $t = trim($t);
        if ($t === '') $t = $def;

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) {
            throw new Exception('Hora inválida');
        }
        if (strlen($t) === 5) $t .= ':00';
        return $t;
    }

    private function rowToArray($r): array
    {
        $a = (array)$r;
        $a['dias_laborales'] = $a['dias_laborales'] ? json_decode($a['dias_laborales'], true) : [];
        $a['pausas']         = $a['pausas'] ? json_decode($a['pausas'], true) : [];
        return $a;
    }

    private function sanitize(array $in): array
    {
        $nombre = trim((string)($in['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 120) throw new Exception('Nombre inválido');

        $horaInicio = $this->normTime((string)($in['hora_inicio'] ?? ''), '08:00:00');
        $horaFin    = $this->normTime((string)($in['hora_fin'] ?? ''), '18:00:00');

        // dias_laborales puede venir como JSON string desde JS
        $dias = $in['dias_laborales'] ?? [];
        if (is_string($dias)) $dias = json_decode($dias, true);
        if (!is_array($dias)) throw new Exception('Días laborables inválidos');

        // Estructura esperada: [{dow:1..7,inicio:"HH:MM",fin:"HH:MM"}]
        foreach ($dias as $d) {
            $dow = (int)($d['dow'] ?? 0);
            $di  = (string)($d['inicio'] ?? '');
            $df  = (string)($d['fin'] ?? '');
            if ($dow < 1 || $dow > 7) throw new Exception('Día laborable inválido');
            if (!preg_match('/^\d{2}:\d{2}$/', $di)) throw new Exception('Inicio día inválido');
            if (!preg_match('/^\d{2}:\d{2}$/', $df)) throw new Exception('Fin día inválido');
        }

        $pausas = $in['pausas'] ?? null;
        if ($pausas !== null && $pausas !== '') {
            if (is_string($pausas)) $pausas = json_decode($pausas, true);
            if (!is_array($pausas)) throw new Exception('Pausas inválidas');

            // Estructura: [{dow:1..7,inicio:"HH:MM",fin:"HH:MM",nombre:""}]
            foreach ($pausas as $p) {
                $dow = (int)($p['dow'] ?? 0);
                $pi  = (string)($p['inicio'] ?? '');
                $pf  = (string)($p['fin'] ?? '');
                if ($dow < 1 || $dow > 7) throw new Exception('Día pausa inválido');
                if (!preg_match('/^\d{2}:\d{2}$/', $pi)) throw new Exception('Inicio pausa inválido');
                if (!preg_match('/^\d{2}:\d{2}$/', $pf)) throw new Exception('Fin pausa inválido');
            }
        } else {
            $pausas = null;
        }

        return [
            'nombre' => $nombre,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'dias_laborales' => json_encode($dias, JSON_UNESCAPED_UNICODE),
            'pausas' => $pausas === null ? null : json_encode($pausas, JSON_UNESCAPED_UNICODE),
        ];
    }

    // =========================
    // DataTables (activos / eliminados)
    // =========================
    public function dtList(int $empresaId, array $dt): array
    {
        return $this->dtListAll($empresaId, $dt, false);
    }

    public function dtListAll(int $empresaId, array $dt, bool $soloEliminados = false): array
    {
        $this->empresaIdOk($empresaId);

        $draw   = (int)($dt['draw'] ?? 1);
        $start  = (int)($dt['start'] ?? 0);
        $length = (int)($dt['length'] ?? 10);
        $search = trim((string)(($dt['search']['value'] ?? '') ?? ''));

        $base = Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId);

        if ($soloEliminados) {
            $base->where('activo', 0)->whereNotNull('eliminado_en');
        } else {
            $base->where('activo', 1)->whereNull('eliminado_en');
        }

        $total = (clone $base)->count();

        $q = (clone $base);
        if ($search !== '') {
            $q->where('nombre', 'like', "%{$search}%");
        }
        $filtered = (clone $q)->count();

        $cols = ['id', 'nombre', 'hora_inicio', 'hora_fin', 'actualizado_en'];
        $orderColIndex = (int)($dt['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($dt['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy = $cols[$orderColIndex] ?? 'id';

        $rows = $q->select('id', 'nombre', 'hora_inicio', 'hora_fin', 'creado_en', 'actualizado_en', 'eliminado_en')
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

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
    public function getOne(int $empresaId, int $id): ?array
    {
        $this->empresaIdOk($empresaId);

        $r = Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? $this->rowToArray($r) : null;
    }

    public function createCal(int $empresaId, array $in): int
    {
        $this->empresaIdOk($empresaId);

        $data = $this->sanitize($in);
        $data['empresa_id'] = $empresaId;
        $data['activo'] = 1;
        $data['eliminado_en'] = null;
        $data['creado_en'] = $this->now();
        $data['actualizado_en'] = $this->now();

        return (int) Capsule::table('calendarios_laborales')->insertGetId($data);
    }

    public function updateCal(int $empresaId, int $id, array $in): void
    {
        $this->empresaIdOk($empresaId);

        // No permitas editar eliminados (evita confusión)
        $exists = Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->where('activo', 1)
            ->whereNull('eliminado_en')
            ->exists();

        if (!$exists) throw new Exception('No se puede editar: está eliminado o no existe');

        $data = $this->sanitize($in);
        $data['actualizado_en'] = $this->now();

        Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update($data);
    }

    // Soft delete
    public function deleteCal(int $empresaId, int $id): void
    {
        $this->empresaIdOk($empresaId);

        Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->where('activo', 1)
            ->whereNull('eliminado_en')
            ->update([
                'activo' => 0,
                'eliminado_en' => $this->now(),
                'actualizado_en' => $this->now(),
            ]);
    }

    public function restoreCal(int $empresaId, int $id): void
    {
        $this->empresaIdOk($empresaId);

        Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->where('activo', 0)
            ->whereNotNull('eliminado_en')
            ->update([
                'activo' => 1,
                'eliminado_en' => null,
                'actualizado_en' => $this->now(),
            ]);
    }

    // Purge SOLO si ya estaba eliminado
    public function purgeCal(int $empresaId, int $id): void
    {
        $this->empresaIdOk($empresaId);

        Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->where('activo', 0)
            ->whereNotNull('eliminado_en')
            ->delete();
    }
}
