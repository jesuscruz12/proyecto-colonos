<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends Controller
{
    /** @var usuariosModel */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();
        $this->_usuarios = $this->loadModel('usuarios');
    }

    public function index()
    {
        if (Session::get('autenticado')) {
            header('Location: ' . BASE_URL . 'admin/index');
            exit;
        }

        if ($this->getInt('enviar') == 1) {

            $email    = trim((string)$this->getPostParam('usuario'));
            $password = (string)$this->getPostParam('password');

            $this->_view->datos = ['usuario' => $email];

            // Solo email + password
            if (!$this->validarEmail($email) || $password === '') {
                $this->_view->_error = 'Credenciales inválidas';
                $this->_view->renderizar(['index'], 'ajax');
                return;
            }

            try {
                $row = $this->_usuarios->buscarPorEmail($email);
            }catch (Throwable $e) {
    echo '<pre>';
    echo $e->getMessage();
    echo "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
}


            if (!$row) {
                $this->_view->_error = 'Email y / o contraseña incorrectos';
                $this->_view->renderizar(['index'], 'ajax');
                return;
            }

            $row = is_object($row) ? (array)$row : (array)$row;

            $stored = (string)($row['password_hash'] ?? '');
            if ($stored === '' || !password_verify($password, $stored)) {
                $this->_view->_error = 'Email y / o contraseña incorrectos';
                $this->_view->renderizar(['index'], 'ajax');
                return;
            }

            if (function_exists('session_regenerate_id')) {
                @session_regenerate_id(true);
            }

            $uid       = (int)($row['id'] ?? 0);
            $empresaId = (int)($row['empresa_id'] ?? 0);

            // Rol principal
            $rolId = 0;
            try {
                $rolId = $this->_usuarios->obtenerRolIdPorUsuario($uid);
            } catch (\Throwable $e) {}

            if ($rolId <= 0) {
                $rolId = 7; // fallback (ajústalo si tu rol admin es otro)
            }

            // Sesión
            Session::set('autenticado', true);

            Session::set('id_usuario', $uid);
            Session::set('usuario_id', $uid);
            Session::set('id',        $uid);

            Session::set('empresa_id', $empresaId);

            Session::set('rol_id', $rolId);
            Session::set('rol',    $rolId);

            Session::set('tiempo', time());

            Session::set('usuario', [
                'id'         => $uid,
                'nombre'     => (string)($row['nombre'] ?? ''),
                'email'      => (string)($row['email'] ?? $email),
                'empresa_id' => $empresaId,
                'rol_id'     => $rolId,
                'puesto'     => (string)($row['puesto'] ?? ''),
            ]);

            // CSRF interno
            Session::set('tokencsrf', bin2hex(random_bytes(16)));

            // Último acceso
            try {
                $this->_usuarios->actualizarUltimoAcceso($uid, date('Y-m-d H:i:s'));
            } catch (\Throwable $e) {}

            header('Location: ' . BASE_URL . 'admin/index');
            exit;
        }

        $this->_view->renderizar(['index'], 'ajax');
    }

    // logout seguro (POST + CSRF)
    public function cerrar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            die('Método no permitido');
        }

        $token = (string)$this->getPostParam('csrf_token');
        $sess  = (string)(Session::get('tokencsrf') ?: '');

        if (!$token || !hash_equals($sess, $token)) {
            header('HTTP/1.1 419 Authentication Timeout');
            die('CSRF inválido');
        }

        Session::destroy();
        header('Location: ' . BASE_URL);
        exit;
    }

    // logout debug (GET)
    public function cerrar_debug()
    {
        Session::destroy();
        header('Location: ' . BASE_URL);
        exit;
    }
}
