<?php
/**
 * Planeación — Modelo TAKTIK (PRO)
 * - DataTables server-side de OTs pendientes
 * - Detalle OT (items + operaciones + tiempos)
 * - Generación de tareas con validaciones (anti duplicado + rutas completas)
 * - Programación automática:
 *    - asigna maquina_id por proceso_maquina
 *    - calcula inicio_planeado/fin_planeado usando calendarios_laborales
 *    - usa calendario de maquina si existe, si no usa calendario activo de empresa
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class planeacionModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function empresaId($empresaId): int
    {
        return (int)$empresaId;
    }

    private function nowSql(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function isListArray($arr): bool
    {
        if (!is_array($arr)) return false;
        $i = 0;
        foreach ($arr as $k => $v) {
            if ($k !== $i) return false;
            $i++;
        }
        return true;
    }

    // =========================================================
    // DataTables: OTs pendientes
    // =========================================================
    public function dtOtsPendientes(int $empresaId, array $dt): array
    {
        $empresaId = $this->empresaId($empresaId);

        $draw   = (int)($dt['draw'] ?? 1);
        $start  = max(0, (int)($dt['start'] ?? 0));
        $length = (int)($dt['length'] ?? 25);
        if ($length <= 0) $length = 25;
        if ($length > 100) $length = 100;

        $search = trim((string)($dt['search']['value'] ?? ''));

        $fPrioridad = trim((string)($dt['f_prioridad'] ?? ''));
        $fDesde     = trim((string)($dt['f_desde'] ?? ''));
        $fHasta     = trim((string)($dt['f_hasta'] ?? ''));

        $cols = [
            0 => 'ot.folio_ot',
            1 => 'ot.descripcion',
            2 => 'c.nombre',
            3 => 'ot.fecha_compromiso',
            4 => 'ot.prioridad',
            5 => 'items_count',
            6 => 'tareas_count',
            7 => 'u.nombre',
            8 => 'ot.creado_en',
        ];

        $orderColIdx = (int)($dt['order'][0]['column'] ?? 3);
        $orderDir    = strtolower((string)($dt['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderCol    = $cols[$orderColIdx] ?? 'ot.fecha_compromiso';

        $recordsTotal = (int) Capsule::table('ordenes_trabajo')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'pendiente')
            ->count();

        $base = Capsule::table('ordenes_trabajo as ot')
            ->leftJoin('clientes as c', 'c.id', '=', 'ot.cliente_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'ot.creado_por')
            ->leftJoin(
                Capsule::raw('(SELECT orden_trabajo_id, COUNT(*) items_count FROM ordenes_trabajo_items GROUP BY orden_trabajo_id) it'),
                'it.orden_trabajo_id',
                '=',
                'ot.id'
            )
            ->leftJoin(
                Capsule::raw('(SELECT orden_trabajo_id, COUNT(*) tareas_count FROM tareas GROUP BY orden_trabajo_id) t'),
                't.orden_trabajo_id',
                '=',
                'ot.id'
            )
            ->where('ot.empresa_id', $empresaId)
            ->where('ot.estado', 'pendiente');

        if ($fPrioridad !== '') $base->where('ot.prioridad', $fPrioridad);
        if ($fDesde !== '')     $base->where('ot.fecha_compromiso', '>=', $fDesde);
        if ($fHasta !== '')     $base->where('ot.fecha_compromiso', '<=', $fHasta);

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('ot.folio_ot', 'like', "%{$search}%")
                  ->orWhere('ot.descripcion', 'like', "%{$search}%")
                  ->orWhere('c.nombre', 'like', "%{$search}%")
                  ->orWhere('ot.numero_dibujo', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (int) (clone $base)->count('ot.id');

        $rows = $base
            ->select([
                'ot.id',
                'ot.folio_ot',
                'ot.numero_dibujo',
                'ot.descripcion',
                'ot.fecha_compromiso',
                'ot.prioridad',
                'ot.estado',
                'ot.creado_en',
                Capsule::raw('COALESCE(c.nombre,"") as cliente_nombre'),
                Capsule::raw('COALESCE(it.items_count,0) as items_count'),
                Capsule::raw('COALESCE(t.tareas_count,0) as tareas_count'),
                Capsule::raw('COALESCE(u.nombre,"") as creado_por_nombre'),
            ])
            ->orderBy($orderCol, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows
        ];
    }

    // =========================================================
    // Detalle OT (header)
    // =========================================================
    public function getOtHeader(int $empresaId, int $otId): ?array
    {
        $empresaId = $this->empresaId($empresaId);

        $r = Capsule::table('ordenes_trabajo as ot')
            ->leftJoin('clientes as c', 'c.id', '=', 'ot.cliente_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'ot.creado_por')
            ->where('ot.empresa_id', $empresaId)
            ->where('ot.id', $otId)
            ->select([
                'ot.id',
                'ot.folio_ot',
                'ot.numero_dibujo',
                'ot.descripcion',
                'ot.fecha_compromiso',
                'ot.prioridad',
                'ot.estado',
                'ot.creado_en',
                Capsule::raw('COALESCE(c.nombre,"") as cliente_nombre'),
                Capsule::raw('COALESCE(u.nombre,"") as creado_por_nombre'),
            ])
            ->first();

        return $r ? (array)$r : null;
    }

    // =========================================================
    // Items OT
    // =========================================================
    public function getOtItems(int $empresaId, int $otId): array
    {
        $empresaId = $this->empresaId($empresaId);

        return Capsule::table('ordenes_trabajo_items as i')
            ->join('ordenes_trabajo as ot', 'ot.id', '=', 'i.orden_trabajo_id')
            ->leftJoin('partes as p', 'p.id', '=', 'i.parte_id')
            ->leftJoin('subensambles as s', 's.id', '=', 'i.subensamble_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'i.producto_id')
            ->where('ot.empresa_id', $empresaId)
            ->where('i.orden_trabajo_id', $otId)
            ->select([
                'i.id',
                'i.tipo_item',
                'i.parte_id',
                'i.subensamble_id',
                'i.producto_id',
                'i.cantidad',
                'i.version_ruta_id',
                'i.notas',
                Capsule::raw('COALESCE(p.numero,"") as parte_numero'),
                Capsule::raw('COALESCE(p.descripcion,"") as parte_descripcion'),
                Capsule::raw('COALESCE(s.nombre,"") as subensamble_nombre'),
                Capsule::raw('COALESCE(pr.nombre,"") as producto_nombre'),
            ])
            ->orderBy('i.id', 'asc')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
    }

    // =========================================================
    // Operaciones OT (por ruta)
    // =========================================================
    public function getOtOperaciones(int $empresaId, int $otId): array
    {
        $empresaId = $this->empresaId($empresaId);

        return Capsule::table('ordenes_trabajo_items as i')
            ->join('ordenes_trabajo as ot', 'ot.id', '=', 'i.orden_trabajo_id')
            ->join('versiones_ruta as vr', 'vr.id', '=', 'i.version_ruta_id')
            ->join('ruta_operaciones as ro', 'ro.version_ruta_id', '=', 'vr.id')
            ->join('procesos as pr', 'pr.id', '=', 'ro.proceso_id')
            ->leftJoin('partes as p', 'p.id', '=', 'i.parte_id')
            ->where('ot.empresa_id', $empresaId)
            ->where('i.orden_trabajo_id', $otId)
            ->select([
                'i.id as item_id',
                'i.tipo_item',
                'i.cantidad',
                Capsule::raw('COALESCE(p.numero,"") as parte_numero'),
                Capsule::raw('COALESCE(p.descripcion,"") as parte_descripcion'),
                'ro.secuencia',
                'ro.setup_minutos',
                'ro.minutos',
                'ro.segundos',
                'ro.proceso_id',
                Capsule::raw('pr.nombre as proceso_nombre'),
            ])
            ->orderBy('i.id', 'asc')
            ->orderBy('ro.secuencia', 'asc')
            ->get()
            ->map(function ($r) {
                $segUnidad = ((int)$r->minutos * 60) + (int)$r->segundos;
                $setupMin  = (int)$r->setup_minutos;
                $cant      = (float)$r->cantidad;
                $durMin    = (int)ceil(((($setupMin * 60) + ($segUnidad * $cant)) / 60));

                return [
                    'item_id' => (int)$r->item_id,
                    'tipo_item' => (string)$r->tipo_item,
                    'cantidad' => (string)$r->cantidad,
                    'parte_numero' => (string)$r->parte_numero,
                    'parte_descripcion' => (string)$r->parte_descripcion,
                    'secuencia' => (int)$r->secuencia,
                    'proceso_id' => (int)$r->proceso_id,
                    'proceso_nombre' => (string)$r->proceso_nombre,
                    'setup_minutos' => $setupMin,
                    'segundos_por_unidad' => $segUnidad,
                    'duracion_minutos' => $durMin,
                ];
            })
            ->toArray();
    }

    // =========================================================
    // Validación planificable
    // =========================================================
    public function validarPlanificable(int $empresaId, int $otId): array
    {
        $empresaId = $this->empresaId($empresaId);

        $ot = Capsule::table('ordenes_trabajo')
            ->select('id', 'estado')
            ->where('empresa_id', $empresaId)
            ->where('id', $otId)
            ->first();

        if (!$ot) return ['ok' => false, 'msg' => 'OT no encontrada'];
        if ($ot->estado !== 'pendiente') return ['ok' => false, 'msg' => 'La OT ya no está en estado pendiente'];

        $yaTiene = (int) Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->where('orden_trabajo_id', $otId)
            ->count();
        if ($yaTiene > 0) return ['ok' => false, 'msg' => 'La OT ya tiene tareas generadas'];

        $items = Capsule::table('ordenes_trabajo_items')
            ->where('orden_trabajo_id', $otId)
            ->get();
        if ($items->count() <= 0) return ['ok' => false, 'msg' => 'La OT no tiene ítems'];

        $sinRuta = $items->filter(fn($i) => empty($i->version_ruta_id))->count();
        if ($sinRuta > 0) return ['ok' => false, 'msg' => "Hay {$sinRuta} ítem(s) sin ruta asignada"];

        $opsCount = (int) Capsule::table('ordenes_trabajo_items as i')
            ->join('versiones_ruta as vr', 'vr.id', '=', 'i.version_ruta_id')
            ->join('ruta_operaciones as ro', 'ro.version_ruta_id', '=', 'vr.id')
            ->where('i.orden_trabajo_id', $otId)
            ->count();
        if ($opsCount <= 0) return ['ok' => false, 'msg' => 'Las rutas no tienen operaciones configuradas'];

        return ['ok' => true, 'msg' => 'OK'];
    }

    // =========================================================
    // Items + ruta (filtrado por OT)
    // =========================================================
    public function itemsConRuta(int $empresaId, int $otId): array
    {
        $empresaId = $this->empresaId($empresaId);

        return Capsule::table('ordenes_trabajo_items as i')
            ->join('ordenes_trabajo as ot', 'ot.id', '=', 'i.orden_trabajo_id')
            ->join('versiones_ruta as vr', 'vr.id', '=', 'i.version_ruta_id')
            ->join('ruta_operaciones as ro', 'ro.version_ruta_id', '=', 'vr.id')
            ->where('ot.empresa_id', $empresaId)
            ->where('i.orden_trabajo_id', $otId)
            ->select(
                'i.id as item_id',
                'i.tipo_item',
                'i.cantidad',
                'i.parte_id',
                'i.subensamble_id',
                'i.producto_id',
                'ro.proceso_id',
                'ro.secuencia',
                'ro.minutos',
                'ro.segundos',
                'ro.setup_minutos'
            )
            ->orderBy('ro.secuencia')
            ->get()
            ->toArray();
    }

    // =========================================================
    // Crear tareas
    // =========================================================
    public function crearTareas(int $empresaId, int $otId, int $uid): int
    {
        $empresaId = $this->empresaId($empresaId);

        $val = $this->validarPlanificable($empresaId, $otId);
        if (!$val['ok']) throw new Exception($val['msg']);

        $items = $this->itemsConRuta($empresaId, $otId);
        if (!$items) return 0;

        $ts = $this->nowSql();

        Capsule::beginTransaction();
        try {
            foreach ($items as $r) {
                $segUnidad = ((int)$r->minutos * 60) + (int)$r->segundos;
                $setupSeg  = ((int)$r->setup_minutos) * 60;
                $cant      = (float)$r->cantidad;
                $durMin    = (int)ceil(($setupSeg + ($segUnidad * $cant)) / 60);

                $tipoOrigen = 'parte';
                if ($r->tipo_item === 'subensamble') $tipoOrigen = 'subensamble';
                if ($r->tipo_item === 'producto')   $tipoOrigen = 'kit_expandido';

                Capsule::table('tareas')->insert([
                    'empresa_id' => $empresaId,
                    'orden_trabajo_id' => $otId,
                    'item_id' => (int)$r->item_id,
                    'tipo_origen' => $tipoOrigen,
                    'parte_id' => $r->parte_id ? (int)$r->parte_id : null,
                    'subensamble_id' => $r->subensamble_id ? (int)$r->subensamble_id : null,
                    'producto_id' => $r->producto_id ? (int)$r->producto_id : null,
                    'proceso_id' => (int)$r->proceso_id,
                    'secuencia' => (int)$r->secuencia,
                    'cantidad' => $cant,
                    'setup_minutos' => (int)$r->setup_minutos,
                    'segundos_por_unidad' => $segUnidad,
                    'duracion_minutos' => $durMin,
                    'estado' => 'programada', // se ajustará en programarOt() si no se puede programar
                    'creado_en' => $ts,
                    'actualizado_en' => $ts
                ]);
            }

            Capsule::table('ordenes_trabajo')
                ->where('empresa_id', $empresaId)
                ->where('id', $otId)
                ->update([
                    'estado' => 'planificada',
                    'actualizado_en' => $ts
                ]);

            Capsule::commit();
            return count($items);

        } catch (Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }

    // =========================================================
    // Calendarios (empresa / máquina)
    // =========================================================
    private function getCalendarioById(int $empresaId, int $calId): ?array
    {
        $empresaId = $this->empresaId($empresaId);

        $cal = Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('id', $calId)
            ->whereNull('eliminado_en')
            ->first();

        if (!$cal) return null;

        return $this->normalizeCalendarioRow($cal);
    }

    private function getCalendarioActivoEmpresa(int $empresaId): array
    {
        $empresaId = $this->empresaId($empresaId);

        $cal = Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->whereNull('eliminado_en')
            ->orderBy('id', 'asc')
            ->first();

        if (!$cal) {
            return [
                'hora_inicio' => '08:00:00',
                'hora_fin' => '18:00:00',
                'dias_laborales' => [1,2,3,4,5],
                'pausas' => [],
            ];
        }

        return $this->normalizeCalendarioRow($cal);
    }

    private function normalizeCalendarioRow($cal): array
    {
        $dias = [];
        $pausas = [];

        try { $dias = json_decode((string)$cal->dias_laborales, true) ?: []; } catch (Throwable $e) { $dias = []; }
        try { $pausas = $cal->pausas ? (json_decode((string)$cal->pausas, true) ?: []) : []; } catch (Throwable $e) { $pausas = []; }

        // Normaliza dias_laborales
        if ($this->isListArray($dias)) {
            $dias = array_values(array_unique(array_map(fn($d)=> (int)$d, $dias)));
        } else {
            $tmp = [];
            foreach ((array)$dias as $k => $v) {
                if ($v) $tmp[] = (int)$k;
            }
            $dias = array_values(array_unique($tmp));
        }
        if (!$dias) $dias = [1,2,3,4,5];

        // Normaliza pausas -> ['inicio'=>'HH:MM:SS','fin'=>'HH:MM:SS']
        $pz = [];
        if (is_array($pausas)) {
            foreach ($pausas as $p) {
                $ini = $p['inicio'] ?? $p['start'] ?? null;
                $fin = $p['fin'] ?? $p['end'] ?? null;
                if (!$ini || !$fin) continue;
                $ini = strlen($ini) === 5 ? ($ini . ':00') : $ini;
                $fin = strlen($fin) === 5 ? ($fin . ':00') : $fin;
                $pz[] = ['inicio' => $ini, 'fin' => $fin];
            }
        }

        return [
            'hora_inicio' => (string)$cal->hora_inicio,
            'hora_fin' => (string)$cal->hora_fin,
            'dias_laborales' => $dias,
            'pausas' => $pz,
        ];
    }

    private function dtCombine(string $ymd, string $hms): string
    {
        return $ymd . ' ' . $hms;
    }

    private function nextWorkStart(array $cal, string $dt): string
    {
        $horaInicio = $cal['hora_inicio'];
        $horaFin    = $cal['hora_fin'];
        $dias       = $cal['dias_laborales'];

        $ts = strtotime($dt);
        if ($ts === false) $ts = time();

        while (true) {
            $ymd = date('Y-m-d', $ts);
            $dow = (int)date('N', $ts);

            $startDay = strtotime($this->dtCombine($ymd, $horaInicio));
            $endDay   = strtotime($this->dtCombine($ymd, $horaFin));

            if (in_array($dow, $dias, true)) {
                if ($ts < $startDay) return date('Y-m-d H:i:s', $startDay);
                if ($ts >= $startDay && $ts < $endDay) return date('Y-m-d H:i:s', $ts);
            }

            $ts = strtotime('+1 day', strtotime($ymd . ' 00:00:00'));
            $ts = strtotime($this->dtCombine(date('Y-m-d', $ts), $horaInicio));
        }
    }

    private function normalizeInsideDay(array $cal, string $dt): string
    {
        $dt = $this->nextWorkStart($cal, $dt);
        $ymd = date('Y-m-d', strtotime($dt));
        $t = strtotime($dt);

        $startDay = strtotime($this->dtCombine($ymd, $cal['hora_inicio']));
        $endDay   = strtotime($this->dtCombine($ymd, $cal['hora_fin']));

        if ($t < $startDay) $t = $startDay;

        // Si cae en pausa, saltar al fin de la pausa
        foreach ($cal['pausas'] as $p) {
            $pIni = strtotime($this->dtCombine($ymd, $p['inicio']));
            $pFin = strtotime($this->dtCombine($ymd, $p['fin']));
            if ($pIni !== false && $pFin !== false && $t >= $pIni && $t < $pFin) {
                $t = $pFin;
                break;
            }
        }

        if ($t >= $endDay) {
            return $this->nextWorkStart($cal, date('Y-m-d H:i:s', strtotime('+1 minute', $endDay)));
        }

        return date('Y-m-d H:i:s', $t);
    }

    private function addWorkingMinutes(array $cal, string $startDt, int $minutes): string
    {
        if ($minutes <= 0) return $startDt;

        $cur = $this->normalizeInsideDay($cal, $startDt);

        while ($minutes > 0) {
            $cur = $this->normalizeInsideDay($cal, $cur);
            $ymd = date('Y-m-d', strtotime($cur));
            $t   = strtotime($cur);

            $endDay = strtotime($this->dtCombine($ymd, $cal['hora_fin']));

            // construir segmentos del día: jornada menos pausas
            $segments = [];

            $segStart = strtotime($this->dtCombine($ymd, $cal['hora_inicio']));
            $segEnd   = $endDay;

            $pausas = $cal['pausas'];
            usort($pausas, fn($a,$b)=> strcmp($a['inicio'], $b['inicio']));

            $cursor = $segStart;
            foreach ($pausas as $p) {
                $pIni = strtotime($this->dtCombine($ymd, $p['inicio']));
                $pFin = strtotime($this->dtCombine($ymd, $p['fin']));
                if ($pIni === false || $pFin === false) continue;

                if ($pFin <= $cursor) continue;
                if ($pIni >= $segEnd) break;

                $a = max($cursor, $segStart);
                $b = min($pIni, $segEnd);
                if ($b > $a) $segments[] = [$a, $b];

                $cursor = min(max($pFin, $cursor), $segEnd);
            }
            if ($segEnd > $cursor) $segments[] = [$cursor, $segEnd];

            $consumed = false;

            foreach ($segments as [$a,$b]) {
                if ($t < $a) $t = $a;
                if ($t >= $b) continue;

                $availMin = (int)floor(($b - $t) / 60);
                if ($availMin <= 0) continue;

                $use = min($minutes, $availMin);
                $t += ($use * 60);
                $minutes -= $use;
                $consumed = true;

                if ($minutes <= 0) return date('Y-m-d H:i:s', $t);
            }

            // se acabó el día o no consumió
            if (!$consumed || $t >= $endDay) {
                $cur = $this->nextWorkStart($cal, date('Y-m-d H:i:s', strtotime('+1 day', strtotime($ymd.' 00:00:00'))));
            } else {
                $cur = date('Y-m-d H:i:s', $t);
            }
        }

        return $cur;
    }

    // =========================================================
    // Máquinas por proceso (empresa-safe)
    // =========================================================
    private function maquinasPorProceso(int $empresaId, array $procesoIds): array
    {
        $empresaId = $this->empresaId($empresaId);
        $procesoIds = array_values(array_unique(array_map('intval', $procesoIds)));
        if (!$procesoIds) return [];

        $rows = Capsule::table('proceso_maquina as pm')
            ->join('procesos as p', 'p.id', '=', 'pm.proceso_id')
            ->where('p.empresa_id', $empresaId)
            ->whereIn('pm.proceso_id', $procesoIds)
            ->select('pm.proceso_id', 'pm.maquina_id')
            ->orderBy('pm.proceso_id','asc')
            ->orderBy('pm.maquina_id','asc')
            ->get()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $pid = (int)$r->proceso_id;
            $mid = (int)$r->maquina_id;
            if (!isset($map[$pid])) $map[$pid] = [];
            $map[$pid][] = $mid;
        }

        return $map;
    }

    private function lastFinPlaneadoPorMaquina(int $empresaId, array $maquinaIds): array
    {
        $empresaId = $this->empresaId($empresaId);
        $maquinaIds = array_values(array_unique(array_map('intval', $maquinaIds)));
        if (!$maquinaIds) return [];

        $rows = Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->whereIn('maquina_id', $maquinaIds)
            ->whereNotNull('fin_planeado')
            ->selectRaw('maquina_id, MAX(fin_planeado) as max_fin')
            ->groupBy('maquina_id')
            ->get()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->maquina_id] = (string)$r->max_fin;
        }
        return $out;
    }

    private function calendarioIdPorMaquina(int $empresaId, array $maquinaIds): array
    {
        $empresaId = $this->empresaId($empresaId);
        $maquinaIds = array_values(array_unique(array_map('intval', $maquinaIds)));
        if (!$maquinaIds) return [];

        $rows = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $maquinaIds)
            ->select('id','calendario_id')
            ->get()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->id] = $r->calendario_id ? (int)$r->calendario_id : null;
        }
        return $out;
    }

    // =========================================================
    // Programar OT (asigna máquina + inicio/fin)
    // =========================================================
    public function programarOt(int $empresaId, int $otId, ?string $startBase = null): array
    {
        $empresaId = $this->empresaId($empresaId);

        $startBase = $startBase ?: $this->nowSql();

        $tareas = Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->where('orden_trabajo_id', $otId)
            ->orderBy('item_id', 'asc')
            ->orderBy('secuencia', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn($r)=>(array)$r)
            ->toArray();

        if (!$tareas) return ['ok'=>true,'programadas'=>0,'pendientes'=>0];

        $procesoIds = array_map(fn($t)=> (int)$t['proceso_id'], $tareas);
        $mapProcMaq = $this->maquinasPorProceso($empresaId, $procesoIds);

        $allMaq = [];
        foreach ($mapProcMaq as $pid => $maqs) {
            foreach ($maqs as $m) $allMaq[] = (int)$m;
        }
        $allMaq = array_values(array_unique($allMaq));

        $lastFin = $this->lastFinPlaneadoPorMaquina($empresaId, $allMaq);
        $maqCal  = $this->calendarioIdPorMaquina($empresaId, $allMaq);

        $calEmpresa = $this->getCalendarioActivoEmpresa($empresaId);

        $programadas = 0;
        $pendientes  = 0;

        Capsule::beginTransaction();
        try {
            foreach ($tareas as $t) {
                $tid = (int)$t['id'];
                $pid = (int)$t['proceso_id'];

                $durMin = (int)($t['duracion_minutos'] ?? 0);
                if ($durMin <= 0) $durMin = 1;

                // asigna máquina si no hay
                $maquinaId = $t['maquina_id'] ? (int)$t['maquina_id'] : null;
                if (!$maquinaId) {
                    $cands = $mapProcMaq[$pid] ?? [];
                    $maquinaId = $cands ? (int)$cands[0] : null;
                }

                if (!$maquinaId) {
                    Capsule::table('tareas')
                        ->where('empresa_id', $empresaId)
                        ->where('id', $tid)
                        ->update([
                            'estado' => 'pendiente',
                            'inicio_planeado' => null,
                            'fin_planeado' => null,
                            'actualizado_en' => $this->nowSql(),
                        ]);
                    $pendientes++;
                    continue;
                }

                // calendario: máquina si tiene, si no empresa
                $cal = $calEmpresa;
                $calIdM = $maqCal[$maquinaId] ?? null;
                if ($calIdM) {
                    $calM = $this->getCalendarioById($empresaId, (int)$calIdM);
                    if ($calM) $cal = $calM;
                }

                // arranque = max(startBase, lastFin[máquina])
                $start = $startBase;
                if (!empty($lastFin[$maquinaId])) {
                    if (strtotime($lastFin[$maquinaId]) > strtotime($start)) {
                        $start = $lastFin[$maquinaId];
                    }
                }

                $inicio = $this->normalizeInsideDay($cal, $start);
                $fin    = $this->addWorkingMinutes($cal, $inicio, $durMin);

                Capsule::table('tareas')
                    ->where('empresa_id', $empresaId)
                    ->where('id', $tid)
                    ->update([
                        'maquina_id' => $maquinaId,
                        'inicio_planeado' => $inicio,
                        'fin_planeado' => $fin,
                        'estado' => 'programada',
                        'actualizado_en' => $this->nowSql(),
                    ]);

                $lastFin[$maquinaId] = $fin;
                $programadas++;
            }

            Capsule::commit();
            return ['ok'=>true,'programadas'=>$programadas,'pendientes'=>$pendientes];

        } catch (Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }
}
