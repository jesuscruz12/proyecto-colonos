<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class Bootstrap
{
    private static $capsuleBooted = false;

    // pasamos el objeto del Request
    public static function run(Request $peticion)
    {
        // ============================================================
        // 0) BOOTSTRAP DB (Capsule) — una sola vez
        // ============================================================
        if (!self::$capsuleBooted) {

            // Composer autoload (necesario para Illuminate)
            $autoload = ROOT . 'vendor' . DS . 'autoload.php';
            if (is_readable($autoload)) {
                require_once $autoload;
            }

            $capsule = new Capsule();

            $capsule->addConnection([
                'driver'    => 'mysql',
                'host'      => DB_HOST,
                'database'  => DB_NAME,
                'username'  => DB_USER,
                'password'  => DB_PASS,
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
                'prefix'    => '',
                'strict'    => false,
            ]);

            // Hacer global Capsule::table()
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            self::$capsuleBooted = true;
        }

        // ============================================================
        // 1) ROUTING NORMAL
        // ============================================================
        $modulo = $peticion->getModulo();
        $controller = $peticion->getControlador() . 'Controller';
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

        if (is_readable($rutaControlador)) {
            require_once $rutaControlador;

            $controller = new $controller;

            if (is_callable(array($controller, $metodo))) {
                $metodo = $peticion->getMetodo();
            } else {
                $metodo = 'index';
            }

            if (isset($args)) {
                call_user_func_array(array($controller, $metodo), $args);
            } else {
                call_user_func(array($controller, $metodo));
            }
        } else {
            throw new Exception('no encontrado');
        }
    }
}
