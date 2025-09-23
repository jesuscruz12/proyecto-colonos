<?php

class tabla_testController extends adminController
{
    private $_tabla_test;
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_tabla_test = $this->loadModel('tabla_test');
        $this->_usuarios = $this->loadModel('usuarios');
    }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        $this->_view->setJs(array('index'));
        $this->_view->renderizar(array('index'));
    }

    public function recursos_list()
    {
        $data = $this->_tabla_test
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    public function registrar_recurso()
    {
        $json = new CORE;

        //$cv_usuario = Session::get('cv_usuario');

        $nuevo = $this->_tabla_test;
        $nuevo->campo1 = $this->getPostParam('campo1');
        $nuevo->campo2 = $this->getPostParam('campo2');
        $nuevo->campo3 = $this->getPostParam('campo3');
        $nuevo->campo4 = date('Y-m-d H:i:s');
        $nuevo->save();

        $json->jsonError('info', 'Registro creado exitosamente.');
    }

    public function datos_show_recurso()
    {
        $clave = $this->getPostParam('clave');

        $data = $this->_tabla_test
            ->select("*")
            ->where("id", "=", $clave)
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    public function editar_recurso()
    {
        $json = new CORE;
        $clave = $this->getPostParam('clave');

        $editar = $this->_tabla_test
            ->where("id", "=", $clave)
            ->first();

        if (!$editar) {
            $json->jsonError('error', 'Registro no encontrado.');
            return;
        }

        $editar->campo1 = $this->getPostParam('campo1');
        $editar->campo2 = $this->getPostParam('campo2');
        $editar->campo3 = $this->getPostParam('campo3');
        $editar->save();

        $json->jsonError('info', 'Registro actualizado correctamente.');
    }

    public function eliminar_recurso()
    {
        $clave = $this->getPostParam('clave');

        $this->_tabla_test
            ->where("id", "=", $clave)
            ->delete();
    }
}
