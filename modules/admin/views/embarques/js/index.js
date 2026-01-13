// C:\xampp\htdocs\qacrmtaktik\modules\admin\views\embarques\js\index.js
(function () {
  "use strict";

  const qs = (sel, root = document) => root.querySelector(sel);
  const $jq = window.jQuery;

  const API = {
    list: () => BASE_URL + "admin/embarques/recursos_list",
    meta: () => BASE_URL + "admin/embarques/recursos_meta",
    get:  (id) => BASE_URL + "admin/embarques/get?id=" + encodeURIComponent(id),
    save: () => BASE_URL + "admin/embarques/save",
    del:  () => BASE_URL + "admin/embarques/delete",

    searchOts: (q) => BASE_URL + "admin/embarques/search_ots?q=" + encodeURIComponent(q || ""),
    searchPartes: (q) => BASE_URL + "admin/embarques/search_partes?q=" + encodeURIComponent(q || ""),
    searchProductos: (q) => BASE_URL + "admin/embarques/search_productos?q=" + encodeURIComponent(q || ""),
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
  let META = { estados: [] };

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
      const r = await Swal.fire({
        icon, title, text,
        showCancelButton:true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
      });
      return !!r.isConfirmed;
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

  function badgeEstado(v){
    const x = String(v||"");
    if (x === "preparando") return `<span class="badge text-bg-secondary">preparando</span>`;
    if (x === "liberado_calidad") return `<span class="badge text-bg-info">calidad</span>`;
    if (x === "enviado") return `<span class="badge text-bg-primary">enviado</span>`;
    if (x === "entregado") return `<span class="badge text-bg-success">entregado</span>`;
    return `<span class="badge text-bg-light border">${esc(x)}</span>`;
  }

  function fillEstados(){
    const f = qs("#f_estado");
    const s = qs("#emb_estado");
    if (f){
      const cur = f.value || "";
      f.innerHTML = `<option value="">Todos</option>`;
      (META.estados||[]).forEach(e=>{
        const opt = document.createElement("option");
        opt.value = e.id;
        opt.textContent = e.nombre;
        f.appendChild(opt);
      });
      f.value = cur;
    }
    if (s){
      const cur = s.value || "";
      s.innerHTML = ``;
      (META.estados||[]).forEach(e=>{
        const opt = document.createElement("option");
        opt.value = e.id;
        opt.textContent = e.nombre;
        s.appendChild(opt);
      });
      s.value = cur || "preparando";
    }
  }

  async function loadMeta(){
    const j = await jget(API.meta());
    if (j && j.ok && j.data){
      META = j.data;
      fillEstados();
    }
  }

  // -------------------------
  // DataTable
  // -------------------------
  function initDT(){
    const tbl = qs("#tbl");
    if (!tbl) return;

    if (!$jq || !$jq.fn || !$jq.fn.DataTable) {
      toastErr("DataTables no está cargado (revisa footer/layout).");
      return;
    }

    dt = $jq(tbl).DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      order: [[9,"desc"]],
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
        data: function(d){
          d.f_estado = qs("#f_estado")?.value || "";
          d.f_desde  = qs("#f_desde")?.value || "";
          d.f_hasta  = qs("#f_hasta")?.value || "";
        }
      },
      columns: [
        { data:"id", width:"60px" },
        { data:"folio", width:"120px", render:(v)=> v ? `<b>${esc(v)}</b>` : `<span class="text-muted">—</span>` },
        { data:"folio_ot", width:"120px", render:(v,_,row)=> `<span class="badge text-bg-light border">${esc(v||"")}</span>` },
        { data:"cliente_nombre", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"estado", width:"120px", render:(v)=> badgeEstado(v) },
        { data:"fecha_envio", width:"140px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"fecha_entrega", width:"140px", render:(v)=> v ? esc(v) : `<span class="text-muted">—</span>` },
        { data:"items_count", width:"70px", render:(v)=> `<span class="badge text-bg-dark">${esc(v||0)}</span>` },
        { data:"qty_total", width:"90px", render:(v)=> `<span class="badge text-bg-secondary">${esc(v||0)}</span>` },
        { data:"creado_en", width:"165px", render:(v)=> `<span class="small text-muted">${esc(v||"")}</span>` },
        {
          data:null,
          orderable:false,
          searchable:false,
          width:"140px",
          render: (_, __, row) => {
            const id = row.id;
            return `
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" data-act="edit" data-id="${id}">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" data-act="del" data-id="${id}">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            `;
          }
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

  function reloadDT(){
    if (dt) dt.ajax.reload(null,false);
  }

  // -------------------------
  // OT select
  // -------------------------
  function setOtOptions(rows, selectedId){
    const sel = qs("#emb_ot_id");
    sel.innerHTML = "";
    rows.forEach(r=>{
      const opt = document.createElement("option");
      opt.value = String(r.id);
      const label = `${r.folio_ot} — ${r.cliente_nombre || "Sin cliente"} — ${r.estado}/${r.prioridad}`;
      opt.textContent = label;
      sel.appendChild(opt);
    });
    if (selectedId) sel.value = String(selectedId);
    updateOtInfo();
  }

  function updateOtInfo(){
    const sel = qs("#emb_ot_id");
    const info = qs("#ot_info");
    const opt = sel?.selectedOptions?.[0];
    info.textContent = opt ? opt.textContent : "";
  }

  async function buscarOts(q){
    const j = await jget(API.searchOts(q || ""));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo buscar OTs");
    const rows = j.data || [];
    setOtOptions(rows, rows[0]?.id);
  }

  // -------------------------
  // Items UI
  // -------------------------
  function itemRowTemplate(){
    return `
      <tr>
        <td>
          <select class="form-select form-select-sm it_tipo">
            <option value="parte">Parte</option>
            <option value="producto">Producto</option>
          </select>
        </td>
        <td>
          <div class="input-group input-group-sm">
            <input class="form-control it_q" placeholder="Buscar... (nombre/código)">
            <button class="btn btn-outline-secondary it_search" type="button"><i class="bi bi-search"></i></button>
          </div>
          <select class="form-select form-select-sm mt-1 it_ref">
            <option value="">— Selecciona —</option>
          </select>
          <input type="hidden" class="it_parte_id" value="">
          <input type="hidden" class="it_producto_id" value="">
        </td>
        <td>
          <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm it_cant" value="1">
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger it_del" type="button"><i class="bi bi-x-lg"></i></button>
        </td>
      </tr>
    `;
  }

  function addItemRow(prefill){
    const tb = qs("#tbl_items tbody");
    tb.insertAdjacentHTML("beforeend", itemRowTemplate());
    const tr = tb.lastElementChild;

    tr.querySelector(".it_tipo").addEventListener("change", ()=>{
      tr.querySelector(".it_ref").innerHTML = `<option value="">— Selecciona —</option>`;
      tr.querySelector(".it_parte_id").value = "";
      tr.querySelector(".it_producto_id").value = "";
    });

    tr.querySelector(".it_search").addEventListener("click", ()=> searchItemForRow(tr));
    tr.querySelector(".it_q").addEventListener("keydown", (e)=>{
      if (e.key === "Enter"){ e.preventDefault(); searchItemForRow(tr); }
    });

    tr.querySelector(".it_ref").addEventListener("change", ()=>{
      const tipo = tr.querySelector(".it_tipo").value;
      const val = tr.querySelector(".it_ref").value || "";
      if (tipo === "parte") {
        tr.querySelector(".it_parte_id").value = val;
        tr.querySelector(".it_producto_id").value = "";
      } else {
        tr.querySelector(".it_producto_id").value = val;
        tr.querySelector(".it_parte_id").value = "";
      }
    });

    tr.querySelector(".it_del").addEventListener("click", ()=> tr.remove());

    if (prefill){
      tr.querySelector(".it_tipo").value = prefill.tipo_item || "parte";
      tr.querySelector(".it_cant").value = prefill.cantidad || 1;

      // cargamos una opción directa
      const ref = tr.querySelector(".it_ref");
      ref.innerHTML = `<option value="">— Selecciona —</option>`;
      if (prefill.tipo_item === "parte"){
        if (prefill.parte_id){
          const opt = document.createElement("option");
          opt.value = String(prefill.parte_id);
          opt.textContent = prefill.parte_nombre || ("Parte #" + prefill.parte_id);
          ref.appendChild(opt);
          ref.value = String(prefill.parte_id);
          tr.querySelector(".it_parte_id").value = String(prefill.parte_id);
        }
      } else {
        if (prefill.producto_id){
          const opt = document.createElement("option");
          opt.value = String(prefill.producto_id);
          opt.textContent = prefill.producto_nombre || ("Producto #" + prefill.producto_id);
          ref.appendChild(opt);
          ref.value = String(prefill.producto_id);
          tr.querySelector(".it_producto_id").value = String(prefill.producto_id);
        }
      }
    }
  }

  async function searchItemForRow(tr){
    const tipo = tr.querySelector(".it_tipo").value;
    const q = tr.querySelector(".it_q").value || "";
    const url = (tipo === "parte") ? API.searchPartes(q) : API.searchProductos(q);

    const j = await jget(url);
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo buscar");

    const rows = j.data || [];
    const ref = tr.querySelector(".it_ref");
    ref.innerHTML = `<option value="">— Selecciona —</option>`;
    rows.forEach(r=>{
      const opt = document.createElement("option");
      opt.value = String(r.id);
      opt.textContent = (r.codigo ? (r.codigo + " — ") : "") + (r.nombre || "");
      ref.appendChild(opt);
    });

    if (rows[0]?.id){
      ref.value = String(rows[0].id);
      ref.dispatchEvent(new Event("change"));
    }
  }

  function collectItems(){
    const out = [];
    qs("#tbl_items tbody").querySelectorAll("tr").forEach(tr=>{
      const tipo = tr.querySelector(".it_tipo").value;
      const cant = parseFloat(tr.querySelector(".it_cant").value || "0");
      const parteId = parseInt(tr.querySelector(".it_parte_id").value || "0", 10);
      const prodId  = parseInt(tr.querySelector(".it_producto_id").value || "0", 10);

      out.push({
        tipo_item: tipo,
        parte_id: (tipo === "parte" ? (parteId || null) : null),
        producto_id: (tipo === "producto" ? (prodId || null) : null),
        cantidad: cant
      });
    });
    return out;
  }

  function resetModal(){
    const form = qs("#frm_emb");
    form.reset();
    form.classList.remove("was-validated");
    qs("#emb_id").value = "0";
    qs("#emb_title").textContent = "Nuevo embarque";
    qs("#emb_subtitle").textContent = "Captura datos + agrega ítems.";
    qs("#ot_info").textContent = "";

    qs("#tbl_items tbody").innerHTML = "";
    addItemRow(); // arranca con 1
  }

  function openNew(){
    resetModal();
    qs("#emb_estado").value = "preparando";
    buscarOts("");
    openModal(qs("#modal_emb"));
  }

  async function openEdit(id){
    resetModal();
    const j = await jget(API.get(id));
    if (!j || !j.ok) return toastErr(j?.msg || "No se pudo cargar");

    const h = j.data?.header || {};
    const items = j.data?.items || [];

    qs("#emb_id").value = String(h.id || 0);
    qs("#emb_folio").value = h.folio || "";
    qs("#emb_estado").value = h.estado || "preparando";
    qs("#emb_notas").value = h.notas || "";

    // datetime-local necesita "YYYY-MM-DDTHH:MM"
    const toLocal = (v) => {
      if (!v) return "";
      const s = String(v).replace(" ", "T");
      return s.length >= 16 ? s.slice(0,16) : s;
    };
    qs("#emb_envio").value = toLocal(h.fecha_envio || "");
    qs("#emb_entrega").value = toLocal(h.fecha_entrega || "");

    // OTs: cargamos lista y seleccionamos
    await buscarOts(h.folio_ot || "");
    qs("#emb_ot_id").value = String(h.orden_trabajo_id || "");

    qs("#tbl_items tbody").innerHTML = "";
    if (items.length){
      items.forEach(it=> addItemRow(it));
    } else {
      addItemRow();
    }

    qs("#emb_title").textContent = "Editar embarque";
    qs("#emb_subtitle").textContent = `OT: ${h.folio_ot || ""}`;
    openModal(qs("#modal_emb"));
  }

  async function saveEmb(){
    const form = qs("#frm_emb");
    if (!form.checkValidity()){
      form.classList.add("was-validated");
      return;
    }

    const items = collectItems();

    // validación UX rápida
    if (!items.length) return toastErr("Agrega al menos 1 ítem.");
    for (const it of items){
      if (!it.cantidad || it.cantidad <= 0) return toastErr("Cantidad inválida.");
      if (it.tipo_item === "parte" && !it.parte_id) return toastErr("Falta seleccionar una parte.");
      if (it.tipo_item === "producto" && !it.producto_id) return toastErr("Falta seleccionar un producto.");
    }

    const fd = fdFromForm(form);
    fd.append("items_json", JSON.stringify(items));

    const j = await jpost(API.save(), fd);
    if (j && j.ok){
      toastOk("Guardado");
      closeModal(qs("#modal_emb"));
      reloadDT();
    } else {
      toastErr(j?.msg || "Error al guardar");
    }
  }

  async function doDelete(id){
    const ok = await askConfirm({
      icon:"warning",
      title:"Eliminar",
      text:'¿Eliminar embarque? (solo si está en "preparando")',
      confirmText:"Sí, eliminar",
      cancelText:"Cancelar"
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append("id", id);
    fd.append("csrf_token", (window.CSRF_TOKEN || window.TOKEN_CSRF || ""));

    const j = await jpost(API.del(), fd);
    if (j && j.ok){
      toastOk("Eliminado");
      reloadDT();
    } else {
      toastErr(j?.msg || "No se pudo eliminar");
    }
  }

  function wireFiltros(){
    qs("#btn_filtrar")?.addEventListener("click", ()=> reloadDT());
    qs("#btn_limpiar")?.addEventListener("click", ()=>{
      if (qs("#f_estado")) qs("#f_estado").value = "";
      if (qs("#f_desde"))  qs("#f_desde").value = "";
      if (qs("#f_hasta"))  qs("#f_hasta").value = "";
      reloadDT();
    });
  }

  document.addEventListener("DOMContentLoaded", async ()=>{
    qs("#btn_new")?.addEventListener("click", openNew);
    qs("#btn_save")?.addEventListener("click", saveEmb);
    qs("#btn_add_item")?.addEventListener("click", ()=> addItemRow());

    qs("#btn_ot_buscar")?.addEventListener("click", ()=> buscarOts(qs("#ot_q")?.value || ""));
    qs("#emb_ot_id")?.addEventListener("change", updateOtInfo);

    wireFiltros();
    await loadMeta();
    initDT();
  });

})();
