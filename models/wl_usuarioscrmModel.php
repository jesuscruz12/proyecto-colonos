<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class wl_usuarioscrmModel extends Model
{

    protected $table = 'wl_usuarioscrm';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    public function __construct()
    {
        parent::__construct();
    }

    // public function verificar_usuario($email, $password)
    // {
    //     $data = $this->whereRaw('email = ? and password = ? and estatus = 1', [$email, $password])->get()->toArray();
    //     return $data;
    // }

    public function verificar_usuario($email, $password)
    {
        $data = $this->select('wl_usuarioscrm.*', 'wlempresas.token_likeapi') // agrega los campos que necesitas
            ->join('wlempresas', 'wl_usuarioscrm.cv_wl', '=', 'wlempresas.cv_wl')
            ->where('wl_usuarioscrm.email', $email)
            ->where('wl_usuarioscrm.password', $password)
            ->where('wl_usuarioscrm.estatus', 1)
            ->first(); // suponiendo que solo debe haber un resultado

        return $data ? $data->toArray() : null;
    }
}
