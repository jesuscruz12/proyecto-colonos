<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends Controller
{
    private $_usuarios;        // Modelo wl_usuarioscrm (tabla de usuarios que hacen login)
    private $_wlempresas;
    private $_wl_usuarioscrm;

    public function __construct()
    {
        parent::__construct();
        $this->_usuarios       = $this->loadModel('usuarios');         // si lo usas en otros lados
        $this->_wlempresas     = $this->loadModel('wlempresas');       // opcional
        $this->_wl_usuarioscrm = $this->loadModel('wl_usuarioscrm');   // <- importante para login
    }

    /** Render del login + autenticación */
    public function index()
    {
        // Ya autenticado → al panel
        if (Session::get('autenticado')) {
            $this->redireccionar('admin');
        }

        // ¿Enviaron formulario?
        if ($this->getInt('enviar') == 1) {
            $email    = trim((string)$this->getPostParam('usuario'));
            $password = (string)$this->getPostParam('password');

            // Para repintar el campo usuario si hay error
            $this->_view->datos = ['usuario' => $email];

            // Validaciones básicas
            if (!$this->validarEmail($email)) {
                $this->_view->_error = 'Credenciales inválidas';
                $this->_view->renderizar(['index'], 'ajax'); return;
            }
            if ($password === '') {
                $this->_view->_error = 'Debes ingresar tu contraseña';
                $this->_view->renderizar(['index'], 'ajax'); return;
            }

            // ===== 1) Traer usuario por email (con password/estatus/rol/cv_wl/ultimo_login)
            $row = $this->_wl_usuarioscrm->buscarPorEmail($email);
            if (!$row) {
                $this->_view->_error = 'Nombre de usuario y / o contraseña incorrectos';
                $this->_view->renderizar(['index'], 'ajax'); return;
            }

            // Normaliza
            if (is_object($row)) $row = (array)$row;

            // Usuario activo
            if ((int)($row['estatus'] ?? 0) !== 1) {
                $this->_view->_error = 'Cuenta deshabilitada. Contacta al administrador.';
                $this->_view->renderizar(['index'], 'ajax'); return;
            }

            // ===== 2) Verificación de contraseña
            $stored = (string)($row['password'] ?? '');
            $ok = false;

            // a) Hash moderno (bcrypt/argon)
            if (preg_match('/^\$2[ayb]\$|\$argon2i\$|\$argon2id\$/', $stored)) {
                $ok = password_verify($password, $stored);
            } else {
                // b) Legacy: sha1 + HASH_KEY (compatibilidad)
                $legacy = Hash::getHash('sha1', $password, HASH_KEY);
                $ok = hash_equals($legacy, $stored);
            }

            if (!$ok) {
                $this->_view->_error = 'Nombre de usuario y / o contraseña incorrectos';
                $this->_view->renderizar(['index'], 'ajax'); return;
            }

            // ===== 3) Auto-upgrade a hash moderno si aún es legacy
            if (!preg_match('/^\$2[ayb]\$|\$argon2i\$|\$argon2id\$/', $stored)) {
                try {
                    $this->_wl_usuarioscrm->actualizarPassword((int)$row['id_usuario'], password_hash($password, PASSWORD_DEFAULT));
                } catch (\Throwable $e) { /* silencioso */ }
            }

            // ===== 4) Regenerar ID de sesión (previene fijación de sesión)
            if (function_exists('session_regenerate_id')) {
                @session_regenerate_id(true);
            }

            // ===== 5) Guardar sesión mínima
            Session::set('autenticado', true);
            Session::set('id_usuario', (int)$row['id_usuario']);
            Session::set('rol',        (int)$row['rol']);
            Session::set('cv_wl',      (int)($row['cv_wl'] ?? 0));
            Session::set('tiempo',     time());

            Session::set('usuario', [
                'id_usuario'   => (int)$row['id_usuario'],
                'nombre'       => (string)($row['nombre'] ?? ''),
                'email'        => (string)($row['email'] ?? $email),
                'rol'          => (int)$row['rol'],
                'cv_wl'        => (int)($row['cv_wl'] ?? 0),
                'ultimo_login' => (string)($row['ultimo_login'] ?? ''),
            ]);

            // CSRF para formularios/acciones POST
            $core = new CORE;
            Session::set('tokencsrf', $core->cadena_aleatoria(32));

            // ===== 6) Actualizar último login
            try {
                $this->_wl_usuarioscrm->actualizarUltimoLogin((int)$row['id_usuario'], date('Y-m-d H:i:s'));
            } catch (\Throwable $e) { /* silencioso */ }

            // ===== 7) Redirigir (ajusta si tienes rutas por rol)
            $this->redireccionar('admin/wlindicadores');
            return;
        }

        // Primera carga o tras error
        $this->_view->renderizar(['index'], 'ajax');
    }

    /** Logout seguro: sólo POST y con CSRF válido */
    public function cerrar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed'); die('Método no permitido');
        }

        $token = (string)$this->getPostParam('csrf_token');
        $sess  = (string)(Session::get('tokencsrf') ?: '');

        if (!$token || !hash_equals($sess, $token)) {
            header('HTTP/1.1 419 Authentication Timeout'); die('CSRF inválido');
        }

        Session::destroy();

        // Opcional: invalidar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        $this->redireccionar('');
    }
}
