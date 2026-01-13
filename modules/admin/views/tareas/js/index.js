// modules/admin/views/tareas/js/index.js
// Tareas — TAKTIK (DataTables + Buttons + CRUD + Autocomplete) — PROD
// Requiere: BASE_URL, CSRF_TOKEN/TOKEN_CSRF, bootstrap, jQuery, DataTables, alertify (preferido)

(function () {
  const $ = (s) => document.querySelector(s);

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function csrf() {
    return window.CSRF_TOKEN || window.TOKEN_CSRF || "";
  }

  function askText(title, msg, placeholder = "") {
  // ✅ alertify prompt
  if (window.alertify && typeof alertify.prompt === "function") {
    return new Promise((resolve) => {
      alertify.prompt(
        title || "Captura",
        msg || "Escribe:",
        placeholder || "",
        function (_evt, value) { resolve(String(value || "").trim()); },
        function () { resolve(""); }
      );
    });
  }
  // fallback
  const v = window.prompt(msg || "Escribe:", placeholder || "");
  return Promise.resolve(String(v || "").trim());
}


  // ✅ Alertify-first (igual que otras vistas)
  function toastOk(msg) {
    if (window.alertify) return alertify.success(msg || "Listo.");
    console.log(msg);
    alert(msg || "Listo.");
  }
  function toastErr(msg) {
    if (window.alertify) return alertify.error(msg || "Error");
    console.error(msg);
    alert(msg || "Error");
  }

  async function jpost(url, formData) {
    const headers = { "X-CSRF-TOKEN": csrf() };
    const r = await fetch(url, { method: "POST", credentials: "same-origin", headers, body: formData });
    const txt = await r.text();
    let data = null;
    try { data = JSON.parse(txt); } catch { data = { ok: false, message: txt || ("HTTP " + r.status) }; }
    if (!r.ok || data.ok === false) throw new Error(data.message || ("HTTP " + r.status));
    return data;
  }

  async function jget(url) {
    const r = await fetch(url, { credentials: "same-origin" });
    const txt = await r.text();
    let data = null;
    try { data = JSON.parse(txt); } catch { data = { ok: false, message: txt || ("HTTP " + r.status) }; }
    if (!r.ok || data.ok === false) throw new Error(data.message || ("HTTP " + r.status));
    return data;
  }

  // -----------------------------------
  // Confirmación consistente
  // -----------------------------------
  let mdlConfirm = null;
  let confirmCb = null;

  function confirmDialog(title, body, dangerText = "Confirmar") {
    const el = $("#mdl_confirm");
    if (!el || !window.bootstrap) return Promise.resolve(window.confirm(body || "¿Confirmas?"));
    if (!mdlConfirm) mdlConfirm = new bootstrap.Modal(el, { backdrop: "static" });

    $("#mdl_confirm_title").textContent = title || "Confirmar acción";
    $("#mdl_confirm_body").textContent = body || "¿Confirmas esta acción?";
    $("#mdl_confirm_ok").textContent = dangerText || "Confirmar";

    return new Promise((resolve) => {
      confirmCb = resolve;
      mdlConfirm.show();
    });
  }

  function wireConfirmOk() {
    const okBtn = $("#mdl_confirm_ok");
    if (!okBtn) return;

    okBtn.addEventListener("click", function () {
      if (mdlConfirm) mdlConfirm.hide();
      if (typeof confirmCb === "function") {
        const cb = confirmCb; confirmCb = null;
        cb(true);
      }
    });

    const el = $("#mdl_confirm");
    if (el) {
      el.addEventListener("hidden.bs.modal", function () {
        if (typeof confirmCb === "function") {
          const cb = confirmCb; confirmCb = null;
          cb(false);
        }
      });
    }
  }

  // -----------------------------------
  // Tooltips
  // -----------------------------------
  function initTooltips() {
    if (!window.bootstrap) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      try { new bootstrap.Tooltip(el); } catch {}
    });
  }

  // -----------------------------------
  // Catálogos
  // -----------------------------------
  let CAT = { procesos: [], maquinas: [], estados: [] };

  function fillSelect(sel, items, placeholder, getVal, getText) {
    const el = $(sel);
    if (!el) return;
    const ph = placeholder ?? "Todos";
    el.innerHTML = `<option value="">${esc(ph)}</option>` + items.map(it => {
      const v = getVal(it);
      const t = getText(it);
      return `<option value="${esc(v)}">${esc(t)}</option>`;
    }).join("");
  }

  async function cargarCatalogos() {
    const data = await jget(BASE_URL + "admin/tareas/catalogos");
    CAT.procesos = data.procesos || [];
    CAT.maquinas = data.maquinas || [];
    CAT.estados  = data.estados || [];

    // filtros
    fillSelect("#f_proceso", CAT.procesos, "Todos", x => x.id, x => x.nombre);
    fillSelect("#f_maquina", CAT.maquinas, "Todas", x => x.id, x => x.nombre);
    fillSelect("#f_estado",  CAT.estados,  "Todos", x => x.id, x => x.nombre);

    // modal
    fillSelect("#t_proceso", CAT.procesos, "Selecciona", x => x.id, x => x.nombre);
    fillSelect("#t_maquina", CAT.maquinas, "Sin máquina", x => x.id, x => x.nombre);

    const est = $("#t_estado");
    if (est) est.innerHTML = CAT.estados.map(e => `<option value="${esc(e.id)}">${esc(e.nombre)}</option>`).join("");
  }

  // -----------------------------------
  // DataTable
  // -----------------------------------
  let dtApi = null;

  function badgeEstado(v) {
    const s = String(v || "");
    const map = {
      pendiente: "secondary",
      programada: "primary",
      en_proceso: "info",
      pausada: "warning",
      terminada: "success",
      bloqueada_calidad: "danger",
      scrap: "dark",
    };
    const cls = map[s] || "secondary";
    return `<span class="badge bg-${cls}">${esc(s || "—")}</span>`;
  }

  function fmt(v) {
    if (!v) return "—";
    return esc(String(v));
  }

  function initDataTable() {
  const table = $("#tbl_tareas");
  if (!table || !window.jQuery || !window.jQuery.fn.dataTable) {
    toastErr("DataTables no está cargado.");
    return;
  }

  const $t = window.jQuery(table);

  dtApi = $t.DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    dom:
      "<'row g-2'<'col-12 col-lg-6'B><'col-12 col-lg-6'f>>" +
      "<'row'<'col-12'tr>>" +
      "<'row g-2'<'col-12 col-lg-5'i><'col-12 col-lg-7'p>>",
    buttons: [
      { extend: "copy", text: "Copy" },
      { extend: "csv", text: "CSV" },
      { extend: "excel", text: "Excel" },
      { extend: "pdf", text: "PDF" },
      { extend: "print", text: "Print" },
      { extend: "colvis", text: "Column visibility" },
    ],
    ajax: {
      url: BASE_URL + "admin/tareas/data",
      type: "GET",
      data: function (d) {
        d.estado     = $("#f_estado")?.value || "";
        d.proceso_id = $("#f_proceso")?.value || "";
        d.maquina_id = $("#f_maquina")?.value || "";
        d.ot_estado  = $("#f_ot_estado")?.value || "";
        d.desde      = $("#f_desde")?.value || "";
        d.hasta      = $("#f_hasta")?.value || "";
      },
      error: function () {
        toastErr("Error al cargar tareas.");
      }
    },
    columns: [
      { data: "id" },
      { data: "folio_ot", render: (v, _t, row) => `<b>${esc(v || ("OT#" + row.orden_trabajo_id))}</b>` },
      { data: "cliente", render: (v) => esc(v || "—") },
      { data: "proceso", render: (v) => esc(v || "—") },
      { data: "secuencia" },
      { data: "cantidad", render: (v) => esc(v ?? "—") },
      { data: "maquina", render: (v) => esc(v || "—") },
      { data: "inicio_planeado", render: (v) => fmt(v) },
      { data: "fin_planeado", render: (v) => fmt(v) },
      { data: "estado", render: (v) => badgeEstado(v) },
      { data: "duracion_minutos", render: (v) => (v ? esc(v) + " min" : "—") },
      { data: "creado_en", render: (v) => fmt(v) },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (row) {
          const id = row.id;
          const est = String(row.estado || "");
          const disabledClosed = (est === "terminada" || est === "scrap");
          const dis = disabledClosed ? "disabled" : "";

          return `
            <div class="d-flex flex-wrap gap-1">
              <button class="btn btn-sm btn-outline-primary" data-act="edit" data-id="${id}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" data-act="del" data-id="${id}">
                <i class="bi bi-trash"></i>
              </button>

              <div class="vr mx-1"></div>

              <button class="btn btn-sm btn-success" ${dis} data-act="set_estado" data-estado="en_proceso" data-id="${id}" title="Iniciar">
                <i class="bi bi-play-fill"></i>
              </button>
              <button class="btn btn-sm btn-warning" ${dis} data-act="set_estado" data-estado="pausada" data-id="${id}" title="Pausar">
                <i class="bi bi-pause-fill"></i>
              </button>
              <button class="btn btn-sm btn-dark" ${dis} data-act="set_estado" data-estado="terminada" data-id="${id}" title="Terminar">
                <i class="bi bi-check2"></i>
              </button>
              <button class="btn btn-sm btn-danger" ${dis} data-act="set_estado" data-estado="bloqueada_calidad" data-id="${id}" title="Bloquear">
                <i class="bi bi-slash-circle"></i>
              </button>
            </div>
          `;
        }
      }
    ],
    order: [[0, "desc"]],
    language: { url: BASE_URL + "public/datatables/spanish.json" }
  });

  // ✅ handler correcto (act/id primero)
  $t.on("click", "button[data-act]", async function () {
    const act = this.getAttribute("data-act");
    const id = parseInt(this.getAttribute("data-id") || "0", 10);
    if (!id) return;

    try {
      if (act === "set_estado") {
        const estado = this.getAttribute("data-estado") || "";
        return await setEstadoRow(id, estado);
      }
      if (act === "edit") return await openEdit(id);
      if (act === "del") return await delRow(id);
    } catch (e) {
      toastErr(e.message || "Error");
    }
  });
}


  function reload() {
    if (!dtApi) return;
    dtApi.ajax.reload(null, false);
  }

  // -----------------------------------
  // Modal CRUD
  // -----------------------------------
  let mdl = null;

  function bsModal(id) {
    const el = document.getElementById(id);
    if (!el || !window.bootstrap) return null;
    return new bootstrap.Modal(el, { backdrop: "static" });
  }

  function setAlert(msg, type) {
    const a = $("#mdl_alert");
    if (!a) return;
    a.className = "alert py-2 alert-" + (type || "info");
    a.textContent = msg || "";
  }

  function toDatetimeLocal(v) {
    if (!v) return "";
    return String(v).replace(" ", "T").slice(0, 16);
  }
  function fromDatetimeLocal(v) {
    if (!v) return "";
    return String(v).replace("T", " ") + ":00";
  }

  function clearSuggest(box) {
    if (!box) return;
    box.classList.add("d-none");
    box.innerHTML = "";
  }

  function clearForm() {
    $("#t_id").value = "";

    // OT
    $("#t_ot_id").value = "";
    $("#t_ot_q").value = "";
    clearSuggest($("#ot_suggest"));

    $("#t_proceso").value = "";
    $("#t_estado").value = "pendiente";
    $("#t_secuencia").value = "1";
    $("#t_cantidad").value = "1";
    $("#t_maquina").value = "";
    $("#t_tipo_origen").value = "parte";
    $("#t_ini_plan").value = "";
    $("#t_fin_plan").value = "";
    $("#t_setup").value = "0";
    $("#t_segu").value = "0";
    $("#t_dur").value = "0";
    $("#t_motivo").value = "";

    // Avanzado (autocompletes)
    $("#t_item_id").value = "";
    $("#t_item_q").value = "";
    clearSuggest($("#item_suggest"));

    $("#t_parte_id").value = "";
    $("#t_parte_q").value = "";
    clearSuggest($("#parte_suggest"));

    $("#t_sub_id").value = "";
    $("#t_sub_q").value = "";
    clearSuggest($("#sub_suggest"));

    $("#t_prod_id").value = "";
    $("#t_prod_q").value = "";
    clearSuggest($("#prod_suggest"));
  }

  function applyTipoOrigenUI() {
    const tipo = ($("#t_tipo_origen")?.value || "parte").trim();

    const elParteId = $("#t_parte_id"), elParteQ = $("#t_parte_q");
    const elSubId   = $("#t_sub_id"),   elSubQ   = $("#t_sub_q");
    const elProdId  = $("#t_prod_id"),  elProdQ  = $("#t_prod_q");

    if (!elParteId || !elSubId || !elProdId) return;

    // habilita todo primero
    [elParteQ, elSubQ, elProdQ].forEach(x => { if (x) x.disabled = false; });

    if (tipo === "parte") {
      // solo parte
      elSubId.value = ""; if (elSubQ) elSubQ.value = "";
      elProdId.value = ""; if (elProdQ) elProdQ.value = "";
      if (elSubQ) elSubQ.disabled = true;
      if (elProdQ) elProdQ.disabled = true;
    } else if (tipo === "subensamble") {
      elParteId.value = ""; if (elParteQ) elParteQ.value = "";
      elProdId.value = ""; if (elProdQ) elProdQ.value = "";
      if (elParteQ) elParteQ.disabled = true;
      if (elProdQ) elProdQ.disabled = true;
    } else if (tipo === "kit_expandido") {
      elParteId.value = ""; if (elParteQ) elParteQ.value = "";
      elSubId.value   = ""; if (elSubQ) elSubQ.value = "";
      if (elParteQ) elParteQ.disabled = true;
      if (elSubQ) elSubQ.disabled = true;
    }

    // limpia sugerencias por orden
    clearSuggest($("#parte_suggest"));
    clearSuggest($("#sub_suggest"));
    clearSuggest($("#prod_suggest"));
  }

  function openCreate() {
    $("#mdl_title").textContent = "Nueva tarea";
    $("#mdl_subtitle").textContent = "Alta manual (OT + proceso + planeación).";
    clearForm();
    applyTipoOrigenUI();
    $("#btn_eliminar").style.display = "none";
    setAlert("Completa los datos.", "info");
    mdl.show();
  }

  async function openEdit(id) {
    const r = await jget(BASE_URL + "admin/tareas/get?id=" + encodeURIComponent(id));
    const t = r.data || {};

    $("#mdl_title").textContent = "Editar tarea";
    $("#mdl_subtitle").textContent = "ID: " + id;

    clearForm();

    $("#t_id").value = String(t.id || id);

    // OT (dejamos OT id y en Q mostramos OT#id si no tenemos folio)
    $("#t_ot_id").value = String(t.orden_trabajo_id || "");
    $("#t_ot_q").value = t.orden_trabajo_id ? ("OT#" + t.orden_trabajo_id) : "";

    $("#t_proceso").value = String(t.proceso_id || "");
    $("#t_estado").value = String(t.estado || "pendiente");
    $("#t_secuencia").value = String(t.secuencia || 1);
    $("#t_cantidad").value = String(t.cantidad || 1);
    $("#t_maquina").value = String(t.maquina_id || "");
    $("#t_tipo_origen").value = String(t.tipo_origen || "parte");

    $("#t_ini_plan").value = toDatetimeLocal(t.inicio_planeado);
    $("#t_fin_plan").value = toDatetimeLocal(t.fin_planeado);

    $("#t_setup").value = String(t.setup_minutos || 0);
    $("#t_segu").value = String(t.segundos_por_unidad || 0);
    $("#t_dur").value = String(t.duracion_minutos || 0);

    $("#t_motivo").value = String(t.motivo_bloqueo || "");

    // avanzado ids (texto placeholder simple)
    $("#t_item_id").value = String(t.item_id || "");
    $("#t_item_q").value = t.item_id ? ("Item#" + t.item_id) : "";

    $("#t_parte_id").value = String(t.parte_id || "");
    $("#t_parte_q").value = t.parte_id ? ("Parte#" + t.parte_id) : "";

    $("#t_sub_id").value = String(t.subensamble_id || "");
    $("#t_sub_q").value = t.subensamble_id ? ("Subensamble#" + t.subensamble_id) : "";

    $("#t_prod_id").value = String(t.producto_id || "");
    $("#t_prod_q").value = t.producto_id ? ("Producto#" + t.producto_id) : "";

    $("#btn_eliminar").style.display = "";
    applyTipoOrigenUI();
    setAlert("Edita y guarda.", "info");
    mdl.show();
  }

  function readForm() {
  const estado = $("#t_estado").value || "pendiente";
  const motivo = ($("#t_motivo").value || "").trim();
  if (estado === "bloqueada_calidad" && !motivo) {
    throw new Error("Motivo requerido cuando estado = bloqueada_calidad.");
  }

  const otId = parseInt($("#t_ot_id").value || "0", 10);
  if (!otId) throw new Error("Selecciona una OT válida.");

  const tipo = ($("#t_tipo_origen").value || "parte").trim();

  const itemId = parseInt($("#t_item_id").value || "0", 10) || "";
  const parteId = parseInt($("#t_parte_id").value || "0", 10) || "";
  const subId = parseInt($("#t_sub_id").value || "0", 10) || "";
  const prodId = parseInt($("#t_prod_id").value || "0", 10) || "";

  // regla UX fuerte
  let finalParte = parteId, finalSub = subId, finalProd = prodId;
  if (tipo === "parte") { finalSub = ""; finalProd = ""; }
  if (tipo === "subensamble") { finalParte = ""; finalProd = ""; }
  if (tipo === "kit_expandido") { finalParte = ""; finalSub = ""; }

  // ✅ aviso antes (porque backend ya lo exige)
  if (tipo === "kit_expandido" && !finalProd) {
    throw new Error("Selecciona un PRODUCTO cuando tipo_origen = kit_expandido.");
  }

  return {
    id: ($("#t_id").value || "").trim(),
    orden_trabajo_id: String(otId),
    proceso_id: ($("#t_proceso").value || "").trim(),
    estado,
    secuencia: ($("#t_secuencia").value || "1").trim(),
    cantidad: ($("#t_cantidad").value || "1").trim(),
    maquina_id: ($("#t_maquina").value || "").trim(),
    tipo_origen: tipo,
    inicio_planeado: fromDatetimeLocal($("#t_ini_plan").value || ""),
    fin_planeado: fromDatetimeLocal($("#t_fin_plan").value || ""),
    setup_minutos: ($("#t_setup").value || "0").trim(),
    segundos_por_unidad: ($("#t_segu").value || "0").trim(),
    duracion_minutos: ($("#t_dur").value || "0").trim(),
    motivo_bloqueo: motivo,
    item_id: itemId ? String(itemId) : "",
    parte_id: finalParte ? String(finalParte) : "",
    subensamble_id: finalSub ? String(finalSub) : "",
    producto_id: finalProd ? String(finalProd) : "",
  };
}


  async function save() {
    try {
      const v = readForm();

      const fd = new FormData();
      fd.append("csrf_token", csrf());

      if (v.id) fd.append("id", v.id);

      fd.append("orden_trabajo_id", v.orden_trabajo_id);
      fd.append("proceso_id", v.proceso_id);
      fd.append("estado", v.estado);
      fd.append("secuencia", v.secuencia);
      fd.append("cantidad", v.cantidad);
      fd.append("maquina_id", v.maquina_id);

      fd.append("tipo_origen", v.tipo_origen);
      fd.append("inicio_planeado", v.inicio_planeado);
      fd.append("fin_planeado", v.fin_planeado);

      fd.append("setup_minutos", v.setup_minutos);
      fd.append("segundos_por_unidad", v.segundos_por_unidad);
      fd.append("duracion_minutos", v.duracion_minutos);

      fd.append("motivo_bloqueo", v.motivo_bloqueo);

      if (v.item_id) fd.append("item_id", v.item_id);
      if (v.parte_id) fd.append("parte_id", v.parte_id);
      if (v.subensamble_id) fd.append("subensamble_id", v.subensamble_id);
      if (v.producto_id) fd.append("producto_id", v.producto_id);

      const url = v.id ? (BASE_URL + "admin/tareas/update") : (BASE_URL + "admin/tareas/create");
      await jpost(url, fd);

      mdl.hide();
      toastOk(v.id ? "Tarea actualizada" : "Tarea creada");
      reload();
    } catch (e) {
      setAlert(e.message || "Error al guardar.", "danger");
      toastErr(e.message || "Error");
    }
  }

  async function delRow(id) {
    const ok = await confirmDialog("Eliminar", "¿Eliminar esta tarea? Esta acción no se puede deshacer.", "Sí, eliminar");
    if (!ok) return;

    const fd = new FormData();
    fd.append("csrf_token", csrf());
    fd.append("id", String(id));

    await jpost(BASE_URL + "admin/tareas/delete", fd);
    toastOk("Tarea eliminada");
    reload();
  }


  async function setEstadoRow(id, estado) {
  const mapTxt = {
    en_proceso: "Iniciar",
    pausada: "Pausar",
    terminada: "Terminar",
    bloqueada_calidad: "Bloquear (calidad)",
  };
  const label = mapTxt[estado] || estado;

  let motivo = "";
  if (estado === "bloqueada_calidad") {
    motivo = await askText("Bloquear tarea", "Motivo del bloqueo:", "");
    if (!motivo) return toastErr("Cancelado: motivo requerido.");
  }

  const ok = await confirmDialog("Confirmar", `¿${label} esta tarea?`, `Sí, ${label}`);
  if (!ok) return;

  const fd = new FormData();
  fd.append("csrf_token", csrf());
  fd.append("id", String(id));
  fd.append("estado", String(estado));
  if (motivo) fd.append("motivo", motivo);

  await jpost(BASE_URL + "admin/tareas/set_estado", fd);

  toastOk("Estado actualizado");
  reload();
}


  // -----------------------------------
  // Autocomplete helper (reusable)
  // -----------------------------------
  function wireSuggest({ inputSel, boxSel, fetchUrl, onPick, minLen = 2, delay = 250, disabledWhen = null }) {
    const inp = $(inputSel);
    const box = $(boxSel);
    if (!inp || !box) return;

    let timer = null;

    function hide() { box.classList.add("d-none"); box.innerHTML = ""; }
    function show() { box.classList.remove("d-none"); }

    inp.addEventListener("input", () => {
      if (typeof disabledWhen === "function" && disabledWhen()) return hide();

      if (timer) clearTimeout(timer);
      const q = (inp.value || "").trim();
      if (q.length < minLen) return hide();

      timer = setTimeout(async () => {
        try {
          const j = await jget(fetchUrl(q));
          const rows = j.data || [];
          if (!rows.length) return hide();

          box.innerHTML = rows.map(r => `
            <button type="button" class="list-group-item list-group-item-action" data-id="${r.id}">
              ${esc(r.label)}
            </button>
          `).join("");
          show();
        } catch (e) {
          hide();
        }
      }, delay);
    });

    box.addEventListener("click", (e) => {
      const b = e.target.closest("button[data-id]");
      if (!b) return;
      const id = b.getAttribute("data-id");
      const label = b.textContent.trim();
      hide();
      onPick({ id, label });
    });

    document.addEventListener("click", (e) => {
      if (e.target === inp || box.contains(e.target)) return;
      hide();
    });

    return { hide };
  }

  function wireAutocompletes() {
    // OT
    wireSuggest({
      inputSel: "#t_ot_q",
      boxSel: "#ot_suggest",
      fetchUrl: (q) => BASE_URL + "admin/tareas/entidades_buscar?tipo=ot&q=" + encodeURIComponent(q),
      onPick: ({ id, label }) => {
        $("#t_ot_id").value = String(id);
        $("#t_ot_q").value = label;
        // al cambiar OT, limpia item
        $("#t_item_id").value = "";
        $("#t_item_q").value = "";
        clearSuggest($("#item_suggest"));
      }
    });

    // Items OT (requiere OT)
    wireSuggest({
      inputSel: "#t_item_q",
      boxSel: "#item_suggest",
      disabledWhen: () => !parseInt($("#t_ot_id").value || "0", 10),
      fetchUrl: (q) => {
        const otId = parseInt($("#t_ot_id").value || "0", 10);
        return BASE_URL + "admin/tareas/entidades_buscar?tipo=item&ot_id=" + encodeURIComponent(otId) + "&q=" + encodeURIComponent(q);
      },
      onPick: ({ id, label }) => {
        $("#t_item_id").value = String(id);
        $("#t_item_q").value = label;
      }
    });

    // Parte
    wireSuggest({
      inputSel: "#t_parte_q",
      boxSel: "#parte_suggest",
      disabledWhen: () => ($("#t_parte_q")?.disabled === true),
      fetchUrl: (q) => BASE_URL + "admin/tareas/entidades_buscar?tipo=parte&q=" + encodeURIComponent(q),
      onPick: ({ id, label }) => {
        $("#t_parte_id").value = String(id);
        $("#t_parte_q").value = label;
      }
    });

    // Subensamble
    wireSuggest({
      inputSel: "#t_sub_q",
      boxSel: "#sub_suggest",
      disabledWhen: () => ($("#t_sub_q")?.disabled === true),
      fetchUrl: (q) => BASE_URL + "admin/tareas/entidades_buscar?tipo=subensamble&q=" + encodeURIComponent(q),
      onPick: ({ id, label }) => {
        $("#t_sub_id").value = String(id);
        $("#t_sub_q").value = label;
      }
    });

    // Producto
    wireSuggest({
      inputSel: "#t_prod_q",
      boxSel: "#prod_suggest",
      disabledWhen: () => ($("#t_prod_q")?.disabled === true),
      fetchUrl: (q) => BASE_URL + "admin/tareas/entidades_buscar?tipo=producto&q=" + encodeURIComponent(q),
      onPick: ({ id, label }) => {
        $("#t_prod_id").value = String(id);
        $("#t_prod_q").value = label;
      }
    });
  }

  // -----------------------------------
  // Init
  // -----------------------------------
  document.addEventListener("DOMContentLoaded", async function () {
    wireConfirmOk();
    initTooltips();

    mdl = bsModal("mdl_tarea");

    try {
      await cargarCatalogos();
    } catch (e) {
      toastErr("No se pudieron cargar catálogos.");
    }

    initDataTable();
    wireAutocompletes();

    $("#btn_nueva")?.addEventListener("click", openCreate);
    $("#btn_guardar")?.addEventListener("click", save);

    $("#t_tipo_origen")?.addEventListener("change", applyTipoOrigenUI);

    $("#btn_eliminar")?.addEventListener("click", async function () {
      const id = parseInt($("#t_id").value || "0", 10);
      if (!id) return;
      await delRow(id);
      if (mdl) mdl.hide();
    });

    // filtros -> reload
    ["#f_estado","#f_proceso","#f_maquina","#f_ot_estado","#f_desde","#f_hasta"].forEach(sel => {
      $(sel)?.addEventListener("change", () => reload());
    });

    $("#btn_reset")?.addEventListener("click", function () {
      $("#f_estado").value = "";
      $("#f_proceso").value = "";
      $("#f_maquina").value = "";
      $("#f_ot_estado").value = "";
      $("#f_desde").value = "";
      $("#f_hasta").value = "";
      reload();
    });
  });
})();
