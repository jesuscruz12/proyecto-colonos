<?php
/**
 * TAKTIK — Footer layout (PROD)
 * - Cierra wrapper
 * - Carga librerías (jQuery, Bootstrap, AdminLTE, DataTables, SweetAlert2, Alertify, Parsley)
 * - Unifica BASE_URL + CSRF_TOKEN global
 * - Carga JS por vista desde $_layoutParams['js']
 */
?>

<?php if (Session::get('autenticado')): ?>
    </div><!-- /.app-wrapper -->

    <footer class="app-footer">
      <strong>
        Copyright &copy; <?= date('Y'); ?>
        <span class="ms-1"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></span>.
      </strong>
      <span class="ms-2">All rights reserved.</span>
    </footer>
<?php endif; ?>

<!-- =========================================================
  LIBRERÍAS BASE
========================================================= -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>

<script src="<?= $_layoutParams['ruta_js']; ?>adminlte.js?v=<?= urlencode(defined('APP_VER') ? (string)APP_VER : '1'); ?>"></script>

<!-- =========================================================
  DataTables + Extensiones
========================================================= -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js" crossorigin="anonymous"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/pdfmake.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/vfs_fonts.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js" crossorigin="anonymous"></script>

<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js" crossorigin="anonymous"></script>

<!-- =========================================================
  UX libs
========================================================= -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js" crossorigin="anonymous"></script>

<!-- =========================================================
  Globals unificados
========================================================= -->
<script>
  window.BASE_URL   = window.BASE_URL || <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES); ?>;
  window.CSRF_TOKEN = window.CSRF_TOKEN || <?= json_encode((string)(Session::get('tokencsrf') ?: ''), JSON_UNESCAPED_SLASHES); ?>;

  // Compat: si algún JS viejo usa TOKEN_CSRF
  window.TOKEN_CSRF = window.TOKEN_CSRF || window.CSRF_TOKEN;

  // AjaxSetup para jQuery (si usas $.ajax)
  if (window.jQuery) {
    $.ajaxSetup({
      headers: { "X-CSRF-TOKEN": window.CSRF_TOKEN }
    });
  }
</script>

<!-- =========================================================
  JS GLOBAL (si existe)
========================================================= -->
<script src="<?= BASE_URL; ?>resources/assets/js/app.js?v=<?= urlencode(defined('APP_VER') ? (string)APP_VER : '1'); ?>"></script>

<!-- =========================================================
  JS por vista
========================================================= -->
<?php if (isset($_layoutParams['js']) && is_array($_layoutParams['js']) && count($_layoutParams['js'])): ?>
  <?php foreach ($_layoutParams['js'] as $src): ?>
    <script src="<?= $src; ?>?v=<?= urlencode(defined('APP_VER') ? (string)APP_VER : '1'); ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
