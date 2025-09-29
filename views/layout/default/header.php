<!doctype html>
<html lang="es">
<?php if (Session::get('autenticado')) : ?>

  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo APP_NAME; ?></title><!--begin::Primary Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="CRM | Dashboard">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous"><!--end::Fonts--><!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" integrity="sha256-dSokZseQNT08wYEWiz5iLI8QPlKxG+TswNRD8k35cpg=" crossorigin="anonymous"><!--end::Third Party Plugin(OverlayScrollbars)--><!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" integrity="sha256-Qsx5lrStHZyR9REqhUF8iQt73X06c8LGIUPzpOhwRrI=" crossorigin="anonymous"><!--end::Third Party Plugin(Bootstrap Icons)--><!--begin::Required Plugin(AdminLTE)-->
    <link href="<?php echo $_layoutParams['ruta_css']; ?>adminlte.css?<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous"><!-- jsvectormap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css" integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous">

    <!-- DataTables + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- AlertifyJS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css" />

    <!-- Font Awesome (para los íconos fas fa-*) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">


    <link href="<?php echo $_layoutParams['ruta_css']; ?>global.css?<?php echo time(); ?>" rel="stylesheet">
  
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>


    <!-- Librerias de complemento para las vistas Fin -->

  </head> <!--end::Head--> <!--begin::Body-->

  <!-- ========== BODY + APP WRAPPER ========== -->

  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary"> <!--begin::App Wrapper-->
    <div class="app-wrapper"> <!--begin::Header-->


      <!-- ========== HEADER (NAVBAR SUPERIOR) INICIO ========== -->
      <nav class="app-header navbar navbar-expand bg-body" aria-label="Barra superior">
        <div class="container-fluid">

          <!-- Izquierda: botón para abrir/cerrar sidebar -->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Alternar menú lateral">
                <i class="bi bi-list"></i>
              </a>
            </li>
          </ul>

          <!-- Derecha: usuario -->
          <ul class="navbar-nav ms-auto">
            <?php
            $u      = Session::get('usuario') ?: [];
            $nombre = (string)($u['nombre'] ?? 'Usuario');
            $email  = (string)($u['email']  ?? '');
            $rol    = (int)   ($u['rol']    ?? 0);
            $ult    = (string)($u['ultimo_login'] ?? '');
            $cv_wl  = $u['cv_wl'] ?? null;

            // Map de roles (ajústalo a tus constantes reales)
            $rolesMap = [1 => 'Administrador', 2 => 'Operador', 3 => 'Cliente'];
            $rolTxt   = $rolesMap[$rol] ?? '—';

            // Avatar: url propia o Gravatar identicon por email (sin CSS custom)
            $avatarUrl = '';
            if (!empty($u['avatar_url'])) {
              $avatarUrl = (string)$u['avatar_url'];
            } elseif ($email !== '') {
              $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=128&d=identicon';
            } else {
              // último fallback: identicon genérico
              $avatarUrl = 'https://www.gravatar.com/avatar/' . md5(uniqid('', true)) . '?s=128&d=identicon';
            }
            ?>

            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                  class="user-image rounded-circle shadow" alt="Avatar" loading="lazy">
                <span class="d-none d-md-inline">
                  <?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="badge bg-secondary d-none d-lg-inline ms-1">
                  <?php echo htmlspecialchars($rolTxt, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </a>

              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end" role="menu" aria-label="Menú de usuario">
                <!-- Encabezado con nombre y email -->
                <li class="dropdown-header">
                  <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                      class="rounded-circle me-2" alt="Avatar" width="40" height="40">
                    <div>
                      <div class="fw-semibold"><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php if ($email): ?>
                        <div class="small text-muted"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </li>

                <li>
                  <hr class="dropdown-divider">
                </li>

                <!-- Info corta -->
                <li class="px-3 py-1 small text-muted">
                  Rol: <span class="fw-semibold"><?php echo htmlspecialchars($rolTxt, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php if ($cv_wl !== null): ?>
                    · Wallet: <span class="fw-semibold"><?php echo htmlspecialchars((string)$cv_wl, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endif; ?>
                </li>
                <?php if ($ult): ?>
                  <li class="px-3 pb-2 small text-muted">
                    Último acceso: <span class="fw-semibold"><?php echo htmlspecialchars($ult, ENT_QUOTES, 'UTF-8'); ?></span>
                  </li>
                <?php endif; ?>

                <li>
                  <hr class="dropdown-divider">
                </li>

                <!-- Acciones -->
                <li>
                  <a href="<?php echo BASE_URL; ?>perfil" class="dropdown-item" role="menuitem">
                    <i class="bi bi-person me-2"></i> Perfil
                  </a>
                </li>
                <li>
                  <a href="<?php echo BASE_URL; ?>perfil/password" class="dropdown-item" role="menuitem">
                    <i class="bi bi-shield-lock me-2"></i> Cambiar contraseña
                  </a>
                </li>

                <li>
                  <hr class="dropdown-divider">
                </li>

                <!-- Logout por POST con CSRF -->
                <li class="px-3 py-2">
                  <form action="<?php echo BASE_URL; ?>index/cerrar" method="post" class="m-0 p-0">
                    <input type="hidden" name="csrf_token"
                      value="<?php echo htmlspecialchars(Session::get('tokencsrf') ?: '', ENT_QUOTES, 'UTF-8'); ?>">
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
      <!-- ========== HEADER (NAVBAR SUPERIOR) FIN ========== -->



      <!-- ========== SIDEBAR (MENÚ LATERAL) INICIO ========== -->

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"> <!--begin::Sidebar Brand-->
        <div class="sidebar-brand"> <!--begin::Brand Link-->
          <a href="<?php echo BASE_URL; ?>" class="brand-link"> <!--begin::Brand Image-->
            <!--end::Brand Image--> <!--begin::Brand Text-->
            <span class="brand-text fw-light">CRM EMP</span> <!--end::Brand Text--> </a> <!--end::Brand Link-->
        </div> <!--end::Sidebar Brand--> <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2"> <!--begin::Sidebar Menu-->
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <?php if (Session::get('rol') == ADMINISTRADOR) : ?>
                <li class="nav-item">
                  <a href="<?php echo BASE_URL . 'admin/tabla_test'; ?>" class="nav-link"> <i class="nav-icon bi bi-palette"></i>
                    <p>Tabla Test</p>
                  </a>
                </li>
              <?php endif; ?>
              <li class="nav-item menu-open"> <a href="#" class="nav-link active"> <i class="nav-icon bi bi-speedometer"></i>
                  <p>
                    Dashboard
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item"> <a href="<?php echo BASE_URL; ?>" class="nav-link active"> <i class="nav-icon bi bi-circle"></i>
                      <p>Dashboard v1</p>
                    </a> </li>
                  <li class="nav-item"> <a href="./index2.html" class="nav-link"> <i class="nav-icon bi bi-circle"></i>
                      <p>Dashboard v2</p>
                    </a> </li>
                  <li class="nav-item"> <a href="./index3.html" class="nav-link"> <i class="nav-icon bi bi-circle"></i>
                      <p>Dashboard v3</p>
                    </a> </li>
                </ul>
              </li>

              <!-- ===== MÓDULO: USUARIOS===== -->
              <?php if (Session::get('rol') == ADMINISTRADOR || (Session::get('permisos')['wlusuarios.ver'] ?? false)) : ?>
                <li class="nav-item">
                  <a href="<?php echo BASE_URL . 'admin/wlusuarios'; ?>" class="nav-link">
                    <i class="nav-icon bi bi-speedometer"></i>
                    <p>Usuarios</p>
                  </a>
                </li>
              <?php endif; ?>
              <!-- ===== FIN MÓDULO: USUARIOS ===== -->

               <!-- ===== MÓDULO: INDICADORES===== -->
              <?php if (Session::get('rol') == ADMINISTRADOR || (Session::get('permisos')['wlindicadores.ver'] ?? false)) : ?>
                <li class="nav-item">
                  <a href="<?php echo BASE_URL . 'admin/wlindicadores'; ?>" class="nav-link">
                    <i class="nav-icon bi bi-speedometer"></i>
                    <p>Indicadores</p>
                  </a>
                </li>
              <?php endif; ?>
              <!-- ===== FIN MÓDULO: INDICADORES ===== -->

              <!-- ===== MÓDULO: INVENTARIO SIMS ===== -->
              <?php if (Session::get('rol') == ADMINISTRADOR || (Session::get('permisos')['wlsims.ver'] ?? false)) : ?>
                <li class="nav-item">
                  <a href="<?php echo BASE_URL . 'admin/wlsims'; ?>" class="nav-link">
                    <i class="nav-icon bi bi-sim"></i>
                    <p>Inventario SIMS</p>
                  </a>
                </li>
              <?php endif; ?>
              <!-- ===== FIN MÓDULO: INVENTARIO SIMS ===== -->

              <!-- ===== MÓDULO: RECARGAS ===== -->
              <?php if (Session::get('rol') == ADMINISTRADOR || (Session::get('permisos')['wlrecargas.ver'] ?? false)) : ?>
                <li class="nav-item">
                  <a href="<?php echo BASE_URL . 'admin/wlrecargas'; ?>" class="nav-link">
                    <i class="nav-icon bi bi-phone-fill"></i>
                    <p>Recargas</p>
                  </a>
                </li>
              <?php endif; ?>
              <!-- ===== FIN MÓDULO: RECARGAS ===== -->

              <!-- ===== MÓDULO: WLPLANES ===== -->
              <?php if (Session::get('rol') == ADMINISTRADOR || (Session::get('permisos')['wlplanes.ver'] ?? false)) : ?>
                <li class="nav-item">
                  <a href="<?= htmlspecialchars(BASE_URL . 'admin/wlplanes', ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="nav-icon fas fa-mobile-alt"></i>
                    <p>Planes de Telefonía</p>
                  </a>
                </li>
              <?php endif; ?>
              <!-- ===== FIN MÓDULO: WLPLANES ===== -->



            </ul> <!--end::Sidebar Menu-->
          </nav>
        </div> <!--end::Sidebar Wrapper-->
      </aside> <!--end::Sidebar--> <!--begin::App Main-->

      <!-- ========== SIDEBAR (MENÚ LATERAL) FIN ========== -->

      <!-- A partir de aquí, en la vista se imprime el <main class="app-main"> ... -->

    <?php endif; ?>