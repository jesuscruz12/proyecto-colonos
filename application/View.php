<?php
//clase para manejar las vistas
class View
{

	private $_request;
	private $_js;
	private $_rutas;

	public function __construct(Request $peticion)
	{
		$this->_request = $peticion;
		$this->_js = array();
		$this->_rutas = array();


		$modulo = $this->_request->getModulo();
		$controlador = $this->_request->getControlador();

		if ($modulo) {
			$this->_rutas['view'] = ROOT . 'modules' . DS . $modulo . DS . 'views' . DS . $controlador . DS;
			$this->_rutas['js'] = BASE_URL . 'modules/' . $modulo . '/views/' . $controlador . '/js/';
			$this->_rutas['ruta_general'] = 'modules/' . $modulo . '/';
		} else {
			$this->_rutas['view'] = ROOT . 'views' . DS . $controlador . DS;
			$this->_rutas['js'] = BASE_URL . 'views/' . $controlador . '/js/';
			$this->_rutas['ruta_general'] = '';
		}
	}

	/*
	 * comprobra si se puede leer una direccion de vista
	 */
	public function disponibleview($vista)
	{
		if (is_readable($this->_rutas['view'] . $vista . '.phtml')) {
			return true;
		} else {
			throw new Exception("Error de vista");
		}
	}



	//metodo que incluye la vista
	public function renderizar(array $vista, $item = false)
	{
		$js = array();
		if (count($this->_js)) {
			$js = $this->_js;
		}
		/*cuando no se desea compartir el layaut
	  $_layoutParams=array(
	  'ruta_css'=>BASE_URL.''.$this->_rutas['ruta_general'].'views/layout/'.DEFAULT_LAYOUT.'/css/',
	  'ruta_img'=>BASE_URL.''.$this->_rutas['ruta_general'].'views/layout/'.DEFAULT_LAYOUT.'/img/',
	  'ruta_js'=>BASE_URL.''.$this->_rutas['ruta_general'].'views/layout/'.DEFAULT_LAYOUT.'/js/',
	  'js'=>$js
	  );*/
		$_layoutParams = array(
			'ruta_css' => BASE_URL . 'views/layout/' . DEFAULT_LAYOUT . '/css/',
			'ruta_img' => BASE_URL . 'views/layout/' . DEFAULT_LAYOUT . '/img/',
			'ruta_js' => BASE_URL . 'views/layout/' . DEFAULT_LAYOUT . '/js/',
			'js' => $js
		);

		if (!$item == 'ajax') {
			//opcion uno es para cuando no se desea compartir el layout del lado usuario normal
			//include_once ROOT .''.$this->_rutas['ruta_general'].'views' .DS .'layout'.DS. DEFAULT_LAYOUT. DS.'header.php';
			include_once ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'header.php';
		}
		//obtener las vistas en forma de array	
		if (is_array($vista) && count($vista)) {
			for ($i = 0; $i < count($vista); $i++) {
				if ($this->disponibleview($vista[$i])) {
					/*
				  * analisar el array por si es una ruta absoluta o relativa
				  */
					if (preg_match("/^@/", $vista[$i])) {
						$rutax = str_replace("@", "", $vista[$i]);
						include_once ROOT . 'views' . DS . $vista[$i] . '.phtml';
					} else {
						include_once $this->_rutas['view'] . $vista[$i] . '.phtml';
					}
				}
			}
		}

		if (!$item == 'ajax') {
			//include_once ROOT .''.$this->_rutas['ruta_general'].'views' .DS .'layout'.DS. DEFAULT_LAYOUT. DS.'footer.php';
			include_once ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'footer.php';
		}
	}
	//agregar js de otras vistas
	public function  setJs(array $js)
	{
		if (is_array($js) && count($js)) {
			for ($i = 0; $i < count($js); $i++) {

				if (preg_match("/^@/", $js[$i])) {
					$rutaxjs = str_replace("@", "", $js[$i]);
					$this->_js[] = BASE_URL . '' . $rutaxjs . '.js';
				} else {
					$this->_js[] = $this->_rutas['js'] . $js[$i] . '.js';
				}
			}
		} else {
			throw new Exception("Error de js");
		}
	}

	//funcion para cargar widget basico
	public function widget($widget, $method, $options = array())
	{
		if (!is_array($options)) {
			$options = array($options);
		}

		if (is_readable(ROOT . 'widgets' . DS . $widget . '.php')) {
			include_once ROOT . 'widgets' . DS . $widget . '.php';

			$widgetClass = $widget . 'Widget';

			if (!class_exists($widgetClass)) {
				throw new Exception('Error clase widget');
			}

			if (is_callable($widgetClass, $method)) {
				if (count($options)) {
					return call_user_func_array(array(new $widgetClass, $method), $options);
				} else {
					return call_user_func(array(new $widgetClass, $method));
				}
			}

			throw new Exception('Error metodo widget');
		}

		throw new Exception('Error de widget');
	}
}
