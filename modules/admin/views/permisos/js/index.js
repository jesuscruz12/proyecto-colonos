// permisos — catálogo + matriz por rol (TAKTIK)

(function () {
  const $ = (sel) => document.querySelector(sel);
  const csrf = () => ($("#csrf_token")?.value || window.TOKEN_CSRF || "").toString();

  function jget(url) {
    return fetch(url, { credentials: "same-origin" }).then(async (r) => {
      const j = await r.json().catch(() => null);
      if (!r.ok) throw new Error((j && (j.message || j.error)) || "HTTP " + r.status);
      return j;
    });
  }

  function jpost(url, data) {
    const payload = Object.assign({}, data || {}, { csrf_token: csrf() });
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() },
      body: JSON.stringify(payload),
    }).then(async (r) => {
      const j = await r.json().catch(() => null);
      if (!r.ok || (j && j.ok === false)) throw new Error((j && (j.message || j.error)) || "Error");
      return j;
    });
  }

  function toastOk(msg) {
    if (window.Swal) return Swal.fire({ icon: "success", title: msg, timer: 1200, showConfirmButton: false });
    alert(msg);
  }
  function toastErr(msg) {
    if (window.Swal) return Swal.fire({ icon: "error", title: "Error", text: msg });
    alert(msg);
  }

  // -------------------------
  // DataTable catálogo
  // -------------------------
  let dt = null;

  function initDt() {
    if (!window.jQuery || !jQuery.fn.DataTable) return;

    dt = jQuery("#dtPermisos").DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: { url: BASE_URL + "admin/permisos/data", type: "GET" },
      columns: [
        { data: "id" },
        { data: "nombre" },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: (row) => `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary btn-edit" data-id="${row.id}" data-nombre="${String(row.nombre||"").replace(/"/g,'&quot;')}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-outline-danger btn-del" data-id="${row.id}"><i class="bi bi-trash"></i></button>
            </div>
          `,
        },
      ],
      order: [[0, "desc"]],
      dom: "Bfrtip",
      buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
      language: { url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json" },
    });

    jQuery("#dtPermisos").on("click", ".btn-edit", function () {
      openModal(this.getAttribute("data-id"), this.getAttribute("data-nombre") || "");
    });

    jQuery("#dtPermisos").on("click", ".btn-del", function () {
      const id = parseInt(this.getAttribute("data-id") || "0", 10);
      if (!id) return;

      const go = () =>
        jpost(BASE_URL + "admin/permisos/delete", { id })
          .then(() => { toastOk("Eliminado"); dt.ajax.reload(null, false); loadMatriz(true); })
          .catch((e) => toastErr(e.message || "No se pudo eliminar"));

      if (window.Swal) {
        Swal.fire({
          icon: "warning",
          title: "¿Eliminar permiso?",
          text: "Esto también afecta asignaciones por rol.",
          showCancelButton: true,
          confirmButtonText: "Sí, eliminar",
          cancelButtonText: "Cancelar",
        }).then((r) => r.isConfirmed && go());
      } else {
        if (confirm("¿Eliminar permiso?")) go();
      }
    });
  }

  // -------------------------
  // Modal
  // -------------------------
  let bsModal = null;

  function openModal(id, nombre) {
    $("#p_id").value = String(id || 0);
    $("#p_nombre").value = String(nombre || "");
    $("#mdlTitle").textContent = id ? "Editar permiso" : "Nuevo permiso";

    const el = document.getElementById("mdlPermiso");
    bsModal = bsModal || new bootstrap.Modal(el);
    bsModal.show();
    setTimeout(() => $("#p_nombre")?.focus(), 150);
  }

  function savePermiso() {
    const id = parseInt($("#p_id").value || "0", 10);
    const nombre = ($("#p_nombre").value || "").trim();
    if (!nombre) return toastErr("Nombre requerido");

    const url = id ? "admin/permisos/update" : "admin/permisos/create";
    const payload = id ? { id, nombre } : { nombre };

    jpost(BASE_URL + url, payload)
      .then(() => { bsModal && bsModal.hide(); toastOk("Guardado"); dt && dt.ajax.reload(null, false); loadMatriz(true); })
      .catch((e) => toastErr(e.message || "No se pudo guardar"));
  }

  // -------------------------
  // Matriz
  // -------------------------
  let matriz = null;
  let rolActual = 0;

  function loadMatriz(silent) {
    return jget(BASE_URL + "admin/permisos/matriz")
      .then((res) => {
        matriz = res.data || res;
        renderRoles();
        renderPerms();
        if (!silent) toastOk("Matriz actualizada");
      })
      .catch((e) => toastErr(e.message || "Error al cargar matriz"));
  }

  function renderRoles() {
    const sel = $("#selRol");
    const roles = (matriz && matriz.roles) || [];
    sel.innerHTML = roles.map((r) => `<option value="${r.id}">${r.nombre}</option>`).join("");
    rolActual = parseInt(sel.value || (roles[0] && roles[0].id) || "0", 10) || 0;
  }

  function isChecked(rolId, permId) {
    const a = (matriz && matriz.asignados) || {};
    return !!(a[rolId] && a[rolId][permId]);
  }

  function renderPerms() {
    const tb = $("#tbMatriz tbody");
    const perms = (matriz && matriz.permisos) || [];
    const filtro = ($("#txtFindPerm").value || "").trim().toLowerCase();

    const rows = perms.filter((p) => !filtro || String(p.nombre || "").toLowerCase().includes(filtro));

    if (!rows.length) {
      tb.innerHTML = `<tr><td colspan="2" class="text-muted p-3">Sin permisos.</td></tr>`;
      return;
    }

    tb.innerHTML = rows
      .map((p) => {
        const ck = isChecked(rolActual, p.id) ? "checked" : "";
        return `
          <tr>
            <td><input type="checkbox" class="form-check-input ckPerm" data-id="${p.id}" ${ck}></td>
            <td><code>${p.nombre}</code></td>
          </tr>
        `;
      })
      .join("");
  }

  function togglePerm(permId) {
    if (!rolActual || !permId) return;
    jpost(BASE_URL + "admin/permisos/rol-toggle", { rol_id: rolActual, permiso_id: permId })
      .then(() => loadMatriz(true))
      .catch((e) => toastErr(e.message || "No se pudo cambiar"));
  }

  function guardarRolCompleto() {
    if (!rolActual) return toastErr("Selecciona un rol");

    const ids = Array.from(document.querySelectorAll("#tbMatriz .ckPerm"))
      .filter((x) => x.checked)
      .map((x) => parseInt(x.getAttribute("data-id") || "0", 10))
      .filter((x) => x > 0);

    jpost(BASE_URL + "admin/permisos/rol-set", { rol_id: rolActual, permisos_ids: ids })
      .then(() => { toastOk("Guardado"); loadMatriz(true); })
      .catch((e) => toastErr(e.message || "No se pudo guardar"));
  }

  document.addEventListener("DOMContentLoaded", function () {
    initDt();

    $("#btnNuevoPermiso")?.addEventListener("click", () => openModal(0, ""));
    $("#btnSavePermiso")?.addEventListener("click", savePermiso);
    $("#btnReloadPermisos")?.addEventListener("click", () => dt && dt.ajax.reload(null, false));

    $("#btnReloadMatriz")?.addEventListener("click", () => loadMatriz());
    $("#btnGuardarMatriz")?.addEventListener("click", guardarRolCompleto);
    $("#selRol")?.addEventListener("change", () => { rolActual = parseInt($("#selRol").value || "0", 10) || 0; renderPerms(); });
    $("#txtFindPerm")?.addEventListener("input", () => renderPerms());

    document.addEventListener("change", function (ev) {
      const t = ev.target;
      if (t && t.classList && t.classList.contains("ckPerm")) {
        togglePerm(parseInt(t.getAttribute("data-id") || "0", 10));
      }
    });

    loadMatriz(true);
  });
})();
