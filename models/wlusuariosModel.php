<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlusuariosModel extends Model
{
    protected $table = 'wl_usuarioscrm';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    public function __construct()
    {
        parent::__construct();
    }
}
