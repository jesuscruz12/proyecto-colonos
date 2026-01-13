// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\partes\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list: () => BASE_URL + "admin/partes/recursos_list",
    meta: () => BASE_URL + "admin/partes/recursos_meta",
    get:  (id) => BASE_URL + "admin/partes/get?id=" + encodeURIComponent(id),
    save: () => BASE_URL + "admin/partes/save",
    del:  () => BASE_URL + "admin/partes/delete",
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
  let META = { clientes: [], unidades: [] };

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;")
      .replaceAll('"',"&quot;").replaceAll("'","&#039;");
  }

  function toastOk(msg){
    if (window.Swal) return Swal.fire({ icon:"success", title:"Listo", text: msg, timer: 1200, showConfirmButton:false });
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
    try { return JSON.parse(txt); } catch { return { ok:false, msg: txt || ("HTTP " + r.status) }; }
  }

  function openModal(el){ bootstrap.Modal.getOrCreateInstance(el).show(); }
  function closeModal(el){ bootstrap.Modal.getOrCreateInstance(el).hide(); }

  function badgeActivo(v){
    const ok = String(v) === "1" || v === 1;
    return ok ? `<span class="badge text-bg-success">sí</span>` : `<span class="badge text-bg-secondary">no</span>`;
  }

  function fillClientes(selectEl, allowEmpty = true){
    const cur = selectEl.value || "";
    selectEl.innerHTML = allowEmpty ? `<option value="">${allowEmpty === true ? "Todos" : "— Sin cliente —"}</option>` : "";
    (META.clientes || []).forEach(c => {
      const opt = document.createElement("option");
      opt.value = String(c.id);
      opt.textContent = c.nombre;
      selectEl.appendChild(opt);
    });
    selectEl.value = cur;
  }

  async function loadMeta(){
    const j = await jget(API.meta());
    if (j && j.ok && j.data){
      META = j.data;

      // filtros
      const fcli = qs("#f_cliente_id");
      if (fcli) {
        fcli.innerHTML = `<option value="">Todos</option>`;
        (META.clientes || []).forEach(c=>{
          const opt = document.createElement("option");
          opt.value = String(c.id);
          opt.textContent = c.nombre;
          fcli.appendChild(opt);
        });
      }

      // modal
      const mcli = qs("#parte_cliente");
      if (mcli) {
        mcli.innerHTML = `<option value="">— Sin cliente —</option>`;
        (META.clientes || []).forEach(c=>{
          const opt = document.createElement("option");
          opt.value = String(c.id);
          opt.textContent = c.nombre;
          mcli.appendChild(opt);
        });
      }
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
          d.f_cliente_id = qs("#f_cliente_id")?.value || "";
        }
      },
      columns: [
        { data:"id", width:"70px" },
        { data:"numero", width:"140px", render:(v)=> `<span class="badge text-bg-light border">${esc(v||"")}</span>` },
        { data:"descripcion", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"cliente_nombre", width:"220px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"material", width:"180px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"unidad", width:"90px", render:(v)=> `<span class="badge text-bg-info">${esc(v||"pza")}</span>` },
        { data:"activo", width:"90px", render:(v)=> badgeActivo(v) },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"140px",
          render: (_, __, row) => `
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-primary" data-act="edit" data-id="${row.id}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" data-act="del" data-id="${row.id}">
                <i class="bi bi-slash-circle"></i>
              </button>
            </div>
          `
        }
      ]
    });

    $jq(tbl).on("click", "button[data-act]", function(){
      const act = this.getAttribute("data-act");
      const id  = parseInt(this.getAttribute("data-id"),10);
      if (!id) return;
      if (act === "edit") return openEdit(id);
      if (act === "del")  return doDelete(id);
    });
  }

  function reloadDT(){ if (dt) dt.ajax.reload(null,false); }

  function openNew(){
    const form = qs("#frm_parte");
    form.reset();
    qs("#parte_id").value = "";
    qs("#parte_unidad").value = "pza";
    qs("#parte_activo").value = "1";
    qs("#parte_title").textContent = "Nueva parte";
    openModal(qs("#modal_parte"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#parte_id").value = d.id || "";
    qs("#parte_numero").value = d.numero || "";
    qs("#parte_descripcion").value = d.descripcion || "";
    qs("#parte_material").value = d.material || "";
    qs("#parte_unidad").value = d.unidad || "pza";
    qs("#parte_activo").value = String(d.activo ?? 1);
    qs("#parte_cliente").value = d.cliente_id ? String(d.cliente_id) : "";

    qs("#parte_title").textContent = "Editar parte";
    openModal(qs("#modal_parte"));
  }

  async function saveParte(){
    const form = qs("#frm_parte");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }
    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_parte"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function doDelete(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Desactivar",
      text: "¿Desactivar esta parte? (No se borra, solo se desactiva)",
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
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al desactivar");
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_save")?.addEventListener("click", saveParte);

    qs("#btn_filtrar")?.addEventListener("click", reloadDT);
    qs("#btn_limpiar")?.addEventListener("click", () => {
      if (qs("#f_activo")) qs("#f_activo").value = "";
      if (qs("#f_cliente_id")) qs("#f_cliente_id").value = "";
      reloadDT();
    });

    await loadMeta();
    initDT();
  });

})();
