<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class tablacoloniasModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ===============================
       Helpers
    =============================== */
    private function i($v): int
    {
        return (int)($v ?? 0);
    }

    // Convierte vacío a NULL
    private function s($v): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : $v;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /* ===============================
       DataTables listado
    =============================== */
    public function dtList(array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search = $this->s($q['search']['value'] ?? '');

        $base = Capsule::table('colonias')
            ->whereNull('eliminado_en')
            ->select([
                'id',
                'clave',
                'nombre',
                'razon_social',
                'rfc',
                'primary_color',
                'ciudad',
                'estado',
                'cp',
                'contacto_nombre',
                'contacto_email',
                'contacto_tel',
                'activo',
                'creado_en'
            ]);

        /* ===============================
           FILTROS PERSONALIZADOS
        =============================== */
        if (!empty($q['f_q'])) {
            $like = '%' . $q['f_q'] . '%';
            $base->where(function ($w) use ($like) {
                $w->where('clave', 'like', $like)
                ->orWhere('nombre', 'like', $like);
            });
        }

        // Estado
        if (!empty($q['f_estado'])) {
            $base->where('estado', 'like', '%' . $q['f_estado'] . '%');
        }

        if (!empty($q['f_ciudad'])) {
            $base->where('ciudad', 'like', '%' . $q['f_ciudad'] . '%');
        }

        if (isset($q['f_activo']) && $q['f_activo'] !== '') {
            $base->where('activo', (int)$q['f_activo']);
        }

        /* ===============================
           BÚSQUEDA GLOBAL
        =============================== */
        if ($search) {
            $like = "%{$search}%";
            $base->where(function ($w) use ($like) {
                $w->where('clave', 'like', $like)
                  ->orWhere('nombre', 'like', $like)
                  ->orWhere('razon_social', 'like', $like)
                  ->orWhere('rfc', 'like', $like)
                  ->orWhere('ciudad', 'like', $like)
                  ->orWhere('estado', 'like', $like)
                  ->orWhere('cp', 'like', $like)
                  ->orWhere('contacto_nombre', 'like', $like)
                  ->orWhere('contacto_email', 'like', $like)
                  ->orWhere('contacto_tel', 'like', $like);
            });
        }

        $total = Capsule::table('colonias')
            ->whereNull('eliminado_en')
            ->count();

        $filtered = (clone $base)->count();

        $rows = $base
            ->orderBy('id', 'desc')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn ($r) => (array)$r)
            ->toArray();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows
        ];
    }

    /* ===============================
       Obtener colonia
    =============================== */
    public function get(int $id): ?array
    {
        $r = Capsule::table('colonias')
            ->where('id', $id)
            ->whereNull('eliminado_en')
            ->first();

        return $r ? (array)$r : null;
    }

    /* ===============================
       Crear / Editar colonia
    =============================== */
    public function saveColonia(array $in): int
    {
        $id     = $this->i($in['id'] ?? 0);
        $clave  = strtolower(trim($in['clave'] ?? ''));
        $nombre = trim($in['nombre'] ?? '');

        if ($clave === '' || $nombre === '') {
            throw new Exception('Clave y nombre son obligatorios');
        }

        $dbName = "qacolonos_colonia_{$clave}";
        $dbTpl  = "qacolonos_colonia_template";

        $data = [
            'clave'           => $clave,
            'nombre'          => $nombre,
            'logo_url'        => $this->s($in['logo_url']),
            'primary_color'   => $this->s($in['primary_color']) ?? '#0A84FF',
            'razon_social'    => $this->s($in['razon_social']),
            'rfc'             => $this->s($in['rfc']),
            'direccion'       => $this->s($in['direccion']),
            'ciudad'          => $this->s($in['ciudad']),
            'estado'          => $this->s($in['estado']),
            'cp'              => $this->s($in['cp']),
            'pais'            => $this->s($in['pais']) ?? 'México',
            'contacto_nombre' => $this->s($in['contacto_nombre']),
            'contacto_email'  => $this->s($in['contacto_email']),
            'contacto_tel'    => $this->s($in['contacto_tel']),
            'activo'          => $this->i($in['activo'] ?? 1),

            /* técnicos */
            'timezone'        => 'America/Monterrey',
            'db_host'         => 'localhost',
            'db_name'         => $dbName,
            'db_user'         => 'root',
            'db_pass_enc'     => '',
            'db_port'         => 3306,
            'estatus_id'      => 1,
            'actualizado_en'  => $this->now()
        ];

        /* ===== EDITAR ===== */
        if ($id > 0) {
            Capsule::table('colonias')->where('id', $id)->update($data);
            return $id;
        }

        /* ===== CREAR ===== */
        $data['creado_en'] = $this->now();
        $newId = Capsule::table('colonias')->insertGetId($data);

        try {
            Capsule::statement("
                CREATE DATABASE `$dbName`
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_unicode_ci
            ");

            $tables = Capsule::select("SHOW TABLES FROM `$dbTpl`");
            $key = "Tables_in_{$dbTpl}";

            foreach ($tables as $t) {
                $table = $t->$key;

                Capsule::statement("
                    CREATE TABLE `$dbName`.`$table`
                    LIKE `$dbTpl`.`$table`
                ");

                Capsule::statement("
                    INSERT INTO `$dbName`.`$table`
                    SELECT * FROM `$dbTpl`.`$table`
                ");
            }
        } catch (Throwable $e) {
            Capsule::table('colonias')->where('id', $newId)->delete();
            throw $e;
        }

        return (int)$newId;
    }

    /* ===============================
       Eliminado lógico
    =============================== */
    public function deleteColonia(int $id): void
    {
        Capsule::table('colonias')
            ->where('id', $id)
            ->update(['eliminado_en' => $this->now()]);
    }
}
