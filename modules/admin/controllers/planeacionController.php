<?php

class planeacionController extends adminController
{
    /** @var planeacionModel */
    private $_p;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_p = $this->loadModel('planeacion');
    }

    private function empresaId(): int
    {
        return (int)Session::get('empresa_id');
    }

    public function index()
    {
        $this->_view->menu = 'planeacion';
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    public function dt_ots_pendientes()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dt = $_GET + $_POST;
            echo json_encode($this->_p->dtOtsPendientes($this->empresaId(), $dt));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => (int)($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar OTs'
            ]);
        }
    }

    public function detalle()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $otId = (int)($_GET['ot_id'] ?? 0);

            if ($otId <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'OT inválida']);
                return;
            }

            $ot = $this->_p->getOtHeader($empresaId, $otId);
            if (!$ot) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'msg' => 'OT no encontrada']);
                return;
            }

            $items = $this->_p->getOtItems($empresaId, $otId);
            $ops   = $this->_p->getOtOperaciones($empresaId, $otId);

            $totalMin = 0;
            foreach ($ops as $o) $totalMin += (int)$o['duracion_minutos'];

            $val = $this->_p->validarPlanificable($empresaId, $otId);

            echo json_encode([
                'ok' => true,
                'ot' => $ot,
                'items' => $items,
                'operaciones' => $ops,
                'total_minutos_estimados' => $totalMin,
                'planificable' => $val['ok'],
                'planificable_msg' => $val['msg'],
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Error al cargar detalle']);
        }
    }

    public function generar()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $empresaId = $this->empresaId();
            $otId = (int)($_POST['ot_id'] ?? 0);
            $uid  = (int)Session::get('usuario_id');

            if ($otId <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'OT inválida']);
                return;
            }

            // 1) crea tareas (sin duplicar)
            $n = $this->_p->crearTareas($empresaId, $otId, $uid);

            // 2) programa por calendario + proceso_maquina
            $prog = $this->_p->programarOt($empresaId, $otId);

            echo json_encode([
                'ok' => true,
                'tareas' => $n,
                'programadas' => $prog['programadas'] ?? 0,
                'pendientes'  => $prog['pendientes'] ?? 0,
            ]);

        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage() ?: 'Error en planeación']);
        }
    }
}
