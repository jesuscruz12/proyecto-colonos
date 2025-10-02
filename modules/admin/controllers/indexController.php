<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends adminController
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

    // public function index()
    // {
    //     $core = new CORE;
    //     $head = $core->head();
    //     eval($head);

    //     $vistas = array('index');
    //     $this->_view->renderizar($vistas);
    // }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        

        //$this->_view->setJs(array('index'));
        //$this->_view->renderizar(array('index'));


        // Redirección limpia (sin eval, sin render, sin JS)
        $this->redireccionar('admin/wlindicadores');
        // Si tu helper ya antepone "admin/", podrías usar:
        // $this->redireccionar('wlindicadores');
    }
}
