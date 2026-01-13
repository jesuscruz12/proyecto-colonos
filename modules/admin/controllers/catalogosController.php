<?php

class catalogosController extends adminController
{
    /** @var catalogosModel */
    private catalogosModel $_catalogos;

    // Slugs “core / nacional” (solo super/admin core)
    private array $CORE_SLUGS = [
        'cat_roles',
        'cat_tipos_entidad',
        'cat_estados',
        'estados_mx',
        'municipios_mx',
    ];

    public function __construct()
    {
        parent::__construct();

        // adminController ya valida sesión y crea $this->permisos
        $this->_catalogos = $this->loadModel('catalogos');
    }

    /* =========================================================
       Helpers JSON
       ========================================================= */

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Control centralizado de acceso a catálogos.
     * - Requiere ver módulo "catalogos"
     * - Catálogos core requieren ver "catalogos_core"
     * - Editar requiere editar "catalogos"
     */
    private function assertCatalogoPermitido(string $slug, string $accion = 'ver'): void
    {
        // 1) ver módulo
        if (!$this->permisos || !$this->permisos->puedeVer('catalogos')) {
            $this->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        // 2) core/nacional
        if (in_array($slug, $this->CORE_SLUGS, true) && !$this->permisos->puedeVer('catalogos_core')) {
            $this->json(['ok' => false, 'message' => 'Catálogo restringido.'], 403);
        }

        // 3) editar
        if ($accion === 'editar' && !$this->permisos->puedeEditar('catalogos')) {
            $this->json(['ok' => false, 'message' => 'Sin permisos para editar.'], 403);
        }
    }

    /* =========================================================
       Vista
       ========================================================= */

    public function index()
    {
        // Si no puede ver, fuera
        if (!$this->permisos->puedeVer('catalogos')) {
            $this->redireccionar('admin'); // o donde tú lo mandes
        }

        $core = new CORE();
        eval($core->head());

        $this->_view->menu   = 'catalogos';
        $this->_view->titulo = 'Catálogos';

        $this->_view->setJs(['index']);
        $this->_view->renderizar(['index']);
    }

    /* =========================================================
       API: lista de catálogos para el combo
       GET /admin/catalogos/catalogos_json
       ========================================================= */

    public function catalogos_json()
    {
        try {
            if (!$this->permisos->puedeVer('catalogos')) {
                return $this->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
            }

            $items = $this->_catalogos->listarCatalogos();

            // Si NO puede ver core, filtramos core
            if (!$this->permisos->puedeVer('catalogos_core')) {
                $items = array_values(array_filter($items, function ($c) {
                    return !in_array($c['slug'], $this->CORE_SLUGS, true);
                }));
            }

            return $this->json([
                'ok'   => true,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /* =========================================================
       API: lista de registros de un catálogo
       GET /admin/catalogos/lista?slug=cat_xxx
       ========================================================= */

    public function lista()
    {
        $slug = trim($_GET['slug'] ?? '');

        if ($slug === '') {
            return $this->json(['ok' => false, 'message' => 'Catálogo no especificado.'], 400);
        }

        // Permisos (ver)
        $this->assertCatalogoPermitido($slug, 'ver');

        $meta = $this->_catalogos->getCatalogMeta($slug);
        if (!$meta) {
            return $this->json(['ok' => false, 'message' => "Catálogo no configurado: $slug"], 404);
        }

        // Estructura (para columnas visibles)
        $colsMeta = $this->_catalogos->obtenerColumnasPorSlug($slug);
        if (!$colsMeta) {
            return $this->json(['ok' => false, 'message' => "No se pudo obtener la estructura del catálogo."], 500);
        }

        $allFields = array_map(fn($c) => $c['Field'], $colsMeta);

        // Filtramos columnas técnicas
        $ignore = [
            'creado_en','actualizado_en',
            'created_at','updated_at',
            'fecha_creado','fecha_actualizado',
            'creado_at','actualizado_at',
        ];

        $columns = [];
        foreach ($allFields as $f) {
            if (in_array($f, $ignore, true)) continue;
            $columns[] = $f;
        }

        // Datos
        $rows = $this->_catalogos->obtenerRegistrosPorSlug($slug);

        // Features para UI
        $hasActivo = in_array('activo', $allFields, true);

        return $this->json([
            'ok' => true,
            'data' => [
                'slug'    => $slug,
                'label'   => $meta['label'] ?? $slug,
                'columns' => $columns,
                'rows'    => $rows,
                'features'=> [
                    'has_activo' => $hasActivo,
                    // En producción: delete físico NO. Se usa baja lógica si hay activo
                    'can_delete' => false,
                    // Para que el front sepa si puede editar
                    'can_edit'   => $this->permisos->puedeEditar('catalogos')
                        && !(in_array($slug, $this->CORE_SLUGS, true) && !$this->permisos->puedeVer('catalogos_core')),
                ],
            ],
        ]);
    }

    /* =========================================================
       API: estructura real SHOW COLUMNS
       GET /admin/catalogos/estructura?slug=cat_xxx
       ========================================================= */

    public function estructura()
    {
        $slug = trim($_GET['slug'] ?? '');

        if ($slug === '') {
            return $this->json(['ok' => false, 'message' => 'Catálogo no especificado.'], 400);
        }

        $this->assertCatalogoPermitido($slug, 'ver');

        $meta = $this->_catalogos->getCatalogMeta($slug);
        if (!$meta) {
            return $this->json(['ok' => false, 'message' => "Catálogo no configurado: $slug"], 404);
        }

        $colsMeta = $this->_catalogos->obtenerColumnasPorSlug($slug);
        if (!$colsMeta) {
            return $this->json(['ok' => false, 'message' => 'No se pudo leer la estructura.'], 500);
        }

        return $this->json([
            'ok' => true,
            'data' => [
                'slug'  => $slug,
                'label' => $meta['label'] ?? $slug,
                'cols'  => $colsMeta, // Field, Type, Key, Extra
            ],
        ]);
    }

    /* =========================================================
       API: guardar registro (create/update)
       POST /admin/catalogos/guardar
       ========================================================= */

    public function guardar()
    {
        $slug = trim($_POST['slug'] ?? '');
        $id   = (isset($_POST['id']) && $_POST['id'] !== '') ? (int)$_POST['id'] : null;

        if ($slug === '') {
            return $this->json(['ok' => false, 'message' => 'Catálogo no especificado.'], 400);
        }

        // Permisos (editar)
        $this->assertCatalogoPermitido($slug, 'editar');

        $meta = $this->_catalogos->getCatalogMeta($slug);
        if (!$meta) {
            return $this->json(['ok' => false, 'message' => "Catálogo no configurado: $slug"], 404);
        }

        // payload: todo menos slug/id
        $data = $_POST;
        unset($data['slug'], $data['id']);

        // Validación mínima: si existen, no vacíos
        if (array_key_exists('clave', $data)) {
            $data['clave'] = trim((string)$data['clave']);
            if ($data['clave'] === '') {
                return $this->json(['ok' => false, 'message' => 'El campo "clave" no puede estar vacío.'], 400);
            }
        }

        if (array_key_exists('nombre', $data)) {
            $data['nombre'] = trim((string)$data['nombre']);
            if ($data['nombre'] === '') {
                return $this->json(['ok' => false, 'message' => 'El campo "nombre" no puede estar vacío.'], 400);
            }
        }

        // Guardado (modelo PRO)
        $res = $this->_catalogos->guardarRegistroPorSlug($slug, $id, $data);

        if (!empty($res['error'])) {
            return $this->json(['ok' => false, 'message' => $res['message'] ?? 'Error al guardar.'], 500);
        }

        return $this->json([
            'ok'      => true,
            'mode'    => $res['mode'] ?? null,
            'id'      => $res['id'] ?? null,
            'message' => (($res['mode'] ?? 'create') === 'create')
                ? 'Registro creado correctamente.'
                : 'Registro actualizado correctamente.',
        ]);
    }

    /* =========================================================
       API: “eliminar” (producción = baja lógica)
       POST /admin/catalogos/eliminar
       ========================================================= */

    public function eliminar()
    {
        $slug = trim($_POST['slug'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);

        if ($slug === '' || $id <= 0) {
            return $this->json(['ok' => false, 'message' => 'Parámetros inválidos.'], 400);
        }

        // Permisos (editar)
        $this->assertCatalogoPermitido($slug, 'editar');

        $meta = $this->_catalogos->getCatalogMeta($slug);
        if (!$meta) {
            return $this->json(['ok' => false, 'message' => "Catálogo no configurado: $slug"], 404);
        }

        $res = $this->_catalogos->eliminarRegistroPorSlug($slug, $id);

        if (!empty($res['error'])) {
            return $this->json(['ok' => false, 'message' => $res['message'] ?? 'No se pudo desactivar.'], 500);
        }

        return $this->json([
            'ok'      => true,
            'mode'    => $res['mode'] ?? null,
            'message' => 'Registro desactivado correctamente.',
        ]);
    }
}
