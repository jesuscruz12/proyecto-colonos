(function () {
  const URL = {
    dt: BASE_URL + "admin/calendarioslaborales/dt",
    dtDeleted: BASE_URL + "admin/calendarioslaborales/dtDeleted",
    one: BASE_URL + "admin/calendarioslaborales/getOne",
    create: BASE_URL + "admin/calendarioslaborales/create",
    update: BASE_URL + "admin/calendarioslaborales/update",
    del: BASE_URL + "admin/calendarioslaborales/delete",
    restore: BASE_URL + "admin/calendarioslaborales/restore",
    purge: BASE_URL + "admin/calendarioslaborales/purge",
    cfg: BASE_URL + "admin/calendarioslaborales/calendarConfig",
  };

  let selectedId = null;
  let fc = null;
  let showDeleted = false;

  const DAYS = [
    { dow: 1, label: "Lun" },
    { dow: 2, label: "Mar" },
    { dow: 3, label: "Mié" },
    { dow: 4, label: "Jue" },
    { dow: 5, label: "Vie" },
    { dow: 6, label: "Sáb" },
    { dow: 7, label: "Dom" },
  ];

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function toastOk(msg) {
    if (!window.Swal) return alert(msg);
    Swal.fire({ icon: "success", title: msg, timer: 1200, showConfirmButton: false });
  }

  function toastErr(msg) {
    if (!window.Swal) return alert(msg);
    Swal.fire({ icon: "error", title: "Error", text: msg });
  }

  function ensureFullCalendar() {
    if (!window.FullCalendar || !window.FullCalendar.Calendar) {
      console.warn("FullCalendar no está cargado.");
      return false;
    }
    return true;
  }

  function initFullCalendar() {
    if (!ensureFullCalendar()) return;

    const el = document.getElementById("fcWrap");
    fc = new FullCalendar.Calendar(el, {
      initialView: "timeGridWeek",
      height: "auto",
      nowIndicator: true,
      firstDay: 1,
      slotMinTime: "06:00:00",
      slotMaxTime: "22:00:00",
      allDaySlot: false,
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "timeGridWeek,timeGridDay",
      },
      businessHours: false,
      events: [],
    });

    fc.render();
  }

  function buildDiasBox(dias = []) {
    const map = new Map((dias || []).map((d) => [Number(d.dow), d]));

    const defHi = $("#cal_hi").val() || "08:00";
    const defHf = $("#cal_hf").val() || "18:00";

    const html = DAYS.map((d) => {
      const v = map.get(d.dow);
      const checked = v ? "checked" : "";
      const inicio = v?.inicio ?? defHi;
      const fin = v?.fin ?? defHf;

      return `
        <div class="col-md-3 mb-2">
          <div class="border rounded p-2">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input dia_chk" id="dia_${d.dow}" data-dow="${d.dow}" ${checked}>
              <label class="custom-control-label" for="dia_${d.dow}">${d.label}</label>
            </div>
            <div class="mt-2">
              <small class="text-muted">Inicio</small>
              <input type="time" class="form-control form-control-sm dia_hi" data-dow="${d.dow}" value="${esc(inicio)}">
            </div>
            <div class="mt-2">
              <small class="text-muted">Fin</small>
              <input type="time" class="form-control form-control-sm dia_hf" data-dow="${d.dow}" value="${esc(fin)}">
            </div>
          </div>
        </div>
      `;
    }).join("");

    $("#diasBox").html(html);
  }

  function addPausaRow(p = {}) {
    const dow = Number(p.dow ?? 1);
    const inicio = p.inicio ?? "13:00";
    const fin = p.fin ?? "14:00";
    const nombre = p.nombre ?? "Comida";

    const opts = DAYS.map((d) =>
      `<option value="${d.dow}" ${d.dow === dow ? "selected" : ""}>${d.label}</option>`
    ).join("");

    $("#tblPausas tbody").append(`
      <tr>
        <td><select class="form-control form-control-sm pausa_dow">${opts}</select></td>
        <td><input type="time" class="form-control form-control-sm pausa_hi" value="${esc(inicio)}"></td>
        <td><input type="time" class="form-control form-control-sm pausa_hf" value="${esc(fin)}"></td>
        <td><input type="text" class="form-control form-control-sm pausa_nombre" value="${esc(nombre)}"></td>
        <td class="text-center">
          <button type="button" class="btn btn-danger btn-sm btnDelPausa"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
    `);
  }

  function readDias() {
    const out = [];
    $(".dia_chk:checked").each(function () {
      const dow = Number(this.dataset.dow);
      const inicio = $(`.dia_hi[data-dow="${dow}"]`).val();
      const fin = $(`.dia_hf[data-dow="${dow}"]`).val();
      out.push({ dow, inicio, fin });
    });
    return out;
  }

  function readPausas() {
    const out = [];
    $("#tblPausas tbody tr").each(function () {
      out.push({
        dow: Number($(this).find(".pausa_dow").val()),
        inicio: $(this).find(".pausa_hi").val(),
        fin: $(this).find(".pausa_hf").val(),
        nombre: $(this).find(".pausa_nombre").val().trim(),
      });
    });
    return out;
  }

  function openNew() {
    $("#mdlTitle").text("Nuevo calendario laboral");
    $("#cal_id").val("");
    $("#cal_nombre").val("");
    $("#cal_hi").val("08:00");
    $("#cal_hf").val("18:00");
    buildDiasBox([]);
    $("#tblPausas tbody").html("");
    $("#mdlCal").modal("show");
  }

  function openEdit(id) {
    $.get(URL.one, { id })
      .done((r) => {
        if (!r.success) return toastErr(r.message || "Error");

        const d = r.data;

        $("#mdlTitle").text("Editar calendario laboral");
        $("#cal_id").val(d.id);
        $("#cal_nombre").val(d.nombre);
        $("#cal_hi").val((d.hora_inicio || "08:00:00").substring(0, 5));
        $("#cal_hf").val((d.hora_fin || "18:00:00").substring(0, 5));

        buildDiasBox(d.dias_laborales || []);
        $("#tblPausas tbody").html("");
        (d.pausas || []).forEach(addPausaRow);

        $("#mdlCal").modal("show");
      })
      .fail(() => toastErr("No se pudo cargar"));
  }

  function saveForm(e) {
    e.preventDefault();

    const id = ($("#cal_id").val() || "").trim();

    const payload = {
      id,
      nombre: $("#cal_nombre").val().trim(),
      hora_inicio: ($("#cal_hi").val() || "08:00") + ":00",
      hora_fin: ($("#cal_hf").val() || "18:00") + ":00",
      dias_laborales: JSON.stringify(readDias()),
      pausas: JSON.stringify(readPausas()),
    };

    const url = id ? URL.update : URL.create;

    $.post(url, payload)
      .done((r) => {
        if (!r.success) return toastErr(r.message || "Error");
        toastOk(r.message || "OK");
        $("#mdlCal").modal("hide");
        dt.ajax.reload(null, false);
      })
      .fail((xhr) => toastErr(xhr?.responseJSON?.message || "Error al guardar"));
  }

  function delRow(id) {
    const doDelete = () => {
      $.post(URL.del, { id })
        .done((r) => {
          if (!r.success) return toastErr(r.message || "Error");
          toastOk("Eliminado");
          if (String(selectedId) === String(id)) {
            selectedId = null;
            $("#calTitle").text("Previsualización");
            $("#btnReloadCal").prop("disabled", true);
            if (fc) fc.removeAllEvents();
          }
          dt.ajax.reload(null, false);
        })
        .fail((xhr) => toastErr(xhr?.responseJSON?.message || "No se pudo eliminar"));
    };

    if (!window.Swal) {
      if (confirm("¿Eliminar calendario?")) doDelete();
      return;
    }

    Swal.fire({
      icon: "warning",
      title: "¿Eliminar?",
      text: "Se enviará a eliminados.",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
    }).then((res) => {
      if (res.isConfirmed) doDelete();
    });
  }

  function restoreRow(id) {
    const doRestore = () => {
      $.post(URL.restore, { id })
        .done((r) => {
          if (!r.success) return toastErr(r.message || "Error");
          toastOk("Restaurado");
          dt.ajax.reload(null, false);
        })
        .fail((xhr) => toastErr(xhr?.responseJSON?.message || "No se pudo restaurar"));
    };

    if (!window.Swal) {
      if (confirm("¿Restaurar calendario?")) doRestore();
      return;
    }

    Swal.fire({
      icon: "question",
      title: "¿Restaurar?",
      text: "Volverá a activos.",
      showCancelButton: true,
      confirmButtonText: "Sí, restaurar",
      cancelButtonText: "Cancelar",
    }).then((res) => {
      if (res.isConfirmed) doRestore();
    });
  }

  function purgeRow(id) {
    const doPurge = () => {
      $.post(URL.purge, { id })
        .done((r) => {
          if (!r.success) return toastErr(r.message || "Error");
          toastOk("Eliminado definitivo");
          dt.ajax.reload(null, false);
        })
        .fail((xhr) => toastErr(xhr?.responseJSON?.message || "No se pudo eliminar definitivo"));
    };

    if (!window.Swal) {
      if (confirm("¿Eliminar definitivo?")) doPurge();
      return;
    }

    Swal.fire({
      icon: "warning",
      title: "Eliminar definitivo",
      text: "Esto ya no se puede deshacer.",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar definitivo",
      cancelButtonText: "Cancelar",
    }).then((res) => {
      if (res.isConfirmed) doPurge();
    });
  }

  function loadCalendar(id) {
    if (!id) return;
    selectedId = id;

    $("#btnReloadCal").prop("disabled", false);

    if (!fc) {
      $("#calTitle").text("Previsualización (FullCalendar no cargado)");
      return;
    }

    $.get(URL.cfg, { id })
      .done((r) => {
        if (!r.success) return toastErr(r.message || "Error");

        const d = r.data;
        $("#calTitle").text("Previsualización: " + d.nombre);

        fc.setOption("businessHours", d.businessHours || false);

        fc.removeAllEvents();
        (d.bgEvents || []).forEach((ev) => fc.addEvent(ev));
      })
      .fail(() => toastErr("No se pudo cargar la previsualización"));
  }

  // =========================
  // DataTable
  // =========================
  const dt = $("#tblCal").DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: URL.dt,
      type: "POST",
    },
    columns: [
      { data: "id" },
      { data: "nombre" },
      { data: "hora_inicio" },
      { data: "hora_fin" },
      {
        data: null,
        orderable: false,
        render: () => {
          if (!showDeleted) {
            return `
              <div class="btn-group btn-group-sm">
                <button class="btn btn-success btnSel" title="Ver"><i class="fas fa-eye"></i></button>
                <button class="btn btn-primary btnEdit" title="Editar"><i class="fas fa-pen"></i></button>
                <button class="btn btn-danger btnDel" title="Eliminar"><i class="fas fa-trash"></i></button>
              </div>
            `;
          }
          return `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-warning btnRestore" title="Restaurar"><i class="fas fa-undo"></i></button>
              <button class="btn btn-danger btnPurge" title="Eliminar definitivo"><i class="fas fa-skull-crossbones"></i></button>
            </div>
          `;
        },
      },
    ],
    order: [[0, "desc"]],
    pageLength: 10,
    responsive: true,
  });

  function switchMode(deleted) {
    showDeleted = !!deleted;
    $("#lblMode").text(showDeleted ? "Mostrando eliminados" : "Mostrando activos");

    const newUrl = showDeleted ? URL.dtDeleted : URL.dt;
    dt.ajax.url(newUrl).load();

    // si estás viendo eliminados, no tiene sentido preview
    if (showDeleted) {
      selectedId = null;
      $("#calTitle").text("Previsualización");
      $("#btnReloadCal").prop("disabled", true);
      if (fc) fc.removeAllEvents();
    }
  }

  // UI events
  $("#btnNew").on("click", function () {
    if (showDeleted) return toastErr("Estás viendo eliminados. Quita el filtro para crear/editar.");
    openNew();
  });

  $("#frmCal").on("submit", saveForm);

  $("#btnAddPausa").on("click", () => addPausaRow());
  $("#tblPausas").on("click", ".btnDelPausa", function () {
    $(this).closest("tr").remove();
  });

  $("#tblCal").on("click", ".btnEdit", function () {
    if (showDeleted) return;
    const row = dt.row($(this).closest("tr")).data();
    openEdit(row.id);
  });

  $("#tblCal").on("click", ".btnDel", function () {
    if (showDeleted) return;
    const row = dt.row($(this).closest("tr")).data();
    delRow(row.id);
  });

  $("#tblCal").on("click", ".btnSel", function () {
    if (showDeleted) return;
    const row = dt.row($(this).closest("tr")).data();
    loadCalendar(row.id);
  });

  $("#tblCal").on("click", ".btnRestore", function () {
    const row = dt.row($(this).closest("tr")).data();
    restoreRow(row.id);
  });

  $("#tblCal").on("click", ".btnPurge", function () {
    const row = dt.row($(this).closest("tr")).data();
    purgeRow(row.id);
  });

  $("#btnReloadCal").on("click", function () {
    if (selectedId) loadCalendar(selectedId);
  });

  $("#swDeleted").on("change", function () {
    switchMode(this.checked);
  });

  $("#cal_hi, #cal_hf").on("change", function () {
    if ($(".dia_chk:checked").length === 0) buildDiasBox([]);
  });

  // init
  $(document).ready(function () {
    buildDiasBox([]);
    initFullCalendar();
  });
})();
