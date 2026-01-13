// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\rutas\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list:       () => BASE_URL + "admin/rutas/recursos_list",
    get:        (id) => BASE_URL + "admin/rutas/get?id=" + encodeURIComponent(id),
    save:       () => BASE_URL + "admin/rutas/save",
    deactivate: () => BASE_URL + "admin/rutas/deactivate",
    vigente:    () => BASE_URL + "admin/rutas/vigente",

    opsMeta:    () => BASE_URL + "admin/rutas/ops_meta",
    opsList:    (vrid) => BASE_URL + "admin/rutas/ops_list?version_ruta_id=" + encodeURIComponent(vrid),
    opGet:      (id) => BASE_URL + "admin/rutas/op_get?id=" + encodeURIComponent(id),
    opSave:     () => BASE_URL + "admin/rutas/op_save",
    opDel:      () => BASE_URL + "admin/rutas/op_delete",
  };

  const DT_LANG_ES = {
    processing: "Procesando...",
    search: "Buscar:",
    lengthMenu: "Mostrar _MENU_",
    info: "Mostrando _START_ a _END_ de _TOTAL_",
    infoEmpty: "Mostrando 0 a 0 de 0",
    infoFiltered: "(filtrado de _MAX_ total)",
    loadingRecords: "Cargando...",
    zeroRecords: "Sin resultados",
    emptyTable: "Sin datos",
    paginate: { first:"Primero", previous:"Anterior", next:"Siguiente", last:"Último" },
    buttons: { colvis: "Columnas" }
  };

  let dt = null;
  let dtOps = null;
  let META = { procesos: [] };

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;")
      .replaceAll('"',"&quot;").replaceAll("'","&#039;");
  }

  function toastOk(msg){
    if (window.Swal) return Swal.fire({ icon:"success", title:"Listo", text: msg, timer: 1400, showConfirmButton:false });
    if (window.alertify) return alertify.success(msg);
    alert(msg);
  }

  function toastErr(msg){
    if (window.Swal) return Swal.fire({ icon:"error", title:"Error", text: msg });
    if (window.alertify) return alertify.error(msg);
    alert(msg);
  }

  async function askConfirm(opts){
    const title = opts?.title || "Confirmar";
    const text  = opts?.text  || "¿Continuar?";
    const icon  = opts?.icon  || "question";
    const confirmText = opts?.confirmText || "Sí";
    const cancelText  = opts?.cancelText  || "Cancelar";

    if (window.Swal) {
      const r = await Swal.fire({
        icon,
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
      });
      return !!r.isConfirmed;
    }

    if (window.alertify && typeof alertify.confirm === "function") {
      return await new Promise((resolve) => {
        alertify.confirm(title, text, () => resolve(true), () => resolve(false));
      });
    }

    return confirm(text);
  }

  function fdFromForm(form){
    const fd = new FormData(form);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));
    return fd;
  }

  async function jget(url){
    const r = await fetch(url, { credentials:"same-origin" });
    const txt = await r.text();
    try { return JSON.parse(txt); } catch { return { ok:false, msg: txt || "Respuesta inválida" }; }
  }

  async function jpost(url, fd){
    const r = await fetch(url, {
      method:"POST",
      body: fd,
      credentials:"same-origin",
      headers: { "X-CSRF-TOKEN": (window.CSRF_TOKEN || window.TOKEN_CSRF || "") }
    });
    const txt = await r.text();
    let j = null;
    try { j = JSON.parse(txt); } catch { j = { ok:false, msg: txt || ("HTTP " + r.status) }; }
    return j;
  }

  function openModal(el){ bootstrap.Modal.getOrCreateInstance(el).show(); }
  function closeModal(el){ bootstrap.Modal.getOrCreateInstance(el).hide(); }

  function initTooltips(root = document){
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
      if (bootstrap.Tooltip.getInstance(el)) return;
      new bootstrap.Tooltip(el);
    });
  }

  function badgeVigente(v){
    const ok = String(v) === "1" || v === 1;
    return ok ? `<span class="badge text-bg-success">sí</span>`
              : `<span class="badge text-bg-secondary">no</span>`;
  }

  // =========================
  // Meta ops (procesos)
  // =========================
  async function loadMeta(){
    const j = await jget(API.opsMeta());
    if (j && j.ok && j.data) {
      META = j.data;
      fillProcesosSelect();
    }
  }

  function fillProcesosSelect(){
    const sel = qs("#op_proceso");
    if (!sel) return;
    const cur = sel.value || "";
    sel.innerHTML = `<option value="">— Selecciona —</option>`;
    (META.procesos || []).forEach(p=>{
      const opt = document.createElement("option");
      opt.value = String(p.id);
      opt.textContent = p.nombre + (String(p.activo)==="1" ? "" : " (inactivo)");
      sel.appendChild(opt);
    });
    sel.value = cur;
  }

  // =========================
  // DT Versiones
  // =========================
  function initDT(){
    const tbl = qs("#tbl");
    if (!tbl) return;

    if (!$jq || !$jq.fn || !$jq.fn.DataTable) {
      toastErr("DataTables no está cargado (revisa footer).");
      return;
    }

    dt = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      order: [[7,"desc"]],
      dom: "Bfrtip",
      buttons: [
        { extend:"copyHtml5", text:"Copy" },
        { extend:"csvHtml5", text:"CSV" },
        { extend:"excelHtml5", text:"Excel" },
        { extend:"pdfHtml5", text:"PDF", orientation:"landscape", pageSize:"A4" },
        { extend:"print", text:"Print" },
        { extend:"colvis", text:"Columnas" }
      ],
      language: DT_LANG_ES,
      ajax: {
        url: API.list(),
        type: "GET",
        data: function (d) {
          d.f_tipo       = qs("#f_tipo")?.value || "";
          d.f_entidad_id = qs("#f_entidad_id")?.value || "";
          d.f_vigente    = qs("#f_vigente")?.value || "";
        }
      },
      columns: [
        { data:"id", width:"70px" },
        { data:"entidad_tipo", width:"120px", render:(v)=> `<span class="badge text-bg-info">${esc(v||"")}</span>` },
        { data:"entidad_id", width:"110px", render:(v)=> `<span class="badge text-bg-light border">${esc(v||"")}</span>` },
        { data:"version", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"vigente", width:"90px", render:(v)=> badgeVigente(v) },
        { data:"ops_count", width:"80px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        { data:"fecha_vigencia", width:"120px", render:(v)=> v ? `<span class="small">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"210px",
          render: (_, __, row) => {
            const id = row.id;
            const label = `${row.entidad_tipo} #${row.entidad_id} · ${row.version}`;
            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" data-act="edit" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>

                <button class="btn btn-outline-secondary" data-act="ops" data-id="${id}"
                  data-label="${esc(label)}"
                  data-bs-toggle="tooltip" data-bs-title="Operaciones">
                  <i class="bi bi-list-ol"></i>
                </button>

                <button class="btn btn-outline-success" data-act="vig" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Hacer vigente">
                  <i class="bi bi-check2-circle"></i>
                </button>

                <button class="btn btn-outline-danger" data-act="off" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Desactivar (vigente=0)">
                  <i class="bi bi-slash-circle"></i>
                </button>
              </div>
            `;
          }
        }
      ],
      drawCallback: function(){
        initTooltips(document);
      }
    });

    $jq(tbl).on("click", "button[data-act]", function(){
      const act = this.getAttribute("data-act");
      const id  = parseInt(this.getAttribute("data-id"),10);
      if (!id) return;

      if (act === "edit") return openEdit(id);
      if (act === "ops")  return openOps(id, this.getAttribute("data-label") || "");
      if (act === "vig")  return makeVigente(id);
      if (act === "off")  return deactivate(id);
    });
  }

  function reloadDT(){
    if (dt) dt.ajax.reload(null,false);
  }

  // =========================
  // CRUD Versiones
  // =========================
  function openNew(){
    const form = qs("#frm_vr");
    form.reset();
    qs("#vr_id").value = "";
    qs("#vr_tipo").value = "parte";
    qs("#vr_vigente").value = "0";
    qs("#vr_title").textContent = "Nueva versión";
    openModal(qs("#modal_vr"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#vr_id").value = d.id || "";
    qs("#vr_tipo").value = d.entidad_tipo || "parte";
    qs("#vr_entidad_id").value = d.entidad_id || "";
    qs("#vr_version").value = d.version || "";
    qs("#vr_vigente").value = String(d.vigente ?? 0);
    qs("#vr_fecha").value = d.fecha_vigencia || "";
    qs("#vr_notas").value = d.notas || "";

    qs("#vr_title").textContent = "Editar versión";
    openModal(qs("#modal_vr"));
  }

  async function saveVersion(){
    const form = qs("#frm_vr");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }
    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_vr"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function deactivate(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Desactivar",
      text: "Esto deja vigente=0. ¿Continuar?",
      confirmText: "Sí, desactivar",
      cancelText: "Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));
    const j = await jpost(API.deactivate(), fd);

    if (j && j.ok){ toastOk("Desactivado"); reloadDT(); }
    else toastErr(j?.msg || "Error al desactivar");
  }

  async function makeVigente(id){
    const ok = await askConfirm({
      icon: "question",
      title: "Hacer vigente",
      text: "Dejará SOLO esta versión como vigente para esa entidad. ¿Continuar?",
      confirmText: "Sí, hacer vigente",
      cancelText: "Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("fecha_vigencia", (new Date()).toISOString().slice(0,10));
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.vigente(), fd);
    if (j && j.ok){ toastOk("Vigente actualizado"); reloadDT(); }
    else toastErr(j?.msg || "Error al actualizar vigente");
  }

  // =========================
  // Operaciones
  // =========================
  async function openOps(vrid, label){
    qs("#ops_version_ruta_id").value = String(vrid);
    qs("#ops_vr_id").textContent = String(vrid);
    qs("#ops_vr_label").textContent = label || "";

    await loadMeta();
    initOpsDT(vrid);

    openModal(qs("#modal_ops"));
  }

  function initOpsDT(vrid){
    const tbl = qs("#tbl_ops");
    if (!tbl) return;

    if (!$jq || !$jq.fn || !$jq.fn.DataTable) {
      toastErr("DataTables no está cargado.");
      return;
    }

    // si ya existe, solo recarga con nuevo vrid
    if (dtOps) {
      dtOps.ajax.url(API.opsList(vrid)).load();
      return;
    }

    dtOps = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      order: [[0,"asc"]],
      language: DT_LANG_ES,
      ajax: { url: API.opsList(vrid), type: "GET" },
      columns: [
        { data:"secuencia", width:"90px" },
        { data:"proceso_nombre", render:(v,row,type,meta)=> `<b>${esc(v||"")}</b>` },
        { data:"minutos", width:"80px" },
        { data:"segundos", width:"80px" },
        { data:"setup_minutos", width:"90px" },
        { data:"notas", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"120px",
          render: (_, __, row) => `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-oact="edit" data-id="${row.id}"
                data-bs-toggle="tooltip" data-bs-title="Editar">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" data-oact="del" data-id="${row.id}"
                data-bs-toggle="tooltip" data-bs-title="Eliminar">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          `
        }
      ],
      drawCallback: function(){ initTooltips(document); }
    });

    $jq(tbl).on("click", "button[data-oact]", function(){
      const act = this.getAttribute("data-oact");
      const id  = parseInt(this.getAttribute("data-id"),10);
      if (!id) return;

      if (act === "edit") return openOpEdit(id);
      if (act === "del")  return deleteOp(id);
    });
  }

  function reloadOps(){
    if (dtOps) dtOps.ajax.reload(null,false);
  }

  function openOpNew(){
    const vrid = parseInt(qs("#ops_version_ruta_id").value || "0", 10);
    if (!vrid) return;

    const form = qs("#frm_op");
    form.reset();
    qs("#op_id").value = "";
    qs("#op_version_ruta_id").value = String(vrid);
    qs("#op_min").value = "0";
    qs("#op_seg").value = "0";
    qs("#op_setup").value = "0";

    fillProcesosSelect();
    qs("#op_title").textContent = "Nueva operación";
    openModal(qs("#modal_op"));
  }

  async function openOpEdit(id){
    const j = await jget(API.opGet(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#op_id").value = d.id || "";
    qs("#op_version_ruta_id").value = String(d.version_ruta_id || "");
    qs("#op_sec").value = d.secuencia ?? "";
    qs("#op_min").value = d.minutos ?? 0;
    qs("#op_seg").value = d.segundos ?? 0;
    qs("#op_setup").value = d.setup_minutos ?? 0;
    qs("#op_notas").value = d.notas || "";

    fillProcesosSelect();
    qs("#op_proceso").value = String(d.proceso_id || "");

    qs("#op_title").textContent = "Editar operación";
    openModal(qs("#modal_op"));
  }

  async function saveOp(){
    const form = qs("#frm_op");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }

    const fd = fdFromForm(form);
    const j = await jpost(API.opSave(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_op"));
      reloadOps();
      reloadDT(); // actualiza conteo ops
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function deleteOp(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Eliminar operación",
      text: "Esto borra la operación de la ruta. ¿Continuar?",
      confirmText: "Sí, eliminar",
      cancelText: "Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.opDel(), fd);
    if (j && j.ok){
      toastOk("Eliminado");
      reloadOps();
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al eliminar");
    }
  }

  // =========================
  // filtros
  // =========================
  function wireFiltros(){
    qs("#btn_filtrar")?.addEventListener("click", ()=> reloadDT());
    qs("#btn_limpiar")?.addEventListener("click", ()=>{
      if (qs("#f_tipo")) qs("#f_tipo").value = "";
      if (qs("#f_entidad_id")) qs("#f_entidad_id").value = "";
      if (qs("#f_vigente")) qs("#f_vigente").value = "";
      reloadDT();
    });
  }

  document.addEventListener("DOMContentLoaded", async ()=>{
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_vr_save")?.addEventListener("click", saveVersion);

    qs("#btn_op_new")?.addEventListener("click", openOpNew);
    qs("#btn_op_save")?.addEventListener("click", saveOp);

    wireFiltros();
    initDT();
  });

})();
