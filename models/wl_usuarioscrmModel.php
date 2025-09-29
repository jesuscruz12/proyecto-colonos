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

    /**
     * LEGACY (compatibilidad):
     * Verifica credenciales con hash legacy (sha1 + HASH_KEY) y regresa datos mínimos.
     */
    public function verificar_usuario(string $email, string $password): ?array
    {
        $row = $this->select(
                    'wl_usuarioscrm.id_usuario   as id_usuario',
                    'wl_usuarioscrm.nombre       as nombre',
                    'wl_usuarioscrm.email        as email',
                    'wl_usuarioscrm.rol          as rol',
                    'wl_usuarioscrm.cv_wl        as cv_wl',
                    'wlempresas.token_likeapi    as token_likeapi'
                )
                ->join('wlempresas', 'wl_usuarioscrm.cv_wl', '=', 'wlempresas.cv_wl')
                ->where('wl_usuarioscrm.email',    '=', $email)
                ->where('wl_usuarioscrm.password', '=', $password) // legacy sha1
                ->where('wl_usuarioscrm.estatus',  '=', 1)
                ->first();

        return $row ? $row->toArray() : null;
    }

    /**
     * Nuevo: Trae la fila completa por email (incluye password/estatus/ultimo_login).
     */
    public function buscarPorEmail(string $email): ?array
    {
        $row = $this->select(
                    'wl_usuarioscrm.id_usuario',
                    'wl_usuarioscrm.nombre',
                    'wl_usuarioscrm.email',
                    'wl_usuarioscrm.password',
                    'wl_usuarioscrm.rol',
                    'wl_usuarioscrm.estatus',
                    'wl_usuarioscrm.cv_wl',
                    'wl_usuarioscrm.ultimo_login',
                    'wlempresas.token_likeapi'
                )
                ->leftJoin('wlempresas', 'wl_usuarioscrm.cv_wl', '=', 'wlempresas.cv_wl')
                ->where('wl_usuarioscrm.email', '=', $email)
                ->first();

        return $row ? (method_exists($row, 'toArray') ? $row->toArray() : (array)$row) : null;
    }

    /**
     * Actualiza el campo ultimo_login.
     */
    public function actualizarUltimoLogin(int $id_usuario, string $fecha): bool
    {
        return (bool)$this->where('id_usuario', '=', $id_usuario)
            ->update(['ultimo_login' => $fecha]);
    }

    /**
     * Actualiza el password con un hash moderno (bcrypt/argon).
     */
    public function actualizarPassword(int $id_usuario, string $nuevoHash): bool
    {
        return (bool)$this->where('id_usuario', '=', $id_usuario)
            ->update(['password' => $nuevoHash]);
    }
}
