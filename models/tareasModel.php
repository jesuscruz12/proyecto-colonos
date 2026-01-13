<?php
/**
 * C:\xampp\htdocs\qacrmtaktik\models\tareasModel.php
 *
 * Tareas — Modelo TAKTIK (PROD) — FIXED (COMPLETO)
 * - Multi-tenant safe joins
 * - NO usa update()/save() para no chocar con Eloquent
 * - Validación fuerte: kit_expandido => producto_id requerido
 * - DataTables server-side
 * - CRUD + Autocomplete + setEstado (piso) con transaction + lockForUpdate
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class tareasModel extends Model
{
    protected $table = 'tareas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Catálogos (selects chicos)
    // =========================
    public function catProcesos(int $empresaId): array
    {
        return Capsule::table('procesos')
            ->select('id', 'nombre', 'setup_minutos')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
    }

    public function catMaquinas(int $empresaId): array
    {
        return Capsule::table('maquinas')
            ->select('id', 'nombre')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
    }

    public function catEstadosTarea(): array
    {
        return [
            ['id' => 'pendiente',         'nombre' => 'pendiente'],
            ['id' => 'programada',        'nombre' => 'programada'],
            ['id' => 'en_proceso',        'nombre' => 'en_proceso'],
            ['id' => 'pausada',           'nombre' => 'pausada'],
            ['id' => 'terminada',         'nombre' => 'terminada'],
            ['id' => 'bloqueada_calidad', 'nombre' => 'bloqueada_calidad'],
            ['id' => 'scrap',             'nombre' => 'scrap'],
        ];
    }

    // =========================
    // Autocomplete
    // =========================
    public function buscarOT(int $empresaId, string $q, int $limit = 15): array
    {
        $q = trim($q);
        $limit = max(5, min(50, (int)$limit));

        $sql = Capsule::table('ordenes_trabajo as ot')
            ->leftJoin('clientes as c', function ($j) use ($empresaId) {
                $j->on('c.id', '=', 'ot.cliente_id')
                  ->where('c.empresa_id', '=', $empresaId);
            })
            ->where('ot.empresa_id', $empresaId)
            ->select(['ot.id', 'ot.folio_ot', 'ot.numero_dibujo', 'ot.estado', 'c.nombre as cliente'])
            ->orderBy('ot.id', 'desc')
            ->limit($limit);

        // ✅ FIX: grupo bien armado (no arranca con orWhere)
        if ($q !== '') {
            $sql->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $idMaybe = ctype_digit($q) ? (int)$q : 0;

                // arranca con where, no con orWhere
                if ($idMaybe > 0) $w->where('ot.id', $idMaybe);
                else $w->where('ot.folio_ot', 'like', $like);

                $w->orWhere('ot.folio_ot', 'like', $like)
                  ->orWhere('ot.numero_dibujo', 'like', $like)
                  ->orWhere('c.nombre', 'like', $like);
            });
        }

        $rows = $sql->get()->map(fn($r) => (array)$r)->toArray();

        return array_map(function ($r) {
            $folio  = $r['folio_ot'] ?: ('OT#' . $r['id']);
            $cli    = $r['cliente'] ? (' — ' . $r['cliente']) : '';
            $dwg    = $r['numero_dibujo'] ? (' · ' . $r['numero_dibujo']) : '';
            $estado = $r['estado'] ? (' [' . $r['estado'] . ']') : '';
            return ['id' => (int)$r['id'], 'label' => $folio . $cli . $dwg . $estado];
        }, $rows);
    }

    public function buscarItemsOT(int $empresaId, int $otId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(5, min(50, (int)$limit));

        $ok = Capsule::table('ordenes_trabajo')
            ->where('empresa_id', $empresaId)
            ->where('id', $otId)
            ->exists();
        if (!$ok) return [];

        $sql = Capsule::table('ordenes_trabajo_items as i')
            ->leftJoin('partes as pa', function ($j) use ($empresaId) {
                $j->on('pa.id', '=', 'i.parte_id')->where('pa.empresa_id', '=', $empresaId);
            })
            ->leftJoin('subensambles as su', function ($j) use ($empresaId) {
                $j->on('su.id', '=', 'i.subensamble_id')->where('su.empresa_id', '=', $empresaId);
            })
            ->leftJoin('productos as pr', function ($j) use ($empresaId) {
                $j->on('pr.id', '=', 'i.producto_id')->where('pr.empresa_id', '=', $empresaId);
            })
            ->where('i.orden_trabajo_id', $otId)
            ->select([
                'i.id', 'i.tipo_item', 'i.cantidad',
                'pa.numero as parte_numero', 'pa.descripcion as parte_desc',
                'su.nombre as sub_nombre',
                'pr.nombre as prod_nombre',
            ])
            ->orderBy('i.id', 'desc')
            ->limit($limit);

        if ($q !== '') {
            $sql->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $idMaybe = ctype_digit($q) ? (int)$q : 0;
                if ($idMaybe > 0) $w->orWhere('i.id', $idMaybe);
                $w->orWhere('pa.numero', 'like', $like)
                  ->orWhere('pa.descripcion', 'like', $like)
                  ->orWhere('su.nombre', 'like', $like)
                  ->orWhere('pr.nombre', 'like', $like)
                  ->orWhere('i.tipo_item', 'like', $like);
            });
        }

        $rows = $sql->get()->map(fn($r) => (array)$r)->toArray();

        return array_map(function ($r) {
            $tipo = $r['tipo_item'] ?? '';
            if ($tipo === 'parte') {
                $base = ($r['parte_numero'] ?: 'Parte') . ' — ' . ($r['parte_desc'] ?: '');
            } elseif ($tipo === 'subensamble') {
                $base = $r['sub_nombre'] ?: 'Subensamble';
            } else {
                $base = $r['prod_nombre'] ?: 'Producto';
            }
            $qty = isset($r['cantidad']) ? (' x' . $r['cantidad']) : '';
            return [
                'id' => (int)$r['id'],
                'label' => 'Item#' . $r['id'] . ' · ' . $tipo . ' · ' . $base . $qty,
                'tipo_item' => $tipo
            ];
        }, $rows);
    }

    public function buscarPartes(int $empresaId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(5, min(50, (int)$limit));

        $sql = Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->select(['id', 'numero', 'descripcion'])
            ->orderBy('numero', 'asc')
            ->limit($limit);

        if ($q !== '') {
            $sql->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $idMaybe = ctype_digit($q) ? (int)$q : 0;
                if ($idMaybe > 0) $w->orWhere('id', $idMaybe);
                $w->orWhere('numero', 'like', $like)
                  ->orWhere('descripcion', 'like', $like);
            });
        }

        $rows = $sql->get()->map(fn($r) => (array)$r)->toArray();
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => ($r['numero'] ?: ('Parte#' . $r['id'])) . ' — ' . ($r['descripcion'] ?: '')
        ], $rows);
    }

    public function buscarSubensambles(int $empresaId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(5, min(50, (int)$limit));

        $sql = Capsule::table('subensambles')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->select(['id', 'nombre', 'descripcion'])
            ->orderBy('nombre', 'asc')
            ->limit($limit);

        if ($q !== '') {
            $sql->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $idMaybe = ctype_digit($q) ? (int)$q : 0;
                if ($idMaybe > 0) $w->orWhere('id', $idMaybe);
                $w->orWhere('nombre', 'like', $like)
                  ->orWhere('descripcion', 'like', $like);
            });
        }

        $rows = $sql->get()->map(fn($r) => (array)$r)->toArray();
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => ($r['nombre'] ?: ('Subensamble#' . $r['id'])) . ($r['descripcion'] ? (' — ' . $r['descripcion']) : '')
        ], $rows);
    }

    public function buscarProductos(int $empresaId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(5, min(50, (int)$limit));

        $sql = Capsule::table('productos')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->select(['id', 'nombre', 'descripcion'])
            ->orderBy('nombre', 'asc')
            ->limit($limit);

        if ($q !== '') {
            $sql->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $idMaybe = ctype_digit($q) ? (int)$q : 0;
                if ($idMaybe > 0) $w->orWhere('id', $idMaybe);
                $w->orWhere('nombre', 'like', $like)
                  ->orWhere('descripcion', 'like', $like);
            });
        }

        $rows = $sql->get()->map(fn($r) => (array)$r)->toArray();
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => ($r['nombre'] ?: ('Producto#' . $r['id'])) . ($r['descripcion'] ? (' — ' . $r['descripcion']) : '')
        ], $rows);
    }

    // =========================
    // Validación
    // =========================
    private function validarInput(array $in): array
    {
        $out = [];

        $out['orden_trabajo_id'] = (int)($in['orden_trabajo_id'] ?? 0);
        if ($out['orden_trabajo_id'] <= 0) throw new Exception('OT es requerida.');

        $out['proceso_id'] = (int)($in['proceso_id'] ?? 0);
        if ($out['proceso_id'] <= 0) throw new Exception('Proceso es requerido.');

        $out['estado'] = trim((string)($in['estado'] ?? 'pendiente'));
        if ($out['estado'] === '') $out['estado'] = 'pendiente';

        $out['secuencia'] = (int)($in['secuencia'] ?? 1);
        if ($out['secuencia'] <= 0) $out['secuencia'] = 1;

        $out['cantidad'] = (float)($in['cantidad'] ?? 1);
        if ($out['cantidad'] <= 0) throw new Exception('Cantidad inválida.');

        $out['setup_minutos'] = (int)($in['setup_minutos'] ?? 0);
        if ($out['setup_minutos'] < 0) $out['setup_minutos'] = 0;

        $out['segundos_por_unidad'] = (int)($in['segundos_por_unidad'] ?? 0);
        if ($out['segundos_por_unidad'] < 0) $out['segundos_por_unidad'] = 0;

        $out['duracion_minutos'] = (int)($in['duracion_minutos'] ?? 0);
        if ($out['duracion_minutos'] < 0) $out['duracion_minutos'] = 0;

        $out['maquina_id'] = (int)($in['maquina_id'] ?? 0);
        $out['maquina_id'] = $out['maquina_id'] > 0 ? $out['maquina_id'] : null;

        $out['inicio_planeado'] = trim((string)($in['inicio_planeado'] ?? ''));
        $out['fin_planeado']    = trim((string)($in['fin_planeado'] ?? ''));

        $out['inicio_real'] = trim((string)($in['inicio_real'] ?? ''));
        $out['fin_real']    = trim((string)($in['fin_real'] ?? ''));

        $out['motivo_bloqueo'] = trim((string)($in['motivo_bloqueo'] ?? ''));

        $out['tipo_origen'] = trim((string)($in['tipo_origen'] ?? 'parte'));
        if (!in_array($out['tipo_origen'], ['parte', 'subensamble', 'kit_expandido'], true)) {
            $out['tipo_origen'] = 'parte';
        }

        $out['item_id'] = (int)($in['item_id'] ?? 0);
        $out['item_id'] = $out['item_id'] > 0 ? $out['item_id'] : null;

        $out['parte_id'] = (int)($in['parte_id'] ?? 0);
        $out['subensamble_id'] = (int)($in['subensamble_id'] ?? 0);
        $out['producto_id'] = (int)($in['producto_id'] ?? 0);

        $out['parte_id'] = $out['parte_id'] > 0 ? $out['parte_id'] : null;
        $out['subensamble_id'] = $out['subensamble_id'] > 0 ? $out['subensamble_id'] : null;
        $out['producto_id'] = $out['producto_id'] > 0 ? $out['producto_id'] : null;

        // Limpia IDs segun tipo
        if ($out['tipo_origen'] === 'parte') {
            $out['subensamble_id'] = null;
            $out['producto_id'] = null;
        } elseif ($out['tipo_origen'] === 'subensamble') {
            $out['parte_id'] = null;
            $out['producto_id'] = null;
        } elseif ($out['tipo_origen'] === 'kit_expandido') {
            $out['parte_id'] = null;
            $out['subensamble_id'] = null;
        }

        // Reglas fuertes
        if ($out['estado'] === 'bloqueada_calidad' && $out['motivo_bloqueo'] === '') {
            throw new Exception('Motivo de bloqueo es requerido cuando está bloqueada_calidad.');
        }
        if ($out['tipo_origen'] === 'kit_expandido' && empty($out['producto_id'])) {
            throw new Exception('Producto es requerido cuando tipo_origen = kit_expandido.');
        }

        // Autocalcular duración
        if ($out['duracion_minutos'] === 0 && ($out['setup_minutos'] > 0 || $out['segundos_por_unidad'] > 0)) {
            $secs = ($out['setup_minutos'] * 60) + (int)ceil($out['segundos_por_unidad'] * $out['cantidad']);
            $out['duracion_minutos'] = (int)ceil($secs / 60);
        }

        // Autocalcular fin_planeado
        if ($out['inicio_planeado'] !== '' && $out['duracion_minutos'] > 0 && $out['fin_planeado'] === '') {
            try {
                $dt = new DateTime($out['inicio_planeado']);
                $dt->modify('+' . $out['duracion_minutos'] . ' minutes');
                $out['fin_planeado'] = $dt->format('Y-m-d H:i:s');
            } catch (Throwable $e) {}
        }

        foreach (['inicio_planeado', 'fin_planeado', 'inicio_real', 'fin_real'] as $k) {
            if ($out[$k] === '') $out[$k] = null;
        }

        return $out;
    }

    // =========================
    // DataTables: query segura
    // =========================
    private function baseQuery(int $empresaId)
    {
        return Capsule::table('tareas as t')
            ->join('ordenes_trabajo as ot', 'ot.id', '=', 't.orden_trabajo_id')
            ->leftJoin('clientes as c', function ($j) use ($empresaId) {
                $j->on('c.id', '=', 'ot.cliente_id')
                  ->where('c.empresa_id', '=', $empresaId);
            })
            ->join('procesos as p', 'p.id', '=', 't.proceso_id')
            ->leftJoin('maquinas as m', function ($j) use ($empresaId) {
                $j->on('m.id', '=', 't.maquina_id')
                  ->where('m.empresa_id', '=', $empresaId);
            })
            ->where('t.empresa_id', $empresaId)
            ->where('ot.empresa_id', $empresaId)
            ->where('p.empresa_id', $empresaId)
            ->select([
                't.id',
                't.orden_trabajo_id',
                'ot.folio_ot',
                'ot.estado as ot_estado',
                'c.nombre as cliente',
                'p.nombre as proceso',
                't.secuencia',
                't.cantidad',
                'm.nombre as maquina',
                't.inicio_planeado',
                't.fin_planeado',
                't.estado',
                't.duracion_minutos',
                't.creado_en'
            ]);
    }

    public function dtTotales(int $empresaId): int
    {
        return (int)Capsule::table('tareas')->where('empresa_id', $empresaId)->count();
    }

    public function dtFiltrados(int $empresaId, array $filtros, string $search): int
    {
        $q = $this->baseQuery($empresaId);

        if (($filtros['estado'] ?? '') !== '') $q->where('t.estado', $filtros['estado']);
        if ((int)($filtros['proceso_id'] ?? 0) > 0) $q->where('t.proceso_id', (int)$filtros['proceso_id']);
        if ((int)($filtros['maquina_id'] ?? 0) > 0) $q->where('t.maquina_id', (int)$filtros['maquina_id']);
        if (($filtros['ot_estado'] ?? '') !== '') $q->where('ot.estado', $filtros['ot_estado']);
        if (($filtros['desde'] ?? '') !== '') $q->whereDate('t.inicio_planeado', '>=', $filtros['desde']);
        if (($filtros['hasta'] ?? '') !== '') $q->whereDate('t.inicio_planeado', '<=', $filtros['hasta']);

        $search = trim($search);
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('ot.folio_ot', 'like', $like)
                  ->orWhere('c.nombre', 'like', $like)
                  ->orWhere('p.nombre', 'like', $like)
                  ->orWhere('m.nombre', 'like', $like)
                  ->orWhere('t.estado', 'like', $like);
            });
        }

        return (int)$q->count();
    }

    public function dtDatos(int $empresaId, array $dt, array $filtros): array
    {
        $start = (int)($dt['start'] ?? 0);
        $len = (int)($dt['length'] ?? 25);
        if ($len <= 0) $len = 25;

        $search = (string)($dt['search']['value'] ?? '');

        $q = $this->baseQuery($empresaId);

        if (($filtros['estado'] ?? '') !== '') $q->where('t.estado', $filtros['estado']);
        if ((int)($filtros['proceso_id'] ?? 0) > 0) $q->where('t.proceso_id', (int)$filtros['proceso_id']);
        if ((int)($filtros['maquina_id'] ?? 0) > 0) $q->where('t.maquina_id', (int)$filtros['maquina_id']);
        if (($filtros['ot_estado'] ?? '') !== '') $q->where('ot.estado', $filtros['ot_estado']);
        if (($filtros['desde'] ?? '') !== '') $q->whereDate('t.inicio_planeado', '>=', $filtros['desde']);
        if (($filtros['hasta'] ?? '') !== '') $q->whereDate('t.inicio_planeado', '<=', $filtros['hasta']);

        $search = trim($search);
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('ot.folio_ot', 'like', $like)
                  ->orWhere('c.nombre', 'like', $like)
                  ->orWhere('p.nombre', 'like', $like)
                  ->orWhere('m.nombre', 'like', $like)
                  ->orWhere('t.estado', 'like', $like);
            });
        }

        $colMap = [
            0  => 't.id',
            1  => 'ot.folio_ot',
            2  => 'c.nombre',
            3  => 'p.nombre',
            4  => 't.secuencia',
            5  => 't.cantidad',
            6  => 'm.nombre',
            7  => 't.inicio_planeado',
            8  => 't.fin_planeado',
            9  => 't.estado',
            10 => 't.duracion_minutos',
            11 => 't.creado_en',
        ];

        $orderCol = (int)($dt['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($dt['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy = $colMap[$orderCol] ?? 't.id';

        $q->orderBy($orderBy, $orderDir);

        return $q->offset($start)->limit($len)->get()->map(fn($r) => (array)$r)->toArray();
    }

    // =========================
    // CRUD
    // =========================
    public function getTarea(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function crearTarea(int $empresaId, int $uid, array $in): int
    {
        $data = $this->validarInput($in);

        $otOk = Capsule::table('ordenes_trabajo')->where('empresa_id', $empresaId)->where('id', $data['orden_trabajo_id'])->exists();
        if (!$otOk) throw new Exception('OT no existe en esta empresa.');

        $procOk = Capsule::table('procesos')->where('empresa_id', $empresaId)->where('id', $data['proceso_id'])->exists();
        if (!$procOk) throw new Exception('Proceso inválido para esta empresa.');

        if (!empty($data['maquina_id'])) {
            $maqOk = Capsule::table('maquinas')->where('empresa_id', $empresaId)->where('id', $data['maquina_id'])->exists();
            if (!$maqOk) throw new Exception('Máquina inválida para esta empresa.');
        }

        if (!empty($data['item_id'])) {
            $itOk = Capsule::table('ordenes_trabajo_items')
                ->where('orden_trabajo_id', $data['orden_trabajo_id'])
                ->where('id', $data['item_id'])
                ->exists();
            if (!$itOk) throw new Exception('Item inválido para esta OT.');
        }

        if ($data['tipo_origen'] === 'parte' && !empty($data['parte_id'])) {
            $ok = Capsule::table('partes')->where('empresa_id', $empresaId)->where('id', $data['parte_id'])->exists();
            if (!$ok) throw new Exception('Parte inválida.');
        }
        if ($data['tipo_origen'] === 'subensamble' && !empty($data['subensamble_id'])) {
            $ok = Capsule::table('subensambles')->where('empresa_id', $empresaId)->where('id', $data['subensamble_id'])->exists();
            if (!$ok) throw new Exception('Subensamble inválido.');
        }
        if ($data['tipo_origen'] === 'kit_expandido') {
            $ok = Capsule::table('productos')->where('empresa_id', $empresaId)->where('id', $data['producto_id'])->exists();
            if (!$ok) throw new Exception('Producto inválido.');
        }

        $now = date('Y-m-d H:i:s');

        $insert = array_merge($data, [
            'empresa_id'     => $empresaId,
            'creado_en'      => $now,
            'actualizado_en' => $now,
        ]);

        return (int)Capsule::table('tareas')->insertGetId($insert);
    }

    public function actualizarTarea(int $empresaId, int $uid, int $id, array $in): void
    {
        $data = $this->validarInput($in);

        $exists = Capsule::table('tareas')->where('empresa_id', $empresaId)->where('id', $id)->exists();
        if (!$exists) throw new Exception('Tarea no encontrada.');

        $otOk = Capsule::table('ordenes_trabajo')->where('empresa_id', $empresaId)->where('id', $data['orden_trabajo_id'])->exists();
        if (!$otOk) throw new Exception('OT no existe en esta empresa.');

        $procOk = Capsule::table('procesos')->where('empresa_id', $empresaId)->where('id', $data['proceso_id'])->exists();
        if (!$procOk) throw new Exception('Proceso inválido para esta empresa.');

        if (!empty($data['maquina_id'])) {
            $maqOk = Capsule::table('maquinas')->where('empresa_id', $empresaId)->where('id', $data['maquina_id'])->exists();
            if (!$maqOk) throw new Exception('Máquina inválida para esta empresa.');
        }

        if (!empty($data['item_id'])) {
            $itOk = Capsule::table('ordenes_trabajo_items')
                ->where('orden_trabajo_id', $data['orden_trabajo_id'])
                ->where('id', $data['item_id'])
                ->exists();
            if (!$itOk) throw new Exception('Item inválido para esta OT.');
        }

        if ($data['tipo_origen'] === 'parte' && !empty($data['parte_id'])) {
            $ok = Capsule::table('partes')->where('empresa_id', $empresaId)->where('id', $data['parte_id'])->exists();
            if (!$ok) throw new Exception('Parte inválida.');
        }
        if ($data['tipo_origen'] === 'subensamble' && !empty($data['subensamble_id'])) {
            $ok = Capsule::table('subensambles')->where('empresa_id', $empresaId)->where('id', $data['subensamble_id'])->exists();
            if (!$ok) throw new Exception('Subensamble inválido.');
        }
        if ($data['tipo_origen'] === 'kit_expandido') {
            $ok = Capsule::table('productos')->where('empresa_id', $empresaId)->where('id', $data['producto_id'])->exists();
            if (!$ok) throw new Exception('Producto inválido.');
        }

        $data['actualizado_en'] = date('Y-m-d H:i:s');

        Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update($data);
    }

    public function eliminarTarea(int $empresaId, int $id): void
    {
        $exists = Capsule::table('tareas')->where('empresa_id', $empresaId)->where('id', $id)->exists();
        if (!$exists) throw new Exception('Tarea no encontrada.');

        Capsule::table('tareas')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->delete();
    }

    // =========================
    // Piso: setEstado (Iniciar/Pausar/Terminar/Bloquear)
    // =========================
    public function setEstado(int $empresaId, int $uid, int $id, string $estado, string $motivo = ''): array
    {
        $estado = trim($estado);
        $motivo = trim($motivo);

        $valid = ['en_proceso', 'pausada', 'terminada', 'bloqueada_calidad'];
        if (!in_array($estado, $valid, true)) throw new Exception('Estado no permitido.');
        if ($estado === 'bloqueada_calidad' && $motivo === '') throw new Exception('Motivo requerido para bloquear.');

        return Capsule::connection()->transaction(function () use ($empresaId, $id, $estado, $motivo) {

            $t = Capsule::table('tareas')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$t) throw new Exception('Tarea no encontrada.');

            $actual = (string)($t->estado ?? '');
            if (in_array($actual, ['terminada', 'scrap'], true)) {
                throw new Exception('La tarea ya está cerrada y no puede cambiar de estado.');
            }

            $now = date('Y-m-d H:i:s');

            $upd = [
                'estado' => $estado,
                'actualizado_en' => $now,
            ];

            if ($estado === 'en_proceso') {
                if (empty($t->inicio_real)) $upd['inicio_real'] = $now;
                $upd['motivo_bloqueo'] = null;
            }

            if ($estado === 'pausada') {
                if (empty($t->inicio_real)) throw new Exception('No puedes pausar una tarea que no ha iniciado.');
            }

            if ($estado === 'terminada') {
                if (empty($t->inicio_real)) throw new Exception('No puedes terminar una tarea que no ha iniciado.');
                if (empty($t->fin_real)) $upd['fin_real'] = $now;
                $upd['motivo_bloqueo'] = null;
            }

            if ($estado === 'bloqueada_calidad') {
                $upd['motivo_bloqueo'] = $motivo;
            }

            Capsule::table('tareas')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($upd);

            return [
                'id' => $id,
                'estado' => $estado,
                'inicio_real' => $upd['inicio_real'] ?? ($t->inicio_real ?? null),
                'fin_real' => $upd['fin_real'] ?? ($t->fin_real ?? null),
                'motivo_bloqueo' => array_key_exists('motivo_bloqueo', $upd)
                    ? $upd['motivo_bloqueo']
                    : ($t->motivo_bloqueo ?? null),
            ];
        });
    }
}
