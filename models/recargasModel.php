<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class recargasModel extends Model
{

  protected $table = 'recargas';
  protected $primaryKey = 'cv_recarga';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }
}
