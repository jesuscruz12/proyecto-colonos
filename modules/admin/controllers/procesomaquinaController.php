<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\procesomaquinaController.php

class procesomaquinaController extends adminController
{
    /** @var procesomaquinaModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('procesomaquina');
    }

    private function empresaId(): int { return (int)(Session::get('empresa_id') ?? 0); }
    private function usuarioId(): int { return (int)(Session::get('usuario_id') ?? 0); }

    private function json($payload, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'procesomaquina';
        $this->_view->setJs(['index']); // /modules/admin/views/procesomaquina/js/index.js
        $this->_view->renderizar(['index']);
    }

    // =========================
    // DataTables
    // =========================
    public function dt_maquinas()
    {
        try {
            $dt = $_GET + $_POST;
            $out = $this->_m->dtMaquinas($this->empresaId(), $dt);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw' => (int)($_GET['draw'] ?? $_POST['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 200);
        }
    }

    public function dt_procesos()
    {
        try {
            $dt = $_GET + $_POST;
            $maquinaId = (int)($dt['maquina_id'] ?? 0);
            $out = $this->_m->dtProcesos($this->empresaId(), $maquinaId, $dt);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw' => (int)($_GET['draw'] ?? $_POST['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 200);
        }
    }

    // =========================
    // Guardar asignación
    // =========================
    public function save()
    {
        try {
            $maquinaId = (int)($_POST['maquina_id'] ?? 0);
            $procesos  = $_POST['procesos'] ?? [];      // procesos[]
            if (!is_array($procesos)) $procesos = [];

            $out = $this->_m->saveAsignacion(
                $this->empresaId(),
                $this->usuarioId(),
                $maquinaId,
                $procesos
            );

            $this->json(['ok'=>true, 'msg'=>'Guardado', 'data'=>$out]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar'], 400);
        }
    }
}
