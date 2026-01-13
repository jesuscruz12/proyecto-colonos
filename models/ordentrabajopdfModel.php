<?php
/**
 * Orden de Trabajo PDF — TAKTIK
 * - SOLO lectura
 * - Dataset completo para impresión PDF
 * - Sin métodos save/update
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class ordentrabajopdfModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getData(int $empresaId, int $otId): array
    {
        // =========================
        // Orden de trabajo (header)
        // =========================
        $ot = Capsule::table('ordenes_trabajo as ot')
            ->leftJoin('clientes as c', function ($j) {
                $j->on('c.id', '=', 'ot.cliente_id')
                  ->on('c.empresa_id', '=', 'ot.empresa_id');
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'ot.creado_por')
            ->leftJoin('versiones_bom as vb', function($j){
                $j->on('vb.id','=','ot.version_bom_id')
                  ->on('vb.empresa_id','=','ot.empresa_id');
            })
            ->select(
                'ot.*',
                'c.nombre as cliente_nombre',
                'c.codigo as cliente_codigo',
                'c.email as cliente_email',
                'c.telefono as cliente_telefono',
                'c.direccion as cliente_direccion',
                'u.nombre as creado_por_nombre',
                'vb.version as bom_version',
                'vb.vigente as bom_vigente',
                'vb.entidad_tipo as bom_entidad_tipo',
                'vb.entidad_id as bom_entidad_id'
            )
            ->where('ot.empresa_id', $empresaId)
            ->where('ot.id', $otId)
            ->first();

        if (!$ot) return ['ok' => false, 'msg' => 'Orden de trabajo no encontrada'];

        $ot = (array)$ot;

        // =========================
        // Items / Detalle proyecto
        // =========================
        $items = Capsule::table('ordenes_trabajo_items as i')
            ->leftJoin('partes as pa', 'pa.id', '=', 'i.parte_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'i.producto_id')
            ->leftJoin('subensambles as su', 'su.id', '=', 'i.subensamble_id')
            ->leftJoin('versiones_ruta as vr', function($j){
                $j->on('vr.id','=','i.version_ruta_id');
            })
            ->select(
                'i.*',
                'pa.numero as parte_numero',
                'pa.descripcion as parte_desc',
                'pa.unidad as parte_unidad',
                'pr.codigo as prod_codigo',
                'pr.nombre as prod_nombre',
                'pr.descripcion as prod_desc',
                'su.nombre as sub_nombre',
                'vr.version as ruta_version',
                'vr.vigente as ruta_vigente'
            )
            ->where('i.orden_trabajo_id', $otId)
            ->orderBy('i.id', 'asc')
            ->get()
            ->map(function ($r) {
                $r = (array)$r;

                $r['codigo_item'] = 'N/A';
                $r['nombre_item'] = 'N/A';
                $r['unidad']      = '';

                if ($r['tipo_item'] === 'parte') {
                    $r['codigo_item'] = $r['parte_numero'] ?? 'N/A';
                    $r['nombre_item'] = $r['parte_desc'] ?? 'N/A';
                    $r['unidad']      = $r['parte_unidad'] ?? 'pza';
                } elseif ($r['tipo_item'] === 'producto') {
                    $r['codigo_item'] = $r['prod_codigo'] ?? 'N/A';
                    $r['nombre_item'] = $r['prod_nombre'] ?? 'N/A';
                    $r['unidad']      = 'pza';
                } else { // subensamble
                    $r['codigo_item'] = $r['sub_nombre'] ?? 'N/A';
                    $r['nombre_item'] = $r['sub_nombre'] ?? 'N/A';
                    $r['unidad']      = 'pza';
                }

                $tag = '';
                if (!empty($r['ruta_version'])) {
                    $tag = $r['ruta_version'] . ((int)($r['ruta_vigente'] ?? 0) === 1 ? ' (vigente)' : '');
                }
                $r['ruta_label'] = $tag;

                return $r;
            })
            ->toArray();

        // =========================
        // Procesos / Tareas
        // =========================
        $tareas = Capsule::table('tareas as t')
            ->join('procesos as p', 'p.id', '=', 't.proceso_id')
            ->select(
                't.secuencia',
                'p.nombre as proceso',
                't.cantidad',
                't.setup_minutos',
                't.segundos_por_unidad'
            )
            ->where('t.empresa_id', $empresaId)
            ->where('t.orden_trabajo_id', $otId)
            ->orderBy('t.secuencia', 'asc')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        return [
            'ok'     => true,
            'ot'     => $ot,
            'items'  => $items,
            'tareas' => $tareas,
        ];
    }
}
