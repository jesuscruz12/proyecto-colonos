<?php

class wlusuariosController extends adminController
{
    /** @var wlusuariosModel */
    private $_usuarioscrm;
    /** @var usuariosModel|null */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        // ===== Seguridad de sesión =====
        if (!Session::get('autenticado')) {
            $this->redireccionar(''); // Redirige al login
        }

        // ===== Modelos =====
        $this->_usuarioscrm = $this->loadModel('wlusuarios'); // tabla: wl_usuarioscrm
        $this->_usuarios    = $this->loadModel('usuarios');   // para derivar cv_wl si no está en sesión

        // ===== Asegurar contexto de wallet =====
        $this->ensureCvWlInSession();
    }

    /**
     * Renderiza la vista de usuarios.
     * Carga el JS `index.js` de la carpeta de la vista.
     */
    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /**
     * Listado server-side para DataTables.
     * Admite GET/POST: draw, start, length, search[value], order...
     * Devuelve: draw, recordsTotal, recordsFiltered, data[]
     */
    public function usuarios_list()
    {
        header('Content-Type: application/json; charset=utf-8');

        // DataTables puede mandar GET o POST; usamos $_REQUEST.
        $req    = $_REQUEST;
        $draw   = (int)($req['draw']   ?? 1);
        $start  = (int)($req['start']  ?? 0);
        $length = (int)($req['length'] ?? 10);

        $cv_wl = (int) (Session::get('cv_wl') ?? 0);
        if ($cv_wl <= 0) {
            return $this->jsonRaw([
                'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []
            ]);
        }

        // Base filtrada por wallet; (opcional) excluir Admin (rol = 1)
        $base = $this->_usuarioscrm
            ->select('id_usuario','nombre','email','telefono','rol','estatus','cv_wl')
            ->where('cv_wl', $cv_wl)
            ->where('rol', '<>', 1);

        // Búsqueda global
        $search = trim((string)($req['search']['value'] ?? ''));
        if ($search !== '') {
            $base->where(function($q) use ($search) {
                $q->where('nombre',  'LIKE', "%{$search}%")
                  ->orWhere('email',  'LIKE', "%{$search}%")
                  ->orWhere('telefono','LIKE', "%{$search}%");
            });
        }

        // Conteos correctos
        $recordsTotal    = $this->_usuarioscrm->where('cv_wl', $cv_wl)->where('rol','<>',1)->count();
        $recordsFiltered = (clone $base)->count();

        // Orden (por defecto id DESC)
        if (!empty($req['order'][0])) {
            $idx = (int)$req['order'][0]['column'];
            $dir = strtoupper($req['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
            $columns = ['id_usuario','nombre','email','telefono','rol','estatus'];
            $col = $columns[$idx] ?? 'id_usuario';
            $base->orderBy($col, $dir);
        } else {
            $base->orderBy('id_usuario','DESC');
        }

        // Paginación
        if ($length > 0) {
            $base->offset($start)->limit($length);
        }

        $data = $base->get();

        return $this->jsonRaw([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ]);
    }

    /**
     * Crear un usuario.
     * Espera: nombre, email, telefono (opcional), password, rol, estatus
     */
    public function crear()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf(); // 🔒 Descomenta si ya envías csrf_token en el form

        $cv_wl = (int) (Session::get('cv_wl') ?? 0);
        if ($cv_wl <= 0) {
            return $this->jsonFail('Wallet no encontrado en sesión.');
        }

        // ===== Sanitización y validación =====
        $nombre   = $this->post('nombre');
        $email    = $this->post('email');
        $telefono = preg_replace('/\D+/', '', $this->post('telefono')); // solo dígitos
        $password = $this->post('password');
        $rol      = (int) ($this->post('rol')     ?: 3);
        $estatus  = (int) ($this->post('estatus') ?: 1);

        if ($nombre === '' || $email === '' || $password === '') {
            return $this->jsonFail('Nombre, Email y Password son obligatorios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonFail('Email inválido.');
        }
        if (strlen($password) < 6) {
            return $this->jsonFail('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($telefono !== '' && strlen($telefono) !== 10) {
            return $this->jsonFail('El teléfono debe tener 10 dígitos (o dejarlo vacío).');
        }

        // Email único por wallet
        $dup = $this->_usuarioscrm
            ->where('cv_wl', $cv_wl)
            ->where('email', $email)
            ->count();
        if ($dup > 0) {
            return $this->jsonFail('El email ya está registrado en este wallet.');
        }

        // Inserción
        try {
            $nuevo = $this->_usuarioscrm;
            $nuevo->cv_wl    = $cv_wl;
            $nuevo->nombre   = $nombre;
            $nuevo->email    = $email;
            $nuevo->telefono = $telefono !== '' ? $telefono : null;
            $nuevo->password = password_hash($password, PASSWORD_BCRYPT);
            $nuevo->rol      = $rol;
            $nuevo->estatus  = $estatus;
            // $nuevo->creado_por = (int) (Session::get('id_usuario') ?? 0);
            $nuevo->save();
        } catch (\Throwable $e) {
            return $this->jsonFail('Error al crear el usuario.');
        }

        return $this->jsonOk('Usuario creado exitosamente.');
    }

    /**
     * Obtener un usuario (para llenar el modal de edición).
     * Espera: POST clave (id_usuario)
     * Devuelve: array con un registro (compatibilidad con tu JS actual).
     */
    public function datos_show_usuario()
    {
        header('Content-Type: application/json; charset=utf-8');

        $clave = (int) $this->post('clave');
        $cv_wl = (int) (Session::get('cv_wl') ?? 0);

        if ($clave <= 0 || $cv_wl <= 0) {
            return $this->jsonRaw([]);
        }

        $data = $this
            ->_usuarioscrm
            ->select('*')
            ->where('id_usuario', $clave)
            ->where('cv_wl', $cv_wl)
            ->get()
            ->toArray();

        return $this->jsonRaw($data);
    }

    /**
     * Editar usuario.
     * Espera: clave (id), nombre, email, telefono (opcional), password (opcional), rol, estatus
     */
    public function editar_usuario()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf(); // 🔒 Descomenta si ya envías csrf_token en el form

        $id    = (int) $this->post('clave');
        $cv_wl = (int) (Session::get('cv_wl') ?? 0);
        if ($id <= 0 || $cv_wl <= 0) {
            return $this->jsonFail('Solicitud inválida.');
        }

        $editar = $this
            ->_usuarioscrm
            ->where('id_usuario', $id)
            ->where('cv_wl', $cv_wl)
            ->first();

        if (!$editar) {
            return $this->jsonFail('Registro no encontrado o sin permisos.');
        }

        // ===== Sanitización y validación =====
        $nombre   = $this->post('nombre');
        $email    = $this->post('email');
        $telefono = preg_replace('/\D+/', '', $this->post('telefono'));
        $rol      = (int) ($this->post('rol')     ?: 3);
        $estatus  = (int) ($this->post('estatus') ?: 1);
        $passNew  = $this->post('password');

        if ($nombre === '' || $email === '') {
            return $this->jsonFail('Nombre y Email son obligatorios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonFail('Email inválido.');
        }
        if ($telefono !== '' && strlen($telefono) !== 10) {
            return $this->jsonFail('El teléfono debe tener 10 dígitos (o dejarlo vacío).');
        }

        // Email único por wallet (excluyendo el propio id)
        $dup = $this
            ->_usuarioscrm
            ->where('cv_wl', $cv_wl)
            ->where('email', $email)
            ->where('id_usuario', '!=', $id)
            ->count();
        if ($dup > 0) {
            return $this->jsonFail('El email ya está en uso por otro usuario de este wallet.');
        }

        // Actualización
        try {
            $editar->nombre   = $nombre;
            $editar->email    = $email;
            $editar->telefono = $telefono !== '' ? $telefono : null;
            $editar->rol      = $rol;
            $editar->estatus  = $estatus;

            // Password opcional
            if ($passNew !== '') {
                if (strlen($passNew) < 6) {
                    return $this->jsonFail('La contraseña debe tener al menos 6 caracteres.');
                }
                $editar->password = password_hash($passNew, PASSWORD_BCRYPT);
            }

            $editar->save();
        } catch (\Throwable $e) {
            return $this->jsonFail('Error al actualizar el usuario.');
        }

        return $this->jsonOk('Usuario actualizado correctamente.');
    }

    /**
     * Eliminar usuario.
     * Espera: POST id_usuario
     */
    public function eliminar_usuario()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf(); // 🔒 Descomenta si ya envías csrf_token en el form

        $id    = (int) $this->post('id_usuario');
        $cv_wl = (int) (Session::get('cv_wl') ?? 0);
        if ($id <= 0 || $cv_wl <= 0) {
            return $this->jsonFail('Solicitud inválida.');
        }

        try {
            $deleted = $this
                ->_usuarioscrm
                ->where('id_usuario', $id)
                ->where('cv_wl', $cv_wl)
                ->delete();

            // Tu JS acepta "ok" o {ok:true}; devolvemos "ok" para 100% compatibilidad
            return $this->jsonRaw($deleted ? 'ok' : ['ok'=>false, 'msg'=>'No se pudo eliminar el usuario.']);
        } catch (\Throwable $e) {
            return $this->jsonFail('Error de servidor al eliminar.');
        }
    }

    // ===============================
    // Helpers privados
    // ===============================

    /** CSRF: compara token del form contra sesión (actívalo en acciones POST). */
    private function ensureCsrf(): void
    {
        $t = $this->post('csrf_token');
        if (!$t || $t !== Session::get('tokencsrf')) {
            http_response_code(419);
            die('CSRF inválido');
        }
    }

    /**
     * Asegura que exista `cv_wl` en sesión.
     * Intenta obtenerlo desde `Session::get('usuario')` o desde la tabla `usuarios`.
     */
    private function ensureCvWlInSession(): void
    {
        if (Session::get('cv_wl')) return;

        $usuario = Session::get('usuario') ?: [];
        if (!empty($usuario['cv_wl'])) {
            Session::set('cv_wl', (int)$usuario['cv_wl']);
            return;
        }

        $id = (int) (Session::get('id_usuario') ?? 0);
        if ($id > 0 && $this->_usuarios) {
            $row = $this
                ->_usuarios
                ->select('cv_wl')
                ->where('id_usuario', $id)
                ->first();

            if ($row) {
                $cv = is_object($row) ? $row->cv_wl : ($row['cv_wl'] ?? null);
                if ($cv !== null) {
                    Session::set('cv_wl', (int)$cv);
                }
            }
        }
    }

    // ---- Utilidades de E/S JSON y POST ----

    /** Lee un parámetro POST de forma segura, devolviendo string siempre. */
    private function post(string $key): string
    {
        // Si tu framework provee getPostParam, úsalo como preferencia:
        if (method_exists($this, 'getPostParam')) {
            $v = $this->getPostParam($key);
            if ($v !== null) return (string)$v;
        }
        return isset($_POST[$key]) ? (string)$_POST[$key] : '';
    }

    /** Respuesta JSON estandarizada de éxito. */
    private function jsonOk(string $msg, array $extra = [])
    {
        echo json_encode(array_merge(['ok'=>true, 'msg'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
        return null;
    }

    /** Respuesta JSON estandarizada de error. */
    private function jsonFail(string $msg, int $code = 200, array $extra = [])
    {
        if ($code !== 200) http_response_code($code);
        echo json_encode(array_merge(['ok'=>false, 'msg'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
        return null;
    }

    /** Respuesta JSON "cruda" (para DataTables, o "ok" string). */
    private function jsonRaw($payload)
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        return null;
    }
}
