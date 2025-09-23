<?php
class Bootstrap
{
	//pasamos el objeto del Request
	public static function run(Request $peticion)
	{
		$modulo = $peticion->getModulo();
		//para que quede el nombre con la nomenclatura nombreController
		$controller = $peticion->getControlador() . 'Controller';
		//ruta del archivo 
		//$rutaControlador= ROOT. 'controllers'. DS . $controller . '.php';
		$metodo = $peticion->getMetodo();
		$args = $peticion->getArgs();
		if ($modulo) {
			$rutaModulo = ROOT . 'controllers' . DS . $modulo . 'Controller.php';

			if (is_readable($rutaModulo)) {
				require_once $rutaModulo;
				$rutaControlador = ROOT . 'modules' . DS . $modulo . DS . 'controllers' . DS . $controller . '.php';
			} else {
				throw new Exception('Error de base de modulo');
			}
		} else {
			$rutaControlador = ROOT . 'controllers' . DS . $controller . '.php';
		}

		//comprueba si se puede leer la ruta
		if (is_readable($rutaControlador)) {
			require_once $rutaControlador;
			$controller = new $controller;
			//verificar que el metodo sea valido
			if (is_callable(array($controller, $metodo))) {
				$metodo = $peticion->getMetodo();
			} else {
				$metodo = 'index';
			}

			if (isset($args)) {
				//enviamos el nombre de la clase el metodo que queremos llamar de la clase y los argumentos
				call_user_func_array(array($controller, $metodo), $args);
			} else {
				call_user_func(array($controller, $metodo));
				//ejecuta una clase y su metodo
			}
		} else {
			//si el archivo no es valido
			throw new Exception('no encontrado');
		}
	}
}
