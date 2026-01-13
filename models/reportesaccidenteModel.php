<?php
use Illuminate\Database\Capsule\Manager as DB;

class reportesaccidenteModel extends Model
{
    protected $table      = 'accidentes';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Helper: arma URL de firma.
     * - Si viene "http..." la respeta.
     * - Si viene ruta relativa, la cuelga de LINK_API/uploads/
     */
    private function buildFirmaUrl(?string $raw): ?string
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '' || $raw === '0') return null;

        // ya es URL completa
        if (preg_match('#^https?://#i', $raw)) return $raw;

        // ruta relativa (ej: firmas/abc.png o uploads/firmas/abc.png)
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^uploads/#i', '', $raw);

        if (!defined('LINK_API')) return null;
        return rtrim(LINK_API, '/') . '/uploads/' . $raw;
    }

    /**
     * Helper: data base64 de firma.
     * Si viene con prefijo data:image... lo regresa tal cual.
     * Si viene puro base64, lo envuelve.
     */
    private function buildFirmaDataUri(?string $raw): ?string
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '' || $raw === '0') return null;

        if (stripos($raw, 'data:image') === 0) return $raw;

        // asumimos PNG si no dice tipo
        return 'data:image/png;base64,' . $raw;
    }

    /**
     * Devuelve TODO el expediente del accidente listo para la vista PDF.
     */
    public function getAccidenteExpediente(int $accidenteId): ?array
    {
        /* ==========================================================
           A) ACCIDENTE + CATÁLOGOS + OFICIAL
           ========================================================== */

        $row = DB::table('accidentes as a')
            ->leftJoin('municipios_mx as m', 'm.id', '=', 'a.municipio_id')
            ->leftJoin('entidades as ent', 'ent.id', '=', 'a.entidad_id')

            ->leftJoin('cat_severidad as sev', 'sev.id', '=', 'a.severidad_id')
            ->leftJoin('cat_estatus_accidente as est', 'est.id', '=', 'a.estatus_id')
            ->leftJoin('cat_tipo_hecho as th', 'th.id', '=', 'a.tipo_hecho_id')
            ->leftJoin('cat_condicion_clima as cli', 'cli.id', '=', 'a.clima_id')
            ->leftJoin('cat_sentido_circulacion as sc', 'sc.id', '=', 'a.sentido_circulacion_id')
            ->leftJoin('cat_via as via', 'via.id', '=', 'a.via_id')
            ->leftJoin('cat_tipo_camino as tc', 'tc.id', '=', 'a.tipo_camino_id')
            ->leftJoin('cat_superficie_rodamiento as sr', 'sr.id', '=', 'a.superficie_rodamiento_id')
            ->leftJoin('cat_senalamiento as sn', 'sn.id', '=', 'a.senalamiento_id')
            ->leftJoin('cat_iluminacion_sitio as ils', 'ils.id', '=', 'a.iluminacion_sitio_id')
            ->leftJoin('cat_luz as luz', 'luz.id', '=', 'a.luz_id')
            ->leftJoin('cat_control_transito as cctrl', 'cctrl.id', '=', 'a.control_transito_id')

            ->leftJoin('usuarios as u', 'u.id', '=', 'a.oficial_asignado_id')
            ->leftJoin('usuarios_roles as ur', function ($join) {
                $join->on('ur.usuario_id', '=', 'u.id')
                     ->whereNull('ur.vigente_hasta');
            })
            ->leftJoin('cat_roles as rol', 'rol.id', '=', 'ur.rol_id')
            ->leftJoin('entidades as ent_of', 'ent_of.id', '=', 'u.entidad_id')

            ->select(
                'a.*',
                'ent.nombre as entidad_nombre',
                'm.nombre   as municipio_nombre',

                'sev.nombre   as severidad_nombre',
                'est.nombre   as estatus_nombre',
                'est.clave    as estatus_clave',
                'th.nombre    as tipo_hecho_nombre',
                'cli.nombre   as clima_nombre',
                'sc.nombre    as sentido_circulacion_nombre',
                'via.nombre   as tipo_via_nombre',
                'tc.nombre    as tipo_camino_nombre',
                'sr.nombre    as superficie_rodamiento_cat',
                'sn.nombre    as senalamiento_cat',
                'ils.nombre   as iluminacion_sitio_nombre',
                'luz.nombre   as condicion_luz_nombre',
                'cctrl.nombre as control_transito_nombre',

                'u.id           as oficial_id',
                'u.nombre       as oficial_nombre',
                'u.num_empleado as oficial_num_empleado',
                'u.telefono     as oficial_telefono',
                'u.email        as oficial_email',
                'ent_of.nombre  as oficial_entidad_nombre',
                'rol.nombre     as oficial_rol_nombre'
            )
            ->where('a.id', $accidenteId)
            ->first();

        if (!$row) return null;

        $accidente = [
            'id'            => (int)$row->id,
            'entidad_id'    => (int)($row->entidad_id ?? 0),
            'municipio_id'  => (int)($row->municipio_id ?? 0),

            'folio'         => (string)($row->folio ?? ''),
            'numero_parte'  => (string)($row->numero_parte ?? ''),
            'origen'        => (string)($row->origen ?? ''),

            'fecha'         => (string)($row->fecha ?? ''),
            'dia_semana'    => (int)($row->dia_semana ?? 0),

            'hora_reporte'   => (string)($row->hora_reporte ?? ''),
            'hora_asignacion'=> (string)($row->hora_asignacion ?? ''),
            'hora_arribo'    => (string)($row->hora_arribo ?? ''),
            'hora_termino'   => (string)($row->hora_termino ?? ''),

            'direccion'     => (string)($row->direccion ?? ''),
            'colonia'       => (string)($row->colonia ?? ''),
            'referencia'    => (string)($row->referencia ?? ''),
            'entre_calle_a' => (string)($row->entre_calle_a ?? ''),
            'entre_calle_b' => (string)($row->entre_calle_b ?? ''),

            'lat' => ($row->lat !== null ? (float)$row->lat : null),
            'lng' => ($row->lng !== null ? (float)$row->lng : null),

            'entidad_nombre'   => (string)($row->entidad_nombre ?? ''),
            'municipio_nombre' => (string)($row->municipio_nombre ?? ''),

            'severidad_nombre' => (string)($row->severidad_nombre ?? ''),
            'estatus_nombre'   => (string)($row->estatus_nombre ?? ''),
            'estatus_clave'    => (string)($row->estatus_clave ?? ''),

            'tipo_hecho_nombre' => (string)($row->tipo_hecho_nombre ?? ''),
            'clima_nombre'      => (string)($row->clima_nombre ?? ''),
            'sentido_circulacion_nombre' => (string)($row->sentido_circulacion_nombre ?? ''),

            'tipo_via_nombre'    => (string)($row->tipo_via_nombre ?? ''),
            'tipo_camino_nombre' => (string)($row->tipo_camino_nombre ?? ''),
            'superficie_rodamiento_cat' => (string)($row->superficie_rodamiento_cat ?? ''),
            'senalamiento_cat'   => (string)($row->senalamiento_cat ?? ''),
            'iluminacion_sitio_nombre' => (string)($row->iluminacion_sitio_nombre ?? ''),
            'condicion_luz_nombre' => (string)($row->condicion_luz_nombre ?? ''),
            'control_transito_nombre'=> (string)($row->control_transito_nombre ?? ''),

            // campos “texto libre” que sí existen en tu vista
            'superficie_rodamiento'   => (string)($row->superficie_rodamiento ?? ''),
            'senalamiento'            => (string)($row->senalamiento ?? ''),
            'iluminacion_descripcion' => (string)($row->iluminacion_descripcion ?? ''),

            'danos_descripcion'       => (string)($row->danos_descripcion ?? ''),
            'causas_probables'        => (string)($row->causas_probables ?? ''),
            'causa_probable'          => (string)($row->causa_probable ?? ''),
            'observaciones'           => (string)($row->observaciones ?? ''),
            'observaciones_croquis'   => (string)($row->observaciones_croquis ?? ''),

            'estado_evento'           => (string)($row->estado_evento ?? ''),

            'lesionados_total' => (int)($row->lesionados_total ?? 0),
            'fallecidos_total' => (int)($row->fallecidos_total ?? 0),
        ];

        $oficial = [
            'id'              => ($row->oficial_id !== null ? (int)$row->oficial_id : null),
            'nombre_completo' => (string)($row->oficial_nombre ?? ''),
            'numero'          => (string)($row->oficial_num_empleado ?? ''),
            'telefono'        => (string)($row->oficial_telefono ?? ''),
            'email'           => (string)($row->oficial_email ?? ''),
            'entidad_nombre'  => (string)($row->oficial_entidad_nombre ?? ''),
            'rol_nombre'      => (string)($row->oficial_rol_nombre ?? ''),
        ];

        /* ==========================================================
           B) VEHÍCULOS (con catálogos reales de tu BD)
           - color: cat_colores
           - disposición: cat_disposicion
           ========================================================== */

        $vehiculosRows = DB::table('vehiculos as v')
            ->leftJoin('cat_tipo_vehiculo as tv', 'tv.id', '=', 'v.tipo_vehiculo_id')
            ->leftJoin('cat_marcas_vehiculo as mv', 'mv.id', '=', 'v.marca_id')
            ->leftJoin('cat_colores as cv', 'cv.id', '=', 'v.color_id')
            ->leftJoin('cat_disposicion as disp', 'disp.id', '=', 'v.a_disposicion')
            ->select(
                'v.*',
                'tv.nombre   as tipo_vehiculo_nombre',
                'mv.nombre   as marca_nombre',
                'cv.nombre   as color_nombre',
                'disp.nombre as disposicion_nombre'
            )
            ->where('v.accidente_id', $accidenteId)
            ->orderBy('v.indice', 'asc')
            ->get();

        $vehiculos = [];
        foreach ($vehiculosRows as $v) {
            $aseguradoTxt = ($v->asegurado == 1 ? 'Sí' : ($v->asegurado == 0 ? 'No' : ''));

            $vehiculos[] = [
                'id'     => (int)$v->id,
                'indice' => (int)($v->indice ?? 0),

                'propietario_nombre'    => (string)($v->propietario_nombre ?? ''),
                'propietario_domicilio' => (string)($v->propietario_domicilio ?? ''),
                'propietario_telefono'  => (string)($v->propietario_telefono ?? ''),

                'tipo_vehiculo_id'     => ($v->tipo_vehiculo_id !== null ? (int)$v->tipo_vehiculo_id : null),
                'tipo_vehiculo_nombre' => (string)($v->tipo_vehiculo_nombre ?? ''),

                'placas'        => (string)($v->placas ?? ''),
                'estado_placas' => (string)($v->estado_placas ?? ''),
                'serie'         => (string)($v->serie ?? ''),

                'marca_id'     => ($v->marca_id !== null ? (int)$v->marca_id : null),
                'marca_nombre' => (string)($v->marca_nombre ?? ''),

                'modelo'      => (string)($v->modelo ?? ''),
                'modelo_anio' => (string)($v->modelo_anio ?? ''),

                'color_id'     => ($v->color_id !== null ? (int)$v->color_id : null),
                'color_nombre' => (string)($v->color_nombre ?? ''),

                'uso_grua'   => (string)($v->uso_de_grua ?? $v->uso_grua ?? ''),
                'tipo_carga' => (string)($v->tipo_de_carga ?? $v->tipo_carga ?? ''),

                'asegurado'   => $aseguradoTxt,
                'aseguradora' => (string)($v->aseguradora ?? ''),
                'poliza_numero'   => (string)($v->poliza ?? ''),         // en tu BD es "poliza"
                'poliza_vigente'  => (string)($v->poliza_vigente ?? ''), // existe en vehiculos

                // ✅ ya no sale "0", viene del catálogo
                'a_disposicion' => (string)($v->disposicion_nombre ?? ''),

                'particular_publico' => (string)($v->particular_publico ?? ''),
            ];
        }

        /* ==========================================================
           C) CONDUCTORES (incluye firma)
           ========================================================== */

        $conductoresRows = DB::table('conductores as c')
            ->where('c.accidente_id', $accidenteId)
            ->orderBy('c.id', 'asc')
            ->get();

        $conductores = [];
        foreach ($conductoresRows as $c) {
            $conductores[] = [
                'id'           => (int)$c->id,
                'rol'          => 'Conductor',
                'vehiculo_id'  => ($c->vehiculo_id !== null ? (int)$c->vehiculo_id : null),

                'nombre'       => (string)($c->nombre ?? ''),
                'edad'         => (string)($c->edad ?? ''),
                'sexo'         => (string)($c->sexo ?? ''),
                'telefono'     => (string)($c->telefono ?? ''),
                'domicilio'    => (string)($c->domicilio ?? ''),

                'no_infraccion'   => (string)($c->no_infraccion ?? ''),

                'licencia_si'      => (isset($c->licencia_si) ? (bool)$c->licencia_si : null),
                'licencia_vigente' => (isset($c->licencia_vigente) ? (bool)$c->licencia_vigente : null),
                'licencia_numero'  => (string)($c->licencia_numero ?? ''),
                'licencia_tipo'    => (string)($c->licencia_tipo ?? ''),
                'licencia_estado'  => (string)($c->licencia_estado ?? ''),

                'lesionado'      => (string)($c->lesionado ?? $c->lesiones ?? ''),
                'atendido_por'   => (string)($c->atendido_por ?? ''),
                'trasladado_por' => (string)($c->trasladado_por ?? ''),
                'no_eco'         => (string)($c->no_eco ?? ''),
                'trasladado_a'   => (string)($c->trasladado_a ?? ''),

                'disposicion' => (string)($c->conductor_a_disposicion ?? $c->disposicion ?? ''),
                'custodia'    => (string)($c->custodia ?? ''),
                'manifestacion_hechos' => (string)($c->manifestacion_hechos ?? ''),

                // ✅ firmas nuevas
                'firma_url'  => $this->buildFirmaUrl($c->firma_conductor_url ?? null),
                'firma_data' => $this->buildFirmaDataUri($c->firma_conductor_data ?? null),
                'firma_ok'   => (isset($c->firma_conductor_ok) ? (int)$c->firma_conductor_ok : null),
            ];
        }

        /* ==========================================================
           D) OCUPANTES (mínimo para tu PDF)
           ========================================================== */

        $ocupantesRows = DB::table('ocupantes as o')
            ->where('o.accidente_id', $accidenteId)
            ->orderBy('o.id', 'asc')
            ->get();

        $ocupantes = [];
        foreach ($ocupantesRows as $o) {
            $ocupantes[] = [
                'id'          => (int)$o->id,
                'rol'         => 'Ocupante',
                'vehiculo_id' => ($o->vehiculo_id !== null ? (int)$o->vehiculo_id : null),
                'vehiculo_indice' => (int)($o->indice ?? 0),

                'nombre'      => (string)($o->nombre ?? ''),
                'edad'        => (string)($o->edad ?? ''),
                'sexo'        => (string)($o->sexo ?? ''),
                'telefono'    => (string)($o->telefono ?? ''),
                'domicilio'   => (string)($o->domicilio ?? ''),

                'lesionado'      => (string)($o->lesionado ?? $o->lesiones ?? ''),
                'atendido_por'   => (string)($o->atendido_por ?? ''),
                'trasladado_por' => (string)($o->trasladado_por ?? ''),
                'no_eco'         => (string)($o->no_eco ?? ''),
                'trasladado_a'   => (string)($o->trasladado_a ?? ''),
            ];
        }

        /* ==========================================================
           E) PEATONES (mínimo para tu PDF)
           ========================================================== */

        $peatonesRows = DB::table('peatones as p')
            ->where('p.accidente_id', $accidenteId)
            ->orderBy('p.id', 'asc')
            ->get();

        $peatones = [];
        foreach ($peatonesRows as $p) {
            $peatones[] = [
                'id'        => (int)$p->id,
                'rol'       => 'Peatón',

                'nombre'    => (string)($p->nombre ?? ''),
                'edad'      => (string)($p->edad ?? ''),
                'sexo'      => (string)($p->sexo ?? ''),
                'telefono'  => (string)($p->telefono ?? ''),
                'domicilio' => (string)($p->domicilio ?? ''),

                'lesionado'      => (string)($p->lesionado ?? $p->lesiones ?? ''),
                'iba_desde'      => (string)($p->iba_desde ?? ''),
                'hacia'          => (string)($p->hacia ?? ''),
                'atendido_por'   => (string)($p->atendido_por ?? ''),
                'trasladado_por' => (string)($p->trasladado_por ?? ''),
                'no_eco'         => (string)($p->no_eco ?? ''),
                'trasladado_a'   => (string)($p->trasladado_a ?? ''),
            ];
        }

        $personas = array_merge($conductores, $ocupantes, $peatones);

        /* ==========================================================
           F) FIRMAS “RESUMEN” (tabla accidentes_firmas)
           ========================================================== */

        $firmasRow = DB::table('accidentes_firmas')
            ->where('accidente_id', $accidenteId)
            ->first();

        $firmas = $firmasRow ? [
            'id' => (int)$firmasRow->id,
            'accidente_id' => (int)$firmasRow->accidente_id,

            'oficial_nombre' => (string)($firmasRow->oficial_nombre ?? ''),
            'oficial_num'    => (string)($firmasRow->oficial_num ?? ''),

            'conductor1_nombre'    => (string)($firmasRow->conductor1_nombre ?? ''),
            'conductor1_firma_ok'  => (isset($firmasRow->conductor1_firma_ok) ? (int)$firmasRow->conductor1_firma_ok : null),

            'conductor2_nombre'    => (string)($firmasRow->conductor2_nombre ?? ''),
            'conductor2_firma_ok'  => (isset($firmasRow->conductor2_firma_ok) ? (int)$firmasRow->conductor2_firma_ok : null),
        ] : null;

        /* ==========================================================
           G) CROQUIS + EVIDENCIAS
           ========================================================== */

        $croquisRow = DB::table('croquis as c')
            ->leftJoin('archivos as a', 'a.id', '=', 'c.archivo_id')
            ->select('c.*', 'a.path_relativo as archivo_path', 'a.nombre_original')
            ->where('c.accidente_id', $accidenteId)
            ->orderBy('c.id', 'asc')
            ->first();

        $croquis = $croquisRow ? [
            'id'              => (int)$croquisRow->id,
            'accidente_id'    => (int)$croquisRow->accidente_id,
            'archivo_id'      => (int)$croquisRow->archivo_id,
            'nota'            => (string)($croquisRow->nota ?? ''),
            'archivo_path'    => (string)($croquisRow->archivo_path ?? ''),
            'nombre_original' => (string)($croquisRow->nombre_original ?? ''),
        ] : null;

        $evidRows = DB::table('evidencias as e')
            ->leftJoin('archivos as a', 'a.id', '=', 'e.archivo_id')
            ->select('e.*', 'a.path_relativo as archivo_path', 'a.nombre_original')
            ->where('e.accidente_id', $accidenteId)
            ->orderBy('e.id', 'asc')
            ->get();

        $evidencias = [];
        foreach ($evidRows as $ev) {
            $evidencias[] = [
                'id'              => (int)$ev->id,
                'accidente_id'    => (int)$ev->accidente_id,
                'archivo_id'      => (int)$ev->archivo_id,
                'tipo'            => (string)($ev->tipo ?? ''),
                'etiqueta'        => (string)($ev->etiqueta ?? ''),
                'archivo_path'    => (string)($ev->archivo_path ?? ''),
                'nombre_original' => (string)($ev->nombre_original ?? ''),
            ];
        }

        $anexos = [];

        return [
            'accidente'   => $accidente,
            'oficial'     => $oficial,
            'vehiculos'   => $vehiculos,
            'personas'    => $personas,
            'conductores' => $conductores,
            'peatones'    => $peatones,
            'ocupantes'   => $ocupantes,
            'croquis'     => $croquis,
            'evidencias'  => $evidencias,
            'anexos'      => $anexos,
            'firmas'      => $firmas, // ✅ NUEVO
        ];
    }
}
