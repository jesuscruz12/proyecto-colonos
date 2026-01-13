<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\embarquesController.php

class embarquesController extends adminController
{
    /** @var embarquesModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('embarques');
    }

    private function empresaId(): int { return (int)(Session::get('empresa_id') ?? 0); }
    private function uid(): int { return (int)(Session::get('usuario_id') ?? 0); }

    private function json($payload, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'embarques';
        $this->_view->setJs(['index']); // /modules/admin/views/embarques/js/index.js
        $this->_view->renderizar(['index']);
    }

    // -------------------------
    // DataTables
    // -------------------------
    public function recursos_list()
    {
        try {
            $out = $this->_m->dtList($this->empresaId(), $_GET + $_POST);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw'=>(int)($_GET['draw'] ?? 1),
                'recordsTotal'=>0,
                'recordsFiltered'=>0,
                'data'=>[],
                'error'=> ($e->getMessage() ?: 'Error')
            ], 200);
        }
    }

    public function recursos_meta()
    {
        try {
            $this->json(['ok'=>true,'data'=>$this->_m->meta($this->empresaId())]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error'], 400);
        }
    }

    // -------------------------
    // GET/SAVE/DELETE
    // -------------------------
    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_m->getEmb($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function save()
    {
        try {
            $id = $this->_m->saveEmb($this->empresaId(), $this->uid(), $_POST);
            $this->json(['ok'=>true,'id'=>$id,'msg'=>'Guardado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar'], 400);
        }
    }

    public function delete()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $this->_m->deleteEmb($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Eliminado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al eliminar'], 400);
        }
    }

    // -------------------------
    // Search helpers
    // -------------------------
    public function search_ots()
    {
        try {
            $q = (string)($_GET['q'] ?? '');
            $rows = $this->_m->searchOts($this->empresaId(), $q, 25);
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error'], 400);
        }
    }

    public function search_partes()
    {
        try {
            $q = (string)($_GET['q'] ?? '');
            $rows = $this->_m->searchPartes($this->empresaId(), $q, 25);
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error'], 400);
        }
    }

    public function search_productos()
    {
        try {
            $q = (string)($_GET['q'] ?? '');
            $rows = $this->_m->searchProductos($this->empresaId(), $q, 25);
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error'], 400);
        }
    }
}
