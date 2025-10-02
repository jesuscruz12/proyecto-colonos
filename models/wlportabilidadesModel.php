<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlportabilidadesModel extends Model
{
  protected $table = 'wlportabilidades';
  protected $primaryKey = 'cv_portabilidad';
  public $timestamps = false;

  public function __construct()
  {
    parent::__construct();
  }
}
