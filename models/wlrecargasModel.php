<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlrecargasModel extends Model
{

  protected $table = 'wlrecargas';
  protected $primaryKey = 'cv_recarga';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }
}