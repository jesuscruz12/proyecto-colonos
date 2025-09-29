/* ==========================================================================
   WLSIMS - index.js (final)
   - DataTable con acciones (Editar / Eliminar / Ver QR)
   - Filtros front (número, iccid, tipo, estatus, origen)
   - KPIs:
       * Disponibles = !Activo && sin fecha_activacion (desglose: Física/eSIM) [NO usa rango por defecto]
       * Activas = total + Nueva/Porta (respeta rango SOLO cuando el usuario aplica)
       * Activas por tipo SIM = total + Física/eSIM (respeta rango SOLO cuando el usuario aplica)
       * Última activación por tipo = Física / eSIM (respeta rango SOLO cuando el usuario aplica)
   - Botón "Refrescar" limpia filtros + recarga datos + resetea rango KPIs
   - Validación: número exactamente 10 dígitos (solo números)
   ========================================================================== */

/* globals $, bootstrap, Swal, alertify, BASE_URL */
app.globales = app.globales || {};
app.globales.cv_temporal = 0;
app.core = app.core || {};

(function () {
  /* ------------------------------- Helpers ------------------------------- */

  // Formato fecha a YYYY-MM-DD HH:mm:ss
  const fmtFecha = (iso) => {
    if (!iso) return "";
    const d = new Date(String(iso).replace(" ", "T"));
    if (isNaN(d.getTime())) return iso;
    const p = (n) => (n < 10 ? "0" + n : n);
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  };

  // Badges (AdminLTE/Bootstrap) para campos enumerados
  const estatusBadge = (v) => {
    const map = { 1: ["Inactivo", "bg-secondary"], 2: ["Activo", "bg-success"] };
    const hit = map[v] || ["—", "bg-light text-muted"];
    return `<span class="badge ${hit[1]}">${hit[0]}</span>`;
  };
  const tipoBadge = (v) => {
    const map = { 1: ["Física", "bg-primary"], 2: ["eSIM", "bg-warning text-dark"] };
    const hit = map[v] || ["—", "bg-light text-muted"];
    return `<span class="badge ${hit[1]}">${hit[0]}</span>`;
  };
  const origenBadge = (v) => {
    const map = { 1: ["Nueva", "bg-info"], 2: ["Porta", "bg-indigo"] };
    const hit = map[v] || ["—", "bg-light text-muted"];
    return `<span class="badge ${hit[1]}">${hit[0]}</span>`;
  };

  // Solo dígitos (en tecleo/pegar/soltar) respetando maxlength
  const wireOnlyDigits = (selector) => {
    $(document).on("input", selector, function () {
      const max = this.maxLength > 0 ? this.maxLength : 999;
      const cleaned = this.value.replace(/\D+/g, "").slice(0, max);
      if (cleaned !== this.value) this.value = cleaned;
    }).on("paste", selector, function (ev) {
      ev.preventDefault();
      const text = (ev.originalEvent || ev).clipboardData.getData("text") || "";
      const max = this.maxLength > 0 ? this.maxLength : 999;
      document.execCommand("insertText", false, text.replace(/\D+/g, "").slice(0, max));
    }).on("drop", selector, function (ev) {
      ev.preventDefault();
    });
  };

  // Rango del mes actual (YYYY-MM-DD)
  const getMonthRange = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const d0 = new Date(y, m, 1);
    const d1 = new Date(y, m + 1, 0);
    const p = (n) => (n < 10 ? "0" + n : n);
    return {
      desde: `${d0.getFullYear()}-${p(d0.getMonth() + 1)}-${p(d0.getDate())}`,
      hasta: `${d1.getFullYear()}-${p(d1.getMonth() + 1)}-${p(d1.getDate())}`,
    };
  };

  // URL de imagen QR para eSIM
  const qrUrl = (data) =>
    `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(data || "")}`;

  /* --------------------------- Filtros DataTable -------------------------- */
  // Columnas: 0 #, 1 msisdn, 2 estatus, 3 tipo, 4 origen, 5 producto, 6 iccid, 7 activación, 8 lote, 9 acciones
  let filtros = { numero: "", iccid: "", tipo: "", estatus: "", origen: "" };

  $.fn.dataTable.ext.search.push(function (_settings, data) {
    if (_settings.nTable.getAttribute("id") !== "wlsims-table") return true;

    const numero  = (data[1] || "");
    const estatus = (data[2] || "").replace(/<.*?>/g, "");
    const tipo    = (data[3] || "").replace(/<.*?>/g, "");
    const origen  = (data[4] || "").replace(/<.*?>/g, "");
    const iccid   = (data[6] || "");

    if (filtros.numero && !numero.includes(filtros.numero)) return false;
    if (filtros.iccid  && !iccid.toUpperCase().includes(filtros.iccid.toUpperCase())) return false;

    if (filtros.estatus) {
      const want = filtros.estatus === "1" ? "Inactivo" : filtros.estatus === "2" ? "Activo" : "";
      if (want && estatus.indexOf(want) === -1) return false;
    }
    if (filtros.tipo) {
      const want = filtros.tipo === "1" ? "Física" : filtros.tipo === "2" ? "eSIM" : "";
      if (want && tipo.indexOf(want) === -1) return false;
    }
    if (filtros.origen) {
      const want = filtros.origen === "1" ? "Nueva" : filtros.origen === "2" ? "Porta" : "";
      if (want && origen.indexOf(want) === -1) return false;
    }

    return true;
  });

  /* ----------------------------- Módulo Vista ----------------------------- */
  app.core.wlsims = {
    _tabla: null,
    _dataRaw: [],
    _kpiRangoAplicado: false, // si true, KPIs de activas respetan el rango

    // KPIs
    _refrescarKPIs: function (rango) {
      const rowsAll = this._dataRaw || [];

      // 1) Disponibles (NO usa rango)
      const disponibles = rowsAll.filter(r => String(r.estatus_linea) !== "2" && !r.fecha_activacion);
      const dispTotal   = disponibles.length;
      const dispFisica  = disponibles.reduce((a, r) => a + (String(r.tipo_sim) === "1" ? 1 : 0), 0);
      const dispEsim    = disponibles.reduce((a, r) => a + (String(r.tipo_sim) === "2" ? 1 : 0), 0);

      // 2) Base de activas (respeta rango solo si _kpiRangoAplicado)
      let activasBase = rowsAll.filter(r => String(r.estatus_linea) === "2");
      if (this._kpiRangoAplicado && rango && (rango.desde || rango.hasta)) {
        activasBase = activasBase.filter((r) => {
          if (!r.fecha_activacion) return false;
          const d = new Date(String(r.fecha_activacion).replace(" ", "T"));
          if (isNaN(d.getTime())) return false;
          const ymd = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,"0")}-${String(d.getDate()).padStart(2,"0")}`;
          if (rango.desde && ymd < rango.desde) return false;
          if (rango.hasta && ymd > rango.hasta) return false;
          return true;
        });
      }

      const actTotal = activasBase.length;
      const actNueva = activasBase.reduce((a, r) => a + (String(r.li_nueva_o_porta) === "1" ? 1 : 0), 0);
      const actPorta = activasBase.reduce((a, r) => a + (String(r.li_nueva_o_porta) === "2" ? 1 : 0), 0);

      // 3) Activas por tipo SIM
      const actTipoTotal = activasBase.length;
      const actFisica = activasBase.reduce((a, r) => a + (String(r.tipo_sim) === "1" ? 1 : 0), 0);
      const actEsim   = activasBase.reduce((a, r) => a + (String(r.tipo_sim) === "2" ? 1 : 0), 0);

      // 4) Última activación por tipo
      const maxDateFor = (rows, tipo) => {
        let max = "";
        rows.forEach(r => {
          if (String(r.tipo_sim) !== tipo || !r.fecha_activacion) return;
          const f = fmtFecha(r.fecha_activacion);
          if (f && (!max || f > max)) max = f;
        });
        return max || "—";
      };
      const ultFisica = maxDateFor(activasBase, "1");
      const ultEsim   = maxDateFor(activasBase, "2");

      // Pintar
      $("#kpi_disp_total").text(dispTotal.toLocaleString());
      $("#kpi_disp_fisica").text(dispFisica.toLocaleString());
      $("#kpi_disp_esim").text(dispEsim.toLocaleString());

      $("#kpi_act_total").text(actTotal.toLocaleString());
      $("#kpi_act_nueva").text(actNueva.toLocaleString());
      $("#kpi_act_porta").text(actPorta.toLocaleString());

      $("#kpi_act_tipo_total").text(actTipoTotal.toLocaleString());
      $("#kpi_act_fisica").text(actFisica.toLocaleString());
      $("#kpi_act_esim").text(actEsim.toLocaleString());

      $("#kpi_ult_fisica").text(ultFisica);
      $("#kpi_ult_esim").text(ultEsim);
    },

    // Pinta tabla
    _pintarTabla: function (rows) {
      const dt = this._tabla;
      dt.clear();

      rows.forEach((r, idx) => {
        const acciones =
          `<div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary accion" data-accion="editar" data-clave="${r.cv_sim}" data-bs-toggle="tooltip" title="Editar">
              <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-outline-danger accion" data-accion="eliminar" data-clave="${r.cv_sim}" data-bs-toggle="tooltip" title="Eliminar">
              <i class="fas fa-times-circle"></i>
            </button>
            ${(String(r.tipo_sim) === "2" && r.codigo_qr) ? `
              <button type="button" class="btn btn-outline-dark accion" data-accion="qr" data-codigo="${$('<div>').text(r.codigo_qr).html()}" data-bs-toggle="tooltip" title="Ver QR">
                <i class="bi bi-qr-code"></i>
              </button>` : ``}
          </div>`;

        dt.row.add([
          idx + 1,
          r.msisdn || "",
          estatusBadge(parseInt(r.estatus_linea || 0, 10)),
          tipoBadge(parseInt(r.tipo_sim || 0, 10)),
          origenBadge(parseInt(r.li_nueva_o_porta || 0, 10)),
          r.producto || "",
          r.iccid || "",
          fmtFecha(r.fecha_activacion) || "",
          r.lote || "",
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

    /* ----------------------------- Modelo (AJAX) ----------------------------- */
    modelo: function () {
      const self = this;

      // Cargar datos
      const llenar_tabla = function () {
        return $.ajax({
          url: BASE_URL + "admin/wlsims/sims_list/",
          dataType: "json",
          success: function (s) {
            self._dataRaw = Array.isArray(s) ? s : [];
            self._pintarTabla(self._dataRaw);

            // KPIs: por defecto mostrar totales (sin rango aplicado)
            const r = getMonthRange();
            $("#kpi_desde").val(r.desde);
            $("#kpi_hasta").val(r.hasta);
            self._kpiRangoAplicado = false;
            self._refrescarKPIs(null);
          },
        });
      };

      // Crear
      const nuevo = function () {
        $("#frm_nuevo .spinner-border").show();

        const data = new FormData();
        // CSRF si existe
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);

        $("#frm_nuevo").serializeArray().forEach((i) => data.append(i.name, i.value));

        // Validación número
        const num = (data.get("msisdn") || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#msisdn").focus();
          $("#frm_nuevo .spinner-border").hide();
          return;
        }

        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlsims/registrar_sim",
          data: data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Guardado correctamente");
              self.modelo().llenar_tabla();

              // Cerrar modal (DOM element)
              const modalEl = document.getElementById("modal-crear");
              if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();

              $("#frm_nuevo")[0].reset();
            } else {
              alertify.error((obj && obj.mensaje) || "Ocurrió un error.");
            }
          },
          error: function () {
            alertify.error("Error al guardar (crear).");
          },
          complete: function () {
            $("#frm_nuevo .spinner-border").hide();
          },
        });
      };

      // Mostrar datos en modal editar
      const datos_show = function (clave) {
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlsims/datos_show_sim/",
          data: { clave: clave },
          dataType: "json",
          success: function (obj) {
            const r = Array.isArray(obj) ? obj[0] : obj;
            if (r) {
              $("#cv_sim_editar").val(r.cv_sim || "");
              $("#msisdn_editar").val(r.msisdn || "");
              $("#iccid_editar").val(r.iccid || "");
              $("#producto_editar").val(r.producto || "");
              $("#tipo_sim_editar").val(r.tipo_sim || "");
              $("#estatus_linea_editar").val(r.estatus_linea || "");
              $("#li_nueva_o_porta_editar").val(r.li_nueva_o_porta || "");
              $("#lote_editar").val(r.lote || "");
              if (r.fecha_activacion) {
                const f = fmtFecha(r.fecha_activacion).replace(" ", "T").slice(0, 16);
                $("#fecha_activacion_editar").val(f);
              } else {
                $("#fecha_activacion_editar").val("");
              }
              $("#codigo_qr_editar").val(r.codigo_qr || "");

              // Abrir modal editar (DOM element)
              const modalEl = document.getElementById("modal-editar");
              if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
              alertify.error("No se encontraron datos para la SIM.");
            }
          },
          error: function () {
            alertify.error("Error al cargar datos para edición.");
          },
        });
      };

      // Editar
      const editar = function () {
        $("#frm_editar .spinner-border").show();

        const data = new FormData();
        data.append("clave", $("#cv_sim_editar").val() || app.globales.cv_temporal);

        // CSRF si existe
        const token = $('input[name="csrf_token"]').first().val() || "";
        if (token) data.append("csrf_token", token);

        $("#frm_editar").serializeArray().forEach((i) => data.append(i.name, i.value));

        // Validación número
        const num = (data.get("msisdn") || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#msisdn_editar").focus();
          $("#frm_editar .spinner-border").hide();
          return;
        }

        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlsims/editar_sim",
          data: data,
          contentType: false,
          cache: false,
          processData: false,
          dataType: "json",
          success: function (obj) {
            if (obj && obj.alert === "info") {
              alertify.success(obj.mensaje || "Actualizado correctamente");
              self.modelo().llenar_tabla();

              // Cerrar modal editar (DOM element)
              const modalEl = document.getElementById("modal-editar");
              if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();

              $("#frm_editar")[0].reset();
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

      // Eliminar
      const eliminar = function (clave) {
        const token = $('input[name="csrf_token"]').first().val() || "";
        $.ajax({
          type: "POST",
          url: BASE_URL + "admin/wlsims/eliminar_sim",
          data: { clave: clave, csrf_token: token },
          success: function () {
            self.modelo().llenar_tabla();
          },
        });
      };

      return { llenar_tabla, nuevo, datos_show, editar, eliminar };
    },

    /* ------------------------------- Controlador ------------------------------ */
    controlador: function () {
      const self = this;

      // Solo dígitos
      wireOnlyDigits("#f_numero, #msisdn, #msisdn_editar");

      // DataTable
      this._tabla = $("#wlsims-table").DataTable({
        lengthChange: false,
        responsive: { details: { type: "inline", target: "tr" } },
        buttons: ["copy", "excel", "pdf", "colvis"],
        pageLength: 25,
        order: [[7, "desc"]], // fecha activación
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
          { targets: 2, className: "text-center" }, // estatus
          { targets: 3, className: "text-center" }, // tipo
          { targets: 4, className: "text-center" }, // origen
          { targets: -1, className: "text-center", orderable: false, searchable: false }, // acciones
        ],
      });

      // Botones DT al wrapper
      this._tabla.buttons().container().appendTo("#wlsims-table_wrapper .col-md-6:eq(0)");

      // Selección visual de fila (para UX en eliminar)
      $("#wlsims-table tbody").on("click", "tr", function () {
        const $tr = $(this);
        const table = $("#wlsims-table").DataTable();
        if ($tr.hasClass("selected")) $tr.removeClass("selected");
        else {
          table.$("tr.selected").removeClass("selected");
          $tr.addClass("selected");
        }
      });

      // Carga inicial
      this.modelo().llenar_tabla();

      // Submit: Crear
      $("#frm_nuevo").on("submit", function (e) {
        e.preventDefault();
        const num = ($("#msisdn").val() || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#msisdn").focus();
          return;
        }
        self.modelo().nuevo();
      });

      // Submit: Editar
      $("#frm_editar").on("submit", function (e) {
        e.preventDefault();
        const num = ($("#msisdn_editar").val() || "").replace(/\D+/g, "");
        if (num.length !== 10) {
          alertify.error("El número debe tener exactamente 10 dígitos.");
          $("#msisdn_editar").focus();
          return;
        }
        self.modelo().editar();
      });

      // Acciones por fila (EDITAR / ELIMINAR / QR)
      $("#wlsims-table").on("click", ".accion", function () {
        const accion = $(this).data("accion");
        const clave  = $(this).data("clave");
        app.globales.cv_temporal = clave;

        if (accion === "eliminar") {
          Swal.fire({
            title: "¿Deseas eliminar la SIM?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#11c46e",
            cancelButtonColor: "#f46a6a",
            cancelButtonText: "Cancelar",
            confirmButtonText: "¡Sí!",
          }).then(function (result) {
            if (result.isConfirmed) {
              const table = $("#wlsims-table").DataTable();
              table.row(".selected").remove().draw(false);
              self.modelo().eliminar(clave);
              Swal.fire("Eliminada", "Se eliminó correctamente.", "success");
            }
          });
        }

        if (accion === "editar") {
          $(".spinner-border").hide();
          self.modelo().datos_show(clave);
        }

        if (accion === "qr") {
          const code = $(this).data("codigo") || "";
          $("#qr_text").text(code || "(sin código)");
          $("#qr_img").attr("src", qrUrl(code)).attr("alt", "QR eSIM");
          $("#btn_qr_descargar").attr("href", qrUrl(code));

          const modalEl = document.getElementById("modal-qr");
          if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      });

      // Copiar texto QR
      $("#btn_qr_copiar").on("click", function () {
        const txt = $("#qr_text").text() || "";
        if (!txt) return;
        navigator.clipboard.writeText(txt).then(
          () => alertify.success("Código QR copiado"),
          () => alertify.error("No se pudo copiar")
        );
      });

      // Abrir modal "Nuevo" -> reset limpio
      $(".extend-new-recurso").on("click", function () {
        $("#frm_nuevo")[0].reset();
        $(".spinner-border").hide();
      });

      // Botón Refrescar (limpia filtros + recarga + resetea KPIs a totales)
      const resetFiltersAndTable = () => {
        const frm = document.getElementById("frm_filtros");
        if (frm) frm.reset();
        filtros = { numero: "", iccid: "", tipo: "", estatus: "", origen: "" };
        const dt = $("#wlsims-table").DataTable();
        dt.search(""); dt.columns().search(""); dt.draw(false);
      };
      $("#btn_recargar").on("click", () => {
        resetFiltersAndTable();
        app.core.wlsims._kpiRangoAplicado = false;
        self.modelo().llenar_tabla();
        alertify.message("Filtros limpiados y datos actualizados");
      });

      /* ----------------------- Filtros (front / DataTables) ---------------------- */
      const aplicarFiltros = () => {
        const num   = ($("#f_numero").val() || "").replace(/\D+/g, "");
        const iccid = $("#f_iccid").val() || "";
        const tipo  = $("#f_tipo").val() || "";
        const est   = $("#f_estatus").val() || "";
        const org   = $("#f_origen").val() || "";
        filtros = { numero: num, iccid, tipo, estatus: est, origen: org };
        self._tabla.draw(false);
      };

      $("#btn_aplicar_filtros").on("click", aplicarFiltros);
      $("#f_numero, #f_iccid, #f_tipo, #f_estatus, #f_origen").on("change", function () {
        aplicarFiltros();
      });
      $("#f_numero, #f_iccid").on("keypress", function (e) {
        if (e.which === 13) { e.preventDefault(); aplicarFiltros(); }
      });

      /* -------------------------- Rango de fechas KPIs -------------------------- */
      $("#btn_kpi_aplicar").on("click", function () {
        const desde = $("#kpi_desde").val() || "";
        const hasta = $("#kpi_hasta").val() || "";
        app.core.wlsims._kpiRangoAplicado = true;
        app.core.wlsims._refrescarKPIs({ desde, hasta });
      });
      // Si decides volver a usar "Mes actual", recuerda crear el botón y listener:
      // $("#btn_kpi_mes").on("click", function () {
      //   const r = getMonthRange();
      //   $("#kpi_desde").val(r.desde);
      //   $("#kpi_hasta").val(r.hasta);
      //   app.core.wlsims._kpiRangoAplicado = true;
      //   app.core.wlsims._refrescarKPIs(r);
      // });

      // Tooltips globales
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    },
  };

  // Bootstrap del módulo
  $(document).ready(function () {
    app.core.wlsims.controlador();
  });
})();
