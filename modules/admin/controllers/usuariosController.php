<?php
/**
 * usuariosController.php — TAKTIK
 *
 * Rutas:
 *  - GET  /admin/usuarios              => vista
 *  - GET  /admin/usuarios/data         => DataTables server-side
 *  - GET  /admin/usuarios/get?id=      => obtener 1
 *  - POST /admin/usuarios/create       => crear
 *  - POST /admin/usuarios/update       => actualizar
 *  - POST /admin/usuarios/activo       => activar/desactivar
 *  - POST /admin/usuarios/password     => cambiar password
 *  - POST /admin/usuarios/delete       => soft delete
 */

class usuariosController extends adminController
{
    /** @var usuariosModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        $this->_m = $this->loadModel('usuarios');
    }

    // =========================================================
    // Vista
    // =========================================================
    public function index()
    {
        $this->_view->menu = 'usuarios';
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    // =========================================================
    // JSON helpers
    // =========================================================
    private function jheader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    private function jok($data = null): void
    {
        $this->jheader();
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jfail(string $msg, int $code = 400, $extra = null): void
    {
        $this->jheader();
        http_response_code($code);
        $out = ['ok' => false, 'message' => $msg];
        if ($extra !== null) $out['data'] = $extra;
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    /**
     * CSRF: acepta:
     * - Header: X-CSRF-TOKEN
     * - POST: csrf_token
     * - JSON: csrf_token
     */
    private function csrfOk(array $json = null): bool
    {
        $tokenSesion = (string)(Session::get('tokencsrf') ?: '');
        if ($tokenSesion === '') return true; // si no hay token configurado, no bloqueamos

        $hdr = '';
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $hdr = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];

        $post = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $j = ($json && isset($json['csrf_token'])) ? (string)$json['csrf_token'] : '';

