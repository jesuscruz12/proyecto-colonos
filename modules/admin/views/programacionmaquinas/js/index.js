/**
 * Programación por Máquinas — JS TAKTIK (PRO)
 * - Rango rápido (Hoy/7/30/Todo)
 * - Default: próximos 7 días
 * - Agenda se recarga al seleccionar máquina
 *
 * Requiere globales: BASE_URL
 */

(function () {
  let maquinaSel = 0;
  let maquinaSelNombre = "";

  const toast = (msg) => {
    if (window.alertify && alertify.success) return alertify.success(msg);
    alert(msg);
  };
  const warn = (msg) => {
    if (window.alertify && alertify.error) return alertify.error(msg);
    alert(msg);
  };

  const pad = (n) => String(n).padStart(2, "0");
  const isoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const setRange = (days) => {
    const d0 = new Date();
    const d1 = new Date();
    d1.setDate(d1.getDate() + days);
    document.querySelector("#f_desde").value = isoDate(d0);
    document.querySelector("#f_hasta").value = isoDate(d1);
  };
  const clearRange = () => {
    document.querySelector("#f_desde").value = "";
    document.querySelector("#f_hasta").value = "";
  };

  const toLocalInput = (dtStr) => {
    if (!dtStr) return "";
    // acepta "YYYY-MM-DD HH:MM:SS" o ISO
    const s = String(dtStr).replace(" ", "T");
    const d = new Date(s);
    if (isNaN(d.getTime())) return "";
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  // ✅ default UX: próximos 7 días
  setRange(7);

  // Modal bootstrap 5
  const mdl = new bootstrap.Modal(document.getElementById("mdl_prog"));

  // =====================
  // DT Máquinas
  // =====================
  const dtMaq = $("#tbl_maquinas").DataTable({
    processing: true,
    serverSide: true,
    paging: true,
    searching: true,
    ordering: true,
    ajax: {
      url: `${BASE_URL}/admin/programacionmaquinas/dt_maquinas`,
      type: "POST",
    },
    columns: [
      { data: "id" },
      { data: "codigo", defaultContent: "" },
      { data: "nombre" },
      { data: "tipo", defaultContent: "" },
      { data: "calendario_nombre", defaultContent: "" },
      {
        data: "activo",
        searchable: false,
        render: (d) =>
          Number(d) === 1
            ? '<span class="badge text-bg-success">Sí</span>'
            : '<span class="badge text-bg-secondary">No</span>',
      },
    ],
    pageLength: 10,
    lengthMenu: [10, 25, 50],
  });

  // =====================
  // DT Agenda
  // =====================
  const dtAg = $("#tbl_agenda").DataTable({
  processing: true,
  serverSide: true,
  paging: true,
  searching: true,
  ordering: true,
  ajax: {
    url: `${BASE_URL}/admin/programacionmaquinas/dt_tareas`,
    type: "POST",
    data: function (d) {
      d.maquina_id = maquinaSel;
      d.desde = $("#f_desde").val() || "";
      d.hasta = $("#f_hasta").val() || "";
    },
  },
  columns: [
    { data: "fecha_inicio" },
    { data: "fecha_fin" },
    {
      data: null,
      render: (r) => `<b>${r.folio_ot || ""}</b><div class="small text-muted">${r.descripcion || ""}</div>`,
    },
    { data: "secuencia" },
    { data: "proceso_nombre" },
    { data: "cantidad", className: "text-end" },
    { data: "setup_minutos", className: "text-end" },
    { data: "segundos_por_unidad", className: "text-end" },
    { data: "programacion_notas", defaultContent: "" },
    {
      data: null,
      orderable: false,
      searchable: false,
      render: (r) => `
        <button class="btn btn-sm btn-outline-primary btn_edit" data-id="${r.programacion_id}" type="button" title="Editar slot">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger btn_del" data-id="${r.programacion_id}" type="button" title="Eliminar slot">
          <i class="bi bi-trash"></i>
        </button>
      `,
    },
  ],
  pageLength: 10,
  lengthMenu: [10, 25, 50],
  order: [[0, "asc"], [3, "asc"]], // ✅ inicio + secuencia
});


  function fillMaqSelect() {
    const rows = dtMaq.rows().data().toArray();
    const $s = $("#p_maquina_id");
    $s.empty();
    rows.forEach((m) => $s.append(`<option value="${m.id}">${m.nombre || ""}</option>`));
    if (maquinaSel > 0) $s.val(String(maquinaSel));
  }

  async function loadOTs(q) {
    const res = await fetch(`${BASE_URL}/admin/programacionmaquinas/cat_ots?q=${encodeURIComponent(q || "")}`);
    const js = await res.json();

    const $ot = $("#p_ot_id");
    $ot.empty();

    if (!js.ok) {
      $ot.append(`<option value="">Error al cargar</option>`);
      return;
    }

    const rows = js.data || [];
    if (!rows.length) {
      $ot.append(`<option value="">Sin resultados</option>`);
      return;
    }

    rows.forEach((r) => {
      const txt = `${r.folio_ot || "OT"} — ${r.descripcion || ""} [${r.estado}] (${r.prioridad})`;
      $ot.append(`<option value="${r.id}">${txt}</option>`);
    });
  }

  // Click máquina: selecciona y recarga agenda
  $("#tbl_maquinas tbody").on("click", "tr", function () {
    const r = dtMaq.row(this).data();
    if (!r) return;

    maquinaSel = Number(r.id);
    maquinaSelNombre = r.nombre || `Máquina ${r.id}`;
    $("#lbl_maquina").text(`${maquinaSelNombre} (ID ${maquinaSel})`);

    fillMaqSelect();
    dtAg.ajax.reload();
  });

  // Filtro manual
  $("#btn_filtrar").on("click", function () {
    if (maquinaSel <= 0) return warn("Selecciona una máquina.");
    dtAg.ajax.reload();
  });

  // Rangos rápidos
  $("#rng_hoy").on("click", function () {
    setRange(0);
    if (maquinaSel > 0) dtAg.ajax.reload();
  });
  $("#rng_7").on("click", function () {
    setRange(7);
    if (maquinaSel > 0) dtAg.ajax.reload();
  });
  $("#rng_30").on("click", function () {
    setRange(30);
    if (maquinaSel > 0) dtAg.ajax.reload();
  });
  $("#rng_todo").on("click", function () {
    clearRange();
    if (maquinaSel > 0) dtAg.ajax.reload();
  });

  // Nuevo
  $("#btn_nueva").on("click", async function () {
    if (maquinaSel <= 0) return warn("Selecciona una máquina.");

    $("#mdl_title").text("Programar OT");
    $("#p_id").val("0");

    fillMaqSelect();
    $("#p_maquina_id").val(String(maquinaSel));

    const d = new Date();
    d.setHours(8, 0, 0, 0);
    const d2 = new Date(d.getTime() + 60 * 60 * 1000);

    $("#p_fi").val(toLocalInput(d.toISOString()));
    $("#p_ff").val(toLocalInput(d2.toISOString()));
    $("#p_notas").val("");
    $("#p_ot_q").val("");

    await loadOTs("");
    mdl.show();
  });

  $("#p_ot_buscar").on("click", function () {
    loadOTs($("#p_ot_q").val() || "");
  });

  $("#p_ot_q").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      loadOTs($("#p_ot_q").val() || "");
    }
  });

  // Edit
  $("#tbl_agenda").on("click", ".btn_edit", async function () {
    const id = Number($(this).data("id") || 0);
    if (!id) return;

    const res = await fetch(`${BASE_URL}/admin/programacionmaquinas/get_one?id=${id}`);
    const js = await res.json();
    if (!js.ok || !js.data) return warn("No se pudo cargar.");

    const r = js.data;
    $("#mdl_title").text("Editar programación");
    $("#p_id").val(String(r.id));

    fillMaqSelect();
    $("#p_maquina_id").val(String(r.maquina_id));

    $("#p_ot_q").val(r.folio_ot || "");
    await loadOTs(r.folio_ot || "");
    $("#p_ot_id").val(String(r.orden_trabajo_id));

    $("#p_fi").val(toLocalInput(r.fecha_inicio));
    $("#p_ff").val(toLocalInput(r.fecha_fin));
    $("#p_notas").val(r.notas || "");

    mdl.show();
  });

  // Delete
  $("#tbl_agenda").on("click", ".btn_del", async function () {
    const id = Number($(this).data("id") || 0);
    if (!id) return;

    const ok = window.alertify
      ? await new Promise((resolve) => {
          alertify.confirm("Eliminar", "¿Eliminar esta programación?", () => resolve(true), () => resolve(false));
        })
      : confirm("¿Eliminar esta programación?");

    if (!ok) return;

    const fd = new FormData();
    fd.append("id", String(id));

    const res = await fetch(`${BASE_URL}/admin/programacionmaquinas/delete`, { method: "POST", body: fd });
    const js = await res.json();

    if (!js.ok) return warn(js.msg || "No se eliminó.");
    toast("Eliminado.");
    dtAg.ajax.reload();
  });

  // Save
  $("#btn_guardar").on("click", async function () {
    const id = Number($("#p_id").val() || 0);
    const maquina_id = Number($("#p_maquina_id").val() || 0);
    const orden_trabajo_id = Number($("#p_ot_id").val() || 0);
    const fi = $("#p_fi").val();
    const ff = $("#p_ff").val();
    const notas = $("#p_notas").val() || "";

    if (!maquina_id) return warn("Selecciona máquina.");
    if (!orden_trabajo_id) return warn("Selecciona OT.");
    if (!fi || !ff) return warn("Captura fechas.");
    if (new Date(ff).getTime() <= new Date(fi).getTime()) return warn("Fin debe ser mayor a inicio.");

    const fd = new FormData();
    fd.append("id", String(id));
    fd.append("maquina_id", String(maquina_id));
    fd.append("orden_trabajo_id", String(orden_trabajo_id));
    fd.append("fecha_inicio", fi.replace("T", " ") + ":00");
    fd.append("fecha_fin", ff.replace("T", " ") + ":00");
    fd.append("notas", notas);

    const res = await fetch(`${BASE_URL}/admin/programacionmaquinas/save`, { method: "POST", body: fd });
    const js = await res.json();

    if (!js.ok) return warn(js.msg || "Error al guardar.");
    toast(js.msg || "Guardado.");
    mdl.hide();
    dtAg.ajax.reload();
  });
})();
