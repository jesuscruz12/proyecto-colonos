<?php

class wlplanesController extends adminController
{
    /** @var wlplanesModel */
    private $_wlplanes;

    /** @var usuariosModel */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_wlplanes = $this->loadModel('wlplanes');
        $this->_usuarios = $this->loadModel('usuarios');

        $this->ensureCvWlInSession();
    }

    /** VISTA */
    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        $this->_view->setJs(['index']); // modules/admin/views/wlplanes/js/index.js
        $this->_view->renderizar(['index']); // modules/admin/views/wlplanes/index.phtml
    }

    /** LISTAR (JSON) */
    public function planes_list()
    {
        header('Content-Type: application/json; charset=utf-8');

        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) { echo json_encode([]); return; }

        $data = $this->_wlplanes
            ->select(
                'cv_plan',
                'offeringId',
                'tipo_producto',
                'precio',
                'nombre_comercial',
                'vigencia',
                'datos',
                'voz',
                'sms',
                'rrss',
                'ticket',
                'primar_secundaria',
                'estatus_paquete',
                'comparte_datos',
                'imagen_web1',
                'imagen_web2',
                'imagen_movil1',
                'imagen_movil2',
                'cv_wl',
                'precio_likephone_wl'
            )
            ->where('cv_wl', '=', $cv_wl)
            ->orderBy('precio', 'asc')
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** CREAR (JSON) */
    public function registrar_plan()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $json  = new CORE;
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) { $json->jsonError('error', 'Wallet no encontrado en sesión.'); return; }

        $nombre = trim((string) $this->getPostParam('nombre_comercial'));
        if ($nombre === '') { $json->jsonError('error', 'Nombre comercial es requerido.'); return; }

        $precio = (float) $this->getPostParam('precio');
        if ($precio < 0) { $json->jsonError('error', 'Precio inválido.'); return; }

        $nuevo = $this->_wlplanes;
        $nuevo->nombre_comercial    = $nombre;
        $nuevo->precio              = $precio;
        $nuevo->precio_likephone_wl = trim((string) $this->getPostParam('precio_likephone_wl'));
        $nuevo->tipo_producto       = (int) $this->getPostParam('tipo_producto');
        $nuevo->primar_secundaria   = (int) $this->getPostParam('primar_secundaria');
        $nuevo->vigencia            = trim((string) $this->getPostParam('vigencia'));
        $nuevo->offeringId          = trim((string) $this->getPostParam('offeringId'));
        $nuevo->datos               = trim((string) $this->getPostParam('datos'));
        $nuevo->voz                 = trim((string) $this->getPostParam('voz'));
        $nuevo->sms                 = trim((string) $this->getPostParam('sms'));
        $nuevo->rrss                = trim((string) $this->getPostParam('rrss'));
        $nuevo->ticket              = trim((string) $this->getPostParam('ticket'));
        $nuevo->comparte_datos      = (int) $this->getPostParam('comparte_datos');
        $nuevo->estatus_paquete     = (int) $this->getPostParam('estatus_paquete');
        $nuevo->imagen_web1         = trim((string) $this->getPostParam('imagen_web1'));
        $nuevo->imagen_web2         = trim((string) $this->getPostParam('imagen_web2'));
        $nuevo->imagen_movil1       = trim((string) $this->getPostParam('imagen_movil1'));
        $nuevo->imagen_movil2       = trim((string) $this->getPostParam('imagen_movil2'));
        $nuevo->cv_wl               = $cv_wl;

        $nuevo->save();

        // El CORE de tu proyecto usa esta firma {"alert":"info","mensaje":"..."}
        $json->jsonError('info', 'Plan creado exitosamente.');
    }

    /** OBTENER 1 REGISTRO (JSON) */
    public function datos_show_plan()
    {
        header('Content-Type: application/json; charset=utf-8');

        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $data = $this->_wlplanes
            ->select('*')
            ->where('cv_plan', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** EDITAR (JSON) */
    public function editar_plan()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $json  = new CORE;
        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $editar = $this->_wlplanes
            ->where('cv_plan', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->first();

        if (!$editar) { $json->jsonError('error', 'Registro no encontrado o sin permisos.'); return; }

        $nombre = trim((string) $this->getPostParam('nombre_comercial'));
        if ($nombre === '') { $json->jsonError('error', 'Nombre comercial es requerido.'); return; }

        $precio = (float) $this->getPostParam('precio');
        if ($precio < 0) { $json->jsonError('error', 'Precio inválido.'); return; }

        $editar->nombre_comercial    = $nombre;
        $editar->precio              = $precio;
        $editar->precio_likephone_wl = trim((string) $this->getPostParam('precio_likephone_wl'));
        $editar->tipo_producto       = (int) $this->getPostParam('tipo_producto');
        $editar->primar_secundaria   = (int) $this->getPostParam('primar_secundaria');
        $editar->vigencia            = trim((string) $this->getPostParam('vigencia'));
        $editar->offeringId          = trim((string) $this->getPostParam('offeringId'));
        $editar->datos               = trim((string) $this->getPostParam('datos'));
        $editar->voz                 = trim((string) $this->getPostParam('voz'));
        $editar->sms                 = trim((string) $this->getPostParam('sms'));
        $editar->rrss                = trim((string) $this->getPostParam('rrss'));
        $editar->ticket              = trim((string) $this->getPostParam('ticket'));
        $editar->comparte_datos      = (int) $this->getPostParam('comparte_datos');
        $editar->estatus_paquete     = (int) $this->getPostParam('estatus_paquete');
        $editar->imagen_web1         = trim((string) $this->getPostParam('imagen_web1'));
        $editar->imagen_web2         = trim((string) $this->getPostParam('imagen_web2'));
        $editar->imagen_movil1       = trim((string) $this->getPostParam('imagen_movil1'));
        $editar->imagen_movil2       = trim((string) $this->getPostParam('imagen_movil2'));

        $editar->save();

        $json->jsonError('info', 'Registro actualizado correctamente.');
    }

    /** ELIMINAR (JSON) */
    public function eliminar_plan()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $this->_wlplanes
            ->where('cv_plan', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->delete();

        echo json_encode(['ok' => true]);
    }

    /* ===== Helpers ===== */

    private function ensureCsrf(): void
    {
        $t = $this->getPostParam('csrf_token');
        if (!$t || $t !== Session::get('tokencsrf')) {
            http_response_code(419);
            die('CSRF inválido');
        }
    }

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
}
