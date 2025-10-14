<?php

class wlrecargasController extends adminController
{
    /** @var wlrecargasModel */
    private $_wlrecargas;
    private $_wlplanes;
    /** @var usuariosModel (usado por CORE->head() y helper ensureCvWlInSession) */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        // 1) Seguridad básica: sólo usuarios autenticados
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        // 2) Carga los modelos que necesitamos
        $this->_wlrecargas = $this->loadModel('wlrecargas');
        $this->_usuarios   = $this->loadModel('usuarios');
        $this->_wlplanes = $this->loadModel('wlplanes');

        // 3) Asegura que exista cv_wl en la sesión (es la “empresa/wallet” del usuario)
        $this->ensureCvWlInSession();
    }

    /** =========================
     *  VISTA PRINCIPAL (HTML)
     *  ========================= */
    public function index()
    {
        // Tu layout actual espera esto:
        $core = new CORE;
        $head = $core->head();
        eval($head);

        // Inyecta el JS de la vista: modules/admin/views/wlrecargas/js/index.js
        $this->_view->setJs(['index']);

        // Renderiza la vista HTML: modules/admin/views/wlrecargas/index.phtml
        $this->_view->renderizar(['index']);
    }

    /** ===========================================================
     *  LISTAR (JSON): devuelve recargas SÓLO del cv_wl en sesión
     *  GET: admin/wlrecargas/recargas_list
     *  =========================================================== */
    public function recargas_list()
    {
        header('Content-Type: application/json; charset=utf-8');

        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) {
            echo json_encode([]);
            return;
        }

        // Trae las columnas que usa tu tabla/JS
        $data = $this->_wlrecargas
            ->select(
                'cv_recarga',
                'numero_telefono',
                'cv_plan',
                'fecha_recarga',
                'saldo_consumido',
                'cv_sim',
                'iccid',
                'canal_venta'
            )
            ->where('cv_wl', '=', $cv_wl)
            ->orderBy('fecha_recarga', 'desc')
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** ===========================================================
     *  CREAR (JSON): inserta una recarga ligada al cv_wl de sesión
     *  POST: admin/wlrecargas/registrar_recarga
     *  Campos esperados:
     *   - numero_telefono (10 dígitos exactos)
     *   - cv_plan (int)
     *   - saldo_consumido (string/decimal)
     *   - canal_venta (NORMAL/BANCARIO/WEB...) -> se normaliza a MAYÚSCULAS
     *   - cv_sim (int, opcional)
     *   - iccid (string, opcional)
     *  =========================================================== */
    public function registrar_recarga()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf(); // ← Actívalo cuando tengas el token en el form

        $json  = new CORE;
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) {
            $json->jsonError('error', 'Wallet no encontrado en sesión.');
            return;
        }

        // --- Validación “idiota-proof”: número debe ser 10 dígitos exactos ---
        $numero = preg_replace('/\D+/', '', (string) $this->getPostParam('numero_telefono')); // sólo dígitos
        if (!preg_match('/^\d{10}$/', $numero)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }

        // Normalizaciones ligeras
        $canal = strtoupper(trim((string) ($this->getPostParam('canal_venta') ?: 'NORMAL')));
        $saldo = trim((string) $this->getPostParam('saldo_consumido'));

        $plan_seleccionado = $this->getPostParam('cv_plan');

        // Llamar al endpoint Recargas
         $r = new ApiLikePhone();
        $r->Login($cv_wl);

        $respuesta = $r->RecargaSIM($plan_seleccionado, $canal);

         if($respuesta['status'] === 200){
            $json->jsonError('info', 'Recarga creada exitosamente.');
            return;
        }else {
            return $json->jsonError('error','No se pudo realizar la recarga, favor de intentarlo más tarde.');
        }

    }

    /** ===========================================================
     *  OBTENER 1 REGISTRO (JSON): para llenar el modal de edición
     *  POST: admin/wlrecargas/datos_show_recarga
     *  Param: clave (cv_recarga)
     *  =========================================================== */
    public function datos_show_recarga()
    {
        header('Content-Type: application/json; charset=utf-8');

        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $data = $this->_wlrecargas
            ->select('*')
            ->where('cv_recarga', '=', $clave)
            ->where('cv_wl', '=', $cv_wl) // evita ver registros de otro wallet
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** ===========================================================
     *  EDITAR (JSON): actualiza si el registro pertenece a tu cv_wl
     *  POST: admin/wlrecargas/editar_recarga
     *  Param: clave (cv_recarga)
     *  =========================================================== */
    public function editar_recarga()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $json  = new CORE;
        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        // Busca el registro y verifica pertenencia
        $editar = $this->_wlrecargas
            ->where('cv_recarga', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->first();

        if (!$editar) {
            $json->jsonError('error', 'Registro no encontrado o sin permisos.');
            return;
        }

        // Validación de número a 10 dígitos exactos (igual que en crear)
        $numero = preg_replace('/\D+/', '', (string) $this->getPostParam('numero_telefono'));
        if (!preg_match('/^\d{10}$/', $numero)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }

        // Normalizaciones ligeras
        $canal = strtoupper(trim((string) ($this->getPostParam('canal_venta') ?: 'NORMAL')));
        $saldo = trim((string) $this->getPostParam('saldo_consumido'));

        // Actualiza campos
        $editar->numero_telefono = $numero;
        $editar->cv_plan         = (int) $this->getPostParam('cv_plan');
        $editar->saldo_consumido = $saldo;
        $editar->canal_venta     = $canal;
        $editar->cv_sim          = (int) $this->getPostParam('cv_sim');
        $editar->iccid           = trim((string) $this->getPostParam('iccid'));
        $editar->save();

        $json->jsonError('info', 'Registro actualizado correctamente.');
    }

    /** ===========================================================
     *  ELIMINAR (JSON): sólo si pertenece a tu cv_wl
     *  POST: admin/wlrecargas/eliminar_recarga
     *  Param: clave (cv_recarga)
     *  =========================================================== */
    public function eliminar_recarga()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $this->_wlrecargas
            ->where('cv_recarga', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->delete();

        // No hace falta retornar body; tu JS vuelve a cargar la tabla.
        echo json_encode(['ok' => true]);
    }

    /* =========================
       Helpers privados (opcional)
       ========================= */

    /**
     * CSRF básico: valida el token del formulario contra la sesión.
     * - Para usarlo, descomenta las llamadas a $this->ensureCsrf();
     *   y asegúrate de enviar <input type="hidden" name="csrf_token" value="...">
     */
    private function ensureCsrf(): void
    {
        $t = $this->getPostParam('csrf_token');
        if (!$t || $t !== Session::get('tokencsrf')) {
            http_response_code(419);
            die('CSRF inválido');
        }
    }

    /**
     * Intenta fijar cv_wl en sesión si aún no existe:
     * - Primero busca en Session::usuario['cv_wl']
     * - Si no, lo busca por id_usuario en la tabla usuarios
     */
    private function ensureCvWlInSession(): void
    {
        if (Session::get('cv_wl')) return;

        $usuario = Session::get('usuario') ?: [];
        if (!empty($usuario['cv_wl'])) {
            Session::set('cv_wl', (int) $usuario['cv_wl']);
            return;
        }

        $id = (int) Session::get('id_usuario');
        if ($id) {
            $row = $this->_usuarios
                ->select('cv_wl')
                ->where('id_usuario', '=', $id)
                ->first();

            if ($row && isset($row->cv_wl)) {
                Session::set('cv_wl', (int) $row->cv_wl);
            }
        }
    }

    public function listarPlanes()
    {
        $this->ensureAjaxPost();

        return $this->jsonOk(['planes'=>$this->_wlplanes->obtenerPlanesActivos(2)]); // 2 Recarga
    }

    /* ================== helpers ================== */
    private function ensureAjaxPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setJsonHeaders();
            http_response_code(405);
            echo json_encode(['ok'=>false,'error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE); exit;
        }
        $this->setJsonHeaders();
    }
    private function setJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    private function jsonOk(array $data = [], int $code = 200): void
    {
        $this->setJsonHeaders();
        http_response_code($code);
        echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE); exit;
    }
    private function jsonError(string $message, int $code = 400): void
    {
        $this->setJsonHeaders();
        http_response_code($code);
        echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE); exit;
    }
}
