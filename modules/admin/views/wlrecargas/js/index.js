/* =======================================================================
   WLRECARGAS - index.js (FIX)
   - Fix Bootstrap Modal (usar elemento DOM, no selector)
   - Agregar csrf_token a crear/editar
   - Estándar dataType:'json' donde aplica (evitar parseJSON manual)
   - Pequeños endurecimientos de null-safety
   ======================================================================= */

/* globals $, bootstrap, Swal, alertify, BASE_URL */
app.globales = app.globales || {};
app.globales.cv_temporal = 0;
app.core = app.core || {};

(function () {
  /* =========================
     Helpers de formato/fecha
     ========================= */
  const fmtFecha = (iso) => {
    if (!iso) return "";
    const d = new Date(iso.toString().replace(" ", "T"));
    if (isNaN(d.getTime())) return iso;
    const p = (n) => (n < 10 ? "0" + n : n);
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  };

  const fmtMoney = (v) => {
    if (v === null || v === undefined || v === "") return "";
    const n = parseFloat(String(v).replace(/[^\d.-]/g, ""));
    if (isNaN(n)) return v;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const canalBadge = (canal) => {
    const c = (canal || "").toString().toUpperCase();
    const map = { NORMAL: "bg-primary", BANCARIO: "bg-success", WEB: "bg-info" };
    const cls = map[c] || "bg-secondary";
    return `<span class="badge ${cls}">${c || "—"}</span>`;
  };

  // Fuerza exactamente 10 dígitos
  const enforceTenDigits = (selector) => {
    $(document).on("input", selector, function () {
      this.value = this.value.replace(/\D+/g, "");
      if (this.value.length > 10) this.value = this.value.slice(0, 10);
    });
  };

  // Rango del mes actual
  const getMonthRange = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const start = new Date(y, m, 1);
    const end = new Date(y, m + 1, 0);
    const p = (n) => (n < 10 ? "0" + n : n);
    return {
      desde: `${start.getFullYear()}-${p(start.getMonth() + 1)}-${p(start.getDate())}`,
      hasta: `${end.getFullYear()}-${p(end.getMonth() + 1)}-${p(end.getDate())}`,
    };
  };

  /* ==========================================
     Filtro custom DataTables (front-end only)
     ========================================== */
  let filtros = { numero: "", desde: "", hasta: "", canal: "" };
  $.fn.dataTable.ext.search.push(function (_settings, data) {
    // Columnas: 0 idx, 1 número, 2 plan, 3 fecha, 4 saldo, 5 sim, 6 iccid, 7 canal, 8 acciones
    const numero = (data[1] || "").replace(/\s/g, "");
    const fecha  = data[3] || "";
    const canal  = (data[7] || "").replace(/<.*?>/g, "").toUpperCase();

    if (filtros.numero && !numero.includes(filtros.numero)) return false;
    if (filtros.canal && canal !== filtros.canal.toUpperCase()) return false;

    if (filtros.desde || filtros.hasta) {
      const f = fecha.slice(0, 10); // YYYY-MM-DD
      if (filtros.desde && f < filtros.desde) return false;
      if (filtros.hasta && f > filtros.hasta) return false;
    }
    return true;
  });

  /* =======================================
     RESET TOTAL de DataTables + filtros UI
     ======================================= */
  const DEFAULT_DT_ORDER = [[3, 'desc']];
  const DEFAULT_DT_LEN   = 25;

  const resetDataTable = (dt) => {
    $('div.dataTables_filter input[type="search"]').val('').trigger('input');
    dt.search('');

    $('#recursos-table thead th input, #recursos-table tfoot th input').each(function () {
      $(this).val('');
    });
    dt.columns().every(function () { this.search(''); });

    dt.order(DEFAULT_DT_ORDER);
    dt.page.len(DEFAULT_DT_LEN);
    dt.page('first');

    $('#recursos-table tr.selected').removeClass('selected');
    dt.draw(false);
  };

  const resetFiltersAndTable = () => {
    const frm = document.getElementById('frm_filtros');
    if (frm) frm.reset();
    filtros = { numero: "", desde: "", hasta: "", canal: "" };
    const dt = $('#recursos-table').DataTable();
    resetDataTable(dt);
  };

  /* ============================
     Módulo principal de la vista
     ============================ */
  app.core.index = {
    _tabla: null,
    _dataRaw: [],

    _refrescarKPIs: function (rango) {
      let rows = this._dataRaw;

      if (rango && (rango.desde || rango.hasta)) {
        rows = rows.filter((r) => {
          const f = fmtFecha(r.fecha_recarga).slice(0, 10);
          if (rango.desde && f < rango.desde) return false;
          if (rango.hasta && f > rango.hasta) return false;
          return true;
        });
      }

      const total = rows.length;

      let sum = 0;
      rows.forEach((r) => {
        const v = parseFloat(String(r.saldo_consumido ?? "").replace(/[^\d.-]/g, ""));
        if (!isNaN(v)) sum += v;
      });

      const unicos = new Set(rows.map((r) => r.numero_telefono).filter(Boolean)).size;

      let maxDate = "";
      rows.forEach((r) => {
        const f = fmtFecha(r.fecha_recarga);
        if (!maxDate || f > maxDate) maxDate = f;
      });

      $("#kpi_total").text(Number(total || 0).toLocaleString());
      $("#kpi_consumido").text(fmtMoney(sum));
      $("#kpi_unicos").text(Number(unicos || 0).toLocaleString());
      $("#kpi_ultima").text(maxDate || "—");
    },

    _pintarTabla: function (rows) {
      const dt = this._tabla;
      dt.clear();

      rows.forEach((r, idx) => {
        const acciones =
          '<button data-bs-toggle="tooltip" title="Editar" type="button" data-clave="' +
          r.cv_recarga +
          '" data-accion="editar" class="accion_user btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></button>' +
          '<button data-bs-toggle="tooltip" title="Eliminar" type="button" data-clave="' +
          r.cv_recarga +
          '" data-accion="eliminar" class="accion_user btn btn-sm btn-outline-danger"><i class="fas fa-times-circle"></i></button>';

        dt.row.add([
          idx + 1,
          r.numero_telefono || "",
          r.cv_plan || "",
          fmtFecha(r.fecha_recarga) || "",
          fmtMoney(r.saldo_consumido) || "",
          r.cv_sim || "",
          r.iccid || "",
          canalBadge(r.canal_venta),
          acciones,
        ]);
      });

      dt.draw(false);

      // Tooltips
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        const t = bootstrap.Tooltip.getInstance(el);
        if (t) t.dispose();
        new bootstrap.Tooltip(el);
      });
    },

    /* =========================
       MODELO: peticiones AJAX
       ========================= */
    modelo: function () {
      const self = this;

      const llenar_tabla = function () {
        return $.ajax({
          url: BASE_URL + "admin/wlrecargas/recargas_list/",
          dataType: "json",
          success: function (s) {
            self._dataRaw = Array.isArray(s) ? s : [];
            self._pintarTabla(self._dataRaw);

            const rangoMes = getMonthRange();
            $("#kpi_desde").val(rangoMes.desde);
            $("#kpi_hasta").val(rangoMes.hasta);
            self._refrescarKPIs(rangoMes);
          },
        });
      };

      const nuevo = function () {
        $("#frm_nuevo .spinner-border").show();
        const data = new FormData();
        // token CSRF si existe
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);

        $("#frm_nuevo").serializeArray().forEach((i) => data.append(i.name, i.value));
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlrecargas/registrar_recarga",
          data: data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Guardado correctamente");
              self.modelo().llenar_tabla();

              // Cerrar modal crear (usar elemento DOM)
              const modalCrearEl = document.getElementById("modal-crear");
              if (modalCrearEl) {
                const modalCrear = bootstrap.Modal.getOrCreateInstance(modalCrearEl);
                modalCrear.hide();
              }
              $("#frm_nuevo")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "Ocurrió un error.");
            }
          },
          error: function (xhr) {
            alertify.error("Error al guardar (crear).");
            // opcional: console.error(xhr?.responseText || xhr.statusText);
          },
          complete: function () {
            $("#frm_nuevo .spinner-border").hide();
          },
        });
      };

      const eliminar = function (clave) {
        const token = $('input[name="csrf_token"]').first().val() || "";
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlrecargas/eliminar_recarga",
          data: { clave: String(clave || ""), csrf_token: token },
          success: function () {
            self.modelo().llenar_tabla();
          },
        });
      };

      const datos_show = function (clave) {
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlrecargas/datos_show_recarga/",
          data: { clave: String(clave || "") },
          dataType: "json",
          success: function (obj) {
            const r = Array.isArray(obj) ? obj[0] : obj;
            if (r) {
              $("#cv_recarga_editar").val(r.cv_recarga ?? "");
              $("#numero_telefono_editar").val(r.numero_telefono ?? "");
              $("#cv_plan_editar").val(r.cv_plan ?? "");
              $("#saldo_consumido_editar").val(r.saldo_consumido ?? "");
              $("#canal_venta_editar").val(r.canal_venta ?? "");
              $("#cv_sim_editar").val(r.cv_sim ?? "");
              $("#iccid_editar").val(r.iccid ?? "");

              // Abrir modal editar (usar elemento DOM)
              const modalEditarEl = document.getElementById("modal-editar");
              if (modalEditarEl) {
                const modalEditar = bootstrap.Modal.getOrCreateInstance(modalEditarEl);
                modalEditar.show();
              }
            } else {
              alertify.error("No se encontraron datos para la clave.");
            }
          },
          error: function () {
            alertify.error("Error al cargar datos para edición.");
          },
        });
      };

      const editar = function () {
        $("#frm_editar .spinner-border").show();
        const data = new FormData();
        data.append("clave", String(app.globales.cv_temporal || ""));

        // token CSRF si existe
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);

        $("#frm_editar").serializeArray().forEach((i) => data.append(i.name, i.value));
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlrecargas/editar_recarga",
          data: data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Actualizado correctamente");
              self.modelo().llenar_tabla();

              // Cerrar modal editar (usar elemento DOM)
              const modalEditarEl = document.getElementById("modal-editar");
              if (modalEditarEl) {
                const modalEditar = bootstrap.Modal.getOrCreateInstance(modalEditarEl);
                modalEditar.hide();
              }
              $("#frm_editar")[0].reset();
              app.globales.cv_temporal = 0;
            } else {
              alertify.error((obj && obj.mensaje) || "Ocurrió un error.");
            }
          },
          error: function () {
            alertify.error("Error al guardar (editar).");
          },
          complete: function () {
            $("#frm_editar .spinner-border").hide();
          },
        });
      };

      return { llenar_tabla, nuevo, datos_show, editar, eliminar };
    },

    /* =================
       CONTROLADOR (UI)
       ================= */
    controlador: function () {
      const self = this;

      // inputs con 10 dígitos exactos
      enforceTenDigits("#f_numero, #numero_telefono, #numero_telefono_editar");

      // DataTable
      this._tabla = $("#recursos-table").DataTable({
        lengthChange: false,
        responsive: { details: { type: "inline", target: "tr" } },
        buttons: ["copy", "excel", "pdf", "colvis"],
        pageLength: DEFAULT_DT_LEN,
        order: DEFAULT_DT_ORDER,
        language: {
          buttons: { colvis: "Ocultar columna", copy: "Copiar", excel: "Excel", pdf: "PDF" },
          sProcessing: "Procesando...",
          sLengthMenu: "Mostrar _MENU_ registros",
          sZeroRecords: "No se encontraron resultados",
          sEmptyTable: "Ningún dato disponible en esta tabla",
          sInfo: "Mostrando _START_–_END_ de _TOTAL_",
          sInfoEmpty: "Mostrando 0–0 de 0",
          sInfoFiltered: "(filtrado de _MAX_)",
          sSearch: "Buscar:",
          oPaginate: { sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior" },
        },
        columnDefs: [
          { targets: 0, className: "text-muted", width: 32 },
          { targets: 4, className: "text-end" },
          { targets: 7, className: "text-center" },
          { targets: -1, className: "text-center dt-actions", orderable: false, searchable: false },
        ],
      });
      this._tabla.buttons().container().appendTo("#recursos-table_wrapper .col-md-6:eq(0)");

      // selección UX
      $("#recursos-table tbody").on("click", "tr", function () {
        const $tr = $(this);
        const table = $("#recursos-table").DataTable();
        if ($tr.hasClass("selected")) $tr.removeClass("selected");
        else {
          table.$("tr.selected").removeClass("selected");
          $tr.addClass("selected");
        }
      });

      // carga inicial
      this.modelo().llenar_tabla();

      // submit CREAR (validación 10 dígitos)
      $("#frm_nuevo").on("submit", function (e) {
        e.preventDefault();
        const num = ($("#numero_telefono").val() || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#numero_telefono").focus();
          return;
        }
        app.core.index.modelo().nuevo();
      });

      // submit EDITAR (validación 10 dígitos)
      $("#frm_editar").on("submit", function (e) {
        e.preventDefault();
        const num = ($("#numero_telefono_editar").val() || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#numero_telefono_editar").focus();
          return;
        }
        app.core.index.modelo().editar();
      });

      // acciones por fila
      $("#recursos-table").on("click", ".accion_user", function () {
        const accion = $(this).data("accion");
        const clave  = $(this).data("clave");
        app.globales.cv_temporal = clave;

        if (accion === "eliminar") {
          Swal.fire({
            title: "¿Deseas eliminar el registro?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#11c46e",
            cancelButtonColor: "#f46a6a",
            cancelButtonText: "Cancelar",
            confirmButtonText: "¡Sí!",
          }).then(function (result) {
            if (result.isConfirmed) {
              const table = $("#recursos-table").DataTable();
              table.row(".selected").remove().draw(false);
              app.core.index.modelo().eliminar(clave);
              Swal.fire("Eliminado", "Se eliminó correctamente.", "success");
            }
          });
        }

        if (accion === "editar") {
          $(".spinner-border").hide();
          app.core.index.modelo().datos_show(clave);
        }
      });

      // modal nuevo -> reset limpio
      $(".extend-new-recurso").on("click", function () {
        $("#frm_nuevo")[0].reset();
        $(".spinner-border").hide();
      });

      /* ==========================
         Filtros de la tabla (front)
         ========================== */
      const aplicarFiltros = () => {
        const num   = ($("#f_numero").val() || "").replace(/\D+/g, "");
        const desde = $("#f_desde").val() || "";
        const hasta = $("#f_hasta").val() || "";
        const canal = $("#f_canal").val() || "";
        filtros = { numero: num, desde, hasta, canal };
        self._tabla.draw();
      };
      $("#btn_aplicar_filtros").on("click", aplicarFiltros);

      // Enter en input número aplica filtros
      $("#f_numero").on("keypress", function (e) {
        if (e.which === 13) {
          e.preventDefault();
          aplicarFiltros();
        }
      });

      /* =====================================
         Control de rango de fechas para KPIs
         ===================================== */
      $("#btn_kpi_aplicar").on("click", function () {
        const desde = $("#kpi_desde").val() || "";
        const hasta = $("#kpi_hasta").val() || "";
        self._refrescarKPIs({ desde, hasta });
      });

      $("#btn_kpi_mes").on("click", function () {
        const r = getMonthRange();
        $("#kpi_desde").val(r.desde);
        $("#kpi_hasta").val(r.hasta);
        self._refrescarKPIs(r);
      });

      /* ===========================
         Botón RECARGAR (completo)
         =========================== */
      $("#btn_recargar").on("click", () => {
        resetFiltersAndTable();
        self.modelo().llenar_tabla();
        alertify.message("Datos actualizados y tabla reiniciada");
      });

      // Tooltips globales
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    },
  };

  $(document).ready(function () {
    app.core.index.controlador();
  });
})();
