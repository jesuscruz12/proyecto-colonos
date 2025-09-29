<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlindicadoresModel extends Model
{
  protected $table = 'wlsims';
  protected $primaryKey = 'cv_sim';
  public $timestamps = false;

  public function __construct()
  {
    parent::__construct();
  }

  /** ===== Helpers ===== */
  /** Limpia '$', comas y espacios antes de castear a DECIMAL. */
  private function moneyExpr(string $col = 'saldo_consumido'): string
  {
    return "CAST(REPLACE(REPLACE(REPLACE($col, '$',''), ',', ''), ' ', '') AS DECIMAL(12,2))";
  }

  /** =========================
   *  KPIs principales
   *  ========================= */
  public function kpis(int $cv_wl, string $desde, string $hasta): array
  {
    // Activaciones (por fecha_activacion)
    $activaciones = Capsule::table('wlsims')
      ->where('cv_wl', $cv_wl)
      // ->where('estatus_venta', 2) // si aplica en tu data
      ->whereBetween('fecha_activacion', [$desde, $hasta])
      ->count();

    // Recargas pagadas (estatus_pago=1)
    $money = $this->moneyExpr('saldo_consumido');

    $recargasMonto = (float) Capsule::table('wlrecargas')
      ->where('cv_wl', $cv_wl)
      ->where('estatus_pago', 1)
      ->whereBetween('fecha_recarga', [$desde, $hasta])
      ->sum(Capsule::raw($money));

    $recargasCount = (int) Capsule::table('wlrecargas')
      ->where('cv_wl', $cv_wl)
      ->where('estatus_pago', 1)
      ->whereBetween('fecha_recarga', [$desde, $hasta])
      ->count();

    $ticketPromedio = $recargasCount > 0 ? ($recargasMonto / $recargasCount) : 0.0;

    return [
      'activaciones'   => (int) $activaciones,
      'recargasMonto'  => (float) $recargasMonto,
      'recargasCount'  => (int) $recargasCount,
      'ticketPromedio' => (float) $ticketPromedio,
    ];
  }

  /** =========================
   *  Activaciones por día
   *  ========================= */
  public function activacionesPorDia(int $cv_wl, string $desde, string $hasta): array
  {
    $rows = Capsule::table('wlsims')
      ->select(
        Capsule::raw('DATE(fecha_activacion) as dia'),
        Capsule::raw('SUM(CASE WHEN li_nueva_o_porta = 1 THEN 1 ELSE 0 END) AS nuevas'),
        Capsule::raw('SUM(CASE WHEN li_nueva_o_porta = 2 THEN 1 ELSE 0 END) AS portadas'),
        Capsule::raw('SUM(CASE WHEN li_nueva_o_porta IN (1,2) THEN 1 ELSE 0 END) AS total')
      )
      ->where('cv_wl', $cv_wl)
      // ->where('estatus_venta', 2) // si aplica en tu data
      ->whereBetween('fecha_activacion', [$desde, $hasta])
      ->groupBy('dia')
      ->orderBy('dia', 'asc')
      ->get();

    $labels=[]; $n=[]; $p=[]; $t=[];
    foreach ($rows as $r) {
      $labels[] = $r->dia;
      $n[] = (int)$r->nuevas;
      $p[] = (int)$r->portadas;
      $t[] = (int)$r->total;
    }
    return [$labels, [
      ['name'=>'Nuevas','data'=>$n],
      ['name'=>'Portadas','data'=>$p],
      ['name'=>'Total','data'=>$t],
    ]];
  }

  /** =========================
   *  Recargas por día (sólo pagadas)
   *  ========================= */
  public function recargasPorDia(int $cv_wl, string $desde, string $hasta): array
  {
    $money = $this->moneyExpr('saldo_consumido');

    $rows = Capsule::table('wlrecargas')
      ->select(
        Capsule::raw('DATE(fecha_recarga) as dia'),
        Capsule::raw('COUNT(*) as recargas'),
        Capsule::raw("SUM($money) as monto")
      )
      ->where('cv_wl', $cv_wl)
      ->where('estatus_pago', 1)
      ->whereBetween('fecha_recarga', [$desde, $hasta])
      ->groupBy('dia')
      ->orderBy('dia', 'asc')
      ->get();

    $labels=[]; $count=[]; $monto=[];
    foreach ($rows as $r) {
      $labels[] = $r->dia;
      $count[]  = (int)$r->recargas;
      $monto[]  = (float)$r->monto;
    }
    return [$labels, [
      ['name'=>'Recargas','data'=>$count],
      ['name'=>'Monto','data'=>$monto],
    ]];
  }

  /** =========================
   *  Top planes (sólo recargas pagadas)
   *  ========================= */
public function topPlanes(int $cv_wl, string $desde, string $hasta): array
{
  $money = $this->moneyExpr('r.saldo_consumido');

  // nombre_plan: primero nombre_comercial, luego offeringId, y si no, el cv_plan
  $nombrePlanExpr = "COALESCE(NULLIF(TRIM(p.nombre_comercial),''), NULLIF(TRIM(p.offeringId),''), CAST(r.cv_plan AS CHAR))";

  $rows = Capsule::table('wlrecargas as r')
    ->leftJoin('wlplanes as p', 'p.cv_plan', '=', 'r.cv_plan') // LEFT para no perder filas
    ->select(
      'r.cv_plan',
      Capsule::raw("$nombrePlanExpr AS nombre_plan"),
      Capsule::raw('COALESCE(p.tipo_producto, 0) AS tipo_producto'),
      Capsule::raw('COALESCE(p.primar_secundaria, 0) AS primar_secundaria'),
      Capsule::raw('COUNT(*) as recargas'),
      Capsule::raw('COUNT(DISTINCT r.numero_telefono) as numeros_unicos'),
      Capsule::raw("SUM($money) as monto")
    )
    ->where('r.cv_wl', $cv_wl)
    ->where('r.estatus_pago', 1)
    ->whereBetween('r.fecha_recarga', [$desde, $hasta])
    ->groupBy('r.cv_plan', 'nombre_plan', 'tipo_producto', 'primar_secundaria')
    ->orderBy('recargas', 'desc')
    ->limit(10)
    ->get();

  $items = [];
  foreach ($rows as $r) {
    $items[] = [
      'plan_id'        => (string)$r->cv_plan,
      'plan'           => (string)$r->nombre_plan,   // ← ya viene con el fallback
      'tipo_prod'      => (int)$r->tipo_producto,
      'prim_sec'       => (int)$r->primar_secundaria,
      'recargas'       => (int)$r->recargas,
      'numeros_unicos' => (int)$r->numeros_unicos,
      'monto'          => (float)$r->monto,
    ];
  }
  return ['by' => 'recargas', 'items' => $items];
}



  /** =========================
   *  CONSUMO POR ACTIVACIONES Y RECARGAS
   *  ========================= */
 /** Consumo total por origen: Recargas (pagadas, sin Conekta) vs Activaciones */

 public function consumoMix(int $cv_wl, string $desde, string $hasta): array
{
  // Recargas: pagadas y motor_pago != 'Conekta'
  $recargasMonto = (float) Capsule::table('wlrecargas')
    ->where('cv_wl', $cv_wl)
    ->where('estatus_pago', 1)
    ->where(function ($q) { $q->whereNull('motor_pago')->orWhere('motor_pago', '<>', 'Conekta'); })
    ->whereBetween('fecha_recarga', [$desde, $hasta])
    ->sum(Capsule::raw($this->moneyExpr('saldo_consumido')));

  $recargasCount = (int) Capsule::table('wlrecargas')
    ->where('cv_wl', $cv_wl)
    ->where('estatus_pago', 1)
    ->where(function ($q) { $q->whereNull('motor_pago')->orWhere('motor_pago', '<>', 'Conekta'); })
    ->whereBetween('fecha_recarga', [$desde, $hasta])
    ->count();

  // Activaciones (wlactivaciones)
  $actMonto = (float) Capsule::table('wlactivaciones')
    ->where('cv_wl', $cv_wl)
    ->whereBetween('fecha_consumo', [$desde, $hasta])
    ->sum(Capsule::raw($this->moneyExpr('saldo_consumido')));

  $actCount = (int) Capsule::table('wlactivaciones')
    ->where('cv_wl', $cv_wl)
    ->whereBetween('fecha_consumo', [$desde, $hasta])
    ->count();

  return [
    'labels' => ['Recargas', 'Activaciones'],
    'series' => [$recargasMonto, $actMonto],
    'counts' => ['recargas' => $recargasCount, 'activaciones' => $actCount],
  ];
}

}
