<?php
/**
 * calendarioslaboralesController — TAKTIK (PROD)
 * Rutas:
 *  - /admin/calendarioslaborales
 *  - /admin/calendarioslaborales/dt
 *  - /admin/calendarioslaborales/dtDeleted
 *  - /admin/calendarioslaborales/getOne?id=
 *  - /admin/calendarioslaborales/create
 *  - /admin/calendarioslaborales/update
 *  - /admin/calendarioslaborales/delete   (soft)
 *  - /admin/calendarioslaborales/restore
 *  - /admin/calendarioslaborales/purge    (hard, solo eliminados)
 *  - /admin/calendarioslaborales/calendarConfig?id=
 */

class calendarioslaboralesController extends adminController
{
    private $_cal;
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');

        $this->_cal = $this->loadModel('calendarioslaborales');

        // tu head() suele usar esto
        $this->_usuarios = $this->loadModel('usuarios');
    }

    private function empresaId(): int
    {
        return (int)(Session::get('empresa_id') ?? 0);
    }

    private function jOk(string $msg = 'OK', array $extra = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true, 'message' => $msg], $extra));
        exit;
    }

    private function jErr(string $msg = 'Error', int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // =========================
    // Vista
    // =========================
    public function index()
    {
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index'], 'admin');
    }

    // =========================
    // DataTables
    // =========================
    public function dt()
    {
        try {
            $empresaId = $this->empresaId();
            $res = $this->_cal->dtListAll($empresaId, $_POST, false);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res);
            exit;
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    public function dtDeleted()
    {
        try {
            $empresaId = $this->empresaId();
            $res = $this->_cal->dtListAll($empresaId, $_POST, true);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res);
            exit;
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    // =========================
    // CRUD
    // =========================
    public function getOne()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $row = $this->_cal->getOne($empresaId, $id);
            if (!$row) $this->jErr('No encontrado', 404);

            $this->jOk('OK', ['data' => $row]);
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    public function create()
    {
        try {
            $empresaId = $this->empresaId();
            $id = $this->_cal->createCal($empresaId, $_POST);
            $this->jOk('Creado', ['id' => $id]);
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    public function update()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $this->_cal->updateCal($empresaId, $id, $_POST);
            $this->jOk('Actualizado');
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    // Soft delete
    public function delete()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $this->_cal->deleteCal($empresaId, $id);
            $this->jOk('Eliminado');
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $this->_cal->restoreCal($empresaId, $id);
            $this->jOk('Restaurado');
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    public function purge()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $this->_cal->purgeCal($empresaId, $id);
            $this->jOk('Eliminado definitivo');
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }

    /**
     * Config para FullCalendar:
     * - businessHours desde dias_laborales
     * - pausas como background events
     */
    public function calendarConfig()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) $this->jErr('ID inválido');

            $row = $this->_cal->getOne($empresaId, $id);
            if (!$row) $this->jErr('No encontrado', 404);

            // FullCalendar: 0=Dom..6=Sab
            $businessHours = [];
            foreach (($row['dias_laborales'] ?? []) as $d) {
                $dow = (int)$d['dow'];     // 1..7 (Lun..Dom)
                $fcDow = ($dow % 7);       // 7->0
                $businessHours[] = [
                    'daysOfWeek' => [$fcDow],
                    'startTime' => $d['inicio'],
                    'endTime' => $d['fin'],
                ];
            }

            $bgEvents = [];
            foreach (($row['pausas'] ?? []) as $p) {
                $dow = (int)$p['dow'];
                $fcDow = ($dow % 7);
                $bgEvents[] = [
                    'daysOfWeek' => [$fcDow],
                    'startTime' => $p['inicio'],
                    'endTime' => $p['fin'],
                    'display' => 'background',
                    'title' => (string)($p['nombre'] ?? 'Pausa')
                ];
            }

            $this->jOk('OK', [
                'data' => [
                    'nombre' => $row['nombre'],
                    'businessHours' => $businessHours,
                    'bgEvents' => $bgEvents
                ]
            ]);
        } catch (Exception $e) {
            $this->jErr($e->getMessage());
        }
    }
}
