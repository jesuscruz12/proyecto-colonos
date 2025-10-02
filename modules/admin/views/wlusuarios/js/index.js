/* globals $, Swal, bootstrap, BASE_URL */
(function () {
  'use strict';

  //========================================
  // Config / Namespace
  //========================================
  window.app = window.app || {};
  app.globales = app.globales || {};
  app.globales.cv_usuario = 0;

  const ENDPOINTS = {
    LIST   : 'admin/wlusuarios/usuarios_list',
    CREATE : 'admin/wlusuarios/crear',
    READ   : 'admin/wlusuarios/datos_show_usuario',
    EDIT   : 'admin/wlusuarios/editar_usuario', // tu método actual en el controlador
    DELETE : 'admin/wlusuarios/eliminar_usuario',
  };

  const DT_SELECTOR = '#wlusuarios-table';

  //========================================
  // Helpers
  //========================================
  const isOk = (resp) => {
    if (typeof resp === 'string') return resp.trim().toLowerCase() === 'ok';
    if (resp && typeof resp === 'object') return !!(resp.ok || resp.success || resp.info);
    return !!resp;
  };

  const getMsg = (resp, fallback) =>
    (resp && (resp.msg || resp.message)) || fallback;

  // Deshabilita SOLO el botón submit (para no romper serialize())
  const setBusy = (form, busy) => {
    const $btn = $(form).find('button[type="submit"]');
    $btn.prop('disabled', !!busy);
    if (busy) form.setAttribute('data-busy', '1');
    else form.removeAttribute('data-busy');
  };

  // Solo dígitos, máx 10, en inputs .input-numeric
  const bindPhoneSanitizer = (scope = document) => {
    scope.querySelectorAll('.input-numeric').forEach(el => {
      el.addEventListener('input', () => {
        el.value = (el.value || '').replace(/[^\d]/g, '').slice(0, 10);
      });
    });
  };

  // Limpia valores + estado de validación
  const resetForm = (form) => {
    if (!form) return;
    form.reset();
    form.classList.remove('was-validated');
    form.querySelectorAll('input[type="password"]').forEach(p => p.value = '');
  };

  // Resetea formularios al abrir/cerrar modales (evita “datos pegados”)
  const bindModalReset = () => {
    const mCrear  = document.getElementById('modal-crear');
    const mEditar = document.getElementById('modal-editar');

    if (mCrear) {
      mCrear.addEventListener('shown.bs.modal',  () => resetForm(document.getElementById('frm_nuevo')));
      mCrear.addEventListener('hidden.bs.modal', () => resetForm(document.getElementById('frm_nuevo')));
    }
    if (mEditar) {
      mEditar.addEventListener('hidden.bs.modal', () => resetForm(document.getElementById('frm_editar')));
    }
  };

  //========================================
  // Módulo
  //========================================
  app.core = app.core || {};
  app.core.wlusuarios = {

    modelo: function () {

      const llenar_tabla = function () {
        const dt = $(DT_SELECTOR).DataTable();
        if (dt) dt.ajax.reload(null, false);
      };

      // ===== CREAR =====
      const crear = function () {
        $('#frm_nuevo').off('submit').on('submit', function (e) {
          e.preventDefault();
          const form = this;

          if (form.checkValidity && !form.checkValidity()) {
            form.classList.add('was-validated');
            return;
          }
          if (form.hasAttribute('data-busy')) return;

          const payload = $(form).serialize(); // ✅ serializa ANTES
          setBusy(form, true);                 // luego deshabilita SOLO el botón

          $.ajax({
            url: BASE_URL + ENDPOINTS.CREATE,
            type: 'POST',
            data: payload,
            dataType: 'json'
          })
          .done(function (resp) {
            if (isOk(resp)) {
              (bootstrap.Modal.getInstance(document.getElementById('modal-crear'))
                || bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-crear'))).hide();
              resetForm(form);
              llenar_tabla();
              Swal.fire('Éxito', getMsg(resp, 'Usuario creado correctamente'), 'success');
            } else {
              Swal.fire('Error', getMsg(resp, 'Ocurrió un error al crear'), 'error');
            }
          })
          .fail(function (xhr) {
            const raw = xhr.responseJSON?.msg || xhr.responseJSON?.message || xhr.responseText || 'Error en la petición AJAX';
            Swal.fire('Error', raw, 'error');
          })
          .always(function () {
            setBusy(form, false);
          });
        });
      };

      // ===== OBTENER DATOS PARA EDICIÓN =====
      const datos_show = function (id) {
        $.ajax({
          url: BASE_URL + ENDPOINTS.READ,
          type: 'POST',
          data: { clave: id },
          dataType: 'json'
        })
        .done(function (obj) {
          let u = null;
          if (Array.isArray(obj) && obj.length > 0) u = obj[0];
          else if (obj && obj.data) u = obj.data;

          if (!u) {
            Swal.fire('Error', getMsg(obj, 'No se pudieron cargar los datos'), 'error');
            return;
          }

          const form = document.getElementById('frm_editar');
          resetForm(form); // evita residuos

          $('#id_usuario_editar').val(u.id_usuario);
          $('#nombre_editar').val(u.nombre);
          $('#email_editar').val(u.email);
          $('#telefono_editar').val(u.telefono || '');
          $('#rol_editar').val(u.rol);
          $('#estatus_editar').val(u.estatus);

          bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-editar')).show();
        })
        .fail(function (xhr) {
          const raw = xhr.responseJSON?.msg || xhr.responseJSON?.message || xhr.responseText || 'No se pudo conectar con el servidor';
          Swal.fire('Error', raw, 'error');
        });
      };

      // ===== EDITAR =====
      const editar = function () {
        $('#frm_editar').off('submit').on('submit', function (e) {
          e.preventDefault();
          const form = this;

          if (form.checkValidity && !form.checkValidity()) {
            form.classList.add('was-validated');
            return;
          }
          if (form.hasAttribute('data-busy')) return;

          const payload = $(form).serialize(); // ✅ serializa ANTES
          setBusy(form, true);

          $.ajax({
            url: BASE_URL + ENDPOINTS.EDIT,
            type: 'POST',
            data: payload, // incluye 'clave'
            dataType: 'json'
          })
          .done(function (resp) {
            if (isOk(resp)) {
              bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-editar')).hide();
              resetForm(form);
              llenar_tabla();
              Swal.fire('Éxito', getMsg(resp, 'Usuario actualizado correctamente'), 'success');
            } else {
              Swal.fire('Error', getMsg(resp, 'Ocurrió un error al actualizar'), 'error');
            }
          })
          .fail(function (xhr) {
            const raw = xhr.responseJSON?.msg || xhr.responseJSON?.message || xhr.responseText || 'Error en la petición AJAX';
            Swal.fire('Error', raw, 'error');
          })
          .always(function () {
            setBusy(form, false);
          });
        });
      };

      // ===== ELIMINAR =====
      const eliminar = function (id) {
        Swal.fire({
          title: '¿Estás seguro?',
          text: 'Este usuario será eliminado permanentemente',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (!result.isConfirmed) return;

          $.ajax({
            url: BASE_URL + ENDPOINTS.DELETE,
            type: 'POST',
            data: { id_usuario: id },
            dataType: 'json'
          })
          .done(function (resp) {
            if (isOk(resp) || (typeof resp === 'string' && resp.trim().toLowerCase() === 'ok')) {
              Swal.fire('Éxito', 'Usuario eliminado correctamente', 'success');
              llenar_tabla();
            } else {
              Swal.fire('Error', getMsg(resp, 'No se pudo eliminar el usuario'), 'error');
            }
          })
          .fail(function (xhr) {
            const raw = xhr.responseJSON?.msg || xhr.responseJSON?.message || xhr.responseText || 'Error de servidor al eliminar';
            Swal.fire('Error', raw, 'error');
          });
        });
      };

      return { llenar_tabla, crear, editar, eliminar, datos_show };
    },

    controlador: function () {
      // ===== DataTable =====
      $(DT_SELECTOR).DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
          url: BASE_URL + ENDPOINTS.LIST,
          type: 'GET',
          dataSrc: (json) => json?.data || json,
          error: function (xhr) {
            const raw = xhr.responseJSON?.message || xhr.responseText || 'No fue posible cargar el listado';
            Swal.fire('Error', raw, 'error');
          }
        },
        order: [[0, 'desc']],
        columns: [
          { data: 'id_usuario' },
          { data: 'nombre' },
          { data: 'email' },
          { data: 'telefono' },
          {
            data: 'rol',
            render: function (data) {
              switch (parseInt(data, 10)) {
                case 1: return '<span class="badge bg-danger">Administrador</span>';
                case 2: return '<span class="badge bg-primary">Operativo</span>';
                case 3: return '<span class="badge bg-secondary">Consulta</span>';
                default: return '<span class="badge bg-dark">Desconocido</span>';
              }
            }
          },
          {
            data: 'estatus',
            render: function (data) {
              return String(data) === '1'
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';
            }
          },
          {
            data: null,
            className: 'dt-actions text-center',
            orderable: false,
            render: function (data, type, row) {
              return `
                <button class="btn btn-sm btn-warning btn-editar" data-clave="${row.id_usuario}" title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-danger btn-eliminar" data-clave="${row.id_usuario}" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </button>`;
            }
          }
        ],
        language: {
          url: BASE_URL + 'public/plugins/datatables/es-ES.json'
        }
      });

      // ===== Acciones de barra =====
      $('#btn_recargar').off('click').on('click', () => {
        app.core.wlusuarios.modelo().llenar_tabla();
      });

      // Búsqueda global (si existe)
      const $filtro = $('#filtro-global');
      if ($filtro.length) {
        const dt = $(DT_SELECTOR).DataTable();
        let t = null;
        $filtro.off('input').on('input', function () {
          clearTimeout(t);
          const val = this.value;
          t = setTimeout(() => dt.search(val).draw(), 220);
        });
      }

      // ===== Inicializaciones =====
      const m = app.core.wlusuarios.modelo();
      m.crear();
      m.editar();
      bindPhoneSanitizer(document);
      bindModalReset();

      // ===== Acciones por fila =====
      $(DT_SELECTOR).off('click', '.btn-editar').on('click', '.btn-editar', function () {
        const id = $(this).data('clave');
        app.globales.cv_usuario = id;
        m.datos_show(id);
      });

      $(DT_SELECTOR).off('click', '.btn-eliminar').on('click', '.btn-eliminar', function () {
        const id = $(this).data('clave');
        app.globales.cv_usuario = id;
        m.eliminar(id);
      });
    }
  };

  // Boot
  $(document).ready(function () {
    app.core.wlusuarios.controlador();
  });
})();
