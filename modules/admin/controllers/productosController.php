<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\productosController.php

class productosController extends adminController
{
    /** @var productosModel */
    private $_p;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_p = $this->loadModel('productos');
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
        $this->_view->menu = 'productos';
        $this->_view->setJs(['index']); // /modules/admin/views/productos/js/index.js
        $this->_view->renderizar(['index']);
    }

    public function recursos_list()
    {
        try {
            $out = $this->_p->dtList($this->empresaId(), $_GET);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json([
                'draw'=>(int)($_GET['draw']??1),
                'recordsTotal'=>0,
                'recordsFiltered'=>0,
                'data'=>[],
                'error'=>$e->getMessage()
            ], 200);
        }
    }

    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_p->getProd($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function save()
    {
        try {
            $id = $this->_p->saveProd($this->empresaId(), $_POST);
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

            $this->_p->deleteProd($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Desactivado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al desactivar'], 400);
        }
    }
}
