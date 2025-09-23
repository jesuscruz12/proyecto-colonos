(function ($) {
  "use strict";

  // --- Fullscreen helpers (opcional; AdminLTE v4 ya trae su propio toggle si usas data-lte-toggle="fullscreen") ---
  function onFsChange() {
    if (
      !document.fullscreenElement &&
      !document.webkitFullscreenElement &&
      !document.mozFullScreenElement &&
      !document.msFullscreenElement
    ) {
      $("body").removeClass("fullscreen-enable");
    }
  }

  // Si tienes algún botón custom que no usa data-lte-toggle="fullscreen", puedes dejar este handler:
  $('[data-lte-toggle="fullscreen"]').on("click", function (ev) {
    ev.preventDefault();
    $("body").toggleClass("fullscreen-enable");

    const docEl = document.documentElement;
    if (
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement
    ) {
      if (document.exitFullscreen) document.exitFullscreen();
      else if (document.webkitCancelFullScreen)
        document.webkitCancelFullScreen();
      else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
    } else {
      if (docEl.requestFullscreen) docEl.requestFullscreen();
      else if (docEl.webkitRequestFullscreen)
        docEl.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
      else if (docEl.mozRequestFullScreen) docEl.mozRequestFullScreen();
    }
  });

  document.addEventListener("fullscreenchange", onFsChange);
  document.addEventListener("webkitfullscreenchange", onFsChange);
  document.addEventListener("mozfullscreenchange", onFsChange);

  // --- Resaltado de links activos (sidebar + navbar) ---
  (function highlightActiveLinks() {
    var current = window.location.href.split(/[?#]/)[0];

    // Sidebar (estructura AdminLTE v4)
    $(".sidebar-menu a[href]").each(function () {
      var href = this.href.split(/[?#]/)[0];
      if (href === current) {
        var $link = $(this);
        $link.addClass("active");

        // Abre todos los niveles de menús padres
        $link.parents(".nav-treeview").each(function () {
          $(this).closest(".nav-item").addClass("menu-open");
          $(this).prev(".nav-link").addClass("active");
        });

        // Asegurar el nav-item inmediato
        $link.closest(".nav-item").addClass("menu-open");
      }
    });

    // Navbar superior
    $(".navbar-nav a[href]").each(function () {
      var href = this.href.split(/[?#]/)[0];
      if (href === current) {
        var $a = $(this);
        $a.addClass("active");
        $a.parent().addClass("active");
        $a.parents(".dropdown-menu").prev(".nav-link").addClass("active");
      }
    });
  })();

  // --- Dropdowns anidados (si usas submenús personalizados en dropdowns) ---
  $(".dropdown-menu a.dropdown-toggle").on("click", function (e) {
    var $subMenu = $(this).next(".dropdown-menu");
    if (!$subMenu.hasClass("show")) {
      $(this).closest(".dropdown-menu").find(".show").removeClass("show");
    }
    $subMenu.toggleClass("show");
    return false;
  });

  // --- Bootstrap 5 Tooltips/Popovers (data-bs-toggle=...) ---
  (function initBs5Overlays() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (el) {
      return new bootstrap.Tooltip(el);
    });

    // Popovers
    var popoverTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="popover"]')
    );
    popoverTriggerList.map(function (el) {
      return new bootstrap.Popover(el);
    });
  })();

  // --- Right bar (si es parte de tu UI) ---
  $(".right-bar-toggle").on("click", function () {
    $("body").toggleClass("right-bar-enabled");
  });
  $(document).on("click", "body", function (e) {
    if ($(e.target).closest(".right-bar-toggle, .right-bar").length === 0) {
      $("body").removeClass("right-bar-enabled");
    }
  });

  // --- Quitar cualquier rastro de metisMenu de v3 ---
  // if ($.fn && $.fn.metisMenu) { $('#side-menu').metisMenu(); } // -> NO usar en v4

  // --- Opcional: si usabas Waves (ripple), protégelo para que no truene si no está cargado ---
  if (window.Waves && typeof window.Waves.init === "function") {
    window.Waves.init();
  }
})(jQuery);
