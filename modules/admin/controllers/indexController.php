<?php

class indexController extends adminController
{
    /** @var indexModel */
    private $_home;

    public function __construct()
    {
        parent::__construct();
        $this->_home = $this->loadModel('index');
    }

    public function index()
    {
        $this->_view->menu = 'home';

        $empresaId = (int)(Session::get('empresa_id') ?? 0);
        $this->_view->empresa_id = $empresaId;

        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    // =========================
    // Helpers (solo controlador)
    // =========================
    private function jsonHeader()
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    private function getEmpresaId(): int
    {
        return (int)(Session::get('empresa_id') ?? 0);
    }

    private function getDateParam(string $key): ?string
    {
        $v = isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
        if ($v === '') return null;

        // valida YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;

        $dt = DateTime::createFromFormat('Y-m-d', $v);
        if (!$dt) return null;

        return $dt->format('Y-m-d');
    }

    private function getLimitParam(int $default = 500, int $max = 5000): int
    {
        $v = isset($_GET['limit']) ? (int)$_GET['limit'] : $default;
        if ($v < 1) $v = $default;
        if ($v > $max) $v = $max;
        return $v;
    }

    private function getStringParam(string $key): ?string
    {
        $v = isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
        return $v === '' ? null : $v;
    }

    private function filtrosBase(): array
    {
        $desde  = $this->getDateParam('desde');
        $hasta  = $this->getDateParam('hasta');
        $estado = $this->getStringParam('estado');

        // normaliza rango si vienen al revés
        if ($desde && $hasta && $desde > $hasta) {
            $tmp = $desde; $desde = $hasta; $hasta = $tmp;
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'estado' => $estado];
    }

    // =========
    // JSON KPIs
    // =========
    public function kpis()
    {
        $this->jsonHeader();

        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            echo json_encode($this->_home->kpis($empresaId, $f['desde'], $f['hasta']));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Error al cargar KPIs']);
        }
    }

    // =========================
    // DataTables JSON {data:[]}
    // =========================
    public function ots_dt()
    {
        $this->jsonHeader();

        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            $limit = $this->getLimitParam(500, 5000);

            $rows = $this->_home->otsListado($empresaId, $f, $limit);
            echo json_encode(['data' => $rows]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['data' => [], 'error' => true, 'message' => 'Error al cargar OTs']);
        }
    }

    public function tareas_dt()
    {
        $this->jsonHeader();

        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            $limit = $this->getLimitParam(500, 5000);

            $rows = $this->_home->tareasListado($empresaId, $f, $limit);
            echo json_encode(['data' => $rows]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['data' => [], 'error' => true, 'message' => 'Error al cargar tareas']);
        }
    }

    public function auditoria_dt()
    {
        $this->jsonHeader();

        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            $limit = $this->getLimitParam(500, 5000);

            $rows = $this->_home->auditoriaListado($empresaId, $f, $limit);
            echo json_encode(['data' => $rows]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['data' => [], 'error' => true, 'message' => 'Error al cargar auditoría']);
        }
    }

    // =========================
    // Export CSV (backend)
    // =========================
    private function csvOut(string $filename, array $header, array $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM para Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, $header);

        foreach ($rows as $r) {
            // $r puede venir como stdClass (Capsule)
            $a = (array)$r;
            fputcsv($out, $a);
        }

        fclose($out);
        exit;
    }

    public function export_ots_csv()
    {
        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            $limit = $this->getLimitParam(5000, 5000);

            $rows = $this->_home->otsListado($empresaId, $f, $limit);

            $name = 'reporte_ots_' . date('Ymd_His') . '.csv';

            $header = ['id','folio_ot','descripcion','estado','fecha_compromiso','prioridad','numero_dibujo','creado_en'];
            $this->csvOut($name, $header, $rows);
        } catch (Throwable $e) {
            http_response_code(500);
            echo "Error exportando CSV";
        }
    }

    public function export_tareas_csv()
    {
        try {
            $empresaId = $this->getEmpresaId();
            $f = $this->filtrosBase();
            $limit = $this->getLimitParam(5000, 5000);

            $rows = $this->_home->tareasListado($empresaId, $f, $limit);

            $name = 'reporte_tareas_' . date('Ymd_His') . '.csv';

            $header = ['id','orden_trabajo_id','folio_ot','proceso_id','secuencia','cantidad','estado','inicio_planeado','fin_planeado','maquina_id','creado_en'];
            $this->csvOut($name, $header, $rows);
        } catch (Throwable $e) {
            http_response_code(500);
            echo "Error exportando CSV";
        }
    }
}
