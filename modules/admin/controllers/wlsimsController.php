<?php

class wlsimsController extends adminController
{
    private $_wlsims;
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        // Requiere sesión
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        // Modelos
        $this->_wlsims   = $this->loadModel('wlsims');
        $this->_usuarios = $this->loadModel('usuarios');

        // Asegura cv_wl en sesión
        $this->ensureCvWlInSession();
    }

    /** Render de la vista /admin/wlsims */
    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        // Carga JS (modules/admin/views/wlsims/js/index.js)
        $this->_view->setJs(['index']);

        // Render de la vista (modules/admin/views/wlsims/index.phtml)
        $this->_view->renderizar(['index']);
    }

    /** LISTAR: sólo registros del cv_wl en sesión */
    public function sims_list()
    {
        $cv_wl = Session::get('cv_wl');
        header('Content-Type: application/json; charset=utf-8');

        if (!$cv_wl) {
            echo json_encode([]);
            return;
        }

        // Ajusta columnas según tu tabla real
        $data = $this->_wlsims
            ->select(
                'cv_sim', 'cv_wl',
                'msisdn', 'iccid', 'producto',
                'fecha_activacion', 'lote',
                'li_nueva_o_porta', 'estatus_linea', 'tipo_sim',
                'codigo_qr'
            )
            ->where('cv_wl', '=', (int) $cv_wl)
            ->orderBy('fecha_activacion', 'desc')
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** CREAR */
    public function registrar_sim()
    {
        // $this->ensureCsrf();

        $json  = new CORE;
        $cv_wl = Session::get('cv_wl');

        if (!$cv_wl) {
            $json->jsonError('error', 'Wallet no encontrado en sesión.');
            return;
        }

        // Normaliza/valida MSISDN (exactamente 10 dígitos)
        $msisdn = preg_replace('/\D+/', '', (string) $this->getPostParam('msisdn'));
        if (!preg_match('/^\d{10}$/', $msisdn)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }

        // Parseo/normalización de campos
        $iccid            = trim((string) $this->getPostParam('iccid'));
        $producto         = trim((string) $this->getPostParam('producto'));
        $lote             = trim((string) $this->getPostParam('lote'));
        $li_nueva_o_porta = (int) ($this->getPostParam('li_nueva_o_porta') ?: 1); // 1=nueva, 2=porta
        $estatus_linea    = (int) ($this->getPostParam('estatus_linea') ?: 1);    // 1=inactivo, 2=activo
        $tipo_sim         = (int) ($this->getPostParam('tipo_sim') ?: 1);         // 1=física, 2=eSIM
        $codigo_qr        = trim((string) $this->getPostParam('codigo_qr')) ?: null;

        // Fecha de activación (regla para KPIs):
        // - Si viene fecha, se respeta.
        // - Si NO viene fecha:
        //    * Si estatus = Activo (2) -> usar "ahora"
        //    * Si estatus != Activo    -> dejar NULL (cuenta como "Disponible")
        $fechaForm = $this->getPostParam('fecha_activacion');
        if ($fechaForm) {
            $fecha_activacion = $fechaForm;
        } else {
            $fecha_activacion = ($estatus_linea === 2) ? date('Y-m-d H:i:s') : null;
        }

        // Inserción
        $nuevo = $this->_wlsims;
        $nuevo->cv_wl            = (int) $cv_wl;
        $nuevo->msisdn           = $msisdn;
        $nuevo->iccid            = $iccid;
        $nuevo->producto         = $producto;
        $nuevo->lote             = $lote;
        $nuevo->li_nueva_o_porta = $li_nueva_o_porta;
        $nuevo->estatus_linea    = $estatus_linea;
        $nuevo->tipo_sim         = $tipo_sim;
        $nuevo->codigo_qr        = $codigo_qr;
        $nuevo->fecha_activacion = $fecha_activacion;

        $nuevo->save();

        // Nota: tu helper usa jsonError('info', ...) para respuestas OK
        $json->jsonError('info', 'SIM creada exitosamente.');
    }

    /** OBTENER 1 REGISTRO para modal de edición */
    public function datos_show_sim()
    {
        $clave = $this->getPostParam('clave');
        $cv_wl = Session::get('cv_wl');

        header('Content-Type: application/json; charset=utf-8');

        $data = $this->_wlsims
            ->select('*')
            ->where('cv_sim', '=', $clave)
            ->where('cv_wl',  '=', (int) $cv_wl)
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** EDITAR */
    public function editar_sim()
    {
        // $this->ensureCsrf();

        $json  = new CORE;
        $clave = $this->getPostParam('clave');
        $cv_wl = Session::get('cv_wl');

        $editar = $this->_wlsims
            ->where('cv_sim', '=', $clave)
            ->where('cv_wl',  '=', (int) $cv_wl)
            ->first();

        if (!$editar) {
            $json->jsonError('error', 'Registro no encontrado o sin permisos.');
            return;
        }

        $msisdn = preg_replace('/\D+/', '', (string) $this->getPostParam('msisdn'));
        if (!preg_match('/^\d{10}$/', $msisdn)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }

        $editar->msisdn           = $msisdn;
        $editar->iccid            = trim((string) $this->getPostParam('iccid'));
        $editar->producto         = trim((string) $this->getPostParam('producto'));
        $editar->lote             = trim((string) $this->getPostParam('lote'));
        $editar->li_nueva_o_porta = (int) ($this->getPostParam('li_nueva_o_porta') ?: 1);
        $editar->estatus_linea    = (int) ($this->getPostParam('estatus_linea') ?: 1);
        $editar->tipo_sim         = (int) ($this->getPostParam('tipo_sim') ?: 1);
        $editar->codigo_qr        = trim((string) $this->getPostParam('codigo_qr')) ?: null;

        $f = $this->getPostParam('fecha_activacion');
        if ($f) {
            $editar->fecha_activacion = $f;
        }

        $editar->save();

        $json->jsonError('info', 'SIM actualizada correctamente.');
    }

    /** ELIMINAR */
    public function eliminar_sim()
    {
        // $this->ensureCsrf();

        $clave = $this->getPostParam('clave');
        $cv_wl = Session::get('cv_wl');

        $this->_wlsims
            ->where('cv_sim', '=', $clave)
            ->where('cv_wl',  '=', (int) $cv_wl)
            ->delete();
    }

    /** CSRF básico */
    private function ensureCsrf(): void
    {
        $t = $this->getPostParam('csrf_token');
        if (!$t || $t !== Session::get('tokencsrf')) {
            http_response_code(419);
            die('CSRF inválido');
        }
    }

    /** Asegura que exista cv_wl en sesión (desde Session::usuario o BD) */
    private function ensureCvWlInSession(): void
    {
        // Si ya existe, salir
        if (Session::get('cv_wl')) {
            return;
        }

        // 1) Intentar desde Session::usuario
        $usuario = Session::get('usuario') ?: [];
        if (isset($usuario['cv_wl']) && $usuario['cv_wl']) { // <-- corregido, sin paréntesis extra
            Session::set('cv_wl', (int) $usuario['cv_wl']);
            return;
        }

        // 2) Buscar en BD por id_usuario de sesión
        $id = Session::get('id_usuario');
        if ($id && $this->_usuarios) {
            $row = $this->_usuarios
                ->select('cv_wl')
                ->where('id_usuario', '=', (int) $id)
                ->first(); // objeto Eloquent o null

            if ($row) {
                $cv = null;

                if (is_object($row) && isset($row->cv_wl)) {
                    $cv = $row->cv_wl;
                } elseif (is_array($row) && isset($row['cv_wl'])) {
                    $cv = $row['cv_wl'];
                }

                if ($cv !== null) {
                    Session::set('cv_wl', (int) $cv);
                    return;
                }
            }
        }
    }
}
