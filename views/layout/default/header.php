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



        <!-- Librerias de complemento para las vistas Fin -->

    </head> <!--end::Head--> <!--begin::Body-->

    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary"> <!--begin::App Wrapper-->
        <div class="app-wrapper"> <!--begin::Header-->
            <nav class="app-header navbar navbar-expand bg-body"> <!--begin::Container-->
                <div class="container-fluid"> <!--begin::Start Navbar Links-->
                    <ul class="navbar-nav">
                        <li class="nav-item"> <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"> <i class="bi bi-list"></i> </a> </li>
                        
                    </ul> <!--end::Start Navbar Links--> <!--begin::End Navbar Links-->
                    <ul class="navbar-nav ms-auto"> <!--begin::Navbar Search-->
                        
                        <li class="nav-item dropdown user-menu"> <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"> <img src="<?php echo BASE_URL; ?>public/img_crm/AdminLTELogo.png" class="user-image rounded-circle shadow" alt="User Image"> <span class="d-none d-md-inline">Alexander Pierce</span> </a>
                            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end"> <!--begin::User Image-->
                               
                                <li class="user-footer"> <a href="<?php echo BASE_URL; ?>index/cerrar" class="btn btn-default btn-flat float-end">Salir</a> </li> <!--end::Menu Footer-->
                            </ul>
                        </li> <!--end::User Menu Dropdown-->
                    </ul> <!--end::End Navbar Links-->
                </div> <!--end::Container-->
            </nav> <!--end::Header--> <!--begin::Sidebar-->

            <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"> <!--begin::Sidebar Brand-->
                <div class="sidebar-brand"> <!--begin::Brand Link--> 
                    <a href="<?php echo BASE_URL; ?>" class="brand-link"> <!--begin::Brand Image--> 
                        <!--end::Brand Image--> <!--begin::Brand Text--> 
                        <span class="brand-text fw-light">CRM EMP</span> <!--end::Brand Text--> </a> <!--end::Brand Link--> </div> <!--end::Sidebar Brand--> <!--begin::Sidebar Wrapper-->
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
                            
                        </ul> <!--end::Sidebar Menu-->
                    </nav>
                </div> <!--end::Sidebar Wrapper-->
            </aside> <!--end::Sidebar--> <!--begin::App Main-->

        <?php endif; ?>