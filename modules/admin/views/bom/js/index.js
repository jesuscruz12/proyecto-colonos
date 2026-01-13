// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\bom\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list:       () => BASE_URL + "admin/bom/recursos_list",
    get:        (id) => BASE_URL + "admin/bom/get?id=" + encodeURIComponent(id),
    save:       () => BASE_URL + "admin/bom/save",
    deactivate: () => BASE_URL + "admin/bom/deactivate",
    vigente:    () => BASE_URL + "admin/bom/vigente",

    compsMeta:  () => BASE_URL + "admin/bom/comps_meta",
    compsList:  (vbId) => BASE_URL + "admin/bom/comps_list?version_bom_id=" + encodeURIComponent(vbId),
    compGet:    (id) => BASE_URL + "admin/bom/comp_get?id=" + encodeURIComponent(id),
    compSave:   () => BASE_URL + "admin/bom/comp_save",
    compDel:    () => BASE_URL + "admin/bom/comp_delete",
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
  let dtComps = null;
  let META = { partes: [], subensambles: [] };

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
      const r = await Swal.fire({ icon, title, text, showCancelButton:true, confirmButtonText:confirmText, cancelButtonText:cancelText });
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
  // META componentes
  // =========================
  async function loadMeta(){
    const j = await jget(API.compsMeta());
    if (j && j.ok && j.data) {
      META = j.data;
      fillSelects();
    }
  }

  function fillSelects(){
    const selP = qs("#comp_parte");
    const selS = qs("#comp_sub");
    if (selP) {
      const cur = selP.value || "";
      selP.innerHTML = `<option value="">— Selecciona parte —</option>`;
      (META.partes || []).forEach(p=>{
        const opt = document.createElement("option");
        opt.value = String(p.id);
        opt.textContent = `${p.numero}${p.descripcion ? " — " + p.descripcion : ""}${String(p.activo)==="1" ? "" : " (inactiva)"}`;
        selP.appendChild(opt);
      });
      selP.value = cur;
    }
    if (selS) {
      const cur = selS.value || "";
      selS.innerHTML = `<option value="">— Selecciona subensamble —</option>`;
      (META.subensambles || []).forEach(s=>{
        const opt = document.createElement("option");
        opt.value = String(s.id);
        opt.textContent = `${s.nombre}${s.descripcion ? " — " + s.descripcion : ""}${String(s.activo)==="1" ? "" : " (inactivo)"}`;
        selS.appendChild(opt);
      });
      selS.value = cur;
    }
  }

  function syncTipoSelector(){
    const tipo = qs("#comp_tipo")?.value || "parte";
    const selP = qs("#comp_parte");
    const selS = qs("#comp_sub");

    if (!selP || !selS) return;

    if (tipo === "parte") {
      selP.classList.remove("d-none");
      selS.classList.add("d-none");
      selS.value = "";
      selP.setAttribute("required","required");
      selS.removeAttribute("required");
    } else {
      selS.classList.remove("d-none");
      selP.classList.add("d-none");
      selP.value = "";
      selS.setAttribute("required","required");
      selP.removeAttribute("required");
    }
  }

  // =========================
  // DT Versiones BOM
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
        { data:"entidad_tipo", width:"130px", render:(v)=> `<span class="badge text-bg-info">${esc(v||"")}</span>` },
        { data:"entidad_id", width:"110px", render:(v)=> `<span class="badge text-bg-light border">${esc(v||"")}</span>` },
        { data:"version", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"vigente", width:"90px", render:(v)=> badgeVigente(v) },
        { data:"comps_count", width:"110px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        { data:"fecha_vigencia", width:"120px", render:(v)=> v ? `<span class="small">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"230px",
          render: (_, __, row) => {
            const id = row.id;
            const label = `${row.entidad_tipo} #${row.entidad_id} · ${row.version}`;
            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" data-act="edit" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>

                <button class="btn btn-outline-secondary" data-act="comps" data-id="${id}"
                  data-label="${esc(label)}"
                  data-bs-toggle="tooltip" data-bs-title="Componentes">
                  <i class="bi bi-diagram-3"></i>
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

      if (act === "edit")  return openEdit(id);
      if (act === "comps") return openComps(id, this.getAttribute("data-label") || "");
      if (act === "vig")   return makeVigente(id);
      if (act === "off")   return deactivate(id);
    });
  }

  function reloadDT(){
    if (dt) dt.ajax.reload(null,false);
  }

  // =========================
  // CRUD Versiones BOM
  // =========================
  function openNew(){
    const form = qs("#frm_vb");
    form.reset();
    qs("#vb_id").value = "";
    qs("#vb_tipo").value = "producto";
    qs("#vb_vigente").value = "0";
    qs("#vb_title").textContent = "Nueva versión BOM";
    openModal(qs("#modal_vb"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#vb_id").value = d.id || "";
    qs("#vb_tipo").value = d.entidad_tipo || "producto";
    qs("#vb_entidad_id").value = d.entidad_id || "";
    qs("#vb_version").value = d.version || "";
    qs("#vb_vigente").value = String(d.vigente ?? 0);
    qs("#vb_fecha").value = d.fecha_vigencia || "";
    qs("#vb_notas").value = d.notas || "";

    qs("#vb_title").textContent = "Editar versión BOM";
    openModal(qs("#modal_vb"));
  }

  async function saveVersion(){
    const form = qs("#frm_vb");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }
    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_vb"));
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
  // Componentes
  // =========================
  async function openComps(vbId, label){
    qs("#comps_version_bom_id").value = String(vbId);
    qs("#comps_vb_id").textContent = String(vbId);
    qs("#comps_vb_label").textContent = label || "";

    await loadMeta();
    syncTipoSelector();
    initCompsDT(vbId);

    openModal(qs("#modal_comps"));
  }

  function initCompsDT(vbId){
    const tbl = qs("#tbl_comps");
    if (!tbl) return;

    if (!$jq || !$jq.fn || !$jq.fn.DataTable) {
      toastErr("DataTables no está cargado.");
      return;
    }

    if (dtComps) {
      dtComps.ajax.url(API.compsList(vbId)).load();
      return;
    }

    dtComps = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      order: [[0,"desc"]],
      language: DT_LANG_ES,
      ajax: { url: API.compsList(vbId), type: "GET" },
      columns: [
        { data:"id", width:"70px" },
        { data:"componente_tipo", width:"120px", render:(v)=> `<span class="badge text-bg-info">${esc(v||"")}</span>` },
        {
          data:null,
          render: (_, __, r) => {
            if (String(r.componente_tipo) === "subensamble") {
              return `<b>${esc(r.sub_nombre||"")}</b><div class="small text-muted">${esc(r.sub_desc||"")}</div>`;
            }
            // parte
            const top = `${r.parte_numero||""}${r.parte_unidad ? " ("+r.parte_unidad+")" : ""}`;
            return `<b>${esc(top)}</b><div class="small text-muted">${esc(r.parte_desc||"")}</div>`;
          }
        },
        { data:"cantidad", width:"110px" },
        { data:"merma_pct", width:"110px" },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"120px",
          render: (_, __, row) => `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-cact="edit" data-id="${row.id}"
                data-bs-toggle="tooltip" data-bs-title="Editar">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" data-cact="del" data-id="${row.id}"
                data-bs-toggle="tooltip" data-bs-title="Eliminar">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          `
        }
      ],
      drawCallback: function(){ initTooltips(document); }
    });

    $jq(tbl).on("click", "button[data-cact]", function(){
      const act = this.getAttribute("data-cact");
      const id  = parseInt(this.getAttribute("data-id"),10);
      if (!id) return;

      if (act === "edit") return openCompEdit(id);
      if (act === "del")  return deleteComp(id);
    });
  }

  function reloadComps(){
    if (dtComps) dtComps.ajax.reload(null,false);
  }

  function openCompNew(){
    const vbId = parseInt(qs("#comps_version_bom_id").value || "0", 10);
    if (!vbId) return;

    const form = qs("#frm_comp");
    form.reset();
    qs("#comp_id").value = "";
    qs("#comp_version_bom_id").value = String(vbId);
    qs("#comp_tipo").value = "parte";
    qs("#comp_cant").value = "1.0000";
    qs("#comp_merma").value = "0.000";

    fillSelects();
    syncTipoSelector();

    qs("#comp_title").textContent = "Nuevo componente";
    openModal(qs("#modal_comp"));
  }

  async function openCompEdit(id){
    const j = await jget(API.compGet(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#comp_id").value = d.id || "";
    qs("#comp_version_bom_id").value = String(d.version_bom_id || "");
    qs("#comp_tipo").value = d.componente_tipo || "parte";
    qs("#comp_cant").value = d.cantidad ?? "1.0000";
    qs("#comp_merma").value = d.merma_pct ?? "0.000";

    fillSelects();
    syncTipoSelector();

    qs("#comp_parte").value = String(d.parte_id || "");
    qs("#comp_sub").value = String(d.subensamble_id || "");

    qs("#comp_title").textContent = "Editar componente";
    openModal(qs("#modal_comp"));
  }

  async function saveComp(){
    const form = qs("#frm_comp");
    syncTipoSelector(); // asegura required correcto

    // validación manual extra
    const tipo = qs("#comp_tipo")?.value || "parte";
    if (tipo === "parte" && !qs("#comp_parte")?.value) return toastErr("Selecciona una parte");
    if (tipo === "subensamble" && !qs("#comp_sub")?.value) return toastErr("Selecciona un subensamble");

    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }

    const fd = fdFromForm(form);
    const j = await jpost(API.compSave(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_comp"));
      reloadComps();
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function deleteComp(id){
    const ok = await askConfirm({
      icon:"warning",
      title:"Eliminar componente",
      text:"Esto borra el componente del BOM. ¿Continuar?",
      confirmText:"Sí, eliminar",
      cancelText:"Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.compDel(), fd);
    if (j && j.ok){
      toastOk("Eliminado");
      reloadComps();
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
    qs("#btn_vb_save")?.addEventListener("click", saveVersion);

    qs("#btn_comp_new")?.addEventListener("click", openCompNew);
    qs("#btn_comp_save")?.addEventListener("click", saveComp);

    qs("#comp_tipo")?.addEventListener("change", syncTipoSelector);

    wireFiltros();
    initDT();
  });

})();
