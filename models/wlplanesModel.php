<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlplanesModel extends Model
{

  protected $table = 'wlplanes';
  protected $primaryKey = 'cv_plan';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }

  /* ====================== PLANES ======================= */
    public function obtenerPlanesActivos($filtro): array
    {

        $rows = Capsule::table('wlplanes')
            ->select([
                'cv_plan',
                Capsule::raw("COALESCE(nombre_comercial,'') AS nombre"),
                Capsule::raw("COALESCE(NULLIF(precio,''), CAST(precio AS CHAR)) AS precio_str"),
                'tipo_producto',
                'primar_secundaria',
                'imagen_web1 AS imagen',
            ])
            ->where('primar_secundaria', $filtro) // 1 activación, 2 recarga
            ->where(function ($q) {
                $q->whereNull('estatus_paquete')->orWhere('estatus_paquete', 2);
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
}