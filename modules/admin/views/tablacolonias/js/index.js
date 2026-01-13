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
      ajax: API.list(),
      order: [[0, 'desc']],
      columns: [
        { data: 'id' },
        { data: 'clave' },
        { data: 'nombre' },
        { data: 'ciudad' },
        { data: 'estado' },
        {
          data: 'activo',
          render: v => v == 1
            ? '<span class="badge bg-success">Sí</span>'
            : '<span class="badge bg-secondary">No</span>'
        },
        {
          data: null,
          orderable: false,
          className: 'text-end',
          render: r => `
            <button class="btn btn-sm btn-outline-primary me-1" data-id="${r.id}" data-e="e">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" data-id="${r.id}" data-e="d">
              <i class="bi bi-trash"></i>
            </button>
          `
        }
      ]
    });

    /* =========================
       Nuevo
    ========================= */
    document.getElementById('btn_new').addEventListener('click', () => {
      document.getElementById('frm').reset();
      document.querySelector('[name="id"]').value = '';
      modal.show();
    });

    /* =========================
       Editar / Eliminar
    ========================= */
    $('#tbl').on('click', 'button', async e => {
      const btn = e.currentTarget;
      const id = btn.dataset.id;
      const ev = btn.dataset.e;

      /* ===== EDITAR ===== */
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

      /* ===== ELIMINAR ===== */
      if (ev === 'd') {
        if (!confirm('¿Eliminar colonia?')) return;

        const fd = new FormData();
        fd.append('id', id);

        await fetch(API.del(), { method: 'POST', body: fd });
        dt.ajax.reload(null, false);
      }
    });

    /* =========================
       Guardar
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
