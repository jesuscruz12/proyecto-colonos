// modules/admin/views/usuarios/js/index.js
// Usuarios — TAKTIK (DataTables + Buttons + CRUD + Batch CSV)
// Requiere globales: BASE_URL, TOKEN_CSRF (ya los pones en footer)

(function () {
  const $ = (sel) => document.querySelector(sel);

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function toastOk(msg) {
    if (window.Swal) return Swal.fire({ icon: "success", title: "Listo", text: msg, timer: 1400, showConfirmButton: false });
    if (window.alertify) return alertify.success(msg);
    alert(msg);
  }

  function toastErr(msg) {
    if (window.Swal) return Swal.fire({ icon: "error", title: "Error", text: msg });
    if (window.alertify) return alertify.error(msg);
    alert(msg);
  }

  async function jpost(url, formOrJson) {
    const headers = { "X-CSRF-TOKEN": (window.TOKEN_CSRF || "") };

    let body;
    if (formOrJson instanceof FormData) {
      body = formOrJson;
    } else {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(formOrJson || {});
    }

    const r = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers,
      body,
    });

    const txt = await r.text();
    let data = null;
    try { data = JSON.parse(txt); } catch { data = { ok: false, message: txt || ("HTTP " + r.status) }; }

    if (!r.ok || data.ok === false || data.error === true) {
      throw new Error(data.message || data.error || ("HTTP " + r.status));
    }
    return data;
  }

  // ----------------------------
  // DataTable
  // ----------------------------
  let dt = null;

  function badgeActivo(v) {
    const on = String(v ?? "") === "1" || v === 1 || v === true;
    return on
      ? `<span class="badge bg-success">Activo</span>`
      : `<span class="badge bg-secondary">Inactivo</span>`;
  }

  function fmtDate(d) {
    // backend puede traer timestamp/ datetime
    if (!d) return "—";
    return esc(String(d));
  }

  function initTable() {
    dt = $("#tablaUsuarios");
    if (!dt) return;

    const $table = window.jQuery ? window.jQuery(dt) : null;
    if (!$table || !window.jQuery.fn.dataTable) {
      toastErr("DataTables no está cargado.");
      return;
    }

    const dtApi = $table.DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      dom: "<'row g-2'<'col-12 col-lg-6'B><'col-12 col-lg-6'f>>" +
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
        url: BASE_URL + "admin/usuarios/data",
        type: "GET",
        data: function (d) {
          // filtros extra (si tu backend los soporta, ya quedan)
          d.activo = $("#fActivo") ? $("#fActivo").value : "";
          d.desde  = $("#fDesde") ? $("#fDesde").value : "";
          d.hasta  = $("#fHasta") ? $("#fHasta").value : "";
        },
        error: function () {
          toastErr("Error al cargar usuarios");
        }
      },
      columns: [
        { data: "id" },
        { data: "nombre" },
        { data: "email" },
        { data: "telefono" },
        { data: "puesto" },
        {
          data: "activo",
          orderable: true,
          render: function (v) { return badgeActivo(v); }
        },
        {
          data: "creado_en",
          render: function (v) { return fmtDate(v); }
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (row) {
            const id = row.id;
            const nombre = esc(row.nombre || "");
            const activo = String(row.activo ?? "1");

            const btnEdit = `<button class="btn btn-sm btn-outline-primary me-1" data-act="edit" data-id="${id}">
              <i class="bi bi-pencil"></i>
            </button>`;

            const btnPwd = `<button class="btn btn-sm btn-outline-danger me-1" data-act="pwd" data-id="${id}" data-nombre="${nombre}">
              <i class="bi bi-shield-lock"></i>
            </button>`;

            const btnToggle = `<button class="btn btn-sm btn-outline-secondary me-1" data-act="toggle" data-id="${id}" data-activo="${activo}">
              <i class="bi bi-toggle2-${activo === "1" ? "on" : "off"}"></i>
            </button>`;

            const btnDel = `<button class="btn btn-sm btn-outline-dark" data-act="del" data-id="${id}">
              <i class="bi bi-trash"></i>
            </button>`;

            return `<div class="d-flex align-items-center">${btnEdit}${btnPwd}${btnToggle}${btnDel}</div>`;
          }
        }
      ],
      order: [[0, "desc"]],
      language: {
        url: BASE_URL + "public/datatables/spanish.json"
      }
    });

    // clicks acciones
    $table.on("click", "button[data-act]", async function () {
      const act = this.getAttribute("data-act");
      const id = parseInt(this.getAttribute("data-id") || "0", 10);

      if (!id) return;

      try {
        if (act === "edit") return openEdit(id);
        if (act === "pwd") return openPwd(id, this.getAttribute("data-nombre") || "");
        if (act === "toggle") return toggleActivo(id, this.getAttribute("data-activo"));
        if (act === "del") return softDelete(id);
      } catch (e) {
        toastErr(e.message || "Error");
      }
    });

    // guardar
    const btnSave = $("#btnGuardarUsuario");
    if (btnSave) btnSave.addEventListener("click", saveUsuario);

    const btnSavePwd = $("#btnGuardarPassword");
    if (btnSavePwd) btnSavePwd.addEventListener("click", savePassword);
  }

  function reload() {
    if (!window.jQuery) return;
    const api = window.jQuery("#tablaUsuarios").DataTable();
    api.ajax.reload(null, false);
  }

  // ----------------------------
  // Modales
  // ----------------------------
  let modalUsuario = null;
  let modalPassword = null;
  let modalImport = null;

  function bsModal(id) {
    const el = document.getElementById(id);
    if (!el || !window.bootstrap) return null;
    return new bootstrap.Modal(el, { backdrop: "static" });
  }

  function openCreate() {
    $("#modalUsuarioTitle").textContent = "Nuevo usuario";
    $("#u_id").value = "";
    $("#u_nombre").value = "";
    $("#u_email").value = "";
    $("#u_telefono").value = "";
    $("#u_puesto").value = "";
    $("#u_activo").value = "1";
    $("#u_password_plain").value = "";
    $("#wrapPasswordCreate").style.display = "";
    modalUsuario.show();
  }

  async function openEdit(id) {
    $("#modalUsuarioTitle").textContent = "Editar usuario";
    $("#wrapPasswordCreate").style.display = "none";
    $("#u_password_plain").value = "";

    // GET /admin/usuarios/get?id=#
    const url = BASE_URL + "admin/usuarios/get?id=" + encodeURIComponent(id);
    const r = await fetch(url, { credentials: "same-origin" });
    const data = await r.json();
    if (!data.ok) throw new Error(data.message || "No se pudo cargar");

    const u = data.data || {};
    $("#u_id").value = u.id || "";
    $("#u_nombre").value = u.nombre || "";
    $("#u_email").value = u.email || "";
    $("#u_telefono").value = u.telefono || "";
    $("#u_puesto").value = u.puesto || "";
    $("#u_activo").value = String(u.activo ?? "1");

    modalUsuario.show();
  }

  function openPwd(id, nombre) {
    $("#p_id").value = String(id);
    $("#p_password").value = "";
    $("#p_label").textContent = nombre ? ("Usuario: " + nombre) : "";
    modalPassword.show();
  }

  async function saveUsuario() {
    const id = parseInt($("#u_id").value || "0", 10);

    const fd = new FormData();
    fd.append("csrf_token", window.TOKEN_CSRF || "");

    fd.append("id", $("#u_id").value || "");
    fd.append("nombre", $("#u_nombre").value || "");
    fd.append("email", $("#u_email").value || "");
    fd.append("telefono", $("#u_telefono").value || "");
    fd.append("puesto", $("#u_puesto").value || "");
    fd.append("activo", $("#u_activo").value || "1");

    // solo al crear
    if (!id) {
      const pw = $("#u_password_plain").value || "";
      if (pw.length < 6) return toastErr("El password debe tener mínimo 6 caracteres.");
      fd.append("password_plain", pw);
    }

    try {
      if (!id) {
        await jpost(BASE_URL + "admin/usuarios/create", fd);
        toastOk("Usuario creado");
      } else {
        await jpost(BASE_URL + "admin/usuarios/update", fd);
        toastOk("Usuario actualizado");
      }

      modalUsuario.hide();
      reload();
    } catch (e) {
      toastErr(e.message || "Error al guardar");
    }
  }

  async function savePassword() {
    const id = parseInt($("#p_id").value || "0", 10);
    const pw = $("#p_password").value || "";
    if (!id || pw.length < 6) return toastErr("Password mínimo 6 caracteres.");

    const fd = new FormData();
    fd.append("csrf_token", window.TOKEN_CSRF || "");
    fd.append("id", String(id));
    fd.append("password_plain", pw);

    try {
      await jpost(BASE_URL + "admin/usuarios/password", fd);
      toastOk("Password actualizado");
      modalPassword.hide();
    } catch (e) {
      toastErr(e.message || "Error al cambiar password");
    }
  }

  async function toggleActivo(id, activoActual) {
    const cur = String(activoActual ?? "1") === "1" ? 1 : 0;
    const next = cur ? 0 : 1;

    const ok = window.Swal
      ? (await Swal.fire({ icon: "question", title: "Confirmar", text: (next ? "Activar usuario?" : "Desactivar usuario?"), showCancelButton: true })).isConfirmed
      : confirm(next ? "¿Activar usuario?" : "¿Desactivar usuario?");

    if (!ok) return;

    const fd = new FormData();
    fd.append("csrf_token", window.TOKEN_CSRF || "");
    fd.append("id", String(id));
    fd.append("activo", String(next));

    await jpost(BASE_URL + "admin/usuarios/activo", fd);
    toastOk("Estado actualizado");
    reload();
  }

  async function softDelete(id) {
    const ok = window.Swal
      ? (await Swal.fire({ icon: "warning", title: "Eliminar", text: "Esto hará soft delete (eliminado_en). ¿Continuar?", showCancelButton: true, confirmButtonText: "Sí, eliminar" })).isConfirmed
      : confirm("¿Eliminar usuario (soft delete)?");

    if (!ok) return;

    const fd = new FormData();
    fd.append("csrf_token", window.TOKEN_CSRF || "");
    fd.append("id", String(id));

    await jpost(BASE_URL + "admin/usuarios/delete", fd);
    toastOk("Usuario eliminado");
    reload();
  }

  // ----------------------------
  // Batch CSV (sin endpoint extra)
  // ----------------------------
  let batchRows = []; // {i,nombre,email,password,telefono,puesto,activo, result}

  function downloadTemplate() {
    const header = "nombre,email,password,telefono,puesto,activo\n";
    const example = "Juan Perez,juan@empresa.com,123456,8112345678,Supervisor,1\n";
    const blob = new Blob([header + example], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "plantilla_usuarios.csv";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function parseCsv(text) {
    // parser simple (CSV con comas; soporta comillas básicas)
    const lines = text.replace(/\r/g, "").split("\n").filter(Boolean);
    if (!lines.length) return [];

    const head = splitCsvLine(lines[0]).map(h => h.trim().toLowerCase());
    const idx = (name) => head.indexOf(name);

    const req = ["nombre", "email", "password"];
    for (const r of req) {
      if (idx(r) === -1) throw new Error("Falta columna requerida: " + r);
    }

    const out = [];
    for (let i = 1; i < lines.length; i++) {
      const cols = splitCsvLine(lines[i]);

      const row = {
        i,
        nombre: (cols[idx("nombre")] || "").trim(),
        email: (cols[idx("email")] || "").trim(),
        password: (cols[idx("password")] || "").trim(),
        telefono: (cols[idx("telefono")] || "").trim(),
        puesto: (cols[idx("puesto")] || "").trim(),
        activo: (cols[idx("activo")] || "1").trim(),
        result: "",
      };

      if (!row.nombre && !row.email) continue; // línea vacía

      out.push(row);
    }
    return out;
  }

  function splitCsvLine(line) {
    const res = [];
    let cur = "";
    let inQ = false;

    for (let i = 0; i < line.length; i++) {
      const ch = line[i];

      if (ch === '"' && (i === 0 || line[i - 1] !== "\\")) {
        inQ = !inQ;
        continue;
      }
      if (ch === "," && !inQ) {
        res.push(cur);
        cur = "";
        continue;
      }
      cur += ch;
    }
    res.push(cur);
    return res.map(s => s.replace(/\\"/g, '"'));
  }

  function renderBatch() {
    const tb = $("#batchPreview");
    if (!tb) return;

    if (!batchRows.length) {
      tb.innerHTML = `<tr><td colspan="7" class="text-muted p-3">Sin datos cargados.</td></tr>`;
      $("#btnRunBatch").disabled = true;
      $("#batchInfo").textContent = "Sin archivo.";
      $("#batchStatus").textContent = "—";
      $("#batchBar").style.width = "0%";
      return;
    }

    $("#btnRunBatch").disabled = false;
    $("#batchInfo").textContent = `${batchRows.length} filas listas.`;

    tb.innerHTML = batchRows.map(r => {
      return `
        <tr>
          <td>${r.i}</td>
          <td>${esc(r.nombre)}</td>
          <td>${esc(r.email)}</td>
          <td>${esc(r.telefono || "—")}</td>
          <td>${esc(r.puesto || "—")}</td>
          <td>${esc(r.activo || "1")}</td>
          <td class="${r.result && r.result.startsWith("OK") ? "text-success" : (r.result ? "text-danger" : "text-muted")}">
            ${esc(r.result || "Pendiente")}
          </td>
        </tr>
      `;
    }).join("");
  }

  async function runBatch() {
    if (!batchRows.length) return;

    $("#btnRunBatch").disabled = true;

    let okCount = 0;
    let errCount = 0;

    for (let i = 0; i < batchRows.length; i++) {
      const r = batchRows[i];

      $("#batchStatus").textContent = `Procesando ${i + 1}/${batchRows.length}...`;
      $("#batchBar").style.width = `${Math.round(((i) / batchRows.length) * 100)}%`;

      // validaciones mínimas
      if (!r.nombre || !r.email || !r.password || r.password.length < 6) {
        r.result = "ERR: datos incompletos o password < 6";
        errCount++;
        renderBatch();
        continue;
      }

      const fd = new FormData();
      fd.append("csrf_token", window.TOKEN_CSRF || "");
      fd.append("nombre", r.nombre);
      fd.append("email", r.email);
      fd.append("password_plain", r.password);
      fd.append("telefono", r.telefono || "");
      fd.append("puesto", r.puesto || "");
      fd.append("activo", (String(r.activo || "1") === "0" ? "0" : "1"));

      try {
        await jpost(BASE_URL + "admin/usuarios/create", fd);
        r.result = "OK";
        okCount++;
      } catch (e) {
        r.result = "ERR: " + (e.message || "falló");
        errCount++;
      }

      renderBatch();
    }

    $("#batchStatus").textContent = `Terminado. OK: ${okCount} · Errores: ${errCount}`;
    $("#batchBar").style.width = "100%";
    $("#btnRunBatch").disabled = false;

    // refresca tabla al final
    reload();
  }

  function openImport() {
    batchRows = [];
    renderBatch();
    modalImport.show();
  }

  function pickCsv() {
    const f = $("#fileCsv");
    if (f) f.click();
  }

  // ----------------------------
  // Init
  // ----------------------------
  document.addEventListener("DOMContentLoaded", function () {
    modalUsuario = bsModal("modalUsuario");
    modalPassword = bsModal("modalPassword");
    modalImport = bsModal("modalImport");

    initTable();

    const btnNuevo = $("#btnNuevo");
    if (btnNuevo) btnNuevo.addEventListener("click", openCreate);

    const btnReload = $("#btnReload");
    if (btnReload) btnReload.addEventListener("click", reload);

    const btnTemplate = $("#btnTemplate");
    if (btnTemplate) btnTemplate.addEventListener("click", downloadTemplate);

    const btnImport = $("#btnImport");
    if (btnImport) btnImport.addEventListener("click", openImport);

    const btnPickCsv = $("#btnPickCsv");
    if (btnPickCsv) btnPickCsv.addEventListener("click", pickCsv);

    const btnClearBatch = $("#btnClearBatch");
    if (btnClearBatch) btnClearBatch.addEventListener("click", function () {
      batchRows = [];
      renderBatch();
    });

    const btnRunBatch = $("#btnRunBatch");
    if (btnRunBatch) btnRunBatch.addEventListener("click", runBatch);

    const fileCsv = $("#fileCsv");
    if (fileCsv) {
      fileCsv.addEventListener("change", async function () {
        const file = this.files && this.files[0];
        if (!file) return;

        try {
          const text = await file.text();
          batchRows = parseCsv(text);
          renderBatch();
          toastOk("CSV cargado");
        } catch (e) {
          batchRows = [];
          renderBatch();
          toastErr(e.message || "CSV inválido");
        } finally {
          this.value = "";
        }
      });
    }

    // filtros
    const btnApply = $("#btnApplyFilters");
    if (btnApply) btnApply.addEventListener("click", function () {
      reload();
    });

    const btnClear = $("#btnClearFilters");
    if (btnClear) btnClear.addEventListener("click", function () {
      if ($("#fActivo")) $("#fActivo").value = "";
      if ($("#fDesde")) $("#fDesde").value = "";
      if ($("#fHasta")) $("#fHasta").value = "";
      reload();
    });
  });
})();
