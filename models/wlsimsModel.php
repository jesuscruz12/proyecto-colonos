<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlsimsModel extends Model
{

  protected $table = 'wlsims';
  protected $primaryKey = 'cv_sim';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }
}