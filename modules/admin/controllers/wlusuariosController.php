<?php

class wlusuariosController extends adminController
{
    private $_usuarioscrm;
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        // Requiere sesión
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        // Modelos
        $this->_usuarioscrm = $this->loadModel('wlusuarios');
        $this->_usuarios    = $this->loadModel('usuarios');

        // Asegura cv_wl en sesión
        $this->ensureCvWlInSession();
    }

    /** Render de la vista /admin/wl_usuarioscrm */
    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /** LISTAR */
    public function usuarios_list()
    {
        $draw   = $_POST['draw'] ?? 1;
        $start  = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;

        $cv_wl = Session::get('cv_wl');
        header('Content-Type: application/json; charset=utf-8');

        if (!$cv_wl) {
            echo json_encode([]);
            return;
        }

        $query = $this->_usuarioscrm
            ->select(
                'id_usuario', 'nombre', 'email', 'telefono',
                'rol', 'estatus', 'cv_wl'
            )
            ->where('cv_wl', '=', (int) $cv_wl)
            ->where('rol', '<>',1);

        $recordsTotal    = $this->_usuarioscrm->count();
        $recordsFiltered = $query->count();

        $data = $query->offset($start)
                      ->limit($length)
                      ->orderBy('id_usuario', 'desc')
                      ->get();
        // echo json_encode($data);
        echo json_encode([
            'draw'            => intval($draw),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    /** CREAR */
    public function crear()
    {
        $json  = new CORE;
        $cv_wl = Session::get('cv_wl');

        if (!$cv_wl) {
            $json->jsonError('error', 'Wallet no encontrado en sesión.');
            return;
        }

        // Validaciones básicas
        $nombre   = trim((string) $this->getPostParam('nombre'));
        $email    = trim((string) $this->getPostParam('email'));
        $telefono = preg_replace('/\D+/', '', (string) $this->getPostParam('telefono'));
        $password = trim((string) $this->getPostParam('password'));
        $rol      = (int) ($this->getPostParam('rol') ?: 3);
        $estatus  = (int) ($this->getPostParam('estatus') ?: 1);

        if (!$nombre || !$email || !$password) {
            $json->jsonError('error', 'Nombre, Email y Password son obligatorios.');
            return;
        }

        // Encriptar password
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $nuevo = $this->_usuarioscrm;
        $nuevo->cv_wl   = (int) $cv_wl;
        $nuevo->nombre  = $nombre;
        $nuevo->email   = $email;
        $nuevo->telefono = $telefono;
        $nuevo->password = $hash;
        $nuevo->rol      = $rol;
        $nuevo->estatus  = $estatus;

        $nuevo->save();

        $json->jsonError('info', 'Usuario creado exitosamente.');
    }

    /** OBTENER 1 REGISTRO */
    public function datos_show_usuario()
    {
        $clave = $this->getPostParam('clave');
        $cv_wl = Session::get('cv_wl');

        //header('Content-Type: application/json; charset=utf-8');

        $data = $this->_usuarioscrm
            ->select('*')
            ->where('id_usuario', '=', (int) $clave)
            ->where('cv_wl', '=', (int) $cv_wl)
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** EDITAR */
    public function editar_usuario()
    {
        $json  = new CORE;
        $clave = $this->getPostParam('clave');
        $cv_wl = Session::get('cv_wl');

        $editar = $this->_usuarioscrm
            ->where('id_usuario', '=', $clave)
            ->where('cv_wl', '=', (int) $cv_wl)
            ->first();

        if (!$editar) {
            $json->jsonError('error', 'Registro no encontrado o sin permisos.');
            return;
        }

        $nombre   = trim((string) $this->getPostParam('nombre'));
        $email    = trim((string) $this->getPostParam('email'));
        $telefono = preg_replace('/\D+/', '', (string) $this->getPostParam('telefono'));
        $rol      = (int) ($this->getPostParam('rol') ?: 3);
        $estatus  = (int) ($this->getPostParam('estatus') ?: 1);

        $editar->nombre   = $nombre;
        $editar->email    = $email;
        $editar->telefono = $telefono;
        $editar->rol      = $rol;
        $editar->estatus  = $estatus;

        // Si envían password nuevo, actualizar
        $password = $this->getPostParam('password');
        if ($password) {
            $editar->password = password_hash($password, PASSWORD_BCRYPT);
        }

        $editar->save();

        $json->jsonError('info', 'Usuario actualizado correctamente.');
    }

    /** ELIMINAR */
    public function eliminar_usuario()
    {
        $clave = $this->getPostParam('id_usuario');
        $cv_wl = Session::get('cv_wl');

        $this->_usuarioscrm
            ->where('id_usuario', '=', $clave)
            ->where('cv_wl', '=', (int) $cv_wl)
            ->delete();

        echo json_encode("ok");
    }

    /** CSRF */
    private function ensureCsrf(): void
    {
        $t = $this->getPostParam('csrf_token');
        if (!$t || $t !== Session::get('tokencsrf')) {
            http_response_code(419);
            die('CSRF inválido');
        }
    }

    /** Asegura cv_wl en sesión */
    private function ensureCvWlInSession(): void
    {
        if (Session::get('cv_wl')) {
            return;
        }

        $usuario = Session::get('usuario') ?: [];
        if (isset($usuario['cv_wl']) && $usuario['cv_wl']) {
            Session::set('cv_wl', (int) $usuario['cv_wl']);
            return;
        }

        $id = Session::get('id_usuario');
        if ($id && $this->_usuarios) {
            $row = $this->_usuarios
                ->select('cv_wl')
                ->where('id_usuario', '=', (int) $id)
                ->first();

            if ($row) {
                $cv = is_object($row) ? $row->cv_wl : $row['cv_wl'] ?? null;
                if ($cv !== null) {
                    Session::set('cv_wl', (int) $cv);
                }
            }
        }
    }
}
