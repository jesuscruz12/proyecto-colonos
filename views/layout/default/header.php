<?php
/**
 * TAKTIK — Header layout (PROD)
 * - MVC clásico
 * - AdminLTE + DataTables CSS
 * - BASE_URL + CSRF global
 * - Sidebar con permisos (Permisos.php)
 */

function taktik_ver(): string {
  if (defined('APP_VER') && APP_VER) return (string)APP_VER;
  return '1';
}

$ver = taktik_ver();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Globals -->
  <script>
    (function () {
      window.BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES); ?>;
      window.CSRF_TOKEN = <?= json_encode((string)(Session::get('tokencsrf') ?: ''), JSON_UNESCAPED_SLASHES); ?>;
    
    })();
  </script>

  <!-- Fonts / Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" crossorigin="anonymous">

  <!-- OverlayScrollbars (CSS) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">

  <!-- AdminLTE + Global -->
  <link rel="stylesheet" href="<?= $_layoutParams['ruta_css']; ?>adminlte.css?v=<?= urlencode($ver); ?>">
  <link rel="stylesheet" href="<?= $_layoutParams['ruta_css']; ?>global.css?v=<?= urlencode($ver); ?>">


  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" crossorigin="anonymous"></script>


  <!-- DataTables (CSS) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" crossorigin="anonymous">

  <!-- Alertify (CSS) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css" crossorigin="anonymous">






  <style>
    .app-sidebar .nav-link:focus-visible { outline: 2px solid #86b7fe; outline-offset: 2px; }
    @supports (height: 100dvh) { .app-wrapper { min-height: 100dvh; } }
    @media (max-width: 480px) { .brand-text { font-size: 1rem; } }


    /* Sidebar: que SI scrollee y no se coma los últimos módulos */
.app-sidebar .sidebar-wrapper{
  height: calc(100vh - 56px); /* 56px aprox header brand, ajusta si tu brand mide distinto */
  overflow-y: auto;
  overflow-x: hidden;
}

  </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

<?php if (Session::get('autenticado')): ?>
<div class="app-wrapper">

<?php
  // ==========================================================
  // Path activo (para marcar menu)
  // ==========================================================
  $requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $baseSlug    = trim(parse_url(BASE_URL, PHP_URL_PATH), '/');
  if ($baseSlug && strpos($requestPath, $baseSlug . '/') === 0) {
    $requestPath = substr($requestPath, strlen($baseSlug) + 1);
  }
  if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n === '' || strpos($h, $n) === 0; }
  }
  $isActive = function (string $route) use ($requestPath): bool {
    $route = trim($route, '/');
    return $route !== '' && str_starts_with($requestPath, $route);
  };

  // ==========================================================
  // Usuario (sesión)
  // ==========================================================
  $u      = Session::get('usuario') ?: [];
  $nombre = (string)($u['nombre'] ?? 'Usuario');
  $email  = (string)($u['email']  ?? '');

  // Avatar
  if (!empty($u['avatar_url'])) {
    $avatarUrl = (string)$u['avatar_url'];
  } elseif ($email !== '') {
    $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=128&d=identicon';
  } else {
    $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(uniqid('', true)) . '?s=128&d=identicon';
  }

  // ==========================================================
  // Permisos helper (inyectado desde adminController)
  // - En tu proyecto normalmente existe $permisos por extract() del view
  // - Si no existe: fallback (no ocultar nada)
  // ==========================================================
  $permisosHelper = isset($permisos) ? $permisos : null;

  $can = function (string $permiso) use ($permisosHelper): bool {
    if (!$permisosHelper instanceof Permisos) return true; // fallback
    $permiso = trim($permiso);
    if ($permiso === '') return false;

    $partes = explode('.', $permiso, 2);
    if (count($partes) !== 2) return false;

    $modulo = strtolower(trim($partes[0]));
    $accion = strtolower(trim($partes[1]));

    switch ($accion) {
      case 'ver':      return $permisosHelper->puedeVer($modulo);
      case 'editar':   return $permisosHelper->puedeEditar($modulo);
      case 'eliminar': return $permisosHelper->puedeEliminar($modulo);
      case 'importar': return $permisosHelper->puedeImportar($modulo);
      case 'exportar': return $permisosHelper->puedeExportar($modulo);
      default:         return false;
    }
  };
?>

  <!-- ======================================================
    NAVBAR
  ======================================================= -->
  <nav class="app-header navbar navbar-expand bg-body" aria-label="Barra superior">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Alternar menú lateral">
            <i class="bi bi-list" aria-hidden="true"></i>
          </a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" class="user-image rounded-circle shadow" alt="Avatar" width="32" height="32" loading="lazy">
            <span class="d-none d-md-inline"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></span>
          </a>

          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end" role="menu" aria-label="Menú de usuario">
            <li class="dropdown-header">
              <div class="d-flex align-items-center">
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle me-2" alt="Avatar" width="40" height="40">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php if ($email): ?>
                    <div class="small text-muted"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </li>

            <li><hr class="dropdown-divider"></li>

            <li><a href="<?= BASE_URL; ?>admin/perfil" class="dropdown-item"><i class="bi bi-person me-2"></i> Perfil</a></li>
            <li><a href="<?= BASE_URL; ?>admin/perfil/password" class="dropdown-item"><i class="bi bi-shield-lock me-2"></i> Cambiar contraseña</a></li>

            <li><hr class="dropdown-divider"></li>

            <li class="px-3 py-2">
              <form action="<?= BASE_URL; ?>index/cerrar" method="post" class="m-0 p-0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)(Session::get('tokencsrf') ?: ''), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                  <i class="bi bi-box-arrow-right me-1"></i> Salir
                </button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  
