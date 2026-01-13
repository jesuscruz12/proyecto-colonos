(function () {
  const qs = (sel) => document.querySelector(sel);

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function can(resourceKey) {
    if (!window.RECURSOS_LIST) return true;
    return window.RECURSOS_LIST.includes(resourceKey);
  }

  function toastOk(msg) {
    if (window.alertify && alertify.success) return alertify.success(msg);
    console.log(msg);
  }
  function toastErr(msg) {
    if (window.alertify && alertify.error) return alertify.error(msg);
    console.error(msg);
  }

  function confirmAfy(title, msg) {
    return new Promise((resolve) => {
      if (!window.alertify || !alertify.confirm) {
        resolve(window.confirm(msg.replace(/<br\s*\/?>/g, "\n").replace(/<[^>]+>/g, "")));
        return;
      }
      alertify.confirm(title, msg,
        () => resolve(true),
        () => resolve(false)
      );
    });
  }

  function badgePrioridad(p) {
    const map = {
      baja: "badge bg-secondary",
      normal: "badge bg-info",
      alta: "badge bg-warning text-dark",
      urgente: "badge bg-danger",
    };
    const cls = map[p] || "badge bg-secondary";
    return `<span class="${cls} text-uppercase">${esc(p)}</span>`;
  }

  function fmtMin(min) {
    min = Number(min || 0);
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${h} h ${m} min`;
  }

  async function apiDetalle(otId) {
    const r = await fetch(BASE_URL + "admin/planeacion/detalle?ot_id=" + encodeURIComponent(otId), {
      credentials: "same-origin",
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) throw new Error(j.msg || "Error al cargar detalle");
    return j;
  }

  async function apiPlanificar(otId) {
    const fd = new FormData();
    fd.append("ot_id", otId);

    // CSRF si existe en tu layout
    const csrf = (window.CSRF_TOKEN || window.TOKEN_CSRF || "");
    if (csrf) fd.append("csrf_token", csrf);

    const r = await fetch(BASE_URL + "admin/planeacion/generar", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
      headers: csrf ? { "X-CSRF-TOKEN": csrf } : undefined
    });

    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) throw new Error(j.msg || "Error al planificar");
    return j;
  }

  function initTooltips(root) {
    try {
      if (!window.bootstrap || !bootstrap.Tooltip) return;
      const el = root || document;
      [...el.querySelectorAll('[data-bs-toggle="tooltip"]')].forEach((t) => {
        bootstrap.Tooltip.getOrCreateInstance(t);
      });
    } catch (_) {}
  }

  function setModalStateLoading() {
    const alertBox = qs("#mdl_alert");
    alertBox.className = "alert alert-info";
    alertBox.textContent = "Cargando detalle...";
    qs("#tbl_items_detalle tbody").innerHTML = "";
    qs("#tbl_ops_detalle tbody").innerHTML = "";
    qs("#btn_planificar_modal").disabled = true;
  }

  function renderModalDetalle(data) {
    const ot = data.ot;
    const items = data.items || [];
    const ops = data.operaciones || [];
    const totalMin = data.total_minutos_estimados || 0;

    const itemsHtml = items.map((i) => {
      const rutaOk = !!i.version_ruta_id;
      const ruta = rutaOk
        ? `<span class="badge bg-success">Ruta OK</span>`
        : `<span class="badge bg-danger">Sin ruta</span>`;

      const ref =
        i.tipo_item === "parte"
          ? `<div><b>${esc(i.parte_numero || "Parte")}</b></div><div class="text-muted small">${esc(i.parte_descripcion || "")}</div>`
          : (i.tipo_item === "subensamble"
              ? `<div><b>${esc(i.subensamble_nombre || "Subensamble")}</b></div><div class="text-muted small">${esc(i.notas || "—")}</div>`
              : `<div><b>${esc(i.producto_nombre || "Producto")}</b></div><div class="text-muted small">${esc(i.notas || "—")}</div>`);

      return `
        <tr>
          <td class="text-uppercase">${esc(i.tipo_item)}</td>
          <td>${ref}</td>
          <td class="text-end">${esc(i.cantidad)}</td>
          <td>${ruta}</td>
        </tr>
      `;
    }).join("");

    const opsHtml = ops.map((o) => {
      return `
        <tr>
          <td class="text-end">${esc(o.secuencia)}</td>
          <td>${esc(o.proceso_nombre)}</td>
          <td class="text-end">${esc(o.setup_minutos)}</td>
          <td class="text-end">${esc(o.segundos_por_unidad)}</td>
          <td class="text-end">${esc(o.duracion_minutos)}</td>
        </tr>
      `;
    }).join("");

    const folioShow = (ot.folio_ot && String(ot.folio_ot).trim() !== "") ? ot.folio_ot : ("OT #" + ot.id);

    qs("#mdl_ot_folio").textContent = folioShow;
    qs("#mdl_ot_cliente").textContent = ot.cliente_nombre || "—";
    qs("#mdl_ot_desc").textContent = ot.descripcion || "—";
    qs("#mdl_ot_compromiso").textContent = ot.fecha_compromiso || "—";
    qs("#mdl_ot_prioridad").innerHTML = badgePrioridad(ot.prioridad);
    qs("#mdl_ot_creado_por").textContent = ot.creado_por_nombre || "—";
    qs("#mdl_total").textContent = fmtMin(totalMin);

    qs("#tbl_items_detalle tbody").innerHTML =
      itemsHtml || `<tr><td colspan="4" class="text-center text-muted py-3">Sin ítems</td></tr>`;

    qs("#tbl_ops_detalle tbody").innerHTML =
      opsHtml || `<tr><td colspan="5" class="text-center text-muted py-3">Sin operaciones</td></tr>`;

    const planificable = !!data.planificable;
    const planMsg = data.planificable_msg || "OK";

    const alertBox = qs("#mdl_alert");
    alertBox.className = "alert " + (planificable ? "alert-success" : "alert-warning");
    alertBox.innerHTML = planificable
      ? `Listo para planificar.`
      : `No planificable: <b>${esc(planMsg)}</b>`;

    const btnPlan = qs("#btn_planificar_modal");
    btnPlan.disabled = !can("planeacion_generar") || !planificable;
    btnPlan.setAttribute("data-ot", ot.id);

    const modalEl = qs("#mdl_detalle_ot");
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    initTooltips(modalEl);
  }

  async function abrirDetalle(otId) {
    try {
      setModalStateLoading();
      bootstrap.Modal.getOrCreateInstance(qs("#mdl_detalle_ot")).show();
      const data = await apiDetalle(otId);
      renderModalDetalle(data);
    } catch (e) {
      toastErr(e.message || "Error detalle");
      bootstrap.Modal.getOrCreateInstance(qs("#mdl_detalle_ot")).hide();
    }
  }

  async function planificar(otId, folioShow) {
    if (!can("planeacion_generar")) {
      toastErr("No tienes permisos para planificar.");
      return { ok: false };
    }

    const ok = await confirmAfy(
      "Planificar",
      `Esto generará tareas para <b>${esc(folioShow)}</b>, asignará máquina(s) y programará inicio/fin.<br><br>¿Continuar?`
    );
    if (!ok) return { ok: false };

    const j = await apiPlanificar(otId);

    toastOk(`Planeación OK: ${j.tareas} tarea(s). Programadas: ${j.programadas ?? 0}. Pendientes: ${j.pendientes ?? 0}.`);
    return { ok: true, tareas: j.tareas };
  }

  document.addEventListener("DOMContentLoaded", function () {
    const $jq = window.jQuery;
    if (!$jq || !$jq.fn || !$jq.fn.dataTable) {
      toastErr("Falta cargar DataTables (JS) en el layout.");
      return;
    }

    initTooltips(document);

    const fPrioridad = qs("#f_prioridad");
    const fDesde = qs("#f_desde");
    const fHasta = qs("#f_hasta");
    const btnReset = qs("#btn_reset");

    const table = $jq("#tbl_ots").DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      order: [[3, "asc"]],
      ajax: {
        url: BASE_URL + "admin/planeacion/dt_ots_pendientes",
        type: "GET",
        data: function (d) {
          d.f_prioridad = fPrioridad?.value || "";
          d.f_desde = fDesde?.value || "";
          d.f_hasta = fHasta?.value || "";
        },
      },

      dom:
        "<'row'<'col-md-6'B><'col-md-6'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row'<'col-md-5'i><'col-md-7'p>>",

      buttons: [
        { extend: "copy", text: "Copy" },
        { extend: "csv", text: "CSV" },
        { extend: "excel", text: "Excel" },
        { extend: "pdf", text: "PDF" },
        { extend: "print", text: "Print" },
        { extend: "colvis", text: "Column visibility" },
      ],

      columns: [
        {
          data: "folio_ot",
          render: (_, __, row) => {
            const folio = (row.folio_ot && String(row.folio_ot).trim() !== "") ? row.folio_ot : ("OT #" + row.id);
            return `<b>${esc(folio)}</b>`;
          }
        },
        { data: "descripcion", render: (d) => esc(d || "—") },
        { data: "cliente_nombre", render: (d) => esc(d || "—") },
        { data: "fecha_compromiso", render: (d) => esc(d || "") },
        { data: "prioridad", render: (d) => badgePrioridad(d), searchable: false },

        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (_, __, row) {
            const items = Number(row.items_count || 0);
            const tareas = Number(row.tareas_count || 0);

            const badgeItems = items > 0
              ? `<span class="badge bg-primary">Ítems OK</span>`
              : `<span class="badge bg-danger">Sin ítems</span>`;

            const badgeT = tareas > 0
              ? `<span class="badge bg-success">Con tareas</span>`
              : `<span class="badge bg-secondary">Sin tareas</span>`;

            return `<div class="small">Ítems: <b>${items}</b> ${badgeItems} · Tareas: <b>${tareas}</b> ${badgeT}</div>`;
          },
        },

        { data: "creado_por_nombre", render: (d) => esc(d || "—") },
        { data: "creado_en", render: (d) => esc(d || "") },

        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (_, __, row) {
            const tareas = Number(row.tareas_count || 0);
            const disDet  = (!can("planeacion_detalle")) ? "disabled" : "";
            const disPlan = (!can("planeacion_generar") || tareas > 0) ? "disabled" : "";

            const folio = (row.folio_ot && String(row.folio_ot).trim() !== "") ? row.folio_ot : ("OT #" + row.id);

            const tipDet = `data-bs-toggle="tooltip" title="Ver detalle (ítems + ruta + tiempos)"`;
            const tipPlan = tareas > 0
              ? `data-bs-toggle="tooltip" title="Ya tiene tareas. No se puede planificar otra vez."`
              : `data-bs-toggle="tooltip" title="Planificar: genera tareas, asigna máquina y programa inicio/fin."`;

            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-secondary"
                        ${disDet}
                        ${tipDet}
                        data-action="detalle"
                        data-id="${row.id}">
                  <i class="bi bi-eye"></i>
                </button>

                <button class="btn btn-primary"
                        ${disPlan}
                        ${tipPlan}
                        data-action="planificar"
                        data-id="${row.id}"
                        data-folio="${esc(folio)}">
                  <i class="bi bi-play-fill"></i>
                </button>
              </div>
            `;
          },
        },
      ],

      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Sin registros",
        zeroRecords: "No se encontraron resultados",
        processing: "Cargando...",
        paginate: { previous: "Anterior", next: "Siguiente" },
      },

      drawCallback: function () {
        initTooltips(document);
      },
    });

    $jq("#tbl_ots tbody").on("click", "button[data-action]", async function () {
      if (this.disabled) return;

      const action = this.getAttribute("data-action");
      const otId = Number(this.getAttribute("data-id"));
      const folio = this.getAttribute("data-folio") || ("OT #" + otId);

      if (!otId) return;

      if (action === "detalle") {
        await abrirDetalle(otId);
        return;
      }

      if (action === "planificar") {
        try {
          const res = await planificar(otId, folio);
          if (res.ok) table.ajax.reload(null, false);
        } catch (e) {
          toastErr(e.message || "Error al planificar");
        }
      }
    });

    [fPrioridad, fDesde, fHasta].forEach((el) => {
      if (!el) return;
      el.addEventListener("change", () => table.ajax.reload());
    });

    if (btnReset) {
      btnReset.addEventListener("click", () => {
        if (fPrioridad) fPrioridad.value = "";
        if (fDesde) fDesde.value = "";
        if (fHasta) fHasta.value = "";
        table.ajax.reload();
      });
    }

    qs("#btn_planificar_modal").addEventListener("click", async () => {
      const btn = qs("#btn_planificar_modal");
      const otId = Number(btn.getAttribute("data-ot") || 0);
      const folio = qs("#mdl_ot_folio").textContent || ("OT #" + otId);
      if (!otId) return;

      try {
        const res = await planificar(otId, folio);
        if (res.ok) {
          bootstrap.Modal.getOrCreateInstance(qs("#mdl_detalle_ot")).hide();
          table.ajax.reload(null, false);
        }
      } catch (e) {
        toastErr(e.message || "Error al planificar");
      }
    });
  });
})();
