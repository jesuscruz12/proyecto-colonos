// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\procesomaquina\js\index.js
(function () {
  "use strict";

  const qs = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const BASE = String(window.BASE_URL || "").replace(/\/+$/, "");

  const API = {
    dtMaquinas: () => `${BASE}/admin/procesomaquina/dt_maquinas`,
    dtProcesos: () => `${BASE}/admin/procesomaquina/dt_procesos`,
    save:      () => `${BASE}/admin/procesomaquina/save`,
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
    paginate: { first:"Primero", previous:"Anterior", next:"Siguiente", last:"Último" }
  };

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;")
      .replaceAll('"',"&quot;").replaceAll("'","&#039;");
  }

  function toastOk(msg){
    if (window.Swal) return Swal.fire({ icon:"success", title:"Listo", text: msg, timer: 1300, showConfirmButton:false });
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
    if (window.Swal) {
      const r = await Swal.fire({
        icon: opts?.icon || "warning",
        title,
        text,
        showCancelButton: true,
        confirmButtonText: opts?.confirmText || "Sí",
        cancelButtonText: opts?.cancelText || "Cancelar",
      });
      return !!r.isConfirmed;
    }
    return confirm(text);
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

  function badgeActivo(v){
    const ok = String(v) === "1" || v === 1;
    return ok ? `<span class="badge text-bg-success">sí</span>` : `<span class="badge text-bg-secondary">no</span>`;
  }

  let dtMaq = null;
  let dtPro = null;

  let maquinaSel = 0;
  let maquinaTxt = "";

  // Set de procesos seleccionados para la máquina actual
  let selected = new Set();

  function setUISelectedLabel(){
    qs("#lbl_sel").textContent = maquinaSel ? `Máquina: ${maquinaTxt}` : "Sin máquina";
    qs("#btn_save").disabled = !maquinaSel;
    qs("#btn_toggle_all").disabled = !maquinaSel;
  }

  function updateCountBadge(){
    qs("#lbl_count").textContent = String(selected.size);
  }

  function initDTMaquinas(){
    const tbl = qs("#tbl_maquinas");
    if (!tbl) return;

    dtMaq = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      pageLength: 10,
      lengthMenu: [10,25,50],
      order: [[0,"asc"]],
      language: DT_LANG_ES,
      ajax: {
        url: API.dtMaquinas(),
        type: "GET",
      },
      columns: [
        { data:"id", width:"60px" },
        { data:"codigo", width:"100px", render:(v)=> v ? `<span class="badge text-bg-light border">${esc(v)}</span>` : `<span class="text-muted">—</span>` },
        { data:"nombre", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"tipo", width:"120px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"activo", width:"80px", render:(v)=> badgeActivo(v) },
        { data:"procesos_count", width:"90px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"70px",
          render: (_, __, row) => `
            <button class="btn btn-sm btn-outline-primary btn_sel" type="button"
              data-id="${row.id}"
              data-nombre="${esc(row.nombre||"")}"
              title="Seleccionar">
              <i class="bi bi-check2"></i>
            </button>
          `
        }
      ],
    });

    $jq(tbl).on("click", "button.btn_sel", function(){
      maquinaSel = parseInt(this.getAttribute("data-id"),10) || 0;
      maquinaTxt = this.getAttribute("data-nombre") || ("ID " + maquinaSel);

      selected = new Set(); // se recalcula al cargar procesos
      updateCountBadge();
      setUISelectedLabel();

      if (dtPro) dtPro.ajax.reload();
    });
  }

  function initDTProcesos(){
    const tbl = qs("#tbl_procesos");
    if (!tbl) return;

    dtPro = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      pageLength: 25,
      lengthMenu: [25,50,100],
      order: [[2,"asc"]],
      language: DT_LANG_ES,
      ajax: {
        url: API.dtProcesos(),
        type: "POST",
        data: function(d){
          d.maquina_id = maquinaSel;
          d.f_activo = qs("#f_activo")?.value || "1";
        }
      },
      columns: [
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"40px",
          render: (_, __, row) => {
            const id = row.id;
            // servidor manda asignado=1/0
            const checked = String(row.asignado) === "1";
            // al dibujar, sincronizamos el set (solo cuando hay máquina)
            if (maquinaSel) {
              if (checked) selected.add(String(id));
              else selected.delete(String(id));
            }
            return `
              <input class="form-check-input chk_proc"
                     type="checkbox"
                     data-id="${id}"
                     ${checked ? "checked" : ""}>
            `;
          }
        },
        { data:"id", width:"70px" },
        { data:"nombre", render:(v)=> `<b>${esc(v||"")}</b>` },
        { data:"setup_minutos", className:"text-end", width:"90px", render:(v)=> esc(v ?? 0) },
        { data:"frecuencia_setup", width:"120px", render:(v)=> esc(v||"") },
        { data:"activo", width:"80px", render:(v)=> badgeActivo(v) },
      ],
      drawCallback: function(){
        updateCountBadge();
      }
    });

    // toggle individual
    $jq(tbl).on("change", "input.chk_proc", function(){
      const id = String(this.getAttribute("data-id") || "");
      if (!id) return;
      if (this.checked) selected.add(id);
      else selected.delete(id);
      updateCountBadge();
    });
  }

  async function saveAsignacion(){
    if (!maquinaSel) return;

    const ok = await askConfirm({
      icon: "warning",
      title: "Guardar",
      text: "¿Guardar asignación de procesos para esta máquina? (Reemplaza la asignación actual)",
      confirmText: "Sí, guardar",
      cancelText: "Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("maquina_id", String(maquinaSel));
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    // procesos[]
    Array.from(selected).forEach(pid => fd.append("procesos[]", String(pid)));

    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      if (dtMaq) dtMaq.ajax.reload(null, false);
      if (dtPro) dtPro.ajax.reload(null, false);
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  function toggleAllOnPage(){
    if (!dtPro || !maquinaSel) return;

    // toma los checkboxes visibles en la página actual
    const checks = qs("#tbl_procesos")?.querySelectorAll("input.chk_proc") || [];
    if (!checks.length) return;

    // si hay alguno desmarcado => marcar todos, si no => desmarcar todos
    let anyOff = false;
    checks.forEach(c => { if (!c.checked) anyOff = true; });

    checks.forEach(c => {
      c.checked = anyOff;
      const id = String(c.getAttribute("data-id") || "");
      if (!id) return;
      if (anyOff) selected.add(id);
      else selected.delete(id);
    });

    updateCountBadge();
  }

  document.addEventListener("DOMContentLoaded", function(){
    if (!$jq || !$jq.fn || !$jq.fn.DataTable) {
      toastErr("DataTables no está cargado (revisa footer).");
      return;
    }

    setUISelectedLabel();
    updateCountBadge();

    initDTMaquinas();
    initDTProcesos();

    qs("#btn_save")?.addEventListener("click", saveAsignacion);
    qs("#btn_toggle_all")?.addEventListener("click", toggleAllOnPage);

    qs("#f_activo")?.addEventListener("change", function(){
      if (dtPro) dtPro.ajax.reload();
    });
  });

})();
