/* =======================================================================
   WLPORTABILIDADES - index.js (con estatus reales)
   - Estatus: 1 Pendiente, 2 Numlex, 3 Procesado, 4 Rechazado, 5 Est5, 6 Est6
   - Lápiz SIEMPRE abre modal (detalle/edición)
   - Editable solo si estatus != 2 (Numlex) y != 3 (Procesado)
   - Si estaba en 6, al guardar pasa a 1 (Pendiente) y actualiza fecha_solicitud
   - CSV/TXT con progreso + resumen
   ======================================================================= */

/* globals $, bootstrap, Swal, alertify, BASE_URL */
app.globales = app.globales || {};
app.core = app.core || {};

(function () {
  /* =========================
     Helpers / formato
     ========================= */
  const fmtFecha = (iso) => {
    if (!iso) return "";
    const d = new Date(String(iso).replace(" ", "T"));
    if (isNaN(d)) return iso;
    const p = (n) => (n < 10 ? "0" + n : n);
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(
      d.getHours()
    )}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  };

  const enforceTenDigits = (selector) => {
    $(document).on("input", selector, function () {
      this.value = this.value.replace(/\D+/g, "");
      if (this.value.length > 10) this.value = this.value.slice(0, 10);
    });
  };

  // === Estatus (reales) ===
  const ESTATUS_TEXT = {
    1: "Pendiente",
    2: "Numlex",
    3: "Procesado",
    4: "Rechazado",
    5: "Estatus 5",
    6: "Estatus 6",
  };
  const ESTATUS_BADGE = {
    1: "bg-primary",
    2: "bg-info",
    3: "bg-warning",
    4: "bg-success",
    5: "bg-danger",
    6: "bg-secondary",
  };
  const estatusBadge = (n) => {
    const k = Number(n);
    const txt = ESTATUS_TEXT[k] || "—";
    const cls = ESTATUS_BADGE[k] || "bg-dark";
    return `<span class="badge ${cls}">${txt}</span>`;
  };
  const estatusNombre = (n) => ESTATUS_TEXT[Number(n)] || "—";

  // Editable si NO es Numlex(2) ni Procesado(3)
  const isEditableStatus = (n) => ![2, 3].includes(Number(n));

  const pill = (v) =>
    String(v) === "1"
      ? '<span class="badge bg-success">Sí</span>'
      : '<span class="badge bg-secondary">No</span>';

  const tipoTxt = (v) => ({ 1: "Prepago", 2: "Pospago" }[Number(v)] || "—");

  const monthRange = () => {
    const now = new Date();
    const y = now.getFullYear(),
      m = now.getMonth();
    const s = new Date(y, m, 1),
      e = new Date(y, m + 1, 0);
    const p = (n) => (n < 10 ? "0" + n : n);
    return {
      desde: `${s.getFullYear()}-${p(s.getMonth() + 1)}-${p(s.getDate())}`,
      hasta: `${e.getFullYear()}-${p(e.getMonth() + 1)}-${p(e.getDate())}`,
    };
  };
  const initMonthRangeUI = () => {
    const r = monthRange();
    $("#kpi_desde").val(r.desde);
    $("#kpi_hasta").val(r.hasta);
    return r;
  };

  function setEditableInModal(isEditable, estatusActual) {
    const $fields = $("#modal-editar")
      .find("input, select, textarea")
      .not("[name='csrf_token'], #cv_portabilidad_editar");
    $fields.prop("disabled", !isEditable);

    const $btn = $("#btn_guardar_editar");
    const $nota = $("#nota_editar");

    if (isEditable) {
      $btn.show();
      if (Number(estatusActual) === 6) {
        $nota
          .removeClass("d-none")
          .html(
            '<i class="bi bi-info-circle me-1"></i> Al guardar se actualizará la <strong>fecha de solicitud</strong> y la solicitud pasará a <strong>Pendiente</strong>.'
          );
      } else {
        $nota
          .removeClass("d-none")
          .html(
            '<i class="bi bi-info-circle me-1"></i> Al guardar se actualizará la <strong>fecha de solicitud</strong> a la fecha y hora actuales.'
          );
      }
    } else {
      $btn.hide();
      $nota.addClass("d-none");
    }
  }

  /* ==========================================
     Filtro custom DataTables (front-end only)
     ========================================== */
  // Columnas: 0 idx, 1 fecha, 2 número, 3 iccid, 4 cliente, 5 correo, 6 estatus, 7 pre, 8 tipo, 9 acciones
  let filtros = { numero: "", estatus: "", desde: "", hasta: "" };
  $.fn.dataTable.ext.search.push(function (_s, data) {
    const numero = (data[2] || "").replace(/\s/g, "");
    const fecha = (data[1] || "").slice(0, 10);
    const estTxt = (data[6] || "").replace(/<[^>]+>/g, ""); // badge -> texto

    if (filtros.numero && !numero.includes(filtros.numero)) return false;

    if (filtros.estatus) {
      const expected = estatusNombre(filtros.estatus);
      if (expected && estTxt.indexOf(expected) === -1) return false;
    }

    if (filtros.desde && fecha < filtros.desde) return false;
    if (filtros.hasta && fecha > filtros.hasta) return false;

    return true;
  });

  /* =======================================
     Reset de DataTables y filtros
     ======================================= */
  const DEFAULT_DT_ORDER = [[1, "desc"]];
  const DEFAULT_DT_LEN = 25;

  const resetDataTable = (dt) => {
    $('div.dataTables_filter input[type="search"]').val("").trigger("input");
    dt.search("");
    $("#recursos-table thead th input, #recursos-table tfoot th input").val("");
    dt.columns().every(function () {
      this.search("");
    });
    dt.order(DEFAULT_DT_ORDER);
    dt.page.len(DEFAULT_DT_LEN);
    dt.page("first");
    $("#recursos-table tr.selected").removeClass("selected");
    dt.draw(false);
  };

  const resetFiltersAndTable = () => {
    document.getElementById("frm_filtros")?.reset();
    filtros = { numero: "", estatus: "", desde: "", hasta: "" };
    resetDataTable($("#recursos-table").DataTable());
  };

  /* ============================
     Módulo principal de la vista
     ============================ */
  app.core.index = {
    _tabla: null,
    _dataRaw: [],

    _refrescarKPIs(r) {
      let rows = this._dataRaw;
      if (r && (r.desde || r.hasta)) {
        rows = rows.filter((x) => {
          const f = fmtFecha(x.fecha_solicitud).slice(0, 10);
          if (r.desde && f < r.desde) return false;
          if (r.hasta && f > r.hasta) return false;
          return true;
        });
      }
      // KPIs: total, "completadas"=Procesado(3), "en proceso"=Numlex(2), "rechazadas"=Rechazado(4)
      $("#kpi_total").text((rows.length || 0).toLocaleString());
      $("#kpi_ok").text(
        rows.filter((x) => +x.estatus === 3).length.toLocaleString()
      );
      $("#kpi_proc").text(
        rows.filter((x) => +x.estatus === 2).length.toLocaleString()
      );
      $("#kpi_rech").text(
        rows.filter((x) => +x.estatus === 4).length.toLocaleString()
      );
    },

    _pintarTabla(rows) {
      const dt = this._tabla;
      dt.clear();

      rows.forEach((r) => {
        // Cancelar SOLO en Pendiente (1). Ajusta si quieres permitir más.
        const canCancel = Number(r.estatus) === 1;
        const btnCancelar = `<button data-bs-toggle="tooltip" title="${
          canCancel ? "Cancelar" : "No disponible por estatus"
        }" type="button" data-clave="${
          r.cv_portabilidad
        }" data-accion="cancelar"
      class="accion_user btn btn-sm btn-outline-danger me-1" ${
        canCancel ? "" : "disabled"
      }>
      <i class="fas fa-times-circle"></i></button>`;

        // Lápiz SIEMPRE habilitado (detalle/edición según estatus)
        const btnEditar = `<button data-bs-toggle="tooltip" title="Ver / Editar"
      type="button" data-clave="${r.cv_portabilidad}" data-accion="editar"
      class="accion_user btn btn-sm btn-outline-warning">
      <i class="fas fa-pencil-alt"></i></button>`;

        dt.row.add([
          r.cv_portabilidad || "", // ← ID real
          fmtFecha(r.fecha_solicitud) || "",
          r.numero_a_portar || "",
          r.icc || "",
          r.nombre_cliente || "",
          r.correo_cliente || "",
          estatusBadge(r.estatus),
          pill(r.preportabilidad),
          tipoTxt(r.tipo_portabilidad),
          btnCancelar + btnEditar,
        ]);
      });

      dt.draw(false);
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        bootstrap.Tooltip.getInstance(el)?.dispose();
        new bootstrap.Tooltip(el);
      });
    },

    /* =========================
       MODELO: peticiones AJAX
       ========================= */
    modelo() {
      const self = this;

      const llenar_tabla = () =>
        $.ajax({
          url: BASE_URL + "admin/wlportabilidades/portabilidades_list",
          dataType: "json",
          success(s) {
            self._dataRaw = Array.isArray(s) ? s : [];
            self._pintarTabla(self._dataRaw);
            const r = $("#kpi_desde").val()
              ? { desde: $("#kpi_desde").val(), hasta: $("#kpi_hasta").val() }
              : initMonthRangeUI();
            self._refrescarKPIs(r);
          },
          error() {
            initMonthRangeUI();
            self._dataRaw = [];
            self._pintarTabla([]);
            self._refrescarKPIs(monthRange());
            alertify.error("No se pudo cargar el listado.");
          },
        });

      const crear = () => {
        $("#frm_nuevo .spinner-border").show();
        const data = new FormData();
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);
        $("#frm_nuevo")
          .serializeArray()
          .forEach((i) => data.append(i.name, i.value));

        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlportabilidades/registrar_portabilidad",
          data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success(obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Solicitud registrada");
              self.modelo().llenar_tabla();
              const el = document.getElementById("modal-crear");
              if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
              $("#frm_nuevo")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "No se pudo registrar.");
            }
          },
          error() {
            alertify.error("Error de red al crear.");
          },
          complete() {
            $("#frm_nuevo .spinner-border").hide();
          },
        });
      };

      const datos_show_editar = (clave) => {
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlportabilidades/datos_show_portabilidad",
          data: { clave: String(clave || "") },
          dataType: "json",
          success(obj) {
            const d = Array.isArray(obj) ? obj[0] : obj;
            if (!d) return alertify.error("No se encontraron datos.");

            // Llenar campos
            $("#cv_portabilidad_editar").val(d.cv_portabilidad || "");
            $("#numero_editar").val(d.numero_a_portar || "");
            $("#icc_editar").val(d.icc || "");
            $("#nip_editar").val(d.nip || "");
            $("#nombre_editar").val(d.nombre_cliente || "");
            $("#correo_editar").val(d.correo_cliente || "");
            $("#pre_editar").val(String(d.preportabilidad ?? "0"));
            $("#tipo_editar").val(String(d.tipo_portabilidad ?? "1"));
            $("#origen_editar").val(String(d.origen_porta ?? "1"));

            // Editable si estatus != 2,3
            setEditableInModal(isEditableStatus(d.estatus), d.estatus);

            const el = document.getElementById("modal-editar");
            if (el) bootstrap.Modal.getOrCreateInstance(el).show();
          },
          error() {
            alertify.error("Error al cargar datos.");
          },
        });
      };

      const editar = () => {
        $("#frm_editar .spinner-border").show();
        const data = new FormData();
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);
        $("#frm_editar")
          .serializeArray()
          .forEach((i) => data.append(i.name, i.value));

        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlportabilidades/editar_portabilidad",
          data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success(obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Actualizado");
              self.modelo().llenar_tabla();
              const el = document.getElementById("modal-editar");
              if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
              $("#frm_editar")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "No se pudo actualizar.");
            }
          },
          error() {
            alertify.error("Error de red al editar.");
          },
          complete() {
            $("#frm_editar .spinner-border").hide();
          },
        });
      };

      const cancelar = (clave) => {
        const token = $('input[name="csrf_token"]').first().val() || "";

        Swal.fire({
          title: "¿Cancelar la solicitud?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#11c46e",
          cancelButtonColor: "#f46a6a",
          confirmButtonText: "Sí, cancelar",
          cancelButtonText: "No",
        }).then((res) => {
          if (!res.isConfirmed) return;

          $.ajax({
            type: "POST",
            url: BASE_URL + "admin/wlportabilidades/cancelar_portabilidad",
            data: { clave: String(clave || ""), csrf_token: token },
            dataType: "json",
            success(obj) {
              if (obj && obj.alert === "info") {
                alertify.success(obj.mensaje || "Solicitud cancelada");
                self.modelo().llenar_tabla();
              } else {
                alertify.error((obj && obj.mensaje) || "No se pudo cancelar.");
              }
            },
            error() {
              alertify.error("Error al cancelar.");
            },
          });
        });
      };

      const subir_csv = (formEl) => {
        const data = new FormData(formEl);
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);

        $("#csv_progress_wrap").removeClass("d-none");
        let p = 15;
        const h = setInterval(() => {
          p = Math.min(95, p + 7);
          $("#csv_progress").css("width", p + "%");
        }, 150);

        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlportabilidades/subir_csv_portabilidades",
          data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success(obj) {
            clearInterval(h);
            $("#csv_progress").css("width", "100%");
            if (obj && obj.ok) {
              alertify.success(obj.msg || "Archivo procesado");
              if (obj.resumen) {
                $("#csv_resumen")
                  .removeClass("d-none")
                  .find("[data-field='ok']")
                  .text(obj.resumen.ok || 0)
                  .end()
                  .find("[data-field='skip']")
                  .text(obj.resumen.skip || 0)
                  .end()
                  .find("[data-field='err']")
                  .text(obj.resumen.err || 0);
              }
              app.core.index.modelo().llenar_tabla();
            } else {
              alertify.error((obj && obj.msg) || "No se pudo procesar.");
            }
          },
          error() {
            clearInterval(h);
            alertify.error("Error al subir archivo.");
          },
        });
      };

      return {
        llenar_tabla,
        crear,
        datos_show_editar,
        editar,
        cancelar,
        subir_csv,
      };
    },

    /* =================
       CONTROLADOR (UI)
       ================= */
    controlador() {
      const self = this;

      // KPIs -> mes actual por default
      initMonthRangeUI();

      // Inputs numéricos de 10 dígitos
      enforceTenDigits("#f_numero, [name='numero_a_portar'], #numero_editar");

      // DataTable
      this._tabla = $("#recursos-table").DataTable({
        lengthChange: false,
        responsive: { details: { type: "inline", target: "tr" } },
        buttons: ["copy", "excel", "pdf", "colvis"],
        pageLength: DEFAULT_DT_LEN,
        order: DEFAULT_DT_ORDER,
        language: {
          buttons: {
            colvis: "Ocultar columna",
            copy: "Copiar",
            excel: "Excel",
            pdf: "PDF",
          },
          sProcessing: "Procesando...",
          sLengthMenu: "Mostrar _MENU_ registros",
          sZeroRecords: "No se encontraron resultados",
          sEmptyTable: "Ningún dato disponible",
          sInfo: "Mostrando _START_–_END_ de _TOTAL_",
          sInfoEmpty: "Mostrando 0–0 de 0",
          sInfoFiltered: "(filtrado de _MAX_)",
          sSearch: "Buscar:",
          oPaginate: {
            sFirst: "Primero",
            sLast: "Último",
            sNext: "Siguiente",
            sPrevious: "Anterior",
          },
        },
        columnDefs: [
          { targets: 0, className: "text-center" }, // ID real centrado
          { targets: 6, className: "text-center" }, // Estatus
          { targets: 7, className: "text-center" }, // Pre
          {
            targets: -1,
            className: "text-center dt-actions",
            orderable: false,
            searchable: false,
          }, // Acciones
        ],
      });
      this._tabla
        .buttons()
        .container()
        .appendTo("#recursos-table_wrapper .col-md-6:eq(0)");

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

      // submit CREAR
      $("#frm_nuevo").on("submit", function (e) {
        e.preventDefault();
        const num = ($("[name='numero_a_portar']").val() || "").replace(
          /\D+/g,
          ""
        );
        const icc = ($("[name='icc']").val() || "").trim();
        const nip = ($("[name='nip']").val() || "").replace(/\D+/g, "");
        const nom = ($("[name='nombre_cliente']").val() || "").trim();

        if (num.length !== 10)
          return alertify.error("El número debe tener 10 dígitos.");
        if (icc.length < 10) return alertify.error("ICCID parece inválido.");
        if (nip.length < 4 || nip.length > 6)
          return alertify.error("NIP inválido (4–6 dígitos).");
        if (nom.length < 3)
          return alertify.error("Nombre del cliente demasiado corto.");

        app.core.index.modelo().crear();
      });

      // submit EDITAR
      $("#frm_editar").on("submit", function (e) {
        e.preventDefault();
        const num = ($("#numero_editar").val() || "").replace(/\D+/g, "");
        const icc = ($("#icc_editar").val() || "").trim();
        const nip = ($("#nip_editar").val() || "").replace(/\D+/g, "");
        const nom = ($("#nombre_editar").val() || "").trim();

        if (num.length !== 10)
          return alertify.error("El número debe tener 10 dígitos.");
        if (icc.length < 10) return alertify.error("ICCID parece inválido.");
        if (nip.length < 4 || nip.length > 6)
          return alertify.error("NIP inválido (4–6 dígitos).");
        if (nom.length < 3)
          return alertify.error("Nombre del cliente demasiado corto.");

        app.core.index.modelo().editar();
      });

      // Acciones por fila
      $("#recursos-table").on("click", ".accion_user", function () {
        const accion = $(this).data("accion");
        const clave = $(this).data("clave");
        if (accion === "editar")
          app.core.index.modelo().datos_show_editar(clave);
        if (accion === "cancelar") app.core.index.modelo().cancelar(clave);
      });

      // Filtros
      const aplicarFiltros = () => {
        filtros.numero = ($("#f_numero").val() || "").replace(/\D+/g, "");
        filtros.estatus = $("#f_estatus").val() || "";
        filtros.desde = $("#f_desde").val() || "";
        filtros.hasta = $("#f_hasta").val() || "";
        self._tabla.draw();
      };
      $("#btn_aplicar_filtros").on("click", aplicarFiltros);
      $("#f_numero").on("keypress", (e) => {
        if (e.which === 13) {
          e.preventDefault();
          aplicarFiltros();
        }
      });

      // KPIs
      $("#btn_kpi_aplicar").on("click", () => {
        const d = $("#kpi_desde").val() || "";
        const h = $("#kpi_hasta").val() || "";
        self._refrescarKPIs({ desde: d, hasta: h });
      });

      // Recargar
      $("#btn_recargar").on("click", () => {
        resetFiltersAndTable();
        self.modelo().llenar_tabla();
        alertify.message("Datos actualizados y tabla reiniciada");
      });

      // CSV/TXT
      $("#frm_csv").on("submit", function (e) {
        e.preventDefault();
        const file = ($(this).find('input[type="file"]').prop("files") ||
          [])[0];
        if (!file || !/\.(csv|txt)$/i.test(file.name))
          return alertify.error("Selecciona un .csv o .txt válido.");
        app.core.index.modelo().subir_csv(this);
      });

      // Tooltips
      document
        .querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => new bootstrap.Tooltip(el));
    },
  };

  $(document).ready(() => app.core.index.controlador());
})();
