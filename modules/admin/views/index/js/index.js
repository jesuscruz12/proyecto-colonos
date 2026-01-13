// modules/admin/views/index/js/index.js
// Home (Dashboard) — AJAX DESACTIVADO TEMPORALMENTE

(function () {
  // 🔴 FLAG GLOBAL
  const DISABLE_DASHBOARD_AJAX = true;

  // ---- Helpers
  const $1 = (sel) => document.querySelector(sel);

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  /* ============================================================
     🔴 KPIs — DESACTIVADOS
     ============================================================ */
  async function loadKpis() {
    return; // ❌ NO SE LLAMA /kpis
  }

  /* ============================================================
     🔴 DATATABLES — DESACTIVADOS
     ============================================================ */
  let dtOts = null, dtTareas = null, dtAuditoria = null;

  function initDataTables() {
    return; // ❌ NO se inicializa ningún DataTable
  }

  function reloadDT() {
    return; // ❌ NO hay reload
  }

  /* ============================================================
     CSV LINKS (esto NO usa AJAX, se puede quedar)
     ============================================================ */
  function filtrosActuales() {
    return {
      desde: ($1("#f_desde")?.value || "").trim(),
      hasta: ($1("#f_hasta")?.value || "").trim(),
      estado: ($1("#f_estado_ot")?.value || "").trim(),
    };
  }

  function buildQuery(params) {
    const sp = new URLSearchParams();
    Object.entries(params || {}).forEach(([k, v]) => {
      if (v !== null && v !== undefined && String(v).trim() !== "") {
        sp.set(k, String(v).trim());
      }
    });
    const qs = sp.toString();
    return qs ? "?" + qs : "";
  }

  function bindCsvLinks() {
    const btnOts = $1("#btn_csv_ots");
    const btnTar = $1("#btn_csv_tareas");

    function hrefOts() {
      const f = filtrosActuales();
      return BASE_URL + "admin/index/export_ots_csv" +
        buildQuery({ desde: f.desde, hasta: f.hasta, estado: f.estado });
    }

    function hrefTareas() {
      const f = filtrosActuales();
      return BASE_URL + "admin/index/export_tareas_csv" +
        buildQuery({ desde: f.desde, hasta: f.hasta });
    }

    if (btnOts) {
      btnOts.addEventListener("click", (e) => {
        e.preventDefault();
        window.location.href = hrefOts();
      });
    }

    if (btnTar) {
      btnTar.addEventListener("click", (e) => {
        e.preventDefault();
        window.location.href = hrefTareas();
      });
    }
  }

  /* ============================================================
     🔴 APLICAR / LIMPIAR — SIN AJAX
     ============================================================ */
  async function aplicar() {
    return; // ❌ NO hace nada
  }

  function limpiar() {
    const d = $1("#f_desde"); if (d) d.value = "";
    const h = $1("#f_hasta"); if (h) h.value = "";
    const e = $1("#f_estado_ot"); if (e) e.value = "";
  }

  /* ============================================================
     DOM READY
     ============================================================ */
  document.addEventListener("DOMContentLoaded", function () {
    bindCsvLinks(); // ✔ esto sí puede quedarse

    const btnA = $1("#btn_aplicar");
    const btnL = $1("#btn_limpiar");

    if (btnA) btnA.addEventListener("click", () => aplicar());
    if (btnL) btnL.addEventListener("click", () => limpiar());
  });
})();
