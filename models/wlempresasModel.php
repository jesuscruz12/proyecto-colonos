<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wlempresasModel extends Model
{

    protected $table = 'wlempresas';
    protected $primaryKey = 'cv_wl';
    public $timestamps = false;
    public function __construct()
    {
        parent::__construct();
    }

    public function verificar_usuario($token_likeapi)
    {
        $data = $this->whereRaw('token_likeapi = ?', [$token_likeapi])->get()->toArray();
        return $data;
    }
}
