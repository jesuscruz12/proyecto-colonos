<?php
/**
 * Tareas — Controller TAKTIK (PROD) — COMPLETO AJUSTADO
 * Endpoints:
 *  - GET  /admin/tareas
 *  - GET  /admin/tareas/data          (DataTables serverSide)
 *  - GET  /admin/tareas/catalogos     (procesos/maquinas/estados)
 *  - GET  /admin/tareas/entidades_buscar?tipo=ot|item|parte|subensamble|producto&ot_id=1&q=...
 *  - GET  /admin/tareas/get?id=#
 *  - POST /admin/tareas/create
 *  - POST /admin/tareas/update
 *  - POST /admin/tareas/delete
 *  - POST /admin/tareas/set_estado
 */

class tareasController extends adminController
{
    /** @var tareasModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');

        $this->_m = $this->loadModel('tareas');
    }

    private function empresaId(): int
    {
        return (int)Session::get('empresa_id');
    }

    private function uid(): int
    {
        // ✅ consistente con tu create/update
        return (int)Session::get('usuario_id');
    }

    private function jOut(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'tareas';
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    // =========================
    // Catálogos chicos
    // =========================
    public function catalogos()
    {
        $empresaId = $this->empresaId();

        $this->jOut([
            'ok' => true,
            'procesos' => $this->_m->catProcesos($empresaId),
            'maquinas' => $this->_m->catMaquinas($empresaId),
            'estados'  => $this->_m->catEstadosTarea(),
        ]);
    }

    // =========================
    // Autocomplete
    // =========================
    public function entidades_buscar()
    {
        $empresaId = $this->empresaId();

        $tipo = (string)($_GET['tipo'] ?? '');
        $q    = (string)($_GET['q'] ?? '');
        $otId = (int)($_GET['ot_id'] ?? 0);

        try {
            if ($tipo === 'ot') {
                $rows = $this->_m->buscarOT($empresaId, $q);
                return $this->jOut(['ok' => true, 'data' => $rows]);
            }

            if ($tipo === 'item') {
                if ($otId <= 0) return $this->jOut(['ok' => true, 'data' => []]);
                $rows = $this->_m->buscarItemsOT($empresaId, $otId, $q);
                return $this->jOut(['ok' => true, 'data' => $rows]);
            }

            if ($tipo === 'parte') {
                $rows = $this->_m->buscarPartes($empresaId, $q);
                return $this->jOut(['ok' => true, 'data' => $rows]);
            }

            if ($tipo === 'subensamble') {
                $rows = $this->_m->buscarSubensambles($empresaId, $q);
                return $this->jOut(['ok' => true, 'data' => $rows]);
            }

            if ($tipo === 'producto') {
                $rows = $this->_m->buscarProductos($empresaId, $q);
                return $this->jOut(['ok' => true, 'data' => $rows]);
            }

            return $this->jOut(['ok' => false, 'message' => 'Tipo inválido'], 400);

        } catch (Throwable $e) {
            return $this->jOut(['ok' => false, 'message' => 'Error al buscar'], 500);
        }
    }

    // =========================
    // DataTables
    // =========================
    public function data()
    {
        $empresaId = $this->empresaId();
        $dt = $_GET;

        $filtros = [
            'estado'     => (string)($_GET['estado'] ?? ''),
            'proceso_id' => (int)($_GET['proceso_id'] ?? 0),
            'maquina_id' => (int)($_GET['maquina_id'] ?? 0),
            'ot_estado'  => (string)($_GET['ot_estado'] ?? ''),
            'desde'      => (string)($_GET['desde'] ?? ''),
            'hasta'      => (string)($_GET['hasta'] ?? ''),
        ];

        try {
            $total    = $this->_m->dtTotales($empresaId);
            $search   = (string)($dt['search']['value'] ?? '');
            $filtered = $this->_m->dtFiltrados($empresaId, $filtros, $search);
            $rows     = $this->_m->dtDatos($empresaId, $dt, $filtros);
            $draw     = (int)($dt['draw'] ?? 1);

            $this->jOut([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows
            ]);
        } catch (Throwable $e) {
            $this->jOut([
                'draw' => (int)($dt['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar tareas'
            ], 500);
        }
    }

    // =========================
    // CRUD
    // =========================
    public function get()
    {
        $empresaId = $this->empresaId();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->jOut(['ok' => false, 'message' => 'ID inválido'], 400);

        $row = $this->_m->getTarea($empresaId, $id);
        if (!$row) $this->jOut(['ok' => false, 'message' => 'No encontrada'], 404);

        $this->jOut(['ok' => true, 'data' => $row]);
    }

    public function create()
    {
        $empresaId = $this->empresaId();
        $uid = $this->uid();

        try {
            $in = $_POST ?: [];
            $id = $this->_m->crearTarea($empresaId, $uid, $in);
            $this->jOut(['ok' => true, 'id' => $id, 'message' => 'Creada']);
        } catch (Throwable $e) {
            $this->jOut(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update()
    {
        $empresaId = $this->empresaId();
        $uid = $this->uid();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jOut(['ok' => false, 'message' => 'ID inválido'], 400);

        try {
            $in = $_POST ?: [];
            $this->_m->actualizarTarea($empresaId, $uid, $id, $in);
            $this->jOut(['ok' => true, 'message' => 'Actualizada']);
        } catch (Throwable $e) {
            $this->jOut(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete()
    {
        $empresaId = $this->empresaId();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jOut(['ok' => false, 'message' => 'ID inválido'], 400);

        try {
            $this->_m->eliminarTarea($empresaId, $id);
            $this->jOut(['ok' => true, 'message' => 'Eliminada']);
        } catch (Throwable $e) {
            $this->jOut(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================
    // Set Estado (Ruta 3 piso)
    // =========================
    public function set_estado()
    {
        // POST /admin/tareas/set_estado
        // in: id, estado, motivo(optional)
        $empresaId = $this->empresaId();
        $uid = $this->uid();

        try {
            // ✅ CSRF requerido (tu JS manda csrf_token + header X-CSRF-TOKEN)
            $csrfPost = (string)($_POST['csrf_token'] ?? '');
            $csrfHdr  = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
            $csrfIn   = $csrfPost !== '' ? $csrfPost : $csrfHdr;

            if ($csrfIn === '') {
                return $this->jOut(['ok' => false, 'message' => 'CSRF requerido.'], 400);
            }

            $id     = (int)($_POST['id'] ?? 0);
            $estado = trim((string)($_POST['estado'] ?? ''));
            $motivo = trim((string)($_POST['motivo'] ?? ''));

            if ($id <= 0) return $this->jOut(['ok' => false, 'message' => 'ID inválido.'], 400);
            if ($estado === '') return $this->jOut(['ok' => false, 'message' => 'Estado requerido.'], 400);

            // ✅ permitidos en UI (botones)
            $valid = ['en_proceso','pausada','terminada','bloqueada_calidad'];
            if (!in_array($estado, $valid, true)) {
                return $this->jOut(['ok' => false, 'message' => 'Estado no permitido.'], 400);
            }

            if ($estado === 'bloqueada_calidad' && $motivo === '') {
                return $this->jOut(['ok' => false, 'message' => 'Motivo requerido para Bloquear (calidad).'], 400);
            }

            // ✅ ejecuta lógica de negocio en modelo
            $out = $this->_m->setEstado($empresaId, $uid, $id, $estado, $motivo);

            return $this->jOut([
                'ok' => true,
                'message' => 'OK',
                'data' => $out
            ]);

        } catch (Throwable $e) {
            return $this->jOut(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
