<?php
/**
 * KPIs + series + puntos de mapa del Dashboard a partir de la tabla `accidentes`.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class indicadoresModel extends Model
{
    protected $table      = 'accidentes';
    protected $primaryKey = 'id';
    public    $timestamps = false;

    private ?string $dateCol = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Detecta la columna de fecha (preferimos `fecha` y luego `creado_en`)
     */
    private function resolveDateColumn(): string
    {
        if ($this->dateCol) return $this->dateCol;

        $candidatas = ['fecha', 'creado_en', 'created_at', 'fecha_hora', 'fecha_reporte'];

        try {
            $schema = Capsule::schema();
            foreach ($candidatas as $c) {
                if ($schema->hasColumn($this->table, $c)) {
                    return $this->dateCol = $c;
                }
            }
        } catch (\Throwable $e) {
            // en caso de error, fall-back
        }

        return $this->dateCol = 'fecha';
    }

    /**
     * KPIs generales + estatus + severidad + fuga.
     * Usa:
     *   - accidentes.estatus_id (1..7 en cat_estatus_accidente)
     *   - accidentes.severidad_id (1..3 en cat_severidad)
     */
   public function kpis(?string $desde = null, ?string $hasta = null): array
{
    $dateCol = $this->resolveDateColumn(); // en tu BD: fecha

    // Rango por defecto: mes actual
    if (!$desde || !$hasta) {
        $desde = date('Y-m-01 00:00:00');
        $hasta = date('Y-m-t 23:59:59');
    }

    // Base de consulta SIN joins y SIN columnas inexistentes
    $base = Capsule::table('accidentes')
        ->whereBetween($dateCol, [$desde, $hasta]);

    // TOTAL
    $total = (int) (clone $base)->count();

    // === ESTATUS POR estatus_id ===
    $fE = function (int $id) use ($base): int {
        return (int) (clone $base)->where('estatus_id', $id)->count();
    };

    $abiertos   = $fE(1); // Abierto
    $asignado   = $fE(2); // Asignado
    $enRuta     = $fE(3); // En ruta
    $enSitio    = $fE(4); // En sitio
    $enProceso  = $fE(5); // En proceso
    $cerrados   = $fE(6); // Cerrado
    $cancelados = $fE(7); // Cancelado

    // === SEVERIDAD POR severidad_id ===
    $fS = function (int $id) use ($base): int {
        return (int) (clone $base)->where('severidad_id', $id)->count();
    };

    $menor    = $fS(1); // MENOR
    $moderado = $fS(2); // MODERADO
    $grave    = $fS(3); // GRAVE

    // === NO hay fuga en esta BD ===
    $con_fuga = 0;

    // === HOY y últimos 7 días ===
    $hoyIni   = date('Y-m-d 00:00:00');
    $hoyFin   = date('Y-m-d 23:59:59');

    $hoy = (int) Capsule::table('accidentes')
        ->whereBetween($dateCol, [$hoyIni, $hoyFin])
        ->count();

    $hace7    = date('Y-m-d 00:00:00', strtotime('-6 days'));
    $ultimos7 = (int) Capsule::table('accidentes')
        ->whereBetween($dateCol, [$hace7, $hoyFin])
        ->count();

    return compact(
        'total',
        'abiertos', 'asignado', 'enRuta', 'enSitio', 'enProceso', 'cerrados', 'cancelados',
        'menor', 'moderado', 'grave',
        'con_fuga',
        'hoy', 'ultimos7', 'dateCol'
    );
}



    /**
     * Serie diaria con totales y severidades.
     * Retorna:
     * {
     *   labels:[], total:[], menor:[], moderado:[], grave:[],
     *   values:[], dateCol:""
     * }
     */
    public function serieDiaria(
        int $dias = 14,
        ?string $desdeDia = null,
        ?string $hastaDia = null
    ): array {
        $dateCol = $this->resolveDateColumn();

        // Definir rango
        if ($desdeDia && $hastaDia) {
            $desde = $desdeDia . ' 00:00:00';
            $hasta = $hastaDia . ' 23:59:59';
        } else {
            $dias  = max(1, min(60, (int) $dias));
            $desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));
            $hasta = date('Y-m-d 23:59:59');
        }

        // Agrupada por día + severidad (join catálogo)
        $rows = Capsule::table('accidentes as a')
            ->leftJoin('cat_severidad as s', 's.id', '=', 'a.severidad_id')
            ->select(
                Capsule::raw("DATE(a.$dateCol) as d"),
                Capsule::raw('COUNT(*) as total'),
                Capsule::raw("SUM(CASE WHEN s.clave = 'MENOR'    THEN 1 ELSE 0 END) as menor"),
                Capsule::raw("SUM(CASE WHEN s.clave = 'MODERADO' THEN 1 ELSE 0 END) as moderado"),
                Capsule::raw("SUM(CASE WHEN s.clave = 'GRAVE'    THEN 1 ELSE 0 END) as grave")
            )
            ->whereBetween("a.$dateCol", [$desde, $hasta])
            ->groupBy('d')
            ->orderBy('d', 'asc')
            ->get();

        // Eje continuo día x día
        $labels = [];
        $map    = [];

        $c = strtotime(substr($desde, 0, 10));
        $f = strtotime(substr($hasta, 0, 10));
        while ($c <= $f) {
            $d = date('Y-m-d', $c);
            $labels[] = $d;
            $map[$d]  = ['total' => 0, 'menor' => 0, 'moderado' => 0, 'grave' => 0];
            $c = strtotime('+1 day', $c);
        }

        foreach ($rows as $r) {
            $day = (string) $r->d;
            if (!isset($map[$day])) continue;

            $map[$day]['total']    = (int) $r->total;
            $map[$day]['menor']    = (int) $r->menor;
            $map[$day]['moderado'] = (int) $r->moderado;
            $map[$day]['grave']    = (int) $r->grave;
        }

        $total    = [];
        $menor    = [];
        $moderado = [];
        $grave    = [];

        foreach ($labels as $d) {
            $total[]    = $map[$d]['total'];
            $menor[]    = $map[$d]['menor'];
            $moderado[] = $map[$d]['moderado'];
            $grave[]    = $map[$d]['grave'];
        }

        return [
            'labels'   => $labels,
            'total'    => $total,
            'menor'    => $menor,
            'moderado' => $moderado,
            'grave'    => $grave,
            'values'   => $total,
            'dateCol'  => $dateCol,
        ];
    }

    /**
     * Puntos de mapa dentro del rango (uno por accidente).
     * Parámetros: días (YYYY-MM-DD).
     */
    public function geopoints(?string $desdeDia = null, ?string $hastaDia = null): array
    {
        $dateCol = $this->resolveDateColumn();
        $desde   = $desdeDia ? ($desdeDia . ' 00:00:00') : date('Y-m-01 00:00:00');
        $hasta   = $hastaDia ? ($hastaDia . ' 23:59:59') : date('Y-m-t 23:59:59');

        return Capsule::table('accidentes as a')
            ->leftJoin('cat_severidad as s', 's.id', '=', 'a.severidad_id')
            ->leftJoin('cat_estatus_accidente as e', 'e.id', '=', 'a.estatus_id')
            ->select(
                'a.id',
                'a.folio',
                'a.referencia',
                'a.lat',
                'a.lng',
                Capsule::raw("COALESCE(s.clave, '') as severidad"),
                Capsule::raw("COALESCE(e.clave, '') as estatus"),
                Capsule::raw("a.$dateCol as fecha_accidente")
            )
            ->whereBetween("a.$dateCol", [$desde, $hasta])
            ->whereNotNull('a.lat')
            ->whereNotNull('a.lng')
            ->orderBy("a.$dateCol", 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Resumen de mapa:
     *  - regions: conteo por región (aprox por estado)
     *  - markers: centroides con conteo
     *  - points: puntos reales (lat/lng)
     */
    public function mapa(?string $desdeDia = null, ?string $hastaDia = null): array
    {
        $puntos = $this->geopoints($desdeDia, $hastaDia);

        // Centroides simples por estado (aprox)
        $centroides = [
            'MX-AGU'=>[21.8818,-102.2950,'Aguascalientes'],'MX-BCN'=>[32.6519,-115.4683,'Baja California'],
            'MX-BCS'=>[24.1444,-110.3005,'Baja California Sur'],'MX-CAM'=>[19.8450,-90.5233,'Campeche'],
            'MX-CHP'=>[16.7520,-93.1167,'Chiapas'],'MX-CHH'=>[28.6320,-106.0691,'Chihuahua'],
            'MX-COA'=>[25.4267,-101.0000,'Coahuila'],'MX-COL'=>[19.2433,-103.7250,'Colima'],
            'MX-DUR'=>[24.0227,-104.6532,'Durango'],'MX-GUA'=>[21.0186,-101.2591,'Guanajuato'],
            'MX-GRO'=>[17.5515,-99.5020,'Guerrero'],'MX-HID'=>[20.1011,-98.7567,'Hidalgo'],
            'MX-JAL'=>[20.6736,-103.3440,'Jalisco'],'MX-MEX'=>[19.2890,-99.6532,'Edo. de México'],
            'MX-MIC'=>[19.7008,-101.1860,'Michoacán'],'MX-MOR'=>[18.9242,-99.2216,'Morelos'],
            'MX-NAY'=>[21.5058,-104.8957,'Nayarit'],'MX-NLE'=>[25.6866,-100.3161,'Nuevo León'],
            'MX-OAX'=>[17.0700,-96.7200,'Oaxaca'],'MX-PUE'=>[19.0433,-98.2019,'Puebla'],
            'MX-QUE'=>[20.5881,-100.3899,'Querétaro'],'MX-ROO'=>[18.5000,-88.3000,'Quintana Roo'],
            'MX-SLP'=>[22.1565,-100.9855,'San Luis Potosí'],'MX-SIN'=>[24.8091,-107.3940,'Sinaloa'],
            'MX-SON'=>[29.0729,-110.9559,'Sonora'],'MX-TAB'=>[17.9895,-92.9475,'Tabasco'],
            'MX-TAM'=>[23.7369,-99.1411,'Tamaulipas'],'MX-TLA'=>[19.3139,-98.2400,'Tlaxcala'],
            'MX-VER'=>[19.5426,-96.9133,'Veracruz'],'MX-YUC'=>[20.9674,-89.5926,'Yucatán'],
            'MX-ZAC'=>[22.7709,-102.5833,'Zacatecas'],'MX-CMX'=>[19.4326,-99.1332,'Ciudad de México'],
            'MX-DIF'=>[19.4326,-99.1332,'CDMX'] // alias
        ];

        $dist = function ($lat1, $lon1, $lat2, $lon2) {
            $p = 0.017453292519943295;
            $a = 0.5 - cos(($lat2 - $lat1) * $p) / 2
                + cos($lat1 * $p) * cos($lat2 * $p)
                * (1 - cos(($lon2 - $lon1) * $p)) / 2;
            return 12742 * asin(sqrt($a));
        };

        $counts = [];
        foreach ($centroides as $code => $_) {
            $counts[$code] = 0;
        }

        $points = [];

        foreach ($puntos as $r) {
            $lat = (float) $r->lat;
            $lng = (float) $r->lng;
            if (!$lat && !$lng) continue;

            $points[] = ['lat' => $lat, 'lng' => $lng];

            $bestCode = null;
            $bestDist = PHP_FLOAT_MAX;

            foreach ($centroides as $code => $info) {
                [$clat, $clng] = $info;
                $d = $dist($lat, $lng, $clat, $clng);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestCode = $code;
                }
            }

            if ($bestCode) {
                $counts[$bestCode]++;
            }
        }

        if ($counts['MX-DIF'] && !$counts['MX-CMX']) {
            $counts['MX-CMX'] = $counts['MX-DIF'];
        }

        $regions = array_filter($counts, fn($n) => $n > 0);
        $total   = count($points);

        $markers = [];
        foreach ($regions as $code => $n) {
            [$lat, $lng, $name] = $centroides[$code];
            $markers[] = [
                'code'  => $code,
                'name'  => $name,
                'lat'   => $lat,
                'lng'   => $lng,
                'count' => (int) $n,
            ];
        }

        return [
            'regions' => $regions,
            'markers' => $markers,
            'points'  => $points,
            'total'   => (int) $total,
        ];
    }
}
