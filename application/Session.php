<?php

class Session
{
    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Si quieres cookies estrictas (opcional):
            // ini_set('session.cookie_httponly', '1');
            // ini_set('session.use_only_cookies', '1');

            session_start();
        }
    }

    // Destruir sesión completa o claves específicas
    public static function destroy($clave = false)
    {
        if ($clave) {
            if (is_array($clave)) {
                for ($i = 0; $i < count($clave); $i++) {   // ✅ FIX
                    if (isset($_SESSION[$clave[$i]])) {
                        unset($_SESSION[$clave[$i]]);
                    }
                }
            } else {
                if (isset($_SESSION[$clave])) {
                    unset($_SESSION[$clave]);
                }
            }
            return;
        }

        // Destruir TODO
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
    }

    public static function set($clave, $valor)
    {
        if ($clave !== '' && $clave !== null) {
            $_SESSION[$clave] = $valor;
        }
    }

    public static function get($clave)
    {
        return $_SESSION[$clave] ?? null;
    }

    // ============================
    // Acceso (legacy)
    // ============================
    public static function acceso($level)
    {
        if (!Session::get('autenticado')) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }

        if (Session::getLevel($level) > Session::getLevel(Session::get('level'))) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }
    }

    public static function accesoView($level)
    {
        if (!Session::get('autenticado')) return false;
        if (Session::getLevel($level) > Session::getLevel(Session::get('level'))) return false;
        return true;
    }

    public static function getLevel($level)
    {
        $role = [
            'admin'     => 3,
            'especial'  => 2,
            'usuario'   => 1
        ];

        if (!array_key_exists($level, $role)) {
            throw new Exception('Error de acceso');
        }

        return $role[$level];
    }

    public static function accesoEstricto(array $level, $noAdmin = false)
    {
        if (!Session::get('autenticado')) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }

        Session::tiempo();

        if ($noAdmin == false && Session::get('level') == 'admin') {
            return;
        }

        if (count($level) && in_array(Session::get('level'), $level)) {
            return;
        }

        header('location:' . BASE_URL . 'error/access/5050');
        exit;
    }

    public static function accesoViewEstricto(array $level, $noAdmin = false)
    {
        if (!Session::get('autenticado')) return false;

        if ($noAdmin == false && Session::get('level') == 'admin') {
            return true;
        }

        if (count($level) && in_array(Session::get('level'), $level)) {
            return true;
        }

        return false;
    }

    // ============================
    // Tiempo de sesión (SEGUNDOS)
    // ============================
    public static function tiempo()
    {
        if (!defined('SESSION_TIME')) {
            throw new Exception('No se ha definido SESSION_TIME');
        }

        // 0 = sesión indefinida
        if ((int)SESSION_TIME === 0) {
            return;
        }

        $t = (int)(Session::get('tiempo') ?? 0);

        // Si no hay tiempo, inicializa
        if ($t <= 0) {
            Session::set('tiempo', time());
            return;
        }

        // ✅ AHORA SESSION_TIME es en segundos (sin *60)
        if ((time() - $t) > (int)SESSION_TIME) {
            Session::destroy();
            header('location:' . BASE_URL . 'error/access/8080');
            exit;
        }

        // refresca actividad
        Session::set('tiempo', time());
    }
}
