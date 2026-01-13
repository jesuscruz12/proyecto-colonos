<?php
/**
 * Dashboard / Home (post-login) para TAKTIK
 * Tablas reales:
 *  - ordenes_trabajo (estado enum: pendiente, planificada, en_proceso, en_cuarentena, cerrada, cancelada)
 *  - tareas          (estado enum: pendiente, programada, en_proceso, pausada, terminada, bloqueada_calidad, scrap)
 *  - usuarios        (activo)
 *  - auditoria
 *
 * REGLAS MVC:
 * - SOLO BD
 * - CERO HTML/echo
 * - NO usa $_GET/$_POST/$_SESSION
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class indexModel extends Model
{
    private const OT_ESTADOS = ['pendiente','planificada','en_proceso','en_cuarentena','cerrada','cancelada'];
    private const T_ESTADOS  = ['pendiente','programada','en_proceso','pausada','terminada','bloqueada_calidad','scrap'];

    public function __construct()
    {
        parent::__construct();
    }

    private function empresaIdOrFail($empresaId): int
    {
        $eid = (int)$empresaId;
        return $eid > 0 ? $eid : 0;
    }

    private function applyDateRange($q, string $col, ?string $desde, ?string $hasta)
    {
        // $desde y $hasta vienen ya validadas (YYYY-MM-DD) desde el controlador
        if ($desde) $q->where($col, '>=', $desde . ' 00:00:00');
        if ($hasta) $q->where($col, '<=', $hasta . ' 23:59:59');
        return $q;
    }

    private function applyEstado($q, string $col, ?string $estado, array $allowed)
    {
        if ($estado && in_array($estado, $allowed, true)) {
            $q->where($col, $estado);
        }
        return $q;
    }

    // =========================
    // KPIs (con filtro opcional)
    // =========================
    public function kpis(int $empresaId, ?string $desde = null, ?string $hasta = null): array
    {
        $empresaId = $this->empresaIdOrFail($empresaId);

        // -------------------
        // OTs (por estado)
        // -------------------
        $ot = Capsule::table('ordenes_trabajo')->where('empresa_id', $empresaId);
        $this->applyDateRange($ot, 'creado_en', $desde, $hasta);

        $ot_total       = (int)(clone $ot)->count();
        $ot_pendiente   = (int)(clone $ot)->where('estado', 'pendiente')->count();
        $ot_planificada = (int)(clone $ot)->where('estado', 'planificada')->count();
        $ot_en_proceso  = (int)(clone $ot)->where('estado', 'en_proceso')->count();
        $ot_cuarentena  = (int)(clone $ot)->where('estado', 'en_cuarentena')->count();
        $ot_cerrada     = (int)(clone $ot)->where('estado', 'cerrada')->count();
        $ot_cancelada   = (int)(clone $ot)->where('estado', 'cancelada')->count();

        // -------------------
        // Tareas (por estado)
        // -------------------
        $ta = Capsule::table('tareas')->where('empresa_id', $empresaId);
        $this->applyDateRange($ta, 'creado_en', $desde, $hasta);

        $t_total        = (int)(clone $ta)->count();
        $t_pendiente    = (int)(clone $ta)->where('estado', 'pendiente')->count();
        $t_programada   = (int)(clone $ta)->where('estado', 'programada')->count();
        $t_en_proceso   = (int)(clone $ta)->where('estado', 'en_proceso')->count();
        $t_pausada      = (int)(clone $ta)->where('estado', 'pausada')->count();
        $t_terminada    = (int)(clone $ta)->where('estado', 'terminada')->count();
        $t_bloq_calidad = (int)(clone $ta)->where('estado', 'bloqueada_calidad')->count();
        $t_scrap        = (int)(clone $ta)->where('estado', 'scrap')->count();

        // Usuarios activos (no depende de fechas normalmente)
        $u_activos = (int) Capsule::table('usuarios')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->count();

        // OTs vencidas: fecha_compromiso < hoy y NO cerrada/cancelada
        // Si hay filtro de fechas, aplicamos sobre fecha_compromiso (más útil que creado_en).
        $hoy = date('Y-m-d');

        $ot_v = Capsule::table('ordenes_trabajo')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('fecha_compromiso')
            ->where('fecha_compromiso', '<', $hoy)
            ->whereNotIn('estado', ['cerrada', 'cancelada']);

        if ($desde) $ot_v->where('fecha_compromiso', '>=', $desde);
        if ($hasta) $ot_v->where('fecha_compromiso', '<=', $hasta);

        $ot_vencidas = (int)$ot_v->count();

        return [
            'empresa_id' => $empresaId,
            'filtro' => ['desde' => $desde, 'hasta' => $hasta],

            'ot_total'       => $ot_total,
            'ot_pendiente'   => $ot_pendiente,
            'ot_planificada' => $ot_planificada,
            'ot_en_proceso'  => $ot_en_proceso,
            'ot_cuarentena'  => $ot_cuarentena,
            'ot_cerrada'     => $ot_cerrada,
            'ot_cancelada'   => $ot_cancelada,

            't_total'        => $t_total,
            't_pendiente'    => $t_pendiente,
            't_programada'   => $t_programada,
            't_en_proceso'   => $t_en_proceso,
            't_pausada'      => $t_pausada,
            't_terminada'    => $t_terminada,
            't_bloq_calidad' => $t_bloq_calidad,
            't_scrap'        => $t_scrap,

            'u_activos'    => $u_activos,
            'ot_vencidas'  => $ot_vencidas,
            't_bloqueadas' => $t_bloq_calidad,
        ];
    }

    // =========================================
    // DataTables: OTs / Tareas / Auditoría
    // =========================================
    public function otsListado(int $empresaId, array $filtros = [], int $limit = 500): array
    {
        $empresaId = $this->empresaIdOrFail($empresaId);
        $limit = max(1, min(5000, (int)$limit));

        $desde  = $filtros['desde']  ?? null;
        $hasta  = $filtros['hasta']  ?? null;
        $estado = $filtros['estado'] ?? null;

        $q = Capsule::table('ordenes_trabajo as ot')
            ->select(
                'ot.id',
                'ot.folio_ot',
                'ot.descripcion',
                'ot.estado',
                'ot.fecha_compromiso',
                'ot.prioridad',
                'ot.numero_dibujo',
                'ot.creado_en'
            )
            ->where('ot.empresa_id', $empresaId);

        $this->applyDateRange($q, 'ot.creado_en', $desde, $hasta);
        $this->applyEstado($q, 'ot.estado', $estado, self::OT_ESTADOS);

        $rows = $q->orderBy('ot.id', 'desc')->limit($limit)->get();
        return $rows ? $rows->toArray() : [];
    }

    public function tareasListado(int $empresaId, array $filtros = [], int $limit = 500): array
    {
        $empresaId = $this->empresaIdOrFail($empresaId);
        $limit = max(1, min(5000, (int)$limit));

        $desde  = $filtros['desde']  ?? null;
        $hasta  = $filtros['hasta']  ?? null;
        $estado = $filtros['estado'] ?? null;

        $q = Capsule::table('tareas as t')
            ->leftJoin('ordenes_trabajo as ot', 'ot.id', '=', 't.orden_trabajo_id')
            ->select(
                't.id',
                't.orden_trabajo_id',
                'ot.folio_ot',
                't.proceso_id',
                't.secuencia',
                't.cantidad',
                't.estado',
                't.inicio_planeado',
                't.fin_planeado',
                't.maquina_id',
                't.creado_en'
            )
            ->where('t.empresa_id', $empresaId);

        $this->applyDateRange($q, 't.creado_en', $desde, $hasta);
        $this->applyEstado($q, 't.estado', $estado, self::T_ESTADOS);

        $rows = $q->orderBy('t.id', 'desc')->limit($limit)->get();
        return $rows ? $rows->toArray() : [];
    }

    public function auditoriaListado(int $empresaId, array $filtros = [], int $limit = 500): array
    {
        $empresaId = $this->empresaIdOrFail($empresaId);
        $limit = max(1, min(5000, (int)$limit));

        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;

        $q = Capsule::table('auditoria as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.actor_usuario_id')
            ->select(
                'a.id',
                'a.accion',
                'a.entidad_tipo',
                'a.entidad_id',
                'a.ip',
                'a.creado_en',
                Capsule::raw("COALESCE(u.nombre,'') as actor_nombre"),
                Capsule::raw("COALESCE(u.email,'') as actor_email")
            )
            ->where('a.empresa_id', $empresaId);

        $this->applyDateRange($q, 'a.creado_en', $desde, $hasta);

        $rows = $q->orderBy('a.id', 'desc')->limit($limit)->get();
        return $rows ? $rows->toArray() : [];
    }
}
