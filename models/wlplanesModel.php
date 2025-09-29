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
}