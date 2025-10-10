<?php

class wlactivalotuController extends adminController
{
    /** @var wlactivalotuModel */
    private $_wlactivalotu;
    /** @var usuariosModel|null */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        $this->_wlactivalotu = $this->loadModel('wlactivalotu');
        $this->_usuarios     = $this->loadModel('usuarios');

        $this->ensureCvWlInSession();
    }

    public function index()
    {
        $core = new CORE;
        $head = $core->head();
        eval($head);

        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /* ===================== API ===================== */

    public function validarCobertura()
    {
        $this->ensureAjaxPost();
        $cp = trim((string)$this->getPostParam('cp'));
        $cp = substr(preg_replace('/\D+/', '', $cp), 0, 5);
        if (!preg_match('/^\d{5}$/', $cp)) {
            return $this->jsonError('Código postal inválido', 422);
        }

        if ($this->isMock() && $this->getCase() === 'sin_cobertura') {
            return $this->jsonOk(['cp'=>$cp,'cobertura'=>false]);
        } elseif ($this->isMock()) {
            return $this->jsonOk(['cp'=>$cp,'cobertura'=>true]);
        }

        $ok = $this->_wlactivalotu->tieneCoberturaPorCP($cp);
        return $this->jsonOk(['cp'=>$cp,'cobertura'=>$ok]);
    }

    public function validarImei()
    {
        $this->ensureAjaxPost();
        $imei = preg_replace('/\D+/', '', (string)$this->getPostParam('imei'));
        if (!preg_match('/^\d{14,16}$/', $imei)) {
            return $this->jsonError('IMEI inválido (14–16 dígitos).', 422);
        }

        if ($this->isMock() && $this->getCase()==='imei_incompatible') {
            return $this->jsonOk(['imei'=>$imei,'compatible_banda28'=>false,'acepta_esim'=>false]);
        } elseif ($this->isMock() && $this->getCase()==='sin_esim') {
            return $this->jsonOk(['imei'=>$imei,'compatible_banda28'=>true,'acepta_esim'=>false]);
        } elseif ($this->isMock()) {
            return $this->jsonOk(['imei'=>$imei,'compatible_banda28'=>true,'acepta_esim'=>true]);
        }

        return $this->jsonOk($this->_wlactivalotu->verificarImei($imei));
    }

    /** Lista ICCs: tipo_sim = 1 (física) | 2 (eSIM) */
    public function listarIccs()
    {
        $this->ensureAjaxPost();
        $tipoSim = (int) ($this->getPostParam('tipo_sim') ?: 1);
        if (!in_array($tipoSim,[1,2],true)) return $this->jsonError('tipo_sim inválido (1=física, 2=eSIM).',422);

        if ($this->isMock() && $this->getCase()==='sin_iccs') {
            return $this->jsonOk(['iccs'=>[]]);
        } elseif ($this->isMock()) {
            return $this->jsonOk(['iccs'=>[
                ['icc'=>'8952012345678901234','almacen'=>'CDMX','status'=>'disponible','tipo_sim'=>1],
                ['icc'=>'895202990000000001','almacen'=>'ESIM','status'=>'disponible','tipo_sim'=>2],
            ]]);
        }

        $list = $this->_wlactivalotu->obtenerIccsDisponibles($tipoSim);
        return $this->jsonOk(['iccs'=>$list]);
    }

    /** Genera QR para un ICC eSIM dado */
    public function generarEsim()
    {
        $this->ensureAjaxPost();
        $icc = trim((string)$this->getPostParam('icc'));
        $icc = preg_replace('/\D+/', '', $icc);
        if (!preg_match('/^\d{18,22}$/', $icc)) {
            return $this->jsonError('ICCID inválido (18–22 dígitos).', 422);
        }

        if ($this->isMock() && $this->getCase()==='error') {
            return $this->jsonError('No fue posible generar el QR eSIM.');
        } elseif ($this->isMock()) {
            return $this->jsonOk([
                'qr_id'        => 'qr_demo_123',
                'qr_img_url'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAA...', // demo
                'expira_en_min'=> 15,
                'icc'          => $icc
            ]);
        }

        $qr = $this->_wlactivalotu->generarPerfilEsim($icc);
        if (!$qr) return $this->jsonError('No fue posible generar eSIM.');
        return $this->jsonOk($qr);
    }

    public function listarPlanes()
    {
        $this->ensureAjaxPost();

        // if ($this->isMock()) {
        //     return $this->jsonOk(['planes'=>[
        //         ['cv_plan'=>101,'nombre'=>'Like Flex','precio'=>48.75,'tipo_producto'=>1,'primar_secundaria'=>1,'imagen'=>'/img/planes/flex.png'],
        //         ['cv_plan'=>102,'nombre'=>'Like Connect','precio'=>109.85,'tipo_producto'=>1,'primar_secundaria'=>1,'imagen'=>'/img/planes/connect.png'],
        //     ]]);
        // }

        return $this->jsonOk(['planes'=>$this->_wlactivalotu->obtenerPlanesActivos()]);
    }

    public function preactivarNueva()
    {
        $this->ensureAjaxPost();

        $tipo_sim = (string)$this->getPostParam('tipo_sim');
        $icc      = preg_replace('/\D+/', '', (string)$this->getPostParam('icc'));
        $cv_plan  = (int)$this->getPostParam('cv_plan');
        $paso_dn      = preg_replace('/\D+/', '', (string)$this->getPostParam('msisdn'));

        if (!in_array($tipo_sim, ['fisica','esim'], true)) return $this->jsonError('Tipo de SIM inválido',422);
        if (!preg_match('/^\d{18,22}$/', $icc)) return $this->jsonError('ICCID inválido (18–22 dígitos).',422);
        if ($cv_plan <= 0) return $this->jsonError('Plan inválido',422);

        // if ($this->isMock() && $this->getCase()==='error') {
        //     return $this->jsonError('No se pudo preactivar la línea.');
        // } elseif ($this->isMock()) {
        //     return $this->jsonOk([
        //         'preactivada'=>true,'msisdn'=>'5580012345','folio'=>'PR-'.date('YmdHis'),
        //         'instrucciones'=> ($tipo_sim==='fisica'?'Inserta la SIM y reinicia tu equipo.':'Escanea el QR eSIM.')
        //     ]);
        // }

        // TODO agrega el llamado a la funcion de ApiLikePhone->PreRegistroSIM($clave,$cv_plan, $msisdn)
        // $r = $this->_wlactivalotu->preactivarLineaNueva($tipo_sim, $icc, $cv_plan);
        // if (!$r['ok']) return $this->jsonError($r['mensaje'] ?? 'No se pudo preactivar.');

        $r = [
            'ok'=> true,
            'data'=>[],
        ];
        return $this->jsonOk($r['data']);
    }

    public function solicitarPortabilidad()
    {
        $this->ensureAjaxPost();

        $tipo_sim = (string)$this->getPostParam('tipo_sim');
        $icc      = preg_replace('/\D+/', '', (string)$this->getPostParam('icc'));
        $cv_plan  = (int)$this->getPostParam('cv_plan');
        $numero   = preg_replace('/\D+/', '', (string)$this->getPostParam('numero'));
        $nip      = preg_replace('/\D+/', '', (string)$this->getPostParam('nip'));
        $nombre   = trim((string)$this->getPostParam('nombre_cliente'));
        $correo   = trim((string)$this->getPostParam('correo_cliente'));

        if (!in_array($tipo_sim,['fisica','esim'],true)) return $this->jsonError('Tipo de SIM inválido',422);
        if (!preg_match('/^\d{18,22}$/', $icc)) return $this->jsonError('ICCID inválido (18–22 dígitos).',422);
        if ($cv_plan <= 0) return $this->jsonError('Plan inválido',422);
        if (!preg_match('/^\d{10}$/', $numero)) return $this->jsonError('El número debe tener exactamente 10 dígitos.',422);
        if (!preg_match('/^\d{4,6}$/', $nip)) return $this->jsonError('NIP inválido (4–6 dígitos).',422);
        if (strlen($nombre) < 3) return $this->jsonError('Nombre del cliente demasiado corto.',422);
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) return $this->jsonError('Correo inválido.',422);

        if ($this->isMock() && $this->getCase()==='error') {
            return $this->jsonError('No se pudo registrar la portabilidad.');
        } elseif ($this->isMock()) {
            return $this->jsonOk([
                'folio'=>'PORT-'.date('YmdHis'),
                'estatus'=>'pendiente_validacion',
                'mensaje'=>'Tu solicitud de portabilidad fue enviada. Recibirás confirmación por SMS.'
            ]);
        }

        $r = $this->_wlactivalotu->registrarPortabilidad($tipo_sim,$icc,$cv_plan,$numero,$nip,$nombre,$correo);
        if (!$r['ok']) return $this->jsonError($r['mensaje'] ?? 'No se pudo registrar la portabilidad.');
        return $this->jsonOk($r['data']);
    }

    public function confirmarActivacion()
    {
        $this->ensureAjaxPost();
        $payload = [
            'tipo_linea' => (string)$this->getPostParam('tipo_linea'),
            'tipo_sim'   => (string)$this->getPostParam('tipo_sim'),
            'icc'        => (string)$this->getPostParam('icc') ?: null,
            'cv_plan'    => (int)$this->getPostParam('cv_plan'),
            'cp'         => (string)$this->getPostParam('cp'),
            'imei'       => (string)$this->getPostParam('imei'),
            'meta'       => $this->getPostParam('meta') ?: [],
            'msisdn'     => (string)$this->getPostParam('msisdn')

        ];

        // if ($this->isMock()) {
        //     return $this->jsonOk(['resultado'=>'ok','mensaje'=>'Flujo confirmado y registrado (mock).','folio'=>'CF-'.date('YmdHis')]);
        // }

        $clave = (int) Session::get('cv_wl');

        // llamada a la API
        $pre_a = new ApiLikePhone();
        $pre_a->Login($clave);
        $respuesta = $pre_a->PreRegistroSIM($clave, $payload['cv_plan'], $payload['msisdn']);
        
        if($respuesta['status'] === 200){

            $m = new wlactivalotuModel();
            $m->preactivarLineaNueva($payload['tipo_sim'], $payload['icc'], $payload['cv_plan'],);
            // Logs
            $this->_wlactivalotu->registrarCierreFlujo($payload);
            return $this->jsonOk(['resultado'=>'ok','mensaje'=>'Flujo confirmado y registrado.','folio'=>'CF-'.date('YmdHis')]);

        }else {
            return $this->jsonError('No se pudo registrar la confirmación, favor de intentarlo más tarde.');
        }

        // $ok = $this->_wlactivalotu->registrarCierreFlujo($payload);
        // if (!$ok) return $this->jsonError('No se pudo registrar la confirmación.');
        // return $this->jsonOk(['resultado'=>'ok','mensaje'=>'Flujo confirmado y registrado.','folio'=>'CF-'.date('YmdHis')]);
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
    private function getQueryParam(string $k, $d=null){ return $_GET[$k]??$d; }
    private function isMock(): bool { return (int)($this->getQueryParam('mock') ?? 0) === 1; }
    private function getCase(): string { return (string)($this->getQueryParam('case') ?? ''); }

    private function ensureCvWlInSession(): void
    {
        if (Session::get('cv_wl')) return;
        $usuario = Session::get('usuario') ?: [];
        if (isset($usuario['cv_wl']) && $usuario['cv_wl']) {
            Session::set('cv_wl', (int)$usuario['cv_wl']); return;
        }
        $id = Session::get('id_usuario');
        if ($id && $this->_usuarios) {
            $row = $this->_usuarios->select('cv_wl')->where('id_usuario','=',(int)$id)->first();
            if ($row && isset($row->cv_wl)) Session::set('cv_wl', (int)$row->cv_wl);
        }
    }
}
