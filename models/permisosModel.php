<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class permisosModel extends Model
{
    private function empresaId(): int
    {
        return (int)(Session::get('empresa_id') ?? 0);
    }

    // =========================================================
    // Roles
    // =========================================================
    public function listarRoles(): array
    {
        $eid = $this->empresaId();
        if ($eid <= 0) return [];

        return Capsule::table('roles')
            ->where('empresa_id', $eid)
            ->select('id', 'nombre', 'descripcion')
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(fn($r)=>(array)$r)
            ->toArray();
    }

    // =========================================================
    // Usuarios con rol (para UI)
    // =========================================================
    public function listarUsuariosConRol(): array
    {
        $eid = $this->empresaId();
        if ($eid <= 0) return [];

        return Capsule::table('usuarios as u')
            ->join('usuario_rol as ur', function($j) use ($eid){
                $j->on('ur.usuario_id','=','u.id')->where('ur.empresa_id','=',$eid);
            })
            ->join('roles as r', function($j) use ($eid){
                $j->on('r.id','=','ur.rol_id')->where('r.empresa_id','=',$eid);
            })
            ->where('u.empresa_id', $eid)
            ->whereNull('u.eliminado_en')
            ->select(
                'u.id',
                'u.nombre',
                'u.email',
                'u.activo',
                'r.id as rol_id',
                'r.nombre as rol_nombre'
            )
            ->orderBy('u.nombre', 'asc')
            ->get()
            ->map(fn($r)=>(array)$r)
            ->toArray();
    }

    // =========================================================
    // Permisos (catálogo)
    // =========================================================
    public function listarPermisos(string $q = ''): array
    {
        $q = trim($q);

        $qb = Capsule::table('permisos')
            ->select('id','nombre')
            ->orderBy('id','desc');

        if ($q !== '') $qb->where('nombre','like',"%{$q}%");

        return $qb->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function crearPermiso(string $nombre): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') return ['ok'=>false,'message'=>'Nombre requerido'];

        $exists = (int) Capsule::table('permisos')->where('nombre',$nombre)->count();
        if ($exists > 0) return ['ok'=>false,'message'=>'Ya existe'];

        $id = Capsule::table('permisos')->insertGetId([
            'nombre' => $nombre
        ]);

        return ['ok'=>true,'id'=>(int)$id];
    }

    public function actualizarPermiso(int $id, string $nombre): array
    {
        $id = (int)$id;
        $nombre = trim($nombre);
        if ($id<=0 || $nombre==='') return ['ok'=>false,'message'=>'Datos inválidos'];

        Capsule::table('permisos')
            ->where('id',$id)
            ->update(['nombre'=>$nombre]);

        return ['ok'=>true,'id'=>$id];
    }

    public function eliminarPermiso(int $id): array
    {
        $id = (int)$id;
        if ($id<=0) return ['ok'=>false,'message'=>'ID inválido'];

        Capsule::beginTransaction();
        try {
            Capsule::table('rol_permiso')->where('permiso_id',$id)->delete();
            Capsule::table('permisos')->where('id',$id)->delete();
            Capsule::commit();
            return ['ok'=>true,'id'=>$id];
        } catch (Throwable $e) {
            Capsule::rollBack();
            return ['ok'=>false,'message'=>'No se pudo eliminar'];
        }
    }

    // =========================================================
    // Matriz
    // =========================================================
    public function matrizPermisos(): array
    {
        $roles = $this->listarRoles();
        $perms = $this->listarPermisos('');

        $asig = [];
        $rows = Capsule::table('rol_permiso')->select('rol_id','permiso_id')->get();

        foreach ($rows as $r) {
            $rid = (int)$r->rol_id;
            $pid = (int)$r->permiso_id;
            if (!isset($asig[$rid])) $asig[$rid] = [];
            $asig[$rid][$pid] = 1;
        }

        return [
            'roles'     => $roles,
            'permisos'  => $perms,
            'asignados' => $asig,
        ];
    }

    public function setPermisosRol(int $rolId, array $permIds): array
    {
        $rolId = (int)$rolId;
        if ($rolId <= 0) return ['ok'=>false,'message'=>'rol_id inválido'];

        $ids = array_values(array_unique(array_filter(array_map('intval',$permIds), fn($x)=>$x>0)));

        Capsule::beginTransaction();
        try {
            Capsule::table('rol_permiso')->where('rol_id',$rolId)->delete();
            foreach ($ids as $pid) {
                Capsule::table('rol_permiso')->insert([
                    'rol_id'     => $rolId,
                    'permiso_id' => $pid,
                ]);
            }
            Capsule::commit();
            return ['ok'=>true,'count'=>count($ids)];
        } catch (Throwable $e) {
            Capsule::rollBack();
            return ['ok'=>false,'message'=>'No se pudo guardar'];
        }
    }

    public function togglePermisoRol(int $rolId, int $permisoId): array
    {
        $rolId = (int)$rolId;
        $permisoId = (int)$permisoId;
        if ($rolId<=0 || $permisoId<=0) return ['ok'=>false,'message'=>'Datos inválidos'];

        $ex = (int) Capsule::table('rol_permiso')
            ->where('rol_id',$rolId)
            ->where('permiso_id',$permisoId)
            ->count();

        if ($ex > 0) {
            Capsule::table('rol_permiso')
                ->where('rol_id',$rolId)
                ->where('permiso_id',$permisoId)
                ->delete();

            return ['ok'=>true,'assigned'=>0];
        }

        Capsule::table('rol_permiso')->insert([
            'rol_id'     => $rolId,
            'permiso_id' => $permisoId
        ]);

        return ['ok'=>true,'assigned'=>1];
    }
}
