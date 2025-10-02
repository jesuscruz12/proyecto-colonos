<?php

class wlportabilidadesController extends adminController
{
    /** @var wlportabilidadesModel */
    private $_wlportabilidades;

    /** @var usuariosModel (para CORE->head() y ensureCvWlInSession) */
    private $_usuarios;

    public function __construct()
    {
        parent::__construct();

        // 1) Solo autenticados
        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        // 2) Modelos
        $this->_wlportabilidades = $this->loadModel('wlportabilidades');
        $this->_usuarios         = $this->loadModel('usuarios');

        // 3) Asegura cv_wl en sesión
        $this->ensureCvWlInSession();
    }

    /** =========================
     *  VISTA PRINCIPAL (HTML)
     *  ========================= */
    public function index()
    {
        // Layout/head como en Recargas
        $core = new CORE;
        $head = $core->head();
        eval($head);

        // Inyecta el JS de la vista: modules/admin/views/wlportabilidades/js/index.js
        $this->_view->setJs(['index']);

        // Renderiza la vista HTML: modules/admin/views/wlportabilidades/index.phtml
        $this->_view->renderizar(['index']);
    }

    /** ===========================================================
     *  LISTAR (JSON): SÓLO del cv_wl en sesión
     *  GET: admin/wlportabilidades/portabilidades_list
     *  =========================================================== */
    public function portabilidades_list()
    {
        header('Content-Type: application/json; charset=utf-8');

        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) {
            echo json_encode([]);
            return;
        }

        // Trae las columnas que usa la tabla/JS
        $data = $this->_wlportabilidades
            ->select(
                'cv_portabilidad',
                'fecha_solicitud',
                'numero_a_portar',
                'icc',
                'nombre_cliente',
                'correo_cliente',
                'estatus',
                'preportabilidad',
                'tipo_portabilidad'
            )
            ->where('cv_wl', '=', $cv_wl)
            ->orderBy('fecha_solicitud', 'desc')
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    /** ===========================================================
     *  CREAR (JSON): inserta solicitud ligada al cv_wl de sesión
     *  POST: admin/wlportabilidades/registrar_portabilidad
     *  Campos esperados:
     *   - numero_a_portar (10 dígitos exactos)
     *   - icc (min ~18 chars)
     *   - nip (4–6 dígitos)
     *   - nombre_cliente (string)
     *   - correo_cliente (string opcional)
     *   - preportabilidad (0/1 opcional, default 1)
     *   - tipo_portabilidad (1/2 opcional, default 1)
     *   - origen_porta (1=Web,2=CSV,3=API; default 1)
     *  =========================================================== */
    public function registrar_portabilidad()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf(); // habilítalo cuando corresponda

