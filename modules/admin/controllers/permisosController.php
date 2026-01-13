<?php

class permisosController extends adminController
{
    /** @var permisosModel */
    private $_m;

    public function __construct()
    {
        parent::__construct();
        $this->_m = $this->loadModel('permisos');
    }

    public function index()
    {
        $this->_view->menu = 'permisos';
        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    private function jheader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    private function jok($data = null): void
    {
        $this->jheader();
        echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jfail(string $msg, int $code = 400): void
    {
        $this->jheader();
        http_response_code($code);
        echo json_encode(['ok'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    private function csrfOk(array $json = null): bool
    {
        $tokenSesion = (string)(Session::get('tokencsrf') ?: '');
        if ($tokenSesion === '') return true;

        $hdr  = !empty($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        $post = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $j    = ($json && isset($json['csrf_token'])) ? (string)$json['csrf_token'] : '';

        $t = $hdr ?: ($post ?: $j);
        return $t !== '' && hash_equals($tokenSesion, $t);
    }

    // =========================
    // ROLES / USUARIOS (AJAX)
    // =========================
    public function roles()
    {
        try {
            $this->jok($this->_m->listarRoles());
        } catch (Throwable $e) {
            $this->jfail('Error al cargar roles', 500);
        }
    }

    public function usuarios()
    {
        try {
            $this->jok($this->_m->listarUsuariosConRol());
        } catch (Throwable $e) {
            $this->jfail('Error al cargar usuarios', 500);
        }
    }

    // =========================
    // MATRIZ
    // =========================
    public function matriz()
    {
        try {
            $this->jok($this->_m->matrizPermisos());
        } catch (Throwable $e) {
            $this->jfail('Error al cargar matriz', 500);
        }
    }

    // =========================
    // DATATABLES CATÁLOGO
    // =========================
    public function data()
    {
        $this->jheader();

        try {
            $draw   = (int)($_GET['draw'] ?? 1);
            $start  = max(0, (int)($_GET['start'] ?? 0));
            $length = (int)($_GET['length'] ?? 25);
            $length = ($length <= 0) ? 25 : min(200, $length);

            $search = '';
            if (isset($_GET['search']['value'])) $search = trim((string)$_GET['search']['value']);

            $all = $this->_m->listarPermisos($search);
            $total = count($this->_m->listarPermisos(''));

            $slice = array_slice($all, $start, $length);

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => count($all),
                'data' => $slice,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => (int)($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error al cargar permisos',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================
    // CRUD
    // =========================
    public function create()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $nombre = trim((string)($json['nombre'] ?? ($_POST['nombre'] ?? '')));
        if ($nombre === '') $this->jfail('Nombre requerido');

        $r = $this->_m->crearPermiso($nombre);
        if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo crear', 400);

        $this->jok($r);
    }

    public function update()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $id = (int)($json['id'] ?? ($_POST['id'] ?? 0));
        $nombre = trim((string)($json['nombre'] ?? ($_POST['nombre'] ?? '')));

        $r = $this->_m->actualizarPermiso($id, $nombre);
        if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo actualizar', 400);

        $this->jok($r);
    }

    public function delete()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $id = (int)($json['id'] ?? ($_POST['id'] ?? 0));

        $r = $this->_m->eliminarPermiso($id);
        if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo eliminar', 400);

        $this->jok($r);
    }

    // =========================
    // ASIGNACIÓN
    // =========================
    public function rolSet()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $rolId = (int)($json['rol_id'] ?? 0);
        $ids   = (array)($json['permisos_ids'] ?? []);

        $r = $this->_m->setPermisosRol($rolId, $ids);
        if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo guardar', 400);

        $this->jok($r);
    }

    public function rolToggle()
    {
        $json = $this->getJsonBody();
        if (!$this->csrfOk($json)) $this->jfail('CSRF inválido', 403);

        $rolId = (int)($json['rol_id'] ?? 0);
        $pid   = (int)($json['permiso_id'] ?? 0);

        $r = $this->_m->togglePermisoRol($rolId, $pid);
        if (empty($r['ok'])) $this->jfail($r['message'] ?? 'No se pudo cambiar', 400);

        $this->jok($r);
    }
}