        $t = $hdr ?: ($post ?: $j);
        return $t !== '' && hash_equals($tokenSesion, $t);
    }

    private function empresaId(): int
    {
        return (int)(Session::get('empresa_id') ?? 0);
    }

    // =========================================================
    // DataTables
    // =========================================================
    public function data()
    {
        $this->jheader();

        try {
            $empresaId = $this->empresaId();
            if ($empresaId <= 0) {
                echo json_encode([
                    'draw' => (int)($_GET['draw'] ?? 1),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'empresa_id inválido',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Construimos array con TODO lo que el modelo necesita
            $dt = [
                'draw'   => (int)($_GET['draw'] ?? 1),
                'start'  => (int)($_GET['start'] ?? 0),
                'length' => (int)($_GET['length'] ?? 25),
                'search' => [
                    'value' => isset($_GET['search']['value']) ? (string)$_GET['search']['value'] : ''
                ],
                'order'  => $_GET['order'] ?? [],

                // Filtros extra (si vienen)
                'activo' => $_GET['activo'] ?? '',
                'desde'  => $_GET['desde'] ?? '',
                'hasta'  => $_GET['hasta'] ?? '',
            ];

            $r = $this->_m->datatable($empresaId, $dt);

            echo json_encode([
                'draw' => (int)($r['draw'] ?? ($dt['draw'] ?? 1)),
                'recordsTotal' => (int)($r['total'] ?? 0),
                'recordsFiltered' => (int)($r['filtered'] ?? 0),
                'data' => $r['rows'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => (int)($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar usuarios',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================
    // GET 1
    // =========================================================
    public function get()
    {
        try {
            $empresaId = $this->empresaId();
            $id = (int)($_GET['id'] ?? 0);

            if ($empresaId <= 0 || $id <= 0) $this->jfail('Datos inválidos');

            $row = $this->_m->getById($empresaId, $id);
            if (!$row) $this->jfail('No encontrado', 404);

            $this->jok($row);
        } catch (Throwable $e) {
            $this->jfail('Error al cargar usuario', 500);
        }
    }

    // =========================================================
    // CREATE
    // =========================================================
    public function create()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $empresaId = $this->empresaId();
        if ($empresaId <= 0) $this->jfail('empresa_id inválido', 400);

        $data = [
            'nombre'         => $_POST['nombre'] ?? ($json['nombre'] ?? ''),
            'email'          => $_POST['email'] ?? ($json['email'] ?? ''),
            'telefono'       => $_POST['telefono'] ?? ($json['telefono'] ?? ''),
            'puesto'         => $_POST['puesto'] ?? ($json['puesto'] ?? ''),
            'activo'         => $_POST['activo'] ?? ($json['activo'] ?? 1),
            'password_plain' => $_POST['password_plain'] ?? ($json['password_plain'] ?? ''),
        ];

        try {
            $r = $this->_m->crearUsuario($empresaId, $data);
            if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo crear');

            $this->jok($r);
        } catch (Throwable $e) {
            $this->jfail('Error al crear usuario', 500);
        }
    }

    // =========================================================
    // UPDATE
    // =========================================================
    public function update()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $empresaId = $this->empresaId();
        if ($empresaId <= 0) $this->jfail('empresa_id inválido', 400);

        $id = (int)($_POST['id'] ?? ($json['id'] ?? 0));
        if ($id <= 0) $this->jfail('ID inválido');

        $data = [
            'nombre'   => $_POST['nombre'] ?? ($json['nombre'] ?? ''),
            'email'    => $_POST['email'] ?? ($json['email'] ?? ''),
            'telefono' => $_POST['telefono'] ?? ($json['telefono'] ?? ''),
            'puesto'   => $_POST['puesto'] ?? ($json['puesto'] ?? ''),
            'activo'   => $_POST['activo'] ?? ($json['activo'] ?? 1),
        ];

        try {
            $r = $this->_m->actualizarUsuario($empresaId, $id, $data);
            if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo actualizar');

            $this->jok($r);
        } catch (Throwable $e) {
            $this->jfail('Error al actualizar usuario', 500);
        }
    }

    // =========================================================
    // ACTIVO
    // =========================================================
    public function activo()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $empresaId = $this->empresaId();
        if ($empresaId <= 0) $this->jfail('empresa_id inválido', 400);

        $id = (int)($_POST['id'] ?? ($json['id'] ?? 0));
        $activo = (int)($_POST['activo'] ?? ($json['activo'] ?? 1));
        if ($id <= 0) $this->jfail('ID inválido');

        try {
            $r = $this->_m->setActivo($empresaId, $id, $activo);
            if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo actualizar');

            $this->jok($r);
        } catch (Throwable $e) {
            $this->jfail('Error al actualizar estado', 500);
        }
    }

    // =========================================================
    // PASSWORD
    // =========================================================
    public function password()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $empresaId = $this->empresaId();
        if ($empresaId <= 0) $this->jfail('empresa_id inválido', 400);

        $id = (int)($_POST['id'] ?? ($json['id'] ?? 0));
        $pw = (string)($_POST['password_plain'] ?? ($json['password_plain'] ?? ''));
        if ($id <= 0) $this->jfail('ID inválido');

        try {
            $r = $this->_m->setPassword($empresaId, $id, $pw);
            if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo cambiar');

            $this->jok($r);
        } catch (Throwable $e) {
            $this->jfail('Error al cambiar password', 500);
        }
    }

    // =========================================================
    // DELETE (soft)
    // =========================================================
    public function delete()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $empresaId = $this->empresaId();
        if ($empresaId <= 0) $this->jfail('empresa_id inválido', 400);

        $id = (int)($_POST['id'] ?? ($json['id'] ?? 0));
        if ($id <= 0) $this->jfail('ID inválido');

        try {
            $r = $this->_m->eliminarUsuario($empresaId, $id);
            if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo eliminar');

            $this->jok($r);
        } catch (Throwable $e) {
            $this->jfail('Error al eliminar usuario', 500);
        }
    }
}
