<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class usuarios_crmController extends adminController
{
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_usuarios = $this->loadModel('usuarios');
    }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);
        $vistas = array('index');
        $this->_view->setJs(array('index'));
        $this->_view->renderizar($vistas);
    }

    public function recursos_list()
    {
        $r = $this->_usuarios
            /* ->leftjoin("sims", "sims.cv_sim", "=", "sociospromotoressims.cv_sim") */
            ->select("*")
            ->where("tipo_usuario", "!=", 1)
            ->where("tipo_usuario", "!=", 2)
            ->where("tipo_usuario", "!=", 3)
            ->get()->toArray();

        echo json_encode($r);
    }

    public function datos_show()
    {
        $clave = $this->getInt('clave');
        $r = $this->_usuarios
            /* ->leftjoin("sims", "sims.cv_sim", "=", "sociospromotoressims.cv_sim") */
            ->select("*")
            ->where("cv_usuario", "=", $clave)
            ->get()->toArray();

        echo json_encode($r);
    }

    public function editar_recurso()
    {
        $clave = $this->getInt('clave');
        $password = $this->getPostParam('password');

        $password_hash = Hash::getHash('sha1', $password, HASH_KEY);

        $jsonx = new CORE;

        $this->_usuarios
            ->where("cv_usuario", "=", $clave)
            ->update(['password' => $password_hash]);

        $jsonx->jsonError('info', 'Usuario editado con exito.');
    }
}
