<?php

class tablacoloniasController extends adminController
{
    private $_m;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_m = $this->loadModel('tablacolonias');
    }

    private function json($p, int $c = 200): void
    {
        http_response_code($c);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($p, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ===============================
       Vista principal
    =============================== */
    public function index()
    {
        $this->_view->menu = 'tablacolonias';
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /* ===============================
       DataTables
    =============================== */
    public function recursos_list()
    {
        $this->json($this->_m->dtList($_GET));
    }

    /* ===============================
       Obtener colonia
    =============================== */
    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido'], 400);
        }

        $r = $this->_m->get($id);
        if (!$r) {
            $this->json(['ok' => false, 'msg' => 'No encontrada'], 404);
        }

        $this->json(['ok' => true, 'data' => $r]);
    }

    /* ===============================
       Crear / Editar
    =============================== */
    public function save()
    {
        try {
            $id = $this->_m->saveColonia($_POST);

            $this->json([
                'ok'  => true,
                'id'  => $id,
                'msg' => 'Colonia guardada correctamente'
            ]);
        } catch (Throwable $e) {
            $this->json([
                'ok'  => false,
                'msg' => $e->getMessage()
            ], 400);
        }
    }

    /* ===============================
       Eliminar (soft delete)
    =============================== */
    public function delete()
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido'], 400);
        }

        // 👈 IMPORTANTE: llamar al método correcto
        $this->_m->deleteColonia($id);

        $this->json([
            'ok'  => true,
            'msg' => 'Colonia eliminada correctamente'
        ]);
    }

    /* ===============================
       PDF
    =============================== */
    public function pdf()
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('ID inválido');

        $colonia = $this->_m->get($id);
        if (!$colonia) die('No encontrada');

        ob_start();
        include ROOT . 'modules/admin/views/tablacolonias/pdf_ot.phtml';
        $html = ob_get_clean();

        require_once ROOT . 'vendor/autoload.php';

        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        header('Content-Type: application/pdf');
        echo $dompdf->output();
    }
}