        $json  = new CORE;
        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) {
            $json->jsonError('error', 'Wallet no encontrado en sesión.');
            return;
        }

        // Validaciones básicas
        $numero = preg_replace('/\D+/', '', (string) $this->getPostParam('numero_a_portar'));
        if (!preg_match('/^\d{10}$/', $numero)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }

        $icc = trim((string) $this->getPostParam('icc'));
        if (strlen($icc) < 18) {
            $json->jsonError('error', 'ICCID parece inválido.');
            return;
        }

        $nip = preg_replace('/\D+/', '', (string) $this->getPostParam('nip'));
        if (strlen($nip) < 4 || strlen($nip) > 6) {
            $json->jsonError('error', 'NIP inválido (4–6 dígitos).');
            return;
        }

        $nombre = trim((string) $this->getPostParam('nombre_cliente'));
        if (strlen($nombre) < 3) {
            $json->jsonError('error', 'Nombre del cliente demasiado corto.');
            return;
        }

        // Defaults
        $pre   = (int) ($this->getPostParam('preportabilidad') ?: 1);
        $tipo  = (int) ($this->getPostParam('tipo_portabilidad') ?: 1);
        $orig  = (int) ($this->getPostParam('origen_porta') ?: 1);

        // Inserta
        $nuevo = $this->_wlportabilidades;
        $nuevo->numero_a_portar  = $numero;
        $nuevo->icc              = $icc;
        $nuevo->nip              = $nip;
        $nuevo->nombre_cliente   = $nombre;
        $nuevo->correo_cliente   = trim((string) $this->getPostParam('correo_cliente'));
        $nuevo->preportabilidad  = $pre;
        $nuevo->tipo_portabilidad = $tipo;
        $nuevo->origen_porta     = $orig;
        $nuevo->cv_wl            = $cv_wl;                 // SIEMPRE desde sesión
        $nuevo->estatus          = 1;                      // 1 = Nuevo
        $nuevo->fecha_solicitud  = date('Y-m-d H:i:s');
        $nuevo->save();

        $json->jsonError('info', 'Solicitud registrada correctamente.');
    }

    /** ===========================================================
     *  OBTENER 1 REGISTRO (JSON): para modal de detalle
     *  POST: admin/wlportabilidades/datos_show_portabilidad
     *  Param: clave (cv_portabilidad)
     *  =========================================================== */
    public function datos_show_portabilidad()
    {
        header('Content-Type: application/json; charset=utf-8');

        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $data = $this->_wlportabilidades
            ->select('*')
            ->where('cv_portabilidad', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->get()
            ->toArray();

        echo json_encode($data);
    }

    public function editar_portabilidad()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $json  = new CORE;
        $cv_wl = (int) Session::get('cv_wl');
        $clave = $this->getPostParam('cv_portabilidad') ?: $this->getPostParam('clave');

        if (!$cv_wl || !$clave) {
            $json->jsonError('error', 'Solicitud inválida.');
            return;
        }

        /** @var wlportabilidadesModel $m */
        $m = $this->_wlportabilidades
            ->where('cv_portabilidad', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->first();

        if (!$m) {
            $json->jsonError('error', 'Registro no encontrado o sin permisos.');
            return;
        }

        // REGLA: No se puede editar si estatus es 2 (Numlex) o 3 (Procesado)
        if (in_array((int)$m->estatus, [2, 3], true)) {
            $json->jsonError('error', 'No es posible editar por el estatus actual.');
            return;
        }

        // Validaciones
        $numero = preg_replace('/\D+/', '', (string) $this->getPostParam('numero_a_portar'));
        if (!preg_match('/^\d{10}$/', $numero)) {
            $json->jsonError('error', 'El número debe tener exactamente 10 dígitos.');
            return;
        }
        $icc = trim((string) $this->getPostParam('icc'));
        $nip = preg_replace('/\D+/', '', (string) $this->getPostParam('nip'));
        if (strlen($icc) < 10) {
            $json->jsonError('error', 'ICCID inválido.');
            return;
        }
        if (strlen($nip) < 4 || strlen($nip) > 6) {
            $json->jsonError('error', 'NIP inválido (4–6 dígitos).');
            return;
        }

        // Actualiza campos editables
        $m->numero_a_portar   = $numero;
        $m->icc               = $icc;
        $m->nip               = $nip;
        $m->nombre_cliente    = trim((string) $this->getPostParam('nombre_cliente'));
        $m->correo_cliente    = trim((string) $this->getPostParam('correo_cliente'));
        $m->preportabilidad   = (int) ($this->getPostParam('preportabilidad') ?: 0);
        $m->tipo_portabilidad = (int) ($this->getPostParam('tipo_portabilidad') ?: 1);
        $m->origen_porta      = (int) ($this->getPostParam('origen_porta') ?: 1);

        // Si estaba en estatus 6 -> al guardar pasa a 1 (Pendiente)
        if ((int)$m->estatus === 6) {
            $m->estatus = 1; // Pendiente
        }

        // Siempre refresca la fecha de solicitud
        $m->fecha_solicitud = date('Y-m-d H:i:s');

        $m->save();

        $json->jsonError('info', 'Solicitud actualizada correctamente.');
    }



    /** ===========================================================
     *  CANCELAR (JSON): sólo si estatus ∈ {1,2}
     *  POST: admin/wlportabilidades/cancelar_portabilidad
     *  Param: clave (cv_portabilidad)
     *  =========================================================== */
    public function cancelar_portabilidad()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $json  = new CORE;
        $clave = $this->getPostParam('clave');
        $cv_wl = (int) Session::get('cv_wl');

        $row = $this->_wlportabilidades
            ->where('cv_portabilidad', '=', $clave)
            ->where('cv_wl', '=', $cv_wl)
            ->first();

        if (!$row) {
            $json->jsonError('error', 'Registro no encontrado o sin permisos.');
            return;
        }

        $estatus = (int) $row->estatus;
        if (!in_array($estatus, [1, 2], true)) {
            $json->jsonError('error', 'Sólo se puede cancelar si está en Nuevo o Enviado.');
            return;
        }

        $row->estatus = 6; // Cancelado
        $row->fecha_cambio = date('Y-m-d');
        $row->save();

        $json->jsonError('info', 'Solicitud cancelada correctamente.');
    }

    /** ===========================================================
     *  SUBIR CSV (JSON): crea por lote con cv_wl de sesión
     *  POST: admin/wlportabilidades/subir_csv_portabilidades
     *  CSV con columnas:
     *   icc, nombre_cliente, correo_cliente, numero_a_portar, nip
     *  Defaults: estatus=1, preportabilidad=1, tipo_portabilidad=1, origen_porta=2 (CSV)
     *  =========================================================== */
    public function subir_csv_portabilidades()
    {
        header('Content-Type: application/json; charset=utf-8');
        // $this->ensureCsrf();

        $cv_wl = (int) Session::get('cv_wl');
        if (!$cv_wl) {
            echo json_encode(['ok' => false, 'msg' => 'Wallet no encontrado en sesión.']);
            return;
        }

        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            echo json_encode(['ok' => false, 'msg' => 'Archivo no recibido.']);
            return;
        }

        $tmp = $_FILES['csv']['tmp_name'];
        $fh  = fopen($tmp, 'r');
        if (!$fh) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo abrir el archivo.']);
            return;
        }

        // Quitar BOM UTF-8 si existe
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Lee primera línea como encabezados (intenta coma, si no, punto y coma)
        $headers = fgetcsv($fh, 0, ',', '"', "\\");
        if ($headers === false || count($headers) === 1) {
            // reintenta con ;
            rewind($fh);
            if ($bom === "\xEF\xBB\xBF") fseek($fh, 3);
            $headers = fgetcsv($fh, 0, ';', '"', "\\");
            $delimiter = ';';
        } else {
            $delimiter = ',';
        }

        if ($headers === false) {
            fclose($fh);
            echo json_encode(['ok' => false, 'msg' => 'Encabezados no encontrados.']);
            return;
        }

        // Normaliza encabezados
        $map = [];
        foreach ($headers as $i => $col) {
            $key = strtolower(trim((string)$col));
            $map[$key] = $i;
        }

        // Requeridos
        $required = ['icc', 'nombre_cliente', 'correo_cliente', 'numero_a_portar', 'nip'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $map)) {
                fclose($fh);
                echo json_encode(['ok' => false, 'msg' => "Falta columna requerida: {$k}"]);
                return;
            }
        }

        $ok = 0;
        $skip = 0;
        $err = 0;
        $line = 1;

        // LEE TODAS LAS FILAS -> NUEVA INSTANCIA POR CADA UNA
        while (($row = fgetcsv($fh, 0, $delimiter, '"', "\\")) !== false) {
            $line++;

            // Salta filas completamente vacías
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            // Extrae por nombre
            $numero = isset($row[$map['numero_a_portar']]) ? preg_replace('/\D+/', '', (string)$row[$map['numero_a_portar']]) : '';
            $icc    = isset($row[$map['icc']]) ? trim((string)$row[$map['icc']]) : '';
            $nip    = isset($row[$map['nip']]) ? preg_replace('/\D+/', '', (string)$row[$map['nip']]) : '';
            $nom    = isset($row[$map['nombre_cliente']]) ? trim((string)$row[$map['nombre_cliente']]) : '';
            $mail   = isset($row[$map['correo_cliente']]) ? trim((string)$row[$map['correo_cliente']]) : '';

            // Validaciones mínimas
            if (!preg_match('/^\d{10}$/', $numero) || strlen($icc) < 10 || strlen($nip) < 4 || strlen($nip) > 6 || strlen($nom) < 2) {
                $skip++;
                continue;
            }

            try {
                // **NUEVA instancia por CADA FILA** (clave del bug)
                $m = new wlportabilidadesModel();

                // Defaults (ajusta a tus reglas reales)
                $m->fecha_solicitud   = date('Y-m-d H:i:s');
                $m->numero_a_portar   = $numero;
                $m->icc               = $icc;
                $m->nip               = $nip;
                $m->nombre_cliente    = $nom;
                $m->correo_cliente    = $mail;
                $m->estatus           = 1; // 1 = Pendiente
                $m->preportabilidad   = 1; // por defecto
                $m->tipo_portabilidad = 1; // 1=Prepago
                $m->origen_porta      = 2; // 2=CSV
                $m->cv_wl             = $cv_wl;

                $m->save();
                $ok++;
            } catch (\Throwable $e) {
                $err++;
                // Opcional: loggear $e->getMessage()
            }
        }

        fclose($fh);

        echo json_encode([
            'ok'      => true,
            'msg'     => "Procesado: {$ok} OK, {$skip} saltos, {$err} errores.",
            'resumen' => ['ok' => $ok, 'skip' => $skip, 'err' => $err],
        ]);
    }


    /** (Opcional) Descargar plantilla CSV */
    public function plantilla_csv()
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_portabilidades.csv"');
        echo "icc,nombre_cliente,correo_cliente,numero_a_portar,nip\n";
        echo "8952021234567890123,Juan Pérez,juan@dominio.com,5512345678,1234\n";
    }

    /* =========================
       Helpers privados (igual)
       ========================= */

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
