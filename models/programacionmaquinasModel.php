<?php
/**
 * Programación por Máquinas — Modelo TAKTIK (PROD)
 * - DataTables server-side: máquinas + agenda
 * - Catálogo OTs (vigentes)
 * - Guardar/editar programación + borrado lógico
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class programacionmaquinasModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // DT: Máquinas
    // =========================
    public function dtMaquinas(int $empresaId, array $req): array
    {
        $draw   = (int)($req['draw'] ?? 1);
        $start  = (int)($req['start'] ?? 0);
        $len    = (int)($req['length'] ?? 10);
        $search = trim((string)($req['search']['value'] ?? ''));

        $qBase = Capsule::table('maquinas as m')
            ->leftJoin('calendarios_laborales as c', function ($j) use ($empresaId) {
                $j->on('c.id', '=', 'm.calendario_id')
                  ->where('c.empresa_id', '=', $empresaId);
            })
            ->where('m.empresa_id', $empresaId);

        if ($search !== '') {
            $qBase->where(function ($w) use ($search) {
                $w->where('m.nombre', 'like', "%{$search}%")
                  ->orWhere('m.codigo', 'like', "%{$search}%")
                  ->orWhere('m.tipo', 'like', "%{$search}%");
            });
        }

        $total = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->count();

        $filtered = (clone $qBase)->count();

        $orderCol = (int)($req['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $cols = [
            0 => 'm.id',
            1 => 'm.codigo',
            2 => 'm.nombre',
            3 => 'm.tipo',
            4 => 'c.nombre',
            5 => 'm.activo',
        ];
        $orderBy = $cols[$orderCol] ?? 'm.nombre';

        $rows = (clone $qBase)
            ->select([
                'm.id',
                'm.codigo',
                'm.nombre',
                'm.tipo',
                'm.activo',
                'm.calendario_id',
                Capsule::raw('COALESCE(c.nombre,"") as calendario_nombre'),
            ])
            ->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($len)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ];
    }

    // =========================
    // DT: Agenda
    // =========================
    public function dtAgenda(int $empresaId, int $maquinaId, array $req): array
{
    $draw   = (int)($req['draw'] ?? 1);
    $start  = (int)($req['start'] ?? 0);
    $len    = (int)($req['length'] ?? 10);
    $search = trim((string)($req['search']['value'] ?? ''));

    $desde = trim((string)($req['desde'] ?? ''));
    $hasta = trim((string)($req['hasta'] ?? ''));

    // ✅ Default UX: si no mandan fechas, muestra próximos 7 días
    // Nota: si mandan explícitamente vacío (modo TODO), el JS mandará desde="" y hasta=""
    // y NO filtramos.
    if ($desde === '__AUTO__' || $hasta === '__AUTO__') {
        // por si en el futuro mandas un flag
        $desde = '';
        $hasta = '';
    }

    $noFechas = ($desde === '' && $hasta === '');

    if (!$noFechas) {
        // Si solo llega una de las dos, completa con lógica sensata
        if ($desde === '' && $hasta !== '') {
            // desde 30 días antes del hasta
            $desde = date('Y-m-d', strtotime($hasta . ' -30 days'));
        }
        if ($hasta === '' && $desde !== '') {
            // hasta 7 días después del desde
            $hasta = date('Y-m-d', strtotime($desde . ' +7 days'));
        }
    } else {
        // si no mandaron nada, aplica default 7 días
        // (esto evita que “Todo” se dispare por accidente en primera carga)
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime('+7 days'));
        $noFechas = false;
    }

    $qBase = Capsule::table('maquinas_programacion as p')
        ->join('ordenes_trabajo as ot', function ($j) use ($empresaId) {
            $j->on('ot.id', '=', 'p.orden_trabajo_id')
              ->where('ot.empresa_id', '=', $empresaId);
        })
        ->where('p.empresa_id', $empresaId)
        ->where('p.maquina_id', $maquinaId)
        ->where('p.activo', 1);

    // ✅ Rango (agenda “visible”)
    if (!$noFechas) {
        // intersección de rangos:
        // p.fin >= desde 00:00:00  AND  p.inicio <= hasta 23:59:59
        $qBase->where('p.fecha_fin', '>=', $desde . ' 00:00:00');
        $qBase->where('p.fecha_inicio', '<=', $hasta . ' 23:59:59');
    }

    if ($search !== '') {
        $qBase->where(function ($w) use ($search) {
            $w->where('ot.folio_ot', 'like', "%{$search}%")
              ->orWhere('ot.descripcion', 'like', "%{$search}%")
              ->orWhere('p.notas', 'like', "%{$search}%");
        });
    }

    $total = Capsule::table('maquinas_programacion')
        ->where('empresa_id', $empresaId)
        ->where('maquina_id', $maquinaId)
        ->where('activo', 1)
        ->count();

    // OJO: total “filtrado” respeta rango + search
    $filtered = (clone $qBase)->count();

    // ✅ Order por default: fecha_inicio asc
    $orderCol = isset($req['order'][0]['column']) ? (int)$req['order'][0]['column'] : -1;
    $orderDir = (isset($req['order'][0]['dir']) && strtolower((string)$req['order'][0]['dir']) === 'desc') ? 'desc' : 'asc';

    $cols = [
        0 => 'p.fecha_inicio',
        1 => 'p.fecha_fin',
        2 => 'ot.folio_ot',
        3 => 'ot.estado',
        4 => 'ot.prioridad',
        5 => 'p.notas',
    ];

    $orderBy = $cols[$orderCol] ?? 'p.fecha_inicio';
    $orderDir = ($cols[$orderCol] ?? null) ? $orderDir : 'asc';

    $rows = (clone $qBase)
        ->select([
            'p.id',
            'p.maquina_id',
            'p.orden_trabajo_id',
            'p.orden_trabajo_item_id',
            'p.fecha_inicio',
            'p.fecha_fin',
            'p.notas',
            'ot.folio_ot',
            'ot.descripcion',
            'ot.estado',
            'ot.prioridad',
        ])
        ->orderBy($orderBy, $orderDir)
        ->orderBy('p.fecha_inicio', 'asc') // ✅ estabilidad
        ->skip($start)
        ->take($len)
        ->get()
        ->map(fn($r) => (array)$r)
        ->toArray();

    return [
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $rows,
    ];
}

    // =========================
    // Catálogo OTs vigentes
    // =========================
    public function catOts(int $empresaId, string $q = ''): array
    {
        $qb = Capsule::table('ordenes_trabajo')
            ->select('id', 'folio_ot', 'descripcion', 'estado', 'prioridad', 'fecha_compromiso')
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ['pendiente','planificada','en_proceso','en_cuarentena'])
            ->orderBy('fecha_compromiso', 'asc')
            ->orderBy('id', 'desc')
            ->limit(50);

        $q = trim($q);
        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('folio_ot', 'like', "%{$q}%")
                  ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        return $qb->get()->map(fn($r) => (array)$r)->toArray();
    }

    public function getProgramacion(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('maquinas_programacion as p')
            ->join('ordenes_trabajo as ot', function ($j) use ($empresaId) {
                $j->on('ot.id', '=', 'p.orden_trabajo_id')
                  ->where('ot.empresa_id', '=', $empresaId);
            })
            ->where('p.empresa_id', $empresaId)
            ->where('p.id', $id)
            ->where('p.activo', 1)
            ->select([
                'p.*',
                'ot.folio_ot',
                'ot.descripcion',
            ])
            ->first();

        return $r ? (array)$r : null;
    }




    public function dtTareasAgenda(int $empresaId, int $maquinaId, array $req): array
{
    $draw   = (int)($req['draw'] ?? 1);
    $start  = (int)($req['start'] ?? 0);
    $len    = (int)($req['length'] ?? 10);
    $search = trim((string)($req['search']['value'] ?? ''));

    $desde = trim((string)($req['desde'] ?? ''));
    $hasta = trim((string)($req['hasta'] ?? ''));

    // ✅ Default: próximos 7 días (sin andar picando fechas)
    if ($desde === '' && $hasta === '') {
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime('+7 days'));
    } else {
        if ($desde === '' && $hasta !== '') $desde = date('Y-m-d', strtotime($hasta . ' -30 days'));
        if ($hasta === '' && $desde !== '') $hasta = date('Y-m-d', strtotime($desde . ' +7 days'));
    }

    // Base: slots programados (p) -> OT (ot) -> tareas (t) -> proceso (pr)
    $qBase = Capsule::table('maquinas_programacion as p')
        ->join('ordenes_trabajo as ot', function ($j) use ($empresaId) {
            $j->on('ot.id', '=', 'p.orden_trabajo_id')
              ->where('ot.empresa_id', '=', $empresaId);
        })
        ->join('tareas as t', function ($j) use ($empresaId) {
            $j->on('t.orden_trabajo_id', '=', 'ot.id')
              ->where('t.empresa_id', '=', $empresaId);
        })
        ->join('procesos as pr', function ($j) use ($empresaId) {
            $j->on('pr.id', '=', 't.proceso_id')
              ->where('pr.empresa_id', '=', $empresaId);
        })
        ->where('p.empresa_id', $empresaId)
        ->where('p.maquina_id', $maquinaId)
        ->where('p.activo', 1);

    // rango por intersección
    $qBase->where('p.fecha_fin', '>=', $desde . ' 00:00:00');
    $qBase->where('p.fecha_inicio', '<=', $hasta . ' 23:59:59');

    if ($search !== '') {
        $qBase->where(function ($w) use ($search) {
            $w->where('ot.folio_ot', 'like', "%{$search}%")
              ->orWhere('ot.descripcion', 'like', "%{$search}%")
              ->orWhere('pr.nombre', 'like', "%{$search}%")
              ->orWhere('t.tipo_origen', 'like', "%{$search}%")
              ->orWhere('p.notas', 'like', "%{$search}%");
        });
    }

    // totals
    $total = (clone $qBase)->count(); // total en rango sin search? aquí ya incluye rango; está bien para operación
    $filtered = $total;

    // order default
    $orderCol = (int)($req['order'][0]['column'] ?? -1);
    $orderDir = strtolower((string)($req['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

    $cols = [
        0 => 'p.fecha_inicio',
        1 => 'p.fecha_fin',
        2 => 'ot.folio_ot',
        3 => 't.secuencia',
        4 => 'pr.nombre',
        5 => 't.cantidad',
    ];

    $orderBy = $cols[$orderCol] ?? 'p.fecha_inicio';

    $rows = (clone $qBase)
        ->select([
            'p.id as programacion_id',
            'p.fecha_inicio',
            'p.fecha_fin',
            'p.notas as programacion_notas',
            'ot.id as ot_id',
            'ot.folio_ot',
            'ot.descripcion',
            'ot.estado',
            'ot.prioridad',
            't.id as tarea_id',
            't.secuencia',
            't.cantidad',
            't.setup_minutos',
            't.segundos_por_unidad',
            't.tipo_origen',
            'pr.nombre as proceso_nombre',
        ])
        ->orderBy($orderBy, $orderDir)
        ->orderBy('p.fecha_inicio', 'asc')
        ->orderBy('t.secuencia', 'asc')
        ->skip($start)
        ->take($len)
        ->get()
        ->map(fn($r) => (array)$r)
        ->toArray();

    return [
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $rows,
    ];
}



    public function saveProgramacion(int $empresaId, int $uid, array $in): array
    {
        $id = (int)($in['id'] ?? 0);

        $maquinaId = (int)($in['maquina_id'] ?? 0);
        $otId      = (int)($in['orden_trabajo_id'] ?? 0);
        $fi        = trim((string)($in['fecha_inicio'] ?? ''));
        $ff        = trim((string)($in['fecha_fin'] ?? ''));
        $notas     = trim((string)($in['notas'] ?? ''));

        if ($maquinaId <= 0) return ['ok'=>false,'msg'=>'Falta máquina.'];
        if ($otId <= 0) return ['ok'=>false,'msg'=>'Falta OT.'];
        if ($fi === '' || $ff === '') return ['ok'=>false,'msg'=>'Faltan fechas.'];
        if (strtotime($ff) <= strtotime($fi)) return ['ok'=>false,'msg'=>'La fecha fin debe ser mayor a inicio.'];

        // pertenencia
        $mOk = Capsule::table('maquinas')->where('empresa_id', $empresaId)->where('id', $maquinaId)->exists();
        if (!$mOk) return ['ok'=>false,'msg'=>'Máquina inválida.'];

        $otOk = Capsule::table('ordenes_trabajo')->where('empresa_id', $empresaId)->where('id', $otId)->exists();
        if (!$otOk) return ['ok'=>false,'msg'=>'OT inválida.'];

        // Anti-traslape (misma máquina)
        $overlap = Capsule::table('maquinas_programacion as p')
            ->where('p.empresa_id', $empresaId)
            ->where('p.maquina_id', $maquinaId)
            ->where('p.activo', 1)
            ->when($id > 0, fn($q) => $q->where('p.id', '<>', $id))
            ->where(function ($w) use ($fi, $ff) {
                $w->whereBetween('p.fecha_inicio', [$fi, $ff])
                  ->orWhereBetween('p.fecha_fin', [$fi, $ff])
                  ->orWhere(function ($x) use ($fi, $ff) {
                      $x->where('p.fecha_inicio', '<=', $fi)
                        ->where('p.fecha_fin', '>=', $ff);
                  });
            })
            ->exists();

        if ($overlap) return ['ok'=>false,'msg'=>'Se cruza con otra programación en esa máquina.'];

        $data = [
            'empresa_id' => $empresaId,
            'maquina_id' => $maquinaId,
            'orden_trabajo_id' => $otId,
            'fecha_inicio' => $fi,
            'fecha_fin' => $ff,
            'notas' => $notas !== '' ? $notas : null,
            'activo' => 1,
        ];

        if ($id > 0) {
            $ok = Capsule::table('maquinas_programacion')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);

            return ['ok'=>(bool)$ok,'id'=>$id,'msg'=>$ok?'Actualizado.':'No se actualizó.'];
        }

        $data['creado_por'] = $uid;
        $newId = Capsule::table('maquinas_programacion')->insertGetId($data);

        return ['ok'=>$newId>0,'id'=>$newId,'msg'=>$newId>0?'Creado.':'No se creó.'];
    }

    public function deleteProgramacion(int $empresaId, int $id): bool
    {
        $ok = Capsule::table('maquinas_programacion')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update(['activo' => 0]);

        return (bool)$ok;
    }
}
