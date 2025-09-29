<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlindicadoresController extends adminController
{
    /** @var wlindicadoresModel */
    private $_indicadores;

    /** @var usuariosModel */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_indicadores = $this->loadModel('wlindicadores');
        $this->_usuarios    = $this->loadModel('usuarios');

        $this->ensureCvWlInSession();
    }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        // Inyecta JS: modules/admin/views/wlindicadores/js/index.js
        $this->_view->setJs(['index']);
        // Renderiza vista: modules/admin/views/wlindicadores/index.phtml
        $this->_view->renderizar(['index']);
    }

    /** ====== Utilidades ====== */
    private function getDateRange(): array
    {
        date_default_timezone_set('America/Monterrey');

        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';

        if ($desde === '' || $hasta === '') {
            $first = new DateTime('first day of this month 00:00:00');
            $last  = new DateTime('last day of this month 23:59:59');
            return [$first->format('Y-m-d H:i:s'), $last->format('Y-m-d H:i:s')];
        }

        $d = DateTime::createFromFormat('Y-m-d', $desde) ?: new DateTime($desde);
        $h = DateTime::createFromFormat('Y-m-d', $hasta) ?: new DateTime($hasta);
        $d->setTime(0, 0, 0);
        $h->setTime(23, 59, 59);
        return [$d->format('Y-m-d H:i:s'), $h->format('Y-m-d H:i:s')];
    }

    private function jsonOut($payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }

    /** ====== Endpoints JSON ====== */

    // GET: admin/wlindicadores/kpis
    public function kpis()
    {
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

        [$desde, $hasta] = $this->getDateRange();
        try {
            // Se ajusta en el modelo para que solo cuente recargas pagadas
            $kpis = $this->_indicadores->kpis($cv_wl, $desde, $hasta);
            return $this->jsonOut(['success' => true, 'data' => $kpis, 'desde' => $desde, 'hasta' => $hasta]);
        } catch (Throwable $e) {
            return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // GET: admin/wlindicadores/activaciones_por_dia
    public function activaciones_por_dia()
    {
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

        [$desde, $hasta] = $this->getDateRange();
        try {
            [$labels, $series] = $this->_indicadores->activacionesPorDia($cv_wl, $desde, $hasta);
            return $this->jsonOut(['success' => true, 'labels' => $labels, 'series' => $series]);
        } catch (Throwable $e) {
            return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // GET: admin/wlindicadores/recargas_por_dia
    public function recargas_por_dia()
    {
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

        [$desde, $hasta] = $this->getDateRange();
        try {
            // Se ajusta en el modelo para que solo use estatus_pago=1
            [$labels, $series] = $this->_indicadores->recargasPorDia($cv_wl, $desde, $hasta);
            return $this->jsonOut(['success' => true, 'labels' => $labels, 'series' => $series]);
        } catch (Throwable $e) {
            return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // GET: admin/wlindicadores/top_socios
    public function top_socios()
    {
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

        [$desde, $hasta] = $this->getDateRange();
        try {
            $items = $this->_indicadores->topSocios($cv_wl, $desde, $hasta);
            return $this->jsonOut(['success' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    // GET: admin/wlindicadores/consumo_mix
// GET: admin/wlindicadores/consumo_mix
public function consumo_mix()
{
    $cv_wl = (int) Session::get('cv_wl');
    if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

    [$desde, $hasta] = $this->getDateRange();
    try {
        $res = $this->_indicadores->consumoMix($cv_wl, $desde, $hasta);
        return $this->jsonOut([
            'success' => true,
            'labels'  => $res['labels'],
            'series'  => $res['series'],
            'counts'  => $res['counts'],   // 👈 aquí estaba el faltante
            'desde'   => $desde,
            'hasta'   => $hasta,
        ]);
    } catch (Throwable $e) {
        return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
    }
}


    // GET: admin/wlindicadores/top_planes
    public function top_planes()
    {
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) return $this->jsonOut(['success' => false, 'error' => 'Wallet no encontrado'], 400);

        [$desde, $hasta] = $this->getDateRange();
        try {
            // Se ajusta en el modelo para que solo use estatus_pago=1
            $res = $this->_indicadores->topPlanes($cv_wl, $desde, $hasta);
            return $this->jsonOut([
                'success' => true,
                'by'      => $res['by'] ?? 'unknown',
                'items'   => $res['items'] ?? []
            ]);
        } catch (Throwable $e) {
            return $this->jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }



    /** ====== Helpers ====== */
    private function ensureCvWlInSession(): void
    {
        if (Session::get('cv_wl')) return;

        $usuario = Session::get('usuario') ?: [];
        if (!empty($usuario['cv_wl'])) {
            Session::set('cv_wl', (int) $usuario['cv_wl']);
            return;
        }

        $id = (int) Session::get('id_usuario');
        if ($id) {
            $row = $this->_usuarios
                ->select('cv_wl')
                ->where('id_usuario', '=', $id)
                ->first();
            if ($row && isset($row->cv_wl)) Session::set('cv_wl', (int) $row->cv_wl);
        }
    }
}
