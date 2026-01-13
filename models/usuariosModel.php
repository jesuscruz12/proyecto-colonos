<?php
/**
 * usuariosModel.php — TAKTIK (MVC clásico)
 *
 * Tabla real: usuarios
 *  - id (bigint unsigned AI)
 *  - empresa_id (bigint unsigned)
 *  - nombre (varchar150)
 *  - email (varchar150)
 *  - password_hash (varchar255)
 *  - telefono (varchar50) NULL
 *  - puesto (varchar80) NULL
 *  - activo (tinyint1) default 1
 *  - ultimo_acceso_en (datetime) NULL
 *  - creado_en (timestamp) NULL
 *  - actualizado_en (timestamp) NULL
 *  - eliminado_en (timestamp) NULL  (soft delete)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class usuariosModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function eid(int $empresaId): int
    {
        $eid = (int)$empresaId;
        return $eid > 0 ? $eid : 0;
    }

    private function normEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function baseQuery(int $empresaId)
    {
        $empresaId = $this->eid($empresaId);

        return Capsule::table('usuarios as u')
            ->where('u.empresa_id', $empresaId)
            ->whereNull('u.eliminado_en');
    }

    // =========================================================
    // LOGIN helpers (NECESARIOS para indexController)
    // =========================================================

    /**
     * Buscar usuario por email (sin filtrar empresa aquí),
     * porque el login primero valida credenciales y luego amarra empresa/rol.
     */
    public function buscarPorEmail(string $email)
    {
        $email = $this->normEmail($email);
        if ($email === '') return null;

        return Capsule::table('usuarios as u')
            ->where('u.email', $email)
            ->whereNull('u.eliminado_en')
            ->first();
    }

    /**
     * Rol principal por usuario + empresa (tabla usuario_rol)
     */
    public function obtenerRolIdPorUsuario(int $usuarioId, int $empresaId): int
    {
        $usuarioId = (int)$usuarioId;
        $empresaId = $this->eid($empresaId);
        if ($usuarioId <= 0 || $empresaId <= 0) return 0;

        try {
            return (int) Capsule::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('empresa_id', $empresaId)
                ->max('rol_id');
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Último acceso (no requiere empresa)
     */
    public function actualizarUltimoAcceso(int $usuarioId, string $fecha): void
    {
        $usuarioId = (int)$usuarioId;
        if ($usuarioId <= 0) return;

        Capsule::table('usuarios')
            ->where('id', $usuarioId)
            ->whereNull('eliminado_en')
            ->update([
                'ultimo_acceso_en' => $fecha,
                'actualizado_en'   => $this->now(),
            ]);
    }

    // =========================================================
    // DataTables server-side
    // Devuelve:
    //  [ 'rows'=>[], 'total'=>int, 'filtered'=>int, 'draw'=>int ]
    // =========================================================

    public function datatable(int $empresaId, array $dt): array
    {
        $empresaId = $this->eid($empresaId);

        $draw   = (int)($dt['draw'] ?? 1);
        $start  = max(0, (int)($dt['start'] ?? 0));
        $length = (int)($dt['length'] ?? 25);
        $length = ($length <= 0) ? 25 : min(200, $length);

        $search = '';
        if (isset($dt['search']['value'])) $search = trim((string)$dt['search']['value']);

        $activo = isset($dt['activo']) ? trim((string)$dt['activo']) : '';
        $desde  = isset($dt['desde']) ? trim((string)$dt['desde']) : '';
        $hasta  = isset($dt['hasta']) ? trim((string)$dt['hasta']) : '';

        $total = (int)$this->baseQuery($empresaId)->count();

        $q = $this->baseQuery($empresaId);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('u.nombre', 'like', '%' . $search . '%')
                  ->orWhere('u.email', 'like', '%' . $search . '%')
                  ->orWhere('u.telefono', 'like', '%' . $search . '%')
                  ->orWhere('u.puesto', 'like', '%' . $search . '%');
            });
        }

        if ($activo !== '' && ($activo === '0' || $activo === '1')) {
            $q->where('u.activo', (int)$activo);
        }

        if ($desde !== '') $q->whereDate('u.creado_en', '>=', $desde);
        if ($hasta !== '') $q->whereDate('u.creado_en', '<=', $hasta);

        $filtered = (int)(clone $q)->count();

        $orderCol = (int)($dt['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($dt['order'][0]['dir'] ?? 'desc'));
        $orderDir = $orderDir === 'asc' ? 'asc' : 'desc';

        $cols = [
            0 => 'u.id',
            1 => 'u.nombre',
            2 => 'u.email',
            3 => 'u.telefono',
            4 => 'u.puesto',
            5 => 'u.activo',
            6 => 'u.creado_en',
        ];
        $orderBy = $cols[$orderCol] ?? 'u.id';

        $rows = $q->select(
                'u.id',
                'u.nombre',
                'u.email',
                'u.telefono',
                'u.puesto',
                'u.activo',
                'u.creado_en',
                'u.actualizado_en',
                'u.ultimo_acceso_en'
            )
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        return [
            'draw'     => $draw,
            'total'    => $total,
            'filtered' => $filtered,
            'rows'     => $rows ? $rows->toArray() : [],
        ];
    }

    // =========================================================
    // CRUD
    // =========================================================

    public function getById(int $empresaId, int $id): array
    {
        $empresaId = $this->eid($empresaId);
        $id = (int)$id;

        if ($empresaId <= 0 || $id <= 0) return [];

        $row = $this->baseQuery($empresaId)
            ->where('u.id', $id)
            ->select(
                'u.id','u.nombre','u.email','u.telefono','u.puesto','u.activo',
                'u.ultimo_acceso_en','u.creado_en','u.actualizado_en'
            )
            ->first();

        return $row ? (array)$row : [];
    }

    public function crearUsuario(int $empresaId, array $data): array
    {
        $empresaId = $this->eid($empresaId);
        if ($empresaId <= 0) return ['ok'=>false,'message'=>'empresa_id inválido'];

        $nombre  = trim((string)($data['nombre'] ?? ''));
        $email   = $this->normEmail((string)($data['email'] ?? ''));
        $tel     = trim((string)($data['telefono'] ?? ''));
        $puesto  = trim((string)($data['puesto'] ?? ''));
        $activo  = isset($data['activo']) ? (int)$data['activo'] : 1;
        $passRaw = (string)($data['password_plain'] ?? '');

        if ($nombre === '' || $email === '') return ['ok'=>false,'message'=>'Nombre y email requeridos'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'message'=>'Email inválido'];
        if (mb_strlen($passRaw) < 6) return ['ok'=>false,'message'=>'Password mínimo 6 caracteres'];

        $exists = (int)$this->baseQuery($empresaId)
            ->where('u.email', $email)
            ->count();
        if ($exists > 0) return ['ok'=>false,'message'=>'Ese email ya existe en tu empresa'];

        $hash = password_hash($passRaw, PASSWORD_BCRYPT);

        $id = Capsule::table('usuarios')->insertGetId([
            'empresa_id'        => $empresaId,
            'nombre'            => $nombre,
            'email'             => $email,
            'password_hash'     => $hash,
            'telefono'          => ($tel !== '' ? $tel : null),
            'puesto'            => ($puesto !== '' ? $puesto : null),
            'activo'            => ($activo === 0 ? 0 : 1),
            'ultimo_acceso_en'  => null,
            'creado_en'         => $this->now(),
            'actualizado_en'    => $this->now(),
            'eliminado_en'      => null,
        ]);

        return ['ok'=>true,'id'=>(int)$id];
    }

    public function actualizarUsuario(int $empresaId, int $id, array $data): array
    {
        $empresaId = $this->eid($empresaId);
        $id = (int)$id;

        if ($empresaId <= 0 || $id <= 0) return ['ok'=>false,'message'=>'Datos inválidos'];

        $nombre = trim((string)($data['nombre'] ?? ''));
        $email  = $this->normEmail((string)($data['email'] ?? ''));
        $tel    = trim((string)($data['telefono'] ?? ''));
        $puesto = trim((string)($data['puesto'] ?? ''));
        $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

        if ($nombre === '' || $email === '') return ['ok'=>false,'message'=>'Nombre y email requeridos'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'message'=>'Email inválido'];

        $cur = $this->baseQuery($empresaId)->where('u.id', $id)->first();
        if (!$cur) return ['ok'=>false,'message'=>'No existe'];

        $exists = (int)$this->baseQuery($empresaId)
            ->where('u.email', $email)
            ->where('u.id', '!=', $id)
            ->count();
        if ($exists > 0) return ['ok'=>false,'message'=>'Ese email ya existe en tu empresa'];

        Capsule::table('usuarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->whereNull('eliminado_en')
            ->update([
                'nombre'         => $nombre,
                'email'          => $email,
                'telefono'       => ($tel !== '' ? $tel : null),
                'puesto'         => ($puesto !== '' ? $puesto : null),
                'activo'         => ($activo === 0 ? 0 : 1),
                'actualizado_en' => $this->now(),
            ]);

        return ['ok'=>true,'id'=>$id];
    }

    public function setActivo(int $empresaId, int $id, int $activo): array
    {
        $empresaId = $this->eid($empresaId);
        $id = (int)$id;
        if ($empresaId <= 0 || $id <= 0) return ['ok'=>false,'message'=>'Datos inválidos'];

        $a = ($activo === 0) ? 0 : 1;

        $ok = Capsule::table('usuarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->whereNull('eliminado_en')
            ->update([
                'activo'         => $a,
                'actualizado_en' => $this->now(),
            ]);

        return ['ok'=>true,'updated'=>(int)$ok];
    }

    public function setPassword(int $empresaId, int $id, string $passwordPlain): array
    {
        $empresaId = $this->eid($empresaId);
        $id = (int)$id;

        $passwordPlain = (string)$passwordPlain;
        if ($empresaId <= 0 || $id <= 0) return ['ok'=>false,'message'=>'Datos inválidos'];
        if (mb_strlen($passwordPlain) < 6) return ['ok'=>false,'message'=>'Password mínimo 6 caracteres'];

        $hash = password_hash($passwordPlain, PASSWORD_BCRYPT);

        $ok = Capsule::table('usuarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->whereNull('eliminado_en')
            ->update([
                'password_hash'  => $hash,
                'actualizado_en' => $this->now(),
            ]);

        return ['ok'=>true,'updated'=>(int)$ok];
    }

    public function eliminarUsuario(int $empresaId, int $id): array
    {
        $empresaId = $this->eid($empresaId);
        $id = (int)$id;
        if ($empresaId <= 0 || $id <= 0) return ['ok'=>false,'message'=>'Datos inválidos'];

        $ok = Capsule::table('usuarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->whereNull('eliminado_en')
            ->update([
                'eliminado_en'   => $this->now(),
                'actualizado_en' => $this->now(),
            ]);

        return ['ok'=>true,'updated'=>(int)$ok];
    }
}
