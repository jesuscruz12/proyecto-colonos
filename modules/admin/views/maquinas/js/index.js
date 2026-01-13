// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\maquinas\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  // ✅ Normaliza BASE_URL: sin slash final y SIEMPRE arma /admin/...
  const BASE = String(window.BASE_URL || "").replace(/\/+$/, "");

  const API = {
    list: () => `${BASE}/admin/maquinas/recursos_list`,
    meta: () => `${BASE}/admin/maquinas/recursos_meta`,
    get:  (id) => `${BASE}/admin/maquinas/get?id=${encodeURIComponent(id)}`,
    save: () => `${BASE}/admin/maquinas/save`,
    del:  () => `${BASE}/admin/maquinas/delete`,
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
  let META = { calendarios: [], tipos: [] };

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
    // si tu app usa token CSRF por header, esto no estorba
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

  function badgeActivo(v){
    const ok = String(v) === "1" || v === 1;
    return ok
      ? `<span class="badge text-bg-success">sí</span>`
      : `<span class="badge text-bg-secondary">no</span>`;
  }

  function initTooltips(root = document){
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
      if (bootstrap.Tooltip.getInstance(el)) return;
      new bootstrap.Tooltip(el);
    });
  }

  function fillSelectTipos(){
    const sel = qs("#f_tipo");
    if (!sel) return;
    const cur = sel.value || "";
    sel.innerHTML = `<option value="">Todos</option>`;
    (META.tipos || []).forEach(t=>{
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = t;
      sel.appendChild(opt);
    });
    sel.value = cur;
  }

  function fillSelectCalendarios(){
    const sel = qs("#maq_calendario");
    if (!sel) return;
    const cur = sel.value || "";
    sel.innerHTML = `<option value="">— Sin calendario —</option>`;
    (META.calendarios || []).forEach(c=>{
      const opt = document.createElement("option");
      opt.value = String(c.id);
      opt.textContent = c.nombre;
      sel.appendChild(opt);
    });
    sel.value = cur;
  }

  async function loadMeta(){
    const j = await jget(API.meta());
    if (j && j.ok && j.data){
      META = j.data;
      fillSelectTipos();
      fillSelectCalendarios();
    }
  }

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
          d.f_activo = qs("#f_activo")?.value || "";
          d.f_tipo   = qs("#f_tipo")?.value || "";
        }
      },
      columns: [
        { data:"id", width:"60px" },
        { data:"codigo", width:"110px", render:(v)=> v ? `<span class="badge text-bg-light border">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"nombre", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"tipo", width:"140px", render:(v)=> v ? `<span class="badge text-bg-info">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"calendario_nombre", width:"160px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"activo", width:"90px", render:(v)=> badgeActivo(v) },
        { data:"procesos_count", width:"90px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"140px",
          render: (_, __, row) => {
            const id = row.id;
            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" data-act="edit" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Editar" type="button">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" data-act="del" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Desactivar" type="button">
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
      if (act === "del")  return doDelete(id);
    });
  }

  function reloadDT(){
    if (dt) dt.ajax.reload(null,false);
  }

  function openNew(){
    const form = qs("#frm_maq");
    form.reset();
    form.classList.remove("was-validated");
    qs("#maq_id").value = "";
    qs("#maq_activo").value = "1";
    qs("#maq_title").textContent = "Nueva máquina";
    fillSelectCalendarios();
    openModal(qs("#modal_maq"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#maq_id").value = d.id || "";
    qs("#maq_codigo").value = d.codigo || "";
    qs("#maq_nombre").value = d.nombre || "";
    qs("#maq_tipo").value = d.tipo || "";
    qs("#maq_activo").value = String(d.activo ?? 1);

    fillSelectCalendarios();
    qs("#maq_calendario").value = d.calendario_id ? String(d.calendario_id) : "";

    qs("#maq_title").textContent = "Editar máquina";
    openModal(qs("#modal_maq"));
  }

  async function saveMaq(){
    const form = qs("#frm_maq");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }

    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_maq"));
      await loadMeta();
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function doDelete(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Desactivar",
      text: "¿Desactivar esta máquina? (No se borra, solo se desactiva)",
      confirmText: "Sí, desactivar",
      cancelText: "Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.del(), fd);
    if (j && j.ok){
      toastOk("Desactivado");
      await loadMeta();
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al desactivar");
    }
  }

  function wireFiltros(){
    qs("#btn_filtrar")?.addEventListener("click", ()=> reloadDT());
    qs("#btn_limpiar")?.addEventListener("click", ()=>{
      if (qs("#f_activo")) qs("#f_activo").value = "";
      if (qs("#f_tipo")) qs("#f_tipo").value = "";
      reloadDT();
    });
  }

  document.addEventListener("DOMContentLoaded", async ()=>{
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_save")?.addEventListener("click", saveMaq);

    wireFiltros();
    await loadMeta();
    initDT();
  });

})();
