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

    // Convierte vacío a NULL (para la mayoría de campos)
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
                'ciudad',
                'estado',
                'contacto_nombre',
                'activo',
                'creado_en'
            ]);

        $total = Capsule::table('colonias')
            ->whereNull('eliminado_en')
            ->count();

        if ($search) {
            $like = "%{$search}%";
            $base->where(function ($w) use ($like) {
                $w->where('clave', 'like', $like)
                  ->orWhere('nombre', 'like', $like)
                  ->orWhere('ciudad', 'like', $like)
                  ->orWhere('estado', 'like', $like)
                  ->orWhere('contacto_nombre', 'like', $like);
            });
        }

        $filtered = (clone $base)->count();

        $rows = $base
            ->orderBy('id', 'desc')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn($r) => (array)$r)
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
            ->whereNull('eliminado_en')
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    /* ===============================
       Crear / Editar colonia
    =============================== */
    public function saveColonia(array $in): int
    {
        $id     = $this->i($in['id'] ?? 0);
        $clave  = $this->s($in['clave']);
        $nombre = $this->s($in['nombre']);

        if (!$clave || !$nombre) {
            throw new Exception('Clave y nombre son obligatorios');
        }

        $data = [
            /* ===== Datos visibles ===== */
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

            /* ===== Técnicos ===== */
            'timezone'        => 'America/Monterrey',
            'db_host'         => 'localhost',
            'db_user'         => 'root',
            'db_port'         => 3306,
            'estatus_id'      => 1,
            'actualizado_en'  => $this->now()
        ];

        /* ===== EDITAR ===== */
        if ($id > 0) {
            Capsule::table('colonias')
                ->where('id', $id)
                ->update($data);

            return $id;
        }

        /* ===== CREAR ===== */
        $data['db_name']     = 'qacolonos_colonia_' . strtolower($clave);
        $data['db_pass_enc'] = ''; // ✅ STRING VACÍO (NO NULL)
        $data['creado_en']   = $this->now();

        return (int) Capsule::table('colonias')->insertGetId($data);
    }

    /* ===============================
       Eliminado lógico
    =============================== */
    public function deleteColonia(int $id): void
    {
        Capsule::table('colonias')
            ->where('id', $id)
            ->update([
                'eliminado_en' => $this->now()
            ]);
    }
}
