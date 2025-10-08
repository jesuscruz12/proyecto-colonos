<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlactivalotuModel extends Model
{
    protected $table = 'wlactivalotu';
    public $timestamps = false;

    /* ===================== COBERTURA ===================== */
    public function tieneCoberturaPorCP(string $cp): bool
    {
        $cp = substr(preg_replace('/\D+/', '', $cp), 0, 5);
        return Capsule::table('coberturasmbb')
            ->where(function ($q) use ($cp) {
                $q->where('codigo_postal', $cp)->orWhere('codigo_postal', (int)$cp);
            })
            ->exists();
    }

    /* ====================== IMEI ========================= */
    public function verificarImei(string $imei): array
    {
        // Demo: par = soporta eSIM
        $esPar = ((int)substr($imei, -1) % 2 === 0);
        return [
            'imei' => $imei,
            'compatible_banda28' => true,
            'acepta_esim' => $esPar
        ];
    }

    /* ======================= SIMS ======================== */
    /** Lista ICCs disponibles por tipo (1=física, 2=eSIM) */
    public function obtenerIccsDisponibles(int $tipoSim = 1): array
    {
        return Capsule::table('wlsims')
            ->select('iccid as icc', 'lote as almacen', 'estatus_linea as status', 'tipo_sim')
            ->where('estatus_linea', 1)        // disponible
            ->where('tipo_sim', $tipoSim)      // 1=física, 2=eSIM
            ->orderBy('iccid', 'asc')
            ->limit(200)
            ->get()
            ->map(function ($r) {
                $r->status = ($r->status == 1 ? 'disponible' : 'ocupado');
                return (array)$r;
            })
            ->toArray();
    }

    /**
     * Devuelve información de QR para un ICC eSIM desde BD:
     * - Busca en wlsims por iccid = $icc y tipo_sim = 2
     * - Toma el campo codigo_qr
     * - Si es texto plano => genera URL de imagen QR
     * - Si ya es data:image... o http(s) => lo usa tal cual
     */
    public function generarPerfilEsim(string $icc): ?array
    {
        $row = Capsule::table('wlsims')
            ->select('codigo_qr')
            ->where('iccid', $icc)
            ->where('tipo_sim', 2)
            ->first();

        if (!$row || empty($row->codigo_qr)) {
            return null; // sin QR almacenado para ese ICC
        }

        $raw = trim((string)$row->codigo_qr);
        $isImage = preg_match('#^(data:image/|https?://)#i', $raw) === 1;

        $imgUrl = $isImage
            ? $raw
            : ('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($raw));

        return [
            'qr_id'        => 'qr_' . md5($icc . microtime(true)),
            'qr_img_url'   => $imgUrl,   // siempre listo para <img src="">
            'qr_text'      => $isImage ? null : $raw, // texto plano (si aplica)
            'expira_en_min'=> 15,        // informativo
            'icc'          => $icc
        ];
    }

    /* ====================== PLANES ======================= */
    public function obtenerPlanesActivos(): array
    {
        $rows = Capsule::table('wlplanes')
            ->select([
                'cv_plan',
                Capsule::raw("COALESCE(nombre_comercial,'') AS nombre"),
                Capsule::raw("COALESCE(NULLIF(precio_likephone_wl,''), CAST(precio AS CHAR)) AS precio_str"),
                'tipo_producto',
                'primar_secundaria',
                'imagen_web1 AS imagen',
            ])
            ->where('primar_secundaria', 1) // activación
            ->where(function ($q) {
                $q->whereNull('estatus_paquete')->orWhere('estatus_paquete', 1);
            })
            ->orderBy('cv_plan', 'asc')
            ->get()
            ->toArray();

        return array_map(function ($r) {
            $p = is_numeric($r->precio_str)
                ? (float)$r->precio_str
                : (float)preg_replace('/[^\d.]/', '', (string)$r->precio_str);
            return [
                'cv_plan'           => (int)$r->cv_plan,
                'nombre'            => (string)$r->nombre,
                'precio'            => $p,
                'tipo_producto'     => (int)$r->tipo_producto,
                'primar_secundaria' => (int)$r->primar_secundaria,
                'imagen'            => $r->imagen ? (string)$r->imagen : null,
            ];
        }, $rows);
    }

    /* =================== PREACTIVACIÓN =================== */
    public function preactivarLineaNueva(string $tipo_sim, string $icc, int $cv_plan): array
    {
        try {
            Capsule::connection()->beginTransaction();

            // Reservar SIM — ajusta estatus según tu catálogo
            $updated = Capsule::table('wlsims')
                ->where('iccid', $icc)
                ->update([
                    'estatus_linea'    => 7, // 7=reservado
                    'fecha_activacion' => date('Y-m-d H:i:s')
                ]);

            if (!$updated) {
                Capsule::connection()->rollBack();
                return ['ok' => false, 'mensaje' => 'La SIM no está disponible o no existe.'];
            }

            $msisdn = '55' . rand(80000000, 99999999);

            Capsule::connection()->commit();

            return [
                'ok' => true,
                'data' => [
                    'preactivada'   => true,
                    'msisdn'        => $msisdn,
                    'folio'         => 'PR-' . date('YmdHis'),
                    'instrucciones' => ($tipo_sim === 'fisica')
                        ? 'Inserta la SIM y reinicia tu equipo.'
                        : 'Escanea el QR mostrado para activar la eSIM.'
                ]
            ];
        } catch (\Throwable $e) {
            Capsule::connection()->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /* =================== PORTABILIDAD ==================== */
  public function registrarPortabilidad(
    string $tipo_sim,
    string $icc,
    int $cv_plan,
    string $numero,     // viene del POST como "numero"
    string $nip,
    string $nombre,
    ?string $correo
): array {
    try {
        Capsule::connection()->beginTransaction();

        // 1) Reservar la SIM
        $upd = Capsule::table('wlsims')
            ->where('iccid', $icc)
            ->update([
                'estatus_linea'    => 7, // reservado
                'fecha_activacion' => date('Y-m-d H:i:s')
            ]);

        if (!$upd) {
            Capsule::connection()->rollBack();
            return ['ok' => false, 'mensaje' => 'La SIM no está disponible o no existe.'];
        }

        // 2) Insertar en wlportabilidades con los NOMBRES REALES de columnas
        //    - numero_a_portar (no "numero")
        //    - estatus INT (1 = pendiente_validacion)
        //    - folio_portabilidad existe y es nullable -> lo llenamos después de obtener el id
        // 2) Insertar en wlportabilidades con los nombres reales de columnas (SIN cv_plan)
$id = Capsule::table('wlportabilidades')->insertGetId([
    'numero_a_portar'  => $numero,
    'nombre_cliente'   => $nombre,
    'correo_cliente'   => $correo,
    'icc'              => $icc,
    'nip'              => $nip,
    'fecha_solicitud'  => date('Y-m-d H:i:s'),
    'estatus'          => 1,  // 1 = pendiente_validacion
    'preportabilidad'  => 1,
    'origen_porta'     => 1,
    'tipo_portabilidad'=> 1,
    'cv_wl'            => (int) Session::get('cv_wl'),
]);


        // 3) Generar y guardar folio en la columna "folio_portabilidad" (opcional pero útil)
        $folio = 'PORT-' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
        Capsule::table('wlportabilidades')
            ->where('cv_portabilidad', $id)
            ->update(['folio_portabilidad' => $folio]);

        Capsule::connection()->commit();

        return [
            'ok' => true,
            'data' => [
                'folio'   => $folio,                 // lo devolvemos para UI
                'estatus' => 'pendiente_validacion', // para texto en la vista
                'mensaje' => 'Tu solicitud de portabilidad fue registrada.'
            ]
        ];
    } catch (\Throwable $e) {
        Capsule::connection()->rollBack();
        return ['ok' => false, 'mensaje' => $e->getMessage()];
    }
}


    /* ====================== LOG/CIERRE =================== */
    public function registrarCierreFlujo(array $payload): bool
    {
        try {
            Capsule::table('wlactivalotu_logs')->insert([
                'cv_wl'      => (int) Session::get('cv_wl'),
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
