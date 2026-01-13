<?php
// C:\xampp\htdocs\qacrmtaktik\modules\admin\controllers\bomController.php

class bomController extends adminController
{
    /** @var bomModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) $this->redireccionar('');
        $this->_m = $this->loadModel('bom');
    }

    private function empresaId(): int { return (int)Session::get('empresa_id'); }

    private function userId(): int
    {
        foreach (['usuario_id','id_usuario','uid','user_id','id'] as $k) {
            $v = (int)Session::get($k);
            if ($v > 0) return $v;
        }
        return 0;
    }

    private function json($payload, int $code=200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index()
    {
        $this->_view->menu = 'bom';
        $this->_view->setJs(['index']); // /modules/admin/views/bom/js/index.js
        $this->_view->renderizar(['index']);
    }

    // =========================
    // Versiones BOM (DataTables)
    // =========================
    public function recursos_list()
    {
        try {
            $out = $this->_m->dtList($this->empresaId(), $_GET);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json(['draw'=>(int)($_GET['draw']??1),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]], 200);
        }
    }

    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_m->getVersion($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function save()
    {
        try {
            $id = $this->_m->saveVersion($this->empresaId(), $this->userId(), $_POST);
            $this->json(['ok'=>true,'id'=>$id,'msg'=>'Guardado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar'], 400);
        }
    }

    public function deactivate()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id<=0) $this->json(['ok'=>false,'msg'=>'ID inválido'], 400);

            $this->_m->deactivateVersion($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Desactivado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al desactivar'], 400);
        }
    }

    public function vigente()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $fecha = trim((string)($_POST['fecha_vigencia'] ?? ''));
            if ($id<=0) $this->json(['ok'=>false,'msg'=>'ID inválido'], 400);
            if ($fecha === '') $fecha = date('Y-m-d');

            $this->_m->makeVigente($this->empresaId(), $id, $fecha);
            $this->json(['ok'=>true,'msg'=>'Marcada como vigente']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al actualizar vigente'], 400);
        }
    }

    // =========================
    // Meta Componentes
    // =========================
    public function comps_meta()
    {
        $this->json([
            'ok'=>true,
            'data'=>[
                'partes'=>$this->_m->partesList($this->empresaId()),
                'subensambles'=>$this->_m->subensamblesList($this->empresaId()),
            ]
        ]);
    }

    // =========================
    // Componentes (DataTables)
    // =========================
    public function comps_list()
    {
        try {
            $vbId = (int)($_GET['version_bom_id'] ?? 0);
            $out  = $this->_m->dtCompList($this->empresaId(), $vbId, $_GET);
            $this->json($out);
        } catch(Throwable $e) {
            $this->json(['draw'=>(int)($_GET['draw']??1),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]], 200);
        }
    }

    public function comp_get()
    {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $this->_m->getComp($this->empresaId(), $id);
        if (!$r) $this->json(['ok'=>false,'msg'=>'No encontrado'], 404);
        $this->json(['ok'=>true,'data'=>$r]);
    }

    public function comp_save()
    {
        try {
            $id = $this->_m->saveComp($this->empresaId(), $_POST);
            $this->json(['ok'=>true,'id'=>$id,'msg'=>'Guardado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al guardar componente'], 400);
        }
    }

    public function comp_delete()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id<=0) $this->json(['ok'=>false,'msg'=>'ID inválido'], 400);

            $this->_m->deleteComp($this->empresaId(), $id);
            $this->json(['ok'=>true,'msg'=>'Eliminado']);
        } catch(Throwable $e) {
            $this->json(['ok'=>false,'msg'=>$e->getMessage() ?: 'Error al eliminar componente'], 400);
        }
    }
}
