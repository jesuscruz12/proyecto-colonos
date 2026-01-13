// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\clientes\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list: () => BASE_URL + "admin/clientes/recursos_list",
    get:  (id) => BASE_URL + "admin/clientes/get?id=" + encodeURIComponent(id),
    save: () => BASE_URL + "admin/clientes/save",
    del:  () => BASE_URL + "admin/clientes/delete",
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
      order: [[6,"desc"]],
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
        }
      },
      columns: [
        { data:"id", width:"70px" },
        { data:"codigo", width:"120px", render:(v)=> v ? `<span class="badge text-bg-light border">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"nombre", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"email", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"telefono", width:"140px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
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
    const form = qs("#frm_cli");
    form.reset();
    qs("#cli_id").value = "";
    qs("#cli_activo").value = "1";
    qs("#cli_title").textContent = "Nuevo cliente";
    openModal(qs("#modal_cli"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#cli_id").value = d.id || "";
    qs("#cli_codigo").value = d.codigo || "";
    qs("#cli_nombre").value = d.nombre || "";
    qs("#cli_email").value = d.email || "";
    qs("#cli_telefono").value = d.telefono || "";
    qs("#cli_direccion").value = d.direccion || "";
    qs("#cli_activo").value = String(d.activo ?? 1);

    qs("#cli_title").textContent = "Editar cliente";
    openModal(qs("#modal_cli"));
  }

  async function saveCli(){
    const form = qs("#frm_cli");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }
    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_cli"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function doDelete(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Desactivar",
      text: "¿Desactivar este cliente? (No se borra, solo se desactiva)",
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

  document.addEventListener("DOMContentLoaded", () => {
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_save")?.addEventListener("click", saveCli);
    qs("#btn_filtrar")?.addEventListener("click", reloadDT);
    qs("#btn_limpiar")?.addEventListener("click", () => {
      if (qs("#f_activo")) qs("#f_activo").value = "";
      reloadDT();
    });
    initDT();
  });

})();
