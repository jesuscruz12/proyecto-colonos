<?php
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Permisos por rol:
 * - permisos: (id, nombre)
 * - rol_permiso: (rol_id, permiso_id)
 *
 * Nombres típicos: "usuarios.gestionar", "ot.crear", "ot.editar"...
 */
class Permisos
{
    protected int $usuarioId;
    protected int $rolId;

    protected array $flat = [];   // ['usuarios.gestionar'=>true,...]
    protected array $cache = [];  // ['usuarios'=>['usuarios.gestionar'=>1,...]]

    public function __construct(int $usuarioId, int $rolId)
    {
        $this->usuarioId = $usuarioId;
        $this->rolId     = $rolId;

        $this->cargarPermisosRol();
    }

    // -------------------------
    // API estilo “clásico”
    // -------------------------
    public function puedeVer(string $modulo): bool
    {
        return $this->tieneAccion($modulo, ['ver','gestionar','editar','crear','inspeccionar','documentos','generar','ejecutar','pnc','exportar','importar']);
    }

    public function puedeEditar(string $modulo): bool
    {
        return $this->tieneAccion($modulo, ['editar','gestionar']);
    }

    public function puedeEliminar(string $modulo): bool
    {
        return $this->tieneAccion($modulo, ['eliminar','gestionar']);
    }

    public function puedeImportar(string $modulo): bool
    {
        return $this->tieneAccion($modulo, ['importar','gestionar']);
    }

    public function puedeExportar(string $modulo): bool
    {
        return $this->tieneAccion($modulo, ['exportar','gestionar']);
    }

    public function exportar(string $modulo): array
    {
        $modulo = $this->normModulo($modulo);
        $this->aseguraCacheModulo($modulo);

        return [
            'puede_ver'      => $this->puedeVer($modulo) ? 1 : 0,
            'puede_editar'   => $this->puedeEditar($modulo) ? 1 : 0,
            'puede_eliminar' => $this->puedeEliminar($modulo) ? 1 : 0,
            'puede_importar' => $this->puedeImportar($modulo) ? 1 : 0,
            'puede_exportar' => $this->puedeExportar($modulo) ? 1 : 0,
            'permisos'       => array_keys($this->cache[$modulo] ?? []),
        ];
    }

    // -------------------------
    // Internals
    // -------------------------
    protected function normModulo(string $modulo): string
    {
        return trim(mb_strtolower($modulo));
    }

    protected function cargarPermisosRol(): void
    {
        $this->flat = [];
        $this->cache = [];

        if ($this->rolId <= 0) return;

        $rows = Capsule::table('rol_permiso as rp')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('rp.rol_id', $this->rolId)
            ->select('p.nombre')
            ->get();

        foreach ($rows as $r) {
            $name = (string)$r->nombre;
            if ($name !== '') $this->flat[$name] = true;
        }
    }

    protected function aseguraCacheModulo(string $modulo): void
    {
        if (isset($this->cache[$modulo])) return;

        $this->cache[$modulo] = [];
        $prefix = $modulo . '.';

        foreach ($this->flat as $perm => $_) {
            if (stripos($perm, $prefix) === 0) {
                $this->cache[$modulo][$perm] = 1;
            }
        }
    }

    protected function tieneAccion(string $modulo, array $acciones): bool
    {
        $modulo = $this->normModulo($modulo);
        if ($modulo === '' || $this->rolId <= 0) return false;

        $this->aseguraCacheModulo($modulo);

        if (!empty($this->flat[$modulo . '.gestionar'])) return true;

        foreach ($acciones as $a) {
            $key = $modulo . '.' . $a;
            if (!empty($this->flat[$key])) return true;
        }

        // Si tiene cualquier permiso del módulo, consideramos “ver”
        if (in_array('ver', $acciones, true) && !empty($this->cache[$modulo])) {
            return true;
        }

        return false;
    }
}
