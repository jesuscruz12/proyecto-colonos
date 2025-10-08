<!doctype html>
<html lang="es">
<?php if (Session::get('autenticado')): ?>

  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tipografías e íconos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">

    <!-- Plugins -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css" crossorigin="anonymous">

    <!-- DataTables + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- AlertifyJS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css" />

    <!-- AdminLTE + Global -->
    <link href="<?= $_layoutParams['ruta_css']; ?>adminlte.css?<?= time(); ?>" rel="stylesheet">
    <link href="<?= $_layoutParams['ruta_css']; ?>global.css?<?= time(); ?>" rel="stylesheet">

    <!-- jsPDF (si tu vista lo usa) -->
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <!-- UX tweaks mínimos -->
    <style>
      /* mejora foco accesible en el sidebar */
      .app-sidebar .nav-link:focus-visible {
        outline: 2px solid #86b7fe;
        outline-offset: 2px;
      }

      /* compensar altura en móviles cuando se abre el teclado */
      @supports (height: 100dvh) {
        .app-wrapper {
          min-height: 100dvh;
        }
      }

      /* compactar sidebar en pantallas xs */
      @media (max-width: 480px) {
        .sidebar-wrapper {
          font-size: .95rem;
        }

        .brand-text {
          font-size: 1rem;
        }
      }
    </style>
  </head>

  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">

      <?php
      // ====== Helpers de ruta/activo ======
      $requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'); // ej: 'crm-emp/admin/wlindicadores'
      // Si tu app corre bajo subcarpeta, normaliza quitando la base del proyecto:
      $baseSlug = trim(parse_url(BASE_URL, PHP_URL_PATH), '/'); // ej: 'crm-emp'
      if ($baseSlug && str_starts_with($requestPath, $baseSlug . '/')) {
        $requestPath = substr($requestPath, strlen($baseSlug) + 1);
      }

      // Compatibilidad PHP < 8 para str_starts_with
      if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle)
        {
          return $needle === '' || strpos($haystack, $needle) === 0;
        }
      }

      // Marca activo si la ruta actual comienza con $route
      $isActive = function (string $route) use ($requestPath): bool {
        $route = trim($route, '/');
        return $route !== '' && str_starts_with($requestPath, $route);
      };

      // Datos de usuario/rol
      $u      = Session::get('usuario') ?: [];
      $nombre = (string)($u['nombre'] ?? 'Usuario');
      $email  = (string)($u['email']  ?? '');
      $rol    = (int)   ($u['rol']    ?? 0);
      $ult    = (string)($u['ultimo_login'] ?? '');
      $cv_wl  = $u['cv_wl'] ?? null;

      $rolesMap = [1 => 'Administrador', 2 => 'Operador', 3 => 'Cliente'];
      $rolTxt   = $rolesMap[$rol] ?? '—';

      // Avatar
      $avatarUrl = '';
      if (!empty($u['avatar_url'])) {
        $avatarUrl = (string)$u['avatar_url'];
      } elseif ($email !== '') {
        $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=128&d=identicon';
      } else {
        $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(uniqid('', true)) . '?s=128&d=identicon';
      }

      // Permisos helper
      $can = function ($perm) {
        if (Session::get('rol') == ADMINISTRADOR) return true;
        $perms = Session::get('permisos') ?? [];
        return (bool)($perms[$perm] ?? false);
      };
      ?>

      <!-- ===== NAVBAR SUPERIOR ===== -->
      <nav class="app-header navbar navbar-expand bg-body" aria-label="Barra superior">
        <div class="container-fluid">

          <!-- Botón abrir/cerrar sidebar -->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Alternar menú lateral">
                <i class="bi bi-list" aria-hidden="true"></i>
              </a>
            </li>
          </ul>

          <!-- Acciones derechas -->
          <ul class="navbar-nav ms-auto">

            <!-- Perfil -->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" class="user-image rounded-circle shadow" alt="Avatar" width="32" height="32" loading="lazy">
                <span class="d-none d-md-inline"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge bg-secondary d-none d-lg-inline ms-1"><?= htmlspecialchars($rolTxt, ENT_QUOTES, 'UTF-8'); ?></span>
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
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li class="px-3 py-1 small text-muted">
                  Rol: <span class="fw-semibold"><?= htmlspecialchars($rolTxt, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php if ($cv_wl !== null): ?> · Wallet: <span class="fw-semibold"><?= htmlspecialchars((string)$cv_wl, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </li>
                <?php if ($ult): ?>
                  <li class="px-3 pb-2 small text-muted">Último acceso: <span class="fw-semibold"><?= htmlspecialchars($ult, ENT_QUOTES, 'UTF-8'); ?></span></li>
                <?php endif; ?>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a href="<?= BASE_URL; ?>perfil" class="dropdown-item" role="menuitem"><i class="bi bi-person me-2"></i> Perfil</a></li>
                <li><a href="<?= BASE_URL; ?>perfil/password" class="dropdown-item" role="menuitem"><i class="bi bi-shield-lock me-2"></i> Cambiar contraseña</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li class="px-3 py-2">
                  <form action="<?= BASE_URL; ?>index/cerrar" method="post" class="m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Session::get('tokencsrf') ?: '', ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100" role="menuitem">
                      <i class="bi bi-box-arrow-right me-1"></i> Salir
                    </button>
                  </form>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <!-- ===== SIDEBAR ===== -->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="<?= BASE_URL; ?>" class="brand-link">
            <span class="brand-text fw-light">CRM EMP</span>
          </a>
        </div>

        <div class="sidebar-wrapper">
          <nav class="mt-2" aria-label="Menú lateral">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

              <!-- Indicadores -->
              <?php if ($can('wlindicadores.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlindicadores'; ?>"
                    class="nav-link <?= $isActive('admin/wlindicadores') ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-speedometer"></i>
                    <p>Indicadores</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Usuarios -->
              <?php if ($can('wlusuarios.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlusuarios'; ?>"
                    class="nav-link <?= $isActive('admin/wlusuarios') ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-people"></i>
                    <p>Usuarios</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Inventario SIMS -->
              <?php if ($can('wlsims.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlsims'; ?>"
                    class="nav-link <?= $isActive('admin/wlsims') ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-sim"></i>
                    <p>Inventario SIMS</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Portabilidades -->
              <?php if ($can('wlportabilidades.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlportabilidades'; ?>"
                    class="nav-link <?= $isActive('admin/wlportabilidades') ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-arrow-left-right"></i>
                    <p>Portabilidades</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Recargas -->
              <?php if ($can('wlrecargas.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlrecargas'; ?>"
                    class="nav-link <?= $isActive('admin/wlrecargas') ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-phone-fill"></i>
                    <p>Recargas</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Planes de Telefonía -->
              <?php if ($can('wlplanes.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlplanes'; ?>"
                    class="nav-link <?= $isActive('admin/wlplanes') ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-mobile-alt"></i>
                    <p>Planes de Telefonía</p>
                  </a>
                </li>
              <?php endif; ?>


              <!-- Activalo TU -->
              <?php if ($can('wlactivalotu.ver')): ?>
                <li class="nav-item">
                  <a href="<?= BASE_URL . 'admin/wlactivalotu'; ?>"
                    class="nav-link <?= $isActive('admin/wlactivalotu') ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-bolt"></i> <!-- Ícono de rayo -->
                    <p>Actívalo Tú</p>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Ejemplo de grupo con submenú (sin activos hardcodeados) -->

              <li class="nav-item <?= $isActive('admin/reportes') ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link <?= $isActive('admin/reportes') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-graph-up"></i>
                  <p>Reportes<i class="nav-arrow bi bi-chevron-right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?= BASE_URL . 'admin/reportes/ventas'; ?>" class="nav-link <?= $isActive('admin/reportes/ventas') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Ventas</p>
                    </a>
                  </li>
                </ul>
              </li>


            </ul>
          </nav>
        </div>
      </aside>

      <!-- A partir de aquí, tu vista imprime <main class="app-main"> ... -->
    <?php endif; ?>