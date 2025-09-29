/* =======================================================================
   WLPLANES - index.js (Bootstrap 5 + DataTables)
   ======================================================================= */

/* globals $, bootstrap, Swal, alertify, BASE_URL */
app.globales = app.globales || {};
app.globales.cv_plan_temp = 0;
app.core = app.core || {};

(function () {
  // ---- Helpers ----
  const fmtMoney = (v) => {
    if (v === null || v === undefined || v === "") return "";
    const n = parseFloat(String(v).replace(/[^\d.-]/g, ""));
    if (isNaN(n)) return v;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const badgeEstatus = (v) => {
    const n = Number(v);
    const txt = n === 1 ? "Activo" : "Inactivo";
    const cls = n === 1 ? "bg-success" : "bg-secondary";
    return `<span class="badge ${cls}">${txt}</span>`;
  };

  const tipoTxt = (t) => {
    const n = Number(t);
    if (n === 1) return "B2B";
    if (n === 3) return "B2C";
    return "Otro";
  };

  const actRecTxt = (v) => Number(v) === 1 ? "Activación" : "Recarga";

  // ---- Filtros DataTables (front) ----
  let filtros = {
    nombre: "", precioMin: "", precioMax: "",
    tipo: "", estatus: "", vigencia: "",
    compartir: "", ticket: "", rrss: ""
  };

  $.fn.dataTable.ext.search.push(function (_settings, data) {
    // Columnas: 0 #, 1 nombre, 2 precio, 3 vigencia, 4 datos, 5 voz, 6 sms, 7 rrss,
    // 8 tipo, 9 act/rec, 10 estatus, 11 comparte, 12 offeringId, 13 precio_LP, 14 acciones
    const nombre = (data[1] || "").toUpperCase();
    const precio = parseFloat(String(data[2]).replace(/[^\d.-]/g, "")) || 0;
    const vigencia = (data[3] || "").replace(/\D+/g, "");
    const rrss = (data[7] || "").toUpperCase();
    const tipo = (data[8] || "").toUpperCase();
    const estatus = (data[10] || "").toUpperCase();
    const comparte = (data[11] || "").toUpperCase();
    const ticket = (data[4] || data[12] || "").toUpperCase(); // backup si cambia orden

    if (filtros.nombre && !nombre.includes(filtros.nombre.toUpperCase())) return false;
    if (filtros.precioMin && precio < parseFloat(filtros.precioMin)) return false;
    if (filtros.precioMax && precio > parseFloat(filtros.precioMax)) return false;
    if (filtros.tipo && tipo !== filtros.tipo.toUpperCase()) return false;
    if (filtros.estatus && estatus !== (Number(filtros.estatus) === 1 ? "ACTIVO" : "INACTIVO")) return false;
    if (filtros.vigencia && vigencia !== String(filtros.vigencia)) return false;
    if (filtros.compartir !== "" && comparte !== (Number(filtros.compartir) === 1 ? "SÍ" : "NO")) return false;
    if (filtros.ticket) {
      const want = filtros.ticket.toUpperCase();
      if (want === "SI" && !/SI|SÍ/.test(ticket)) return false;
      if (want === "NO" && /SI|SÍ/.test(ticket)) return false;
    }
    if (filtros.rrss) {
      const want = filtros.rrss.toUpperCase();
      if (want === "ILIMITADAS" && !/ILIMIT/.test(rrss)) return false;
      if (want === "LIMITADAS" && (/ILIMIT/.test(rrss) || /NO/.test(rrss))) return false;
      if (want === "NO" && !/NO/.test(rrss)) return false;
    }
    return true;
  });

  const DEFAULT_DT_ORDER = [[2, 'asc']];
  const DEFAULT_DT_LEN = 25;

  const resetDataTable = (dt) => {
    $('div.dataTables_filter input[type="search"]').val('').trigger('input');
    dt.search('');
    $('#planes-table thead th input, #planes-table tfoot th input').val('');
    dt.columns().every(function () { this.search(''); });
    dt.order(DEFAULT_DT_ORDER);
    dt.page.len(DEFAULT_DT_LEN);
    dt.page('first');
    $('#planes-table tr.selected').removeClass('selected');
    dt.draw(false);
  };

  const resetFiltersAndTable = () => {
    const frm1 = document.getElementById('frm_toolbar');
    if (frm1) frm1.reset();
    const frm2 = document.getElementById('frm_filtros');
    if (frm2) frm2.reset();
    filtros = { nombre: "", precioMin: "", precioMax: "", tipo: "", estatus: "", vigencia: "", compartir: "", ticket: "", rrss: "" };
    resetDataTable($('#planes-table').DataTable());
  };

  // ---- Módulo principal ----
  app.core.index = {
    _tabla: null,
    _dataRaw: [],

    _refrescarKPIs: function () {
      const rows = this._dataRaw;
      const total = rows.length;
      const activos = rows.filter(r => Number(r.estatus_paquete) === 1).length;

      let suma = 0, c = 0, rrssIlimit = 0;
      rows.forEach(r => {
        const p = parseFloat(r.precio ?? 0);
        if (!isNaN(p)) { suma += p; c++; }
        if ((r.rrss || '').toUpperCase().includes('ILIMIT')) rrssIlimit++;
      });
      const prom = c ? (suma / c) : 0;

      $("#kpi_total").text(total.toLocaleString());
      $("#kpi_activos").text(activos.toLocaleString());
      $("#kpi_promedio").text(fmtMoney(prom));
      $("#kpi_rrss").text(rrssIlimit.toLocaleString());
    },

    _pintarTabla: function (rows) {
      const dt = this._tabla;
      dt.clear();

      rows.forEach((r, idx) => {
        const acciones =
          '<button data-bs-toggle="tooltip" title="Editar" type="button" data-clave="' + r.cv_plan +
          '" data-accion="editar" class="accion_user btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></button>' +
          '<button data-bs-toggle="tooltip" title="Eliminar" type="button" data-clave="' + r.cv_plan +
          '" data-accion="eliminar" class="accion_user btn btn-sm btn-outline-danger"><i class="fas fa-times-circle"></i></button>';

        dt.row.add([
          idx + 1,
          r.nombre_comercial || "",
          fmtMoney(r.precio) || "",
          r.vigencia || "",
          r.datos || "",
          r.voz || "",
          r.sms || "",
          r.rrss || "",
          tipoTxt(r.tipo_producto),
          actRecTxt(r.primar_secundaria),
          badgeEstatus(r.estatus_paquete),
          Number(r.comparte_datos) === 1 ? "Sí" : "No",
          r.offeringId || "",
          r.precio_likephone_wl || "",
          acciones
        ]);
      });

      dt.draw(false);
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        const t = bootstrap.Tooltip.getInstance(el);
        if (t) t.dispose();
        new bootstrap.Tooltip(el);
      });
    },

    // ---- Peticiones AJAX ----
    modelo: function () {
      const self = this;

      const llenar_tabla = function () {
        return $.ajax({
          url: BASE_URL + "admin/wlplanes/planes_list/",
          dataType: "json",
          success: function (s) {
            self._dataRaw = Array.isArray(s) ? s : [];
            self._pintarTabla(self._dataRaw);
            self._refrescarKPIs();
          },
          error: function (xhr) {
            console.error("planes_list error:", xhr.responseText);
            alertify.error("No se pudo cargar el listado.");
          }
        });
      };

      const nuevo = function () {
        $("#frm_nuevo .spinner-border").show();
        const data = new FormData();
        $("#frm_nuevo").serializeArray().forEach((i) => data.append(i.name, i.value));
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlplanes/registrar_plan",
          data: data,
          contentType: false, cache: false, processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Creado");
              app.core.index.modelo().llenar_tabla();
              bootstrap.Modal.getOrCreateInstance(document.getElementById("modal-crear")).hide();
              $("#frm_nuevo")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "Ocurrió un error.");
            }
          },
          error: function (xhr) {
            console.error("registrar_plan error:", xhr.responseText);
            alertify.error("No se pudo guardar.");
          },
          complete: function () { $("#frm_nuevo .spinner-border").hide(); }
        });
      };

      const datos_show = function (clave) {
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlplanes/datos_show_plan/",
          data: { clave },
          dataType: "json",
          success: function (o) {
            if (o && o.length) {
              const x = o[0];
              $("#cv_plan_editar").val(x.cv_plan);
              $("#nombre_comercial_editar").val(x.nombre_comercial);
              $("#precio_editar").val(x.precio);
              $("#precio_likephone_wl_editar").val(x.precio_likephone_wl);
              $("#tipo_producto_editar").val(x.tipo_producto);
              $("#primar_secundaria_editar").val(x.primar_secundaria);
              $("#vigencia_editar").val(x.vigencia);
              $("#offeringId_editar").val(x.offeringId);
              $("#datos_editar").val(x.datos);
              $("#voz_editar").val(x.voz);
              $("#sms_editar").val(x.sms);
              $("#rrss_editar").val(x.rrss);
              $("#ticket_editar").val(x.ticket);
              $("#comparte_datos_editar").val(x.comparte_datos);
              $("#estatus_paquete_editar").val(x.estatus_paquete);
              $("#imagen_web1_editar").val(x.imagen_web1);
              $("#imagen_web2_editar").val(x.imagen_web2);
              $("#imagen_movil1_editar").val(x.imagen_movil1);
              $("#imagen_movil2_editar").val(x.imagen_movil2);

              // **Abrir modal usando elemento DOM (Bootstrap 5)**
              bootstrap.Modal.getOrCreateInstance(document.getElementById("modal-editar")).show();
            } else {
              alertify.error("Registro no encontrado.");
            }
          },
          error: function (xhr) {
            console.error("datos_show_plan error:", xhr.responseText);
            alertify.error("No se pudo cargar el registro.");
          }
        });
      };

      const editar = function () {
        $("#frm_editar .spinner-border").show();
        const data = new FormData();
        data.append("clave", app.globales.cv_plan_temp);
        $("#frm_editar").serializeArray().forEach((i) => data.append(i.name, i.value));
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlplanes/editar_plan",
          data: data,
          contentType: false, cache: false, processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Actualizado");
              app.core.index.modelo().llenar_tabla();
              bootstrap.Modal.getOrCreateInstance(document.getElementById("modal-editar")).hide();
              $("#frm_editar")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "Ocurrió un error.");
            }
          },
          error: function (xhr) {
            console.error("editar_plan error:", xhr.responseText);
            alertify.error("No se pudo actualizar.");
          },
          complete: function () { $("#frm_editar .spinner-border").hide(); }
        });
      };

      const eliminar = function (clave) {
        const token = $('input[name="csrf_token"]').first().val() || "";
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlplanes/eliminar_plan",
          data: { clave, csrf_token: token },
          dataType: "json",
          success: function () { app.core.index.modelo().llenar_tabla(); },
          error: function (xhr) { console.error("eliminar_plan error:", xhr.responseText); }
        });
      };

      return { llenar_tabla, nuevo, datos_show, editar, eliminar };
    },

    // ---- Controlador (UI) ----
    controlador: function () {
      const self = this;

      this._tabla = $("#planes-table").DataTable({
        lengthChange: false,
        responsive: { details: { type: "inline", target: "tr" } },
        buttons: ["copy", "excel", "pdf", "colvis"],
        pageLength: DEFAULT_DT_LEN,
        order: DEFAULT_DT_ORDER,
        language: {
          buttons: { colvis: "Ocultar columna", copy: "Copiar", excel: "Excel", pdf: "PDF" },
          sProcessing: "Procesando...", sLengthMenu: "Mostrar _MENU_ registros",
          sZeroRecords: "No se encontraron resultados", sEmptyTable: "Ningún dato disponible en esta tabla",
          sInfo: "Mostrando _START_–_END_ de _TOTAL_", sInfoEmpty: "Mostrando 0–0 de 0",
          sInfoFiltered: "(filtrado de _MAX_)", sSearch: "Buscar:",
          oPaginate: { sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior" }
        },
        columnDefs: [
          { targets: 0, className: "text-muted", width: 32 },
          { targets: 2, className: "text-end" },
          { targets: [10], className: "text-center" },
          { targets: -1, className: "text-center dt-actions", orderable: false, searchable: false },
        ],
      });
      this._tabla.buttons().container().appendTo("#planes-table_wrapper .col-md-6:eq(0)");

      // selección UX
      $("#planes-table tbody").on("click", "tr", function () {
        const $tr = $(this);
        const table = $("#planes-table").DataTable();
        if ($tr.hasClass("selected")) $tr.removeClass("selected");
        else { table.$("tr.selected").removeClass("selected"); $tr.addClass("selected"); }
      });

      // carga inicial
      this.modelo().llenar_tabla();

      // Crear
      $("#frm_nuevo").on("submit", function (e) {
        e.preventDefault();
        const nombre = ($("#nombre_comercial").val() || "").trim();
        if (!nombre) { alertify.error("Nombre comercial es requerido."); return; }
        app.core.index.modelo().nuevo();
      });

      // Editar
      $("#frm_editar").on("submit", function (e) {
        e.preventDefault();
        const nombre = ($("#nombre_comercial_editar").val() || "").trim();
        if (!nombre) { alertify.error("Nombre comercial es requerido."); return; }
        app.core.index.modelo().editar();
      });

      // Acciones
      $("#planes-table").on("click", ".accion_user", function () {
        const accion = $(this).data("accion");
        const clave = $(this).data("clave");
        app.globales.cv_plan_temp = clave;

        if (accion === "eliminar") {
          Swal.fire({
            title: "¿Deseas eliminar el plan?",
            icon: "warning", showCancelButton: true,
            confirmButtonColor: "#11c46e", cancelButtonColor: "#f46a6a",
            cancelButtonText: "Cancelar", confirmButtonText: "¡Sí!"
          }).then(function (r) {
            if (r.isConfirmed) {
              const table = $("#planes-table").DataTable();
              table.row(".selected").remove().draw(false);
              app.core.index.modelo().eliminar(clave);
              Swal.fire("Eliminado", "Plan eliminado correctamente.", "success");
            }
          });
        }
        if (accion === "editar") {
          $(".spinner-border").hide();
          app.core.index.modelo().datos_show(clave);
        }
      });

      // Modal nuevo -> reset
      $(".extend-new-recurso").on("click", function () {
        $("#frm_nuevo")[0].reset();
        $(".spinner-border").hide();
      });

      // Filtros (toolbar + avanzados)
      const aplicarFiltros = () => {
        filtros = {
          nombre: $("#f_nombre").val() || "",
          precioMin: $("#price_min").val() || "",
          precioMax: $("#price_max").val() || "",
          tipo: (function () {
            const v = $("#f_tipo_producto").val() || "";
            if (v === "1") return "B2B";
            if (v === "3") return "B2C";
            if (v === "2") return "OTRO";
            return "";
          })(),
          estatus: $("#f_estatus").val() || "",
          vigencia: $("#f_vigencia").val() || "",
          compartir: $("#f_compartir").val() || "",
          ticket: $("#f_ticket").val() || "",
          rrss: $("#f_rrss").val() || ""
        };
        self._tabla.draw();
        self._refrescarKPIs();
      };

      $("#btn_toolbar_aplicar, #btn_aplicar_filtros").on("click", aplicarFiltros);
      $("#btn_toolbar_limpiar, #btn_recargar").on("click", function () {
        resetFiltersAndTable();
        app.core.index.modelo().llenar_tabla();
        alertify.message("Datos actualizados y filtros reiniciados");
      });

      // Tooltips globales
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    },
  };

  $(document).ready(function () { app.core.index.controlador(); });
})();
