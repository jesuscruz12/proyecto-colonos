<?php

class programacionmaquinasController extends adminController
{
    /** @var programacionmaquinasModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('programacionmaquinas');
    }

    private function empresaId(): int
    {
        return (int)Session::get('empresa_id');
    }

    public function index()
    {
        $this->_view->menu = 'programacionmaquinas';
        $this->_view->setJs(['index']);     // ✅ aquí se carga /views/programacionmaquinas/js/index.js
        $this->_view->renderizar(['index']); // ✅ aquí mete layout completo (como planeación)
    }

    public function dt_maquinas()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dt = $_GET + $_POST;
            echo json_encode($this->_m->dtMaquinas($this->empresaId(), $dt));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => (int)($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar máquinas'
            ]);
        }
    }

    public function dt_agenda()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $maquinaId = (int)($_REQUEST['maquina_id'] ?? 0);
            if ($maquinaId <= 0) {
                echo json_encode([
                    'draw' => (int)($_REQUEST['draw'] ?? 1),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => []
                ]);
                return;
            }

            $dt = $_GET + $_POST;
            echo json_encode($this->_m->dtAgenda($empresaId, $maquinaId, $dt));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => (int)($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar agenda'
            ]);
        }
    }

    public function cat_ots()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $q = (string)($_GET['q'] ?? '');
            echo json_encode(['ok' => true, 'data' => $this->_m->catOts($empresaId, $q)]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Error al cargar OTs']);
        }
    }

    public function get_one()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $id = (int)($_GET['id'] ?? 0);
            $r = $id > 0 ? $this->_m->getProgramacion($empresaId, $id) : null;

            echo json_encode(['ok' => (bool)$r, 'data' => $r]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Error al cargar registro']);
        }
    }

    public function save()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $uid = (int)Session::get('usuario_id');

            $out = $this->_m->saveProgramacion($empresaId, $uid, $_POST);
            echo json_encode($out);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage() ?: 'Error al guardar']);
        }
    }

    public function dt_tareas()
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        $empresaId = $this->empresaId();
        $maquinaId = (int)($_REQUEST['maquina_id'] ?? 0);

        if ($maquinaId <= 0) {
            echo json_encode([
                'draw' => (int)($_REQUEST['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ]);
            return;
        }

        $dt = $_GET + $_POST;
        echo json_encode($this->_m->dtTareasAgenda($empresaId, $maquinaId, $dt));
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'draw' => (int)($_GET['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Error al cargar tareas'
        ]);
    }
}


    public function delete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
                return;
            }

            $ok = $this->_m->deleteProgramacion($empresaId, $id);
            echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Eliminado.' : 'No se eliminó.']);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Error al eliminar']);
        }
    }
}
