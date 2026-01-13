<?php
class indicadoresController extends adminController
{
    /** @var indicadoresModel */
    private $_indicadores;

    /** @var usuariosModel */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_indicadores = $this->loadModel('indicadores');
        $this->_usuarios    = $this->loadModel('usuarios');
    }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        // para resaltar menú en el header
        $this->_view->menu = 'indicadores';

        // js de la vista
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /* =======================
       HELPERS DE PARÁMETROS
       ======================= */

    /** Normaliza fechas YYYY-MM-DD -> rango completo del día */
    private function rangoDia(string $desdeDia = null, string $hastaDia = null): array
    {
        $desde = $desdeDia ? ($desdeDia . ' 00:00:00') : null;
        $hasta = $hastaDia ? ($hastaDia . ' 23:59:59') : null;
        return [$desde, $hasta];
    }

    private function getQuery(string $key, $default = null)
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /* ==========
       ENDPOINTS
       ========== */

    /**
     * GET /admin/indicadores/kpis?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     */
    public function kpis()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            [$desde, $hasta] = $this->rangoDia(
                $this->getQuery('desde'),
                $this->getQuery('hasta')
            );

            $data = $this->_indicadores->kpis($desde, $hasta);
            echo json_encode($data);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/indicadores/serie_diaria?dias=N
     *     /admin/indicadores/serie_diaria?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     */
    public function serie_diaria()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dias     = (int) $this->getQuery('dias', 14);
            $desdeDia = $this->getQuery('desde');
            $hastaDia = $this->getQuery('hasta');

            if ($desdeDia && $hastaDia) {
                echo json_encode(
                    $this->_indicadores->serieDiaria($dias, $desdeDia, $hastaDia)
                );
                return;
            }

            echo json_encode($this->_indicadores->serieDiaria($dias));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/indicadores/geopoints?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     * Devuelve puntos con lat/lng para el mapa (uno por accidente).
     */
    public function geopoints()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // OJO: aquí el modelo espera días (YYYY-MM-DD), no timestamps completos
            $desdeDia = $this->getQuery('desde');
            $hastaDia = $this->getQuery('hasta');

            $rows = $this->_indicadores->geopoints($desdeDia, $hastaDia);
            echo json_encode($rows);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/indicadores/mapa?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     * Alias para el resumen por regiones + markers + puntos.
     */
    public function mapa()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $desdeDia = $this->getQuery('desde');
            $hastaDia = $this->getQuery('hasta');

            $data = $this->_indicadores->mapa($desdeDia, $hastaDia);
            echo json_encode($data);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
