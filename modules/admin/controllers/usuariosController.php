<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class usuariosController extends adminController
{

	private $_logs;
	private $_usuarios;
	public function __construct()
	{
		parent::__construct();
		if (!Session::get('autenticado')) {
			$this->redireccionar('');
		}

		$this->_logs = $this->loadModel('logs');
		$this->_usuarios = $this->loadModel('usuarios');
	}


	public function index()
	{
		$core = new CORE;
		$head = $core->head();
		eval($head);
		$this->_view->activo_administradores = "mm-active";
		$vistas = array('index');
		$this->_view->setJs(array('index'));
		$this->_view->renderizar($vistas);
	}

	public function recursos_list()
	{
		$r = $this->_usuarios
			->select("*")
			->where("usuarios.tipo_usuario", "=", ADMINISTRADOR)
			->get()->toArray();
		echo json_encode($r);
	}


	//nuevo usuario
	public function registrar_recurso()
	{
		$x2 = $this->getCadena('x1');
		$x6 = $this->getPostParam('x2');
		$x7 = $this->getPostParam('x3');
		$jsonx = new CORE;

		if (!$this->validarEmail($x6)) {
			$jsonx->jsonError('warning', 'Correo electronico no valido.');
		}

		$datax = $this->_usuarios->where("email", "=", $x6)->get()->toArray();
		if (!empty($datax)) {
			$jsonx->jsonError('warning', 'El email ya se encuentra registrado.');
		}


		$contra = $x7;
		$password = Hash::getHash('sha1', $x7, HASH_KEY);
		$nuevo = $this->_usuarios;
		$nuevo->nombre_usuario = $x2;
		$nuevo->tipo_usuario = 1;
		$nuevo->email = $x6;
		$nuevo->password = $password;
		$nuevo->fecha_registro = date("Y-m-d");
		$nuevo->save();

		$log = $this->_logs;
		$log->contenido = "Registro nuevo usuario Correo " . $x6;
		$log->cv_usuario = Session::get('cv_usuario');
		$log->seccion = "Usuarios";
		$log->fecha_log = date('Y-m-d H:i:s');
		$log->save();
		$jsonx->jsonError('info', 'Creado con éxito.');
	}


	public function eliminar_recurso()
	{
		$clave = $this->getInt('clave');
		$dat = $this->_usuarios->find($clave);
		$dat->delete();

		$log = $this->_logs;
		$log->contenido = "Elimino usuario ID " . $clave;
		$log->cv_usuario = Session::get('cv_usuario');
		$log->seccion = "Usuarios";
		$log->fecha_log = date('Y-m-d H:i:s');
		$log->save();
	}

	public function datos_show_recurso()
	{
		$id = $this->getInt('clave');
		$data = $this->_usuarios
			->select("*")
			->where("cv_usuario", "=", $id)
			->get()->toArray();
		echo json_encode($data);
		exit;
	}

	public function editar_recurso()
	{
		$x2 = $this->getCadena('x1_x');
		$x6 = $this->getPostParam('x2_x');
		$x7 = $this->getPostParam('x3_x');
		$cv_usuario = $this->getInt('clave');
		$jsonx = new CORE;

		if (!$this->validarEmail($x6)) {
			$jsonx->jsonError('warning', 'Correo electronico no valido.');
		}

		$datax = $this->_usuarios
			->whereRaw("email = ? and cv_usuario != ?", [$x6, $cv_usuario])
			->get()->toArray();
		if (!empty($datax)) {
			$jsonx->jsonError('warning', 'El email ya se encuentra registrado.');
		}

		$nuevo = $this->_usuarios->find($cv_usuario);
		if ($x7 != '') {
			$password = Hash::getHash('sha1', $x7, HASH_KEY);
			$nuevo->password = $password;
		}
		$nuevo->nombre_usuario = $x2;
		$nuevo->email = $x6;
		$nuevo->save();


		$log = $this->_logs;
		$log->contenido = "Edito usuario Correo " . $x6;
		$log->cv_usuario = Session::get('cv_usuario');
		$log->seccion = "Usuarios";
		$log->fecha_log = date('Y-m-d H:i:s');
		$log->save();


		$jsonx->jsonError('info', 'Fue actualizado con éxito.');
	}
}
