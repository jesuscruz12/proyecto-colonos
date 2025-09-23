<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class administradoresModel extends Model
{

  protected $table = 'administradores';
  protected $primaryKey = 'cv_administrador';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }
}
