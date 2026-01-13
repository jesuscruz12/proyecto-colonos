<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class catalogosModel extends Model
{
    protected $table = '';
    public $timestamps = false;

    /**
     * slug => [tabla, label]
     * OJO: aquí mete TODOS los catálogos reales (cat_* + geo si aplica).
     */
    protected array $catalogMap = [
        'cat_accion_peaton'           => ['tabla' => 'cat_accion_peaton',           'label' => 'Acción peatón'],
        'cat_accion_vehiculo'         => ['tabla' => 'cat_accion_vehiculo',         'label' => 'Acción vehículo'],
        'cat_agente_natural'          => ['tabla' => 'cat_agente_natural',          'label' => 'Agente natural'],
        'cat_alineamiento_horizontal' => ['tabla' => 'cat_alineamiento_horizontal', 'label' => 'Alineamiento horizontal'],
        'cat_alineamiento_vertical'   => ['tabla' => 'cat_alineamiento_vertical',   'label' => 'Alineamiento vertical'],
        'cat_aseguradoras'            => ['tabla' => 'cat_aseguradoras',            'label' => 'Aseguradoras'],
        'cat_carroceria'              => ['tabla' => 'cat_carroceria',              'label' => 'Carrocería'],
        'cat_causas_probables'        => ['tabla' => 'cat_causas_probables',        'label' => 'Causas probables'],
        'cat_circunst_conductor'      => ['tabla' => 'cat_circunst_conductor',      'label' => 'Circunstancias conductor'],
        'cat_circunst_vehiculo'       => ['tabla' => 'cat_circunst_vehiculo',       'label' => 'Circunstancias vehículo'],
        'cat_clasificacion_accidente' => ['tabla' => 'cat_clasificacion_accidente', 'label' => 'Clasificación accidente'],
        'cat_colision_sobre_camino'   => ['tabla' => 'cat_colision_sobre_camino',   'label' => 'Colisión sobre camino'],
        'cat_colores'                 => ['tabla' => 'cat_colores',                 'label' => 'Colores'],
        'cat_condicion_clima'         => ['tabla' => 'cat_condicion_clima',         'label' => 'Condición del clima'],
        'cat_control_transito'        => ['tabla' => 'cat_control_transito',        'label' => 'Control de tránsito'],
        'cat_disposicion'             => ['tabla' => 'cat_disposicion',             'label' => 'Disposición'],
        'cat_estados'                 => ['tabla' => 'cat_estados',                 'label' => 'Estados (cat)'],
        'cat_estatus_accidente'       => ['tabla' => 'cat_estatus_accidente',       'label' => 'Estatus del accidente'],
        'cat_estatus_cierre'          => ['tabla' => 'cat_estatus_cierre',          'label' => 'Estatus de cierre'],
        'cat_grado_alcohol'           => ['tabla' => 'cat_grado_alcohol',           'label' => 'Grado de alcohol'],
        'cat_iluminacion_sitio'       => ['tabla' => 'cat_iluminacion_sitio',       'label' => 'Iluminación del sitio'],
        'cat_infracciones'            => ['tabla' => 'cat_infracciones',            'label' => 'Infracciones'],
        'cat_licencia_tipo'           => ['tabla' => 'cat_licencia_tipo',           'label' => 'Tipo de licencia'],
        'cat_luz'                     => ['tabla' => 'cat_luz',                     'label' => 'Condición de luz'],
        'cat_marcas_vehiculo'         => ['tabla' => 'cat_marcas_vehiculo',         'label' => 'Marcas de vehículo'],
        'cat_roles'                   => ['tabla' => 'cat_roles',                   'label' => 'Roles'],
        'cat_senalamiento'            => ['tabla' => 'cat_senalamiento',            'label' => 'Señalamientos'],
        'cat_sentido_circulacion'     => ['tabla' => 'cat_sentido_circulacion',     'label' => 'Sentido de circulación'],
        'cat_severidad'               => ['tabla' => 'cat_severidad',               'label' => 'Severidad'],
        'cat_superficie_rodamiento'   => ['tabla' => 'cat_superficie_rodamiento',   'label' => 'Superficie de rodamiento'],
        'cat_tipo_camino'             => ['tabla' => 'cat_tipo_camino',             'label' => 'Tipo de camino'],
        'cat_tipo_carga'              => ['tabla' => 'cat_tipo_carga',              'label' => 'Tipo de carga'],
        'cat_tipo_ebriedad'           => ['tabla' => 'cat_tipo_ebriedad',           'label' => 'Tipo de ebriedad'],
        'cat_tipo_hecho'              => ['tabla' => 'cat_tipo_hecho',              'label' => 'Tipo de hecho'],
        'cat_tipo_servicio_vehiculo'  => ['tabla' => 'cat_tipo_servicio_vehiculo',  'label' => 'Tipo de servicio (vehículo)'],
        'cat_tipo_vehiculo'           => ['tabla' => 'cat_tipo_vehiculo',           'label' => 'Tipo de vehículo'],
        'cat_tipos_entidad'           => ['tabla' => 'cat_tipos_entidad',           'label' => 'Tipos de entidad'],
        'cat_via'                     => ['tabla' => 'cat_via',                     'label' => 'Vía'],

        // Geo (si quieres CRUD aquí también)
        'estados_mx'                  => ['tabla' => 'estados_mx',                  'label' => 'Estados MX'],
        'municipios_mx'               => ['tabla' => 'municipios_mx',               'label' => 'Municipios MX'],
    ];

    public function listarCatalogos(): array
    {
        $out = [];
        foreach ($this->catalogMap as $slug => $meta) {
            $out[] = ['slug' => $slug, 'tabla' => $meta['tabla'], 'label' => $meta['label']];
        }
        return $out;
    }

    public function getCatalogMeta(string $slug): ?array
    {
        return $this->catalogMap[$slug] ?? null;
    }

    /** Seguridad: solo operamos con tablas del mapa */
    protected function tablaPorSlug(string $slug): ?string
    {
        $meta = $this->getCatalogMeta($slug);
        return $meta ? $meta['tabla'] : null;
    }

    public function obtenerColumnasPorSlug(string $slug): array
    {
        $tabla = $this->tablaPorSlug($slug);
        if (!$tabla) return [];

        try {
            $cols = Capsule::select("SHOW COLUMNS FROM `$tabla`");
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($cols as $c) {
            $c = (array)$c;
            $out[] = [
                'Field' => $c['Field'] ?? '',
                'Type'  => $c['Type']  ?? '',
                'Key'   => $c['Key']   ?? '',
                'Extra' => $c['Extra'] ?? '',
            ];
        }
        return $out;
    }

    protected function colExists(array $colNames, string $col): bool
    {
        return in_array($col, $colNames, true);
    }

    /** Lista de registros (orden inteligente) */
    public function obtenerRegistrosPorSlug(string $slug): array
    {
        $tabla = $this->tablaPorSlug($slug);
        if (!$tabla) return [];

        $colsMeta = $this->obtenerColumnasPorSlug($slug);
        $colNames = array_column($colsMeta, 'Field');

        $order = 'id';
        foreach (['nombre', 'label', 'clave'] as $c) {
            if ($this->colExists($colNames, $c)) { $order = $c; break; }
        }

        try {
            $q = Capsule::table($tabla)->orderBy($order, 'asc');
            $rows = $q->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(fn($r) => (array)$r, $rows);
    }

    /** Create/Update */
    public function guardarRegistroPorSlug(string $slug, ?int $id, array $data): array
    {
        $tabla = $this->tablaPorSlug($slug);
        if (!$tabla) return ['error' => true, 'message' => 'Catálogo inválido.'];

        $colsMeta = $this->obtenerColumnasPorSlug($slug);
        if (!$colsMeta) return ['error' => true, 'message' => 'No fue posible leer la estructura del catálogo.'];

        $colNames = array_column($colsMeta, 'Field');

        // columnas que nunca se pisan desde UI
        $ignore = [
            'id',
            'created_at','updated_at',
            'creado_en','actualizado_en',
            'fecha_creado','fecha_actualizado',
            'creado_at','actualizado_at',
        ];

        $payload = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $colNames, true)) continue;
            if (in_array($k, $ignore, true)) continue;

            if (is_string($v)) $v = trim($v);
            if ($v === '') $v = null;

            // normaliza activo
            if ($k === 'activo' && $v !== null) {
                $v = (int)!!$v;
            }

            $payload[$k] = $v;
        }

        if (empty($payload) && (!$id || $id <= 0)) {
            return ['error' => true, 'message' => 'No hay campos válidos para guardar.'];
        }

        // timestamps automáticos si existen
        $nowDT = date('Y-m-d H:i:s');
        $nowD  = date('Y-m-d');

        $isUpdate = ($id && $id > 0);

        // actualizado_en / fecha_actualizado / updated_at
        if ($isUpdate) {
            if ($this->colExists($colNames, 'actualizado_en'))   $payload['actualizado_en']   = $nowDT;
            if ($this->colExists($colNames, 'fecha_actualizado')) $payload['fecha_actualizado'] = $nowDT;
            if ($this->colExists($colNames, 'updated_at'))       $payload['updated_at']       = $nowDT;
            if ($this->colExists($colNames, 'actualizado_at'))   $payload['actualizado_at']   = $nowDT;
        } else {
            if ($this->colExists($colNames, 'creado_en'))        $payload['creado_en']        = $nowDT;
            if ($this->colExists($colNames, 'fecha_creado'))     $payload['fecha_creado']     = $nowDT;
            if ($this->colExists($colNames, 'created_at'))       $payload['created_at']       = $nowDT;
            if ($this->colExists($colNames, 'creado_at'))        $payload['creado_at']        = $nowDT;

            // Algunos catálogos traen fecha (no datetime) — si existiera
            if ($this->colExists($colNames, 'fecha'))            $payload['fecha']            = $payload['fecha'] ?? $nowD;
        }

        try {
            if ($isUpdate) {
                Capsule::table($tabla)->where('id', $id)->update($payload);
                return ['ok' => true, 'mode' => 'update', 'id' => $id];
            }

            $newId = Capsule::table($tabla)->insertGetId($payload);
            return ['ok' => true, 'mode' => 'create', 'id' => $newId];

        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * “Eliminar” seguro:
     * - si existe columna activo => lo desactiva
     * - si NO existe => bloquea por defecto (para no romper integridad)
     */
    public function eliminarRegistroPorSlug(string $slug, int $id): array
    {
        $tabla = $this->tablaPorSlug($slug);
        if (!$tabla) return ['error' => true, 'message' => 'Catálogo inválido.'];

        $colsMeta = $this->obtenerColumnasPorSlug($slug);
        $colNames = array_column($colsMeta, 'Field');

        try {
            if ($this->colExists($colNames, 'activo')) {
                Capsule::table($tabla)->where('id', $id)->update(['activo' => 0]);
                return ['ok' => true, 'mode' => 'deactivate'];
            }

            return ['error' => true, 'message' => 'Este catálogo no permite eliminación física.'];

        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
