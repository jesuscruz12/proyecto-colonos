<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class usuariosModel extends Model
{

    protected $table = 'usuarios';
    protected $primaryKey = 'cv_usuario';
    public $timestamps = false;
    public function __construct()
    {
        parent::__construct();
    }

    public function verificar_usuario($email)
    {
        $data = $this->whereRaw('token_likeapi = ?', [$email])->get()->toArray();
        return $data;
    }
}
