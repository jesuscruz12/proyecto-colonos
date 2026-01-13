<?php
use Illuminate\Database\Capsule\Manager as DB;

class reporteaccidentesModel extends Model
{
    protected $table      = 'accidentes';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    public function __construct()
    {
        parent::__construct();
    }

    /* ================= Filtros comunes ================= */

    private function aplicarFiltros($q, array $f = [])
    {
        // Rango de fechas
        if (!empty($f['desde'])) {
            $q->where('a.fecha', '>=', $f['desde']);
        }
        if (!empty($f['hasta'])) {
            $q->where('a.fecha', '<=', $f['hasta']);
        }

        // Entidad / municipio
        if (!empty($f['entidad_id'])) {
            $q->where('a.entidad_id', (int)$f['entidad_id']);
        }

        if (!empty($f['municipio_id'])) {
            $q->where('a.municipio_id', (int)$f['municipio_id']);
        }

        // Severidad
        if (isset($f['severidad_id'])) {
            if (is_array($f['severidad_id'])) {
                $q->whereIn('a.severidad_id', $f['severidad_id']);
            } elseif ($f['severidad_id'] > 0) {
                $q->where('a.severidad_id', (int)$f['severidad_id']);
            }
        }

        // Estatus
        if (isset($f['estatus_id'])) {
            if (is_array($f['estatus_id'])) {
                $q->whereIn('a.estatus_id', $f['estatus_id']);
            } elseif ($f['estatus_id'] > 0) {
                $q->where('a.estatus_id', (int)$f['estatus_id']);
            }
        }

        // Oficial asignado
        if (!empty($f['oficial_id'])) {
            $q->where('a.oficial_asignado_id', (int)$f['oficial_id']);
        }

        return $q;
    }

    /* ==================== LISTADO DETALLE ==================== */

    /**
     * Listado detallado para DataTables / export.
     */
    
