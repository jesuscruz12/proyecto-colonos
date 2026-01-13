<?php
use Illuminate\Database\Capsule\Manager as Capsule;

require_once ROOT . 'libs/Permisos.php';

class adminController extends Controller
{
    /** @var Permisos */
    protected $permisos;

    public function __construct()
    {
        parent::__construct();

        if (!Session::get('autenticado')) {
            $this->redireccionar('');
        }

        Session::tiempo();

        $uidSession = Session::get('id_usuario')
            ?: Session::get('usuario_id')
            ?: Session::get('id');

        $uid = (int)($uidSession ?? 0);
        if ($uid <= 0) {
            Session::destroy();
            $this->redireccionar('');
        }

        // Empresa (OBLIGATORIA para tu multi-empresa)
        $empresaId = (int)(Session::get('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            try {
                $empresaId = (int) Capsule::table('usuarios')->where('id', $uid)->value('empresa_id');
            } catch (Throwable $e) {
                $empresaId = 0;
            }
            if ($empresaId > 0) Session::set('empresa_id', $empresaId);
        }

        // Rol desde sesión
        $rolSession = Session::get('rol_id') ?: Session::get('rol');
        $rolId = (int)($rolSession ?? 0);

        // Fallback: usuario_rol (FILTRADO POR EMPRESA)
        if ($rolId <= 0) {
            try {
                $q = Capsule::table('usuario_rol')->where('usuario_id', $uid);
                if ($empresaId > 0) $q->where('empresa_id', $empresaId);
                $rolId = (int) $q->max('rol_id');
            } catch (Throwable $e) {
                $rolId = 0;
            }

            if ($rolId > 0) {
                Session::set('rol_id', $rolId);
                Session::set('rol', $rolId);
            }
        }

        if ($rolId <= 0) {
            Session::destroy();
            $this->redireccionar('');
        }
        /*
        // Permisos
        $this->permisos = new Permisos($uid, $rolId);
        $this->_view->permisos = $this->permisos;*/
    }

    public function index()
    {
        // Dashboard admin
    }
}
