// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\procesos\js\index.js
(function () {
  "use strict";

  const qs  = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list:   () => BASE_URL + "admin/procesos/recursos_list",
    get:    (id) => BASE_URL + "admin/procesos/get?id=" + encodeURIComponent(id),
    save:   () => BASE_URL + "admin/procesos/save",
    del:    () => BASE_URL + "admin/procesos/delete",
    maqs:   () => BASE_URL + "admin/procesos/maquinas",
    asgGet: (pid) => BASE_URL + "admin/procesos/asignacion_get?proceso_id=" + encodeURIComponent(pid),
    asgSave:() => BASE_URL + "admin/procesos/asignacion_save",
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
    if (window.Swal) return Swal.fire({ icon:"success", title:"Listo", text: msg, timer: 1400, showConfirmButton:false });
    if (window.alertify) return alertify.success(msg);
    alert(msg);
  }

  function toastErr(msg){
    if (window.Swal) return Swal.fire({ icon:"error", title:"Error", text: msg });
    if (window.alertify) return alertify.error(msg);
    alert(msg);
  }

  // ✅ Confirm estilo Usuarios: Swal -> alertify -> confirm
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
        alertify.confirm(
          title,
          text,
          function(){ resolve(true); },
          function(){ resolve(false); }
        );
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
    return await r.json();
  }

  async function jpost(url, fd){
    const r = await fetch(url, {
      method:"POST",
      body: fd,
      credentials:"same-origin",
      headers: { "X-CSRF-TOKEN": (window.CSRF_TOKEN || window.TOKEN_CSRF || "") }
    });
    return await r.json();
  }

  function openModal(el){ bootstrap.Modal.getOrCreateInstance(el).show(); }
  function closeModal(el){ bootstrap.Modal.getOrCreateInstance(el).hide(); }

  function badgeActivo(v){
    const ok = String(v) === "1" || v === 1;
    return ok
      ? `<span class="badge text-bg-success">sí</span>`
      : `<span class="badge text-bg-secondary">no</span>`;
  }

  // ✅ Tooltips Bootstrap 5
  function initTooltips(root = document){
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
      if (bootstrap.Tooltip.getInstance(el)) return;
      new bootstrap.Tooltip(el);
    });
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
        { data:"id", width:"60px" },
        { data:"nombre", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"setup_minutos", width:"110px" },
        { data:"frecuencia_setup", width:"110px", render:(v)=> `<span class="badge text-bg-info">${esc(v||"")}</span>` },
        { data:"activo", width:"90px", render:(v)=> badgeActivo(v) },
        { data:"maquinas_count", width:"90px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        { data:"creado_en", width:"170px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"160px",
          render: (_, __, row) => {
            const id = row.id;
            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" data-act="edit" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>

                <button class="btn btn-outline-secondary" data-act="asig" data-id="${id}"
                  data-nombre="${esc(row.nombre||"")}"
                  data-bs-toggle="tooltip" data-bs-title="Asignar máquinas">
                  <i class="bi bi-hdd-stack"></i>
                </button>

                <button class="btn btn-outline-danger" data-act="del" data-id="${id}"
                  data-bs-toggle="tooltip" data-bs-title="Desactivar">
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
      if (act === "edit") return openEdit(id);
      if (act === "asig") return openAsignacion(id, this.getAttribute("data-nombre") || "");
      if (act === "del")  return doDelete(id);
    });
  }

  function reloadDT(){
    if (dt) dt.ajax.reload(null,false);
  }

  // =========================
  // CRUD Proceso
  // =========================
  function openNew(){
    const form = qs("#frm_proc");
    form.reset();
    qs("#proc_id").value = "";
    qs("#proc_setup").value = "0";
    qs("#proc_freq").value = "orden";
    qs("#proc_activo").value = "1";
    qs("#proc_title").textContent = "Nuevo proceso";
    openModal(qs("#modal_proc"));
  }

  async function openEdit(id){
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const d = j.data || {};
    qs("#proc_id").value = d.id || "";
    qs("#proc_nombre").value = d.nombre || "";
    qs("#proc_setup").value = d.setup_minutos ?? 0;
    qs("#proc_freq").value = d.frecuencia_setup || "orden";
    qs("#proc_activo").value = String(d.activo ?? 1);

    qs("#proc_title").textContent = "Editar proceso";
    openModal(qs("#modal_proc"));
  }

  async function saveProc(){
    const form = qs("#frm_proc");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }
    const fd = fdFromForm(form);
    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_proc"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function doDelete(id){
    const ok = await askConfirm({
      icon: "warning",
      title: "Desactivar",
      text: "¿Desactivar este proceso? (No se borra, solo se desactiva)",
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

  // =========================
  // Asignación máquinas
  // =========================
  async function openAsignacion(procesoId, nombre){
    qs("#asig_proceso_id").value = String(procesoId);
    qs("#asig_proc_id").textContent = String(procesoId);
    qs("#asig_proc_nombre").textContent = nombre || "";

    await renderMaqTable(procesoId);
    openModal(qs("#modal_asig"));
  }

  async function renderMaqTable(procesoId){
    const [jm, ja] = await Promise.all([
      jget(API.maqs()),
      jget(API.asgGet(procesoId))
    ]);

    const maquinas  = (jm && jm.ok) ? (jm.data||[]) : [];
    const asignadas = (ja && ja.ok) ? (ja.data||[]) : [];

    const setAsg = new Set(asignadas.map(x=>String(x.id)));

    const tbody = qs("#tbl_maqs tbody");
    tbody.innerHTML = "";

    maquinas.forEach(m=>{
      const checked = setAsg.has(String(m.id)) ? "checked" : "";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>
          <input type="checkbox" class="form-check-input asg-chk" value="${m.id}" ${checked}>
        </td>
        <td>${esc(m.id)}</td>
        <td>${esc(m.codigo||"")}</td>
        <td><b>${esc(m.nombre||"")}</b></td>
        <td>${esc(m.tipo||"")}</td>
        <td>${(String(m.activo)==="1") ? '<span class="badge text-bg-success">sí</span>' : '<span class="badge text-bg-secondary">no</span>'}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function setAllAsignacion(val){
    document.querySelectorAll(".asg-chk").forEach(chk=> chk.checked = !!val);
  }

  async function saveAsignacion(){
    const pid = parseInt(qs("#asig_proceso_id").value || "0", 10);
    if (!pid) return;

    const ids = Array.from(document.querySelectorAll(".asg-chk"))
      .filter(x=>x.checked)
      .map(x=>x.value);

    const fd = new FormData();
    fd.append("proceso_id", String(pid));
    ids.forEach(id=> fd.append("maquina_ids[]", id));
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.asgSave(), fd);
    if (j && j.ok){
      toastOk("Asignación guardada");
      closeModal(qs("#modal_asig"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar asignación");
    }
  }

  function wireFiltros(){
    qs("#btn_filtrar")?.addEventListener("click", ()=> reloadDT());
    qs("#btn_limpiar")?.addEventListener("click", ()=>{
      if (qs("#f_activo")) qs("#f_activo").value = "";
      reloadDT();
    });
  }

  document.addEventListener("DOMContentLoaded", async ()=>{
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_save")?.addEventListener("click", saveProc);

    qs("#btn_asig_all")?.addEventListener("click", ()=> setAllAsignacion(true));
    qs("#btn_asig_none")?.addEventListener("click", ()=> setAllAsignacion(false));
    qs("#btn_asig_save")?.addEventListener("click", saveAsignacion);

    wireFiltros();
    initDT();
  });

})();
