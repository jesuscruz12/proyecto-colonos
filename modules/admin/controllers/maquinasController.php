<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\maquinasController.php

class maquinasController extends adminController
{
    /** @var maquinasModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('maquinas');
    }

    private function empresaId(): int { return (int)(Session::get('empresa_id') ?? 0); }

    private function json($payload, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'maquinas';
        $this->_view->setJs(['index']); // /modules/admin/views/maquinas/js/index.js
        $this->_view->renderizar(['index']);
    }

    // =========================
    // DataTables
    // =========================
    public function recursos_list()
    {
        try {
            $dt = $_GET + $_POST;
            $out = $this->_m->dtList($this->empresaId(), $dt);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw'=>(int)(($_GET['draw'] ?? $_POST['draw'] ?? 1)),
                'recordsTotal'=>0,
                'recordsFiltered'=>0,
                'data'=>[],
                'error'=>$e->getMessage()
            ], 200);
        }
    }

    // =========================
    // Catálogos auxiliares
    // =========================
    public function recursos_meta()
    {
        try {
            $eid = $this->empresaId();
            $this->json([
                'ok' => true,
                'data' => [
                    'calendarios' => $this->_m->calendariosList($eid),
                    'tipos' => $this->_m->tiposList($eid),
                ]
            ]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error'], 400);
        }
    }

    // =========================
    // CRUD
    // =========================
    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_m->getMaq($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function save()
    {
        try {
            $id = $this->_m->saveMaq($this->empresaId(), $_POST);
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

            $this->_m->deleteMaq($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Desactivado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al desactivar'], 400);
        }
    }
}
