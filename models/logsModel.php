<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class logsModel extends Model
{

  protected $table = 'logs';
  protected $primaryKey = 'cv_log';
  public $timestamps = false;
  public function __construct()
  {
    parent::__construct();
  }
}