<!-- ======================================================
  SIDEBAR — TAKTIK (ordenado + iconos + lógica)
======================================================= -->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="<?= BASE_URL; ?>admin/index" class="brand-link">
      <span class="brand-text fw-light"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
    </a>
  </div>

  <div class="sidebar-wrapper">
    <nav class="mt-2" aria-label="Menú lateral">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

        <!-- Home -->
        <li class="nav-item">
          <a href="<?= BASE_URL; ?>admin/index"
             class="nav-link <?= $isActive('admin/index') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer2"></i>
            <p>Inicio</p>
          </a>
        </li>

        <!-- =========================
             OPERACIÓN
        ========================== -->
        <li class="nav-header text-uppercase small opacity-75">Operación</li>

        <?php if ($can('ot.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/tablacolonias"
               class="nav-link <?= $isActive('admin/tablacolonias') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-clipboard-check"></i>
              <p>Colonias</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('calendarioslaborales.ver') || $can('ot.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/calendarioslaborales"
               class="nav-link <?= $isActive('admin/calendarioslaborales') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-calendar3"></i>
              <p>Calendarios laborales</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('ot.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/planeacion"
               class="nav-link <?= $isActive('admin/planeacion') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-diagram-3"></i>
              <p>Planeación</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('ot.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/programacionmaquinas"
               class="nav-link <?= $isActive('admin/programacionmaquinas') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-calendar2-week"></i>
              <p>Programación</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('tareas.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/tareas"
               class="nav-link <?= $isActive('admin/tareas') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-list-task"></i>
              <p>Tareas</p>
            </a>
          </li>
        <?php endif; ?>


        <!-- =========================
             INGENIERÍA
        ========================== -->
        <li class="nav-header text-uppercase small opacity-75">Ingeniería</li>

        <?php if ($can('procesos.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/procesos"
               class="nav-link <?= $isActive('admin/procesos') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-activity"></i>
              <p>Procesos</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('maquinas.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/maquinas"
               class="nav-link <?= $isActive('admin/maquinas') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-cpu"></i>
              <p>Máquinas</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('procesomaquina.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/procesomaquina"
               class="nav-link <?= $isActive('admin/procesomaquina') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-link-45deg"></i>
              <p>Procesos ↔ Máquinas</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('rutas.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/rutas"
               class="nav-link <?= $isActive('admin/rutas') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-signpost-2"></i>
              <p>Rutas</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('bom.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/bom"
               class="nav-link <?= $isActive('admin/bom') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-boxes"></i>
              <p>BOM</p>
            </a>
          </li>
        <?php endif; ?>


        <!-- =========================
             CATÁLOGOS (operativos)
        ========================== -->
        <li class="nav-header text-uppercase small opacity-75">Catálogos</li>

        <?php if ($can('clientes.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/clientes"
               class="nav-link <?= $isActive('admin/clientes') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-people"></i>
              <p>Clientes</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('partes.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/partes"
               class="nav-link <?= $isActive('admin/partes') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-tags"></i>
              <p>Partes</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('productos.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/productos"
               class="nav-link <?= $isActive('admin/productos') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-box-seam"></i>
              <p>Productos</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($can('embarques.ver')): ?>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/embarques"
               class="nav-link <?= $isActive('admin/embarques') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-truck"></i>
              <p>Embarques</p>
            </a>
          </li>
        <?php endif; ?>


        <!-- =========================
             CONTROL
        ========================== -->

        <!-- <?php if ($can('auditoria.ver')): ?>
          <li class="nav-header text-uppercase small opacity-75">Control</li>
          <li class="nav-item">
            <a href="<?= BASE_URL; ?>admin/auditoria"
               class="nav-link <?= $isActive('admin/auditoria') ? 'active' : ''; ?>">
              <i class="nav-icon bi bi-shield-check"></i>
              <p>Auditoría</p>
            </a>
          </li>
        <?php endif; ?> -->


        <!-- =========================
             ADMINISTRACIÓN (solo sistema)
        ========================== -->
        <?php
          $adminOpen =
            $isActive('admin/usuarios') ||
            $isActive('admin/permisos') ||
            $isActive('admin/catalogos');
        ?>
        <li class="nav-header text-uppercase small opacity-75">Administración</li>

        <li class="nav-item <?= $adminOpen ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?= $adminOpen ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-gear"></i>
            <p>Administración <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>

          <ul class="nav nav-treeview">
            <?php if ($can('usuarios.ver')): ?>
              <li class="nav-item">
                <a href="<?= BASE_URL; ?>admin/usuarios"
                   class="nav-link <?= $isActive('admin/usuarios') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-person-gear"></i>
                  <p>Usuarios</p>
                </a>
              </li>
            <?php endif; ?>

            <?php if ($can('permisos.ver')): ?>
              <li class="nav-item">
                <a href="<?= BASE_URL; ?>admin/permisos"
                   class="nav-link <?= $isActive('admin/permisos') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-lock"></i>
                  <p>Permisos</p>
                </a>
              </li>
            <?php endif; ?>

            <!-- <?php if ($can('catalogos.ver')): ?>
              <li class="nav-item">
                <a href="<?= BASE_URL; ?>admin/catalogos"
                   class="nav-link <?= $isActive('admin/catalogos') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-grid-3x3-gap"></i>
                  <p>Catálogos</p>
                </a>
              </li>
            <?php endif; ?> -->


          </ul>
        </li>

      </ul>
    </nav>
  </div>
</aside>



  <!-- A partir de aquí, tu vista imprime <main class="app-main"> ... -->

<?php endif; ?>
