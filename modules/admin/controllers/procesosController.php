<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\procesosController.php

class procesosController extends adminController
{
    /** @var procesosModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('procesos');
    }

    private function empresaId(): int { return (int)Session::get('empresa_id'); }

    private function json($payload, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'procesos';
        $this->_view->setJs(['index']); // /modules/admin/views/procesos/js/index.js
        $this->_view->renderizar(['index']);
    }

    // =========================
    // DataTables
    // =========================
    public function recursos_list()
    {
        try {
            $out = $this->_m->dtList($this->empresaId(), $_GET);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw'=>(int)($_GET['draw']??1),
                'recordsTotal'=>0,
                'recordsFiltered'=>0,
                'data'=>[]
            ], 200);
        }
    }

    // =========================
    // CRUD
    // =========================
    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_m->getProc($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function save()
    {
        try {
            $id = $this->_m->saveProc($this->empresaId(), $_POST);
            $this->json(['ok'=>true,'id'=>$id,'msg'=>'Guardado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar'], 400);
        }
    }

    public function delete()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id<=0) $this->json(['ok'=>false,'msg'=>'ID inválido'], 400);

            // ✅ desactivar (borrado lógico)
            $this->_m->deleteProc($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Desactivado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>'Error al desactivar'], 400);
        }
    }

    // =========================
    // Máquinas / Asignación
    // =========================
    public function maquinas()
    {
        $this->json(['ok'=>true,'data'=>$this->_m->maquinasActivas($this->empresaId())]);
    }

    public function asignacion_get()
    {
        $pid = (int)($_GET['proceso_id'] ?? 0);
        $this->json(['ok'=>true,'data'=>$this->_m->maquinasAsignadas($this->empresaId(), $pid)]);
    }

    public function asignacion_save()
    {
        try {
            $pid = (int)($_POST['proceso_id'] ?? 0);
            if ($pid<=0) $this->json(['ok'=>false,'msg'=>'Proceso inválido'], 400);

            $ids = $_POST['maquina_ids'] ?? [];
            if (!is_array($ids)) $ids = [];

            $ids = array_values(array_unique(array_map('intval', $ids)));

            $this->_m->setAsignacionMaquinas($this->empresaId(), $pid, $ids);
            $this->json(['ok'=>true,'msg'=>'Asignación guardada']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar asignación'], 400);
        }
    }
}
