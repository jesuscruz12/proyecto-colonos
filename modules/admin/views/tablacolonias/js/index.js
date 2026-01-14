(function () {

  const API = {
    list: () => BASE_URL + 'admin/tablacolonias/recursos_list',
    get:  id => BASE_URL + 'admin/tablacolonias/get?id=' + id,
    save: () => BASE_URL + 'admin/tablacolonias/save',
    del:  () => BASE_URL + 'admin/tablacolonias/delete'
  };

  let dt;
  let modal;

  document.addEventListener('DOMContentLoaded', () => {

    modal = new bootstrap.Modal(document.getElementById('modal'));

    /* =========================
       DataTable
    ========================= */
    dt = $('#tbl').DataTable({
      serverSide: true,
      processing: true,
      scrollX: true,
      autoWidth: false,

      /* ===== FILTROS PERSONALIZADOS ===== */
      ajax: {
        url: API.list(),
        data: function (d) {
          d.f_q      = document.getElementById('f_q')?.value || '';   // 👈 Clave / Nombre
          d.f_estado = document.getElementById('f_estado')?.value || '';
          d.f_ciudad = document.getElementById('f_ciudad')?.value || '';
          d.f_activo = document.getElementById('f_activo')?.value || '';
        }
      },

      order: [[0, 'desc']],
      columns: [

        /* ===== EXISTENTES ===== */
        { data: 'id' },
        { data: 'clave' },
        { data: 'nombre' },

        /* ===== COLOR ===== */
        {
          data: 'primary_color',
          className: 'text-center',
          orderable: false,
          render: v => `
            <span
              title="${v}"
              style="
                display:inline-block;
                width:22px;
                height:22px;
                border-radius:4px;
                background:${v || '#0A84FF'};
                border:1px solid #ccc;
              ">
            </span>
          `
        },
        /* ===== NUEVOS (AGREGADOS) ===== */
        { data: 'razon_social', defaultContent: '' },
        { data: 'rfc', defaultContent: '' },
        { data: 'cp', defaultContent: '' },

        { data: 'ciudad' },
        { data: 'estado' },

        /* ===== CONTACTOS ===== */
        { data: 'contacto_nombre', defaultContent: '' },
        { data: 'contacto_email', defaultContent: '' },
        { data: 'contacto_tel', defaultContent: '' },

        /* ===== ACTIVO ===== */
        {
          data: 'activo',
          className: 'text-center',
          render: v =>
            v == 1
              ? '<span class="badge bg-success">Sí</span>'
              : '<span class="badge bg-secondary">No</span>'
        },

        /* ===== ACCIONES ===== */
        {
          data: null,
          orderable: false,
          className: 'text-end text-nowrap',
          render: r => `
            <div class="d-inline-flex gap-1">
              <button class="btn btn-sm btn-outline-primary" data-id="${r.id}" data-e="e">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" data-id="${r.id}" data-e="d">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          `
        }
      ]
    });

    /* =========================
       APLICAR FILTROS
    ========================= */
    document.getElementById('btn_filtrar')?.addEventListener('click', () => {
      dt.ajax.reload();
    });

    /* =========================
       LIMPIAR FILTROS
    ========================= */
    document.getElementById('btn_limpiar')?.addEventListener('click', () => {
      document.getElementById('f_q').value  = '';
      document.getElementById('f_estado').value = '';
      document.getElementById('f_ciudad').value = '';
      document.getElementById('f_activo').value = '';
      dt.ajax.reload();
    });

    /* =========================
       NUEVO
    ========================= */
    document.getElementById('btn_new').addEventListener('click', () => {
      const form = document.getElementById('frm');
      form.reset();
      form.classList.remove('was-validated');
      form.querySelector('[name="id"]').value = '';
      modal.show();
    });

    /* =========================
       EDITAR / ELIMINAR
    ========================= */
    $('#tbl').on('click', 'button', async e => {
      const btn = e.currentTarget;
      const id = btn.dataset.id;
      const ev = btn.dataset.e;

      if (ev === 'e') {
        const r = await fetch(API.get(id)).then(r => r.json());
        if (!r.ok) {
          alert(r.msg || 'Error al cargar');
          return;
        }

        Object.entries(r.data).forEach(([k, v]) => {
          const el = document.querySelector(`[name="${k}"]`);
          if (el) el.value = v ?? '';
        });

        modal.show();
      }

      if (ev === 'd') {
        if (!confirm('¿Eliminar colonia?')) return;

        const fd = new FormData();
        fd.append('id', id);

        await fetch(API.del(), { method: 'POST', body: fd });
        dt.ajax.reload(null, false);
      }
    });

    /* =========================
       GUARDAR
    ========================= */
    document.getElementById('btn_save').addEventListener('click', async () => {
      const form = document.getElementById('frm');

      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
      }

      const fd = new FormData(form);

      const r = await fetch(API.save(), {
        method: 'POST',
        body: fd
      }).then(r => r.json());

      if (!r.ok) {
        alert(r.msg || 'Error al guardar');
        return;
      }

      modal.hide();
      form.reset();
      form.classList.remove('was-validated');
      dt.ajax.reload(null, false);
    });

  });

})();
