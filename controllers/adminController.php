<?php
class adminController extends Controller
{


	public function __construct()
	{
		parent::__construct();
		if (!Session::get('autenticado')) {
			$this->redireccionar('');
		}
		// if (Session::get('tipo_usuario') == ADMINISTRADOR) {
		// } else {
		// 	$this->redireccionar('');
		// }
	}
	public function index() {}
}