    public function listar(array $filtros = [], int $limit = 500, int $offset = 0): array
{
    $q = DB::table('accidentes as a')
        ->leftJoin('municipios_mx as m', 'm.id', '=', 'a.municipio_id')
        ->leftJoin('cat_severidad as sev', 'sev.id', '=', 'a.severidad_id')
        ->leftJoin('cat_estatus_accidente as est', 'est.id', '=', 'a.estatus_id')
        ->leftJoin('usuarios as u', 'u.id', '=', 'a.oficial_asignado_id')

        // INEGI
        ->leftJoin('cat_tipo_hecho as th', 'th.id', '=', 'a.tipo_hecho_id')
        ->leftJoin('cat_luz as luz', 'luz.id', '=', 'a.luz_id')
        ->leftJoin('cat_condicion_clima as cli', 'cli.id', '=', 'a.clima_id')
        ->leftJoin('cat_tipo_camino as tc', 'tc.id', '=', 'a.tipo_camino_id')
        ->leftJoin('cat_via as via', 'via.id', '=', 'a.via_id')

        ->select(
            'a.id',
            'a.fecha',
            'a.folio',
            'a.numero_parte',

            'm.nombre  as municipio',
            'sev.nombre as severidad',
            'est.nombre as estatus',
            'est.clave  as estatus_clave',

            // INEGI
            'th.nombre   as tipo_hecho',
            'luz.nombre  as condicion_luz',
            'cli.nombre  as clima',          // 👈 alias ya es "clima"
            'tc.nombre   as tipo_camino',
            'via.nombre  as tipo_via',

            'a.pavimentada',
            'a.direccion',
            'a.referencia',
            'a.superficie_rodamiento',
            'a.senalamiento',
            'a.iluminacion_descripcion',

            'a.lesionados_total',
            'a.fallecidos_total',
            'a.origen',

            'u.nombre as oficial'
        );

    $this->aplicarFiltros($q, $filtros);

    $q->orderBy('a.fecha', 'desc')
      ->orderBy('a.id', 'desc')
      ->offset($offset)
      ->limit($limit);

    $rows = $q->get();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'                       => (int)$r->id,
            'fecha'                    => (string)$r->fecha,
            'folio'                    => (string)($r->folio ?? ''),
            'numero_parte'             => (string)($r->numero_parte ?? ''),

            'municipio'                => (string)($r->municipio ?? ''),
            'severidad'                => (string)($r->severidad ?? ''),
            'estatus'                  => (string)($r->estatus ?? ''),
            'estatus_clave'            => (string)($r->estatus_clave ?? ''),

            'tipo_hecho'               => (string)($r->tipo_hecho ?? ''),
            'condicion_luz'            => (string)($r->condicion_luz ?? ''),
            'clima'                    => (string)($r->clima ?? ''),   // 👈 key "clima"
            'tipo_camino'              => (string)($r->tipo_camino ?? ''),
            'tipo_via'                 => (string)($r->tipo_via ?? ''),

            'pavimentada'              => (int)($r->pavimentada ?? 0),
            'direccion'                => (string)($r->direccion ?? ''),
            'referencia'               => (string)($r->referencia ?? ''),
            'superficie_rodamiento'    => (string)($r->superficie_rodamiento ?? ''),
            'senalamiento'             => (string)($r->senalamiento ?? ''),
            'iluminacion_descripcion'  => (string)($r->iluminacion_descripcion ?? ''),

            'lesionados_total'         => (int)($r->lesionados_total ?? 0),
            'fallecidos_total'         => (int)($r->fallecidos_total ?? 0),
            'oficial'                  => (string)($r->oficial ?? ''),
        ];
    }

    return $out;
}


    /* ==================== KPIs / CONTEOS ==================== */

    public function contar(array $filtros = []): int
    {
        $q = DB::table('accidentes as a');
        $this->aplicarFiltros($q, $filtros);
        return (int)$q->count();
    }

    public function conteoPorMunicipio(array $filtros = []): array
    {
        $q = DB::table('accidentes as a')
            ->leftJoin('municipios_mx as m', 'm.id', '=', 'a.municipio_id')
            ->selectRaw('m.nombre as municipio, COUNT(*) as total')
            ->groupBy('m.id', 'm.nombre');

        $this->aplicarFiltros($q, $filtros);

        $rows = $q->get();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'municipio' => (string)($r->municipio ?? 'N/D'),
                'total'     => (int)$r->total,
            ];
        }
        return $out;
    }

    public function conteoPorSeveridad(array $filtros = []): array
    {
        $q = DB::table('accidentes as a')
            ->leftJoin('cat_severidad as sev', 'sev.id', '=', 'a.severidad_id')
            ->selectRaw('sev.nombre as severidad, COUNT(*) as total')
            ->groupBy('sev.id', 'sev.nombre');

        $this->aplicarFiltros($q, $filtros);

        $rows = $q->get();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'severidad' => (string)($r->severidad ?? 'Sin dato'),
                'total'     => (int)$r->total,
            ];
        }
        return $out;
    }

    public function conteoPorEstatus(array $filtros = []): array
    {
        $q = DB::table('accidentes as a')
            ->leftJoin('cat_estatus_accidente as est', 'est.id', '=', 'a.estatus_id')
            ->selectRaw('est.nombre as estatus, est.clave as clave, COUNT(*) as total')
            ->groupBy('est.id', 'est.nombre', 'est.clave');

        $this->aplicarFiltros($q, $filtros);

        $rows = $q->get();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'estatus' => (string)($r->estatus ?? 'Sin dato'),
                'clave'   => (string)($r->clave ?? ''),
                'total'   => (int)$r->total,
            ];
        }
        return $out;
    }

    public function conteoPorOficial(array $filtros = []): array
    {
        $q = DB::table('accidentes as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.oficial_asignado_id')
            ->selectRaw('u.nombre as oficial, COUNT(*) as total')
            ->groupBy('u.id', 'u.nombre');

        $this->aplicarFiltros($q, $filtros);

        $rows = $q->get();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'oficial' => (string)($r->oficial ?? 'Sin asignar'),
                'total'   => (int)$r->total,
            ];
        }
        return $out;
    }
}
