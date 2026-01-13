// ======================================================================
// modules/admin/views/catalogos/js/index.js
// Catálogos — CRUD genérico PRO (producción + AdminLTE UX + KPIs)
// Requiere:
// - BASE_URL global
// - DataTables + spanish.json
// - Bootstrap 5 (Modal)
// - AdminLTE (opcional)
// - SweetAlert2 (recomendado; AdminLTE suele traerlo). Fallback a confirm().
// ======================================================================

$(function () {

  // -------------------------
  // DOM
  // -------------------------
  var $select          = $('#catalogoSelect');
  var $btnReload       = $('#btnReloadCatalogs');
  var $btnNuevo        = $('#btnNuevoRegistro');
  var $btnRefreshTable = $('#btnRefrescarTabla');
  var $btnMore         = $('#btnMore');
  var $btnExportCsv    = $('#btnExportCsv');
  var $btnExportJson   = $('#btnExportJson');
  var $btnVerEstructura= $('#btnVerEstructura');

  var $alert           = $('#catalogosAlert');

  var $badgeCatalogo   = $('#badgeCatalogo');
  var $badgeActivo     = $('#badgeActivoSoportado');
  var $badgeProtegido  = $('#badgeSoloLectura');
  var $badgeCount      = $('#badgeCount');

  var $tableTitle      = $('#tableTitle');
  var $tableSubtitle   = $('#tableSubtitle');

  var $quickSearch     = $('#quickSearch');
  var $btnClearSearch  = $('#btnClearSearch');

  var $table           = $('#catalogosTable');

  var $modal           = $('#catalogoModal');
  var $form            = $('#catalogoForm');
  var $fieldsWrapper   = $('#catalogoDynamicFields');
  var $slugInput       = $('#catalogoSlug');
  var $idInput         = $('#catalogoId');
  var $btnGuardar      = $('#btnGuardar');
  var $formMeta        = $('#catalogoFormMeta');
  var $formMetaText    = $('#catalogoFormMetaText');

  var $estructuraModal = $('#estructuraModal');
  var $estructuraBody  = $('#estructuraBody');
  var $estructuraSub   = $('#estructuraSub');

  // -------------------------
  // KPI DOM (si existen en la vista)
  // -------------------------
  var $kpiCatalogo    = $('#kpiCatalogo');
  var $kpiRegistros   = $('#kpiRegistros');
  var $kpiActivos     = $('#kpiActivos');
  var $kpiInactivos   = $('#kpiInactivos');
  var $kpiProtegido   = $('#kpiProtegido');

  // Bootstrap modal instances (BS5)
  var modalInstance = null;
  try { modalInstance = bootstrap.Modal.getOrCreateInstance($modal[0]); } catch (e) {}
  var estructuraInstance = null;
  try { estructuraInstance = bootstrap.Modal.getOrCreateInstance($estructuraModal[0]); } catch (e) {}

  // -------------------------
  // Estado
  // -------------------------
  var dt               = null;
  var currentSlug      = null;
  var currentLabel     = '';
  var currentColumns   = [];   // columnas visibles en tabla
  var currentRows      = [];   // cache rows (para editar sin recargar)
  var features         = { has_activo: false, can_delete: false };

  // Catálogos protegidos (ajusta a tu gusto)
  var PROTECTED_SLUGS = new Set([
    'cat_roles',
    'estados_mx',
    'municipios_mx'
  ]);

  // -------------------------
  // Helpers UI
  // -------------------------
  function showAlert(type, msg) {
    $alert
      .removeClass('d-none alert-success alert-danger alert-warning alert-info')
      .addClass('alert-' + type)
      .text(msg);

    // autohide suave
    if (type === 'success') {
      setTimeout(function () { hideAlert(); }, 2200);
    }
  }

  function hideAlert() {
    $alert.addClass('d-none').text('');
  }

  function ajaxErrorMsg(jqXHR) {
    var msg = 'Error de comunicación con el servidor.';
    if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
      msg = jqXHR.responseJSON.message;
    }
    return msg;
  }

  function beautifyColumnName(col) {
    if (!col) return '';
    if (col === 'id') return 'ID';
    return col.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function boolish(val) {
    return String(val) === '1' || val === 1 || val === true;
  }

  function setEnabledForCatalog(selected) {
    var on = !!selected;

    $btnNuevo.prop('disabled', !on);
    $btnRefreshTable.prop('disabled', !on);

    $btnMore.prop('disabled', !on);
    $btnExportCsv.prop('disabled', !on);
    $btnExportJson.prop('disabled', !on);
    $btnVerEstructura.prop('disabled', !on);

    $quickSearch.prop('disabled', !on);
    $btnClearSearch.prop('disabled', !on);
  }

  // -------------------------
  // KPIs
  // -------------------------
  function resetKPIs() {
    if (!$kpiCatalogo.length) return; // si no existen, no truena
    $kpiCatalogo.text('—');
    $kpiRegistros.text('0');
    $kpiActivos.text('0');
    $kpiInactivos.text('0');
    $kpiProtegido.text('—');
  }

  function updateKPIs() {
    if (!$kpiCatalogo.length) return;

    var total = (currentRows || []).length;
    var activos = 0;
    var inactivos = 0;

    if (features && features.has_activo) {
      (currentRows || []).forEach(function (r) {
        if (boolish(r.activo)) activos++;
        else inactivos++;
      });
    } else {
      activos = total;
      inactivos = 0;
    }

    $kpiCatalogo.text(currentLabel || currentSlug || '—');
    $kpiRegistros.text(String(total));
    $kpiActivos.text(String(activos));
    $kpiInactivos.text(String(inactivos));
    $kpiProtegido.text(PROTECTED_SLUGS.has(currentSlug) ? 'Sí' : 'No');
  }

  function updateHeaderState() {
    if (!currentSlug) {
      $badgeCatalogo
        .removeClass('text-bg-success')
        .addClass('text-bg-light border')
        .html('<i class="bi bi-info-circle"></i> Sin catálogo seleccionado');

      $badgeActivo.addClass('d-none');
      $badgeProtegido.addClass('d-none');
      $badgeCount.addClass('d-none').text('');

      $tableTitle.text('Registros del catálogo');
      $tableSubtitle.text('Selecciona un catálogo para ver los registros.');

      setEnabledForCatalog(false);
      resetKPIs();
      return;
    }

    $badgeCatalogo
      .removeClass('text-bg-light border')
      .addClass('text-bg-success')
      .html('<i class="bi bi-collection"></i> ' + escapeHtml(currentLabel || currentSlug));

    if (features.has_activo) $badgeActivo.removeClass('d-none'); else $badgeActivo.addClass('d-none');

    if (PROTECTED_SLUGS.has(currentSlug)) $badgeProtegido.removeClass('d-none'); else $badgeProtegido.addClass('d-none');

    $badgeCount.removeClass('d-none').text((currentRows || []).length + ' registros');

    $tableTitle.text('Registros: ' + (currentLabel || currentSlug));
    $tableSubtitle.text('CRUD genérico con estructura dinámica.');

    setEnabledForCatalog(true);

    // protegido = sin altas/bajas/edición
    if (PROTECTED_SLUGS.has(currentSlug)) {
      $btnNuevo.prop('disabled', true);
    }
  }

  function resetTable() {
    currentColumns = [];
    currentRows = [];
    features = { has_activo: false, can_delete: false };
    currentLabel = '';

    if (dt) {
      dt.destroy();
      dt = null;
    }
    $table.find('thead tr').empty();
    $table.find('tbody').empty();
  }

  // -------------------------
  // SweetAlert2 (AdminLTE) confirm
  // -------------------------
  function confirmAdminLTE(opts) {
    opts = opts || {};
    var title = opts.title || 'Confirmar';
    var text  = opts.text  || '';
    var icon  = opts.icon  || 'question';
    var confirmText = opts.confirmText || 'Aceptar';
    var cancelText  = opts.cancelText  || 'Cancelar';
    var danger      = !!opts.danger;

    // Si SweetAlert2 existe, úsalo
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        focusCancel: true,
        buttonsStyling: false, // IMPORTANTE: para que respete clases btn (AdminLTE/BS)
        customClass: {
          confirmButton: danger ? 'btn btn-danger ms-2' : 'btn btn-primary ms-2',
          cancelButton: 'btn btn-outline-secondary'
        }
      }).then(function (r) { return r.isConfirmed; });
    }

    // Fallback feo (browser) pero funcional
    return Promise.resolve(window.confirm((text ? (text + '\n\n') : '') + title));
  }

  // -------------------------
  // Data fetch
  // -------------------------
  function cargarCatalogos() {
    hideAlert();
    $select.prop('disabled', true);
    $select.empty().append('<option value="">-- Selecciona --</option>');

    $.getJSON(BASE_URL + 'admin/catalogos/catalogos_json', function (resp) {
      if (!resp || !resp.ok) {
        showAlert('danger', (resp && resp.message) ? resp.message : 'No se pudieron cargar los catálogos.');
        return;
      }

      (resp.data || []).forEach(function (c) {
        var text = (c.label || c.slug) + ' (' + (c.tabla || '-') + ')';
        $select.append($('<option>', { value: c.slug, text: text }));
      });

    }).fail(function (jqXHR) {
      showAlert('danger', ajaxErrorMsg(jqXHR));
    }).always(function () {
      $select.prop('disabled', false);
    });
  }

  function cargarDatosCatalogo(slug) {
    if (!slug) return;

    hideAlert();
    resetTable();
    currentSlug = slug;

    // loading UI
    currentLabel = 'Cargando...';
    updateHeaderState();
    updateKPIs();

    $.getJSON(BASE_URL + 'admin/catalogos/lista', { slug: slug }, function (resp) {
      if (!resp || !resp.ok) {
        showAlert('danger', (resp && resp.message) ? resp.message : 'Error al obtener datos del catálogo.');
        currentSlug = null;
        updateHeaderState();
        return;
      }

      currentLabel   = resp.data.label || slug;
      currentColumns = resp.data.columns || [];
      currentRows    = resp.data.rows || [];
      features       = resp.data.features || { has_activo: false, can_delete: false };

      construirDataTable(currentColumns, currentRows);
      updateHeaderState();
      updateKPIs();

      // limpia búsqueda
      $quickSearch.val('');
      if (dt) dt.search('').draw();

    }).fail(function (jqXHR) {
      showAlert('danger', ajaxErrorMsg(jqXHR));
      currentSlug = null;
      updateHeaderState();
    });
  }

  // -------------------------
  // DataTable build
  // -------------------------
  function construirDataTable(columnNames, rows) {
    if (dt) {
      dt.destroy();
      dt = null;
      $table.find('thead tr').empty();
      $table.find('tbody').empty();
    }

    var $trHead = $table.find('thead tr');

    (columnNames || []).forEach(function (col) {
      $trHead.append($('<th>').text(beautifyColumnName(col)));
    });

    $trHead.append('<th style="width:130px;">Acciones</th>');

    var tbodyHtml = '';

    (rows || []).forEach(function (row) {
      tbodyHtml += '<tr data-rowid="' + escapeHtml(row.id || '') + '">';

      (columnNames || []).forEach(function (col) {
        var val = (row[col] !== undefined && row[col] !== null) ? row[col] : '';

        if (col === 'activo') {
          var b = boolish(val)
            ? '<span class="badge text-bg-success">Activo</span>'
            : '<span class="badge text-bg-secondary">Inactivo</span>';
          tbodyHtml += '<td>' + b + '</td>';
        } else {
          tbodyHtml += '<td>' + escapeHtml(val) + '</td>';
        }
      });

      var idVal = row.id || '';
      var disabled = PROTECTED_SLUGS.has(currentSlug) ? 'disabled' : '';

      tbodyHtml += '<td class="text-center">';

      tbodyHtml += '  <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + escapeHtml(idVal) + '" title="Editar" ' + disabled + '>';
      tbodyHtml += '    <i class="bi bi-pencil-square"></i>';
      tbodyHtml += '  </button>';

      if (features.has_activo) {
        var isOn = boolish(row.activo);
        var cls  = isOn ? 'btn-outline-warning' : 'btn-outline-success';
        var ico  = isOn ? 'bi-toggle-off' : 'bi-toggle-on';
        var ttl  = isOn ? 'Desactivar' : 'Activar';

        tbodyHtml += '  <button type="button" class="btn btn-sm ' + cls + ' btn-toggle" data-id="' + escapeHtml(idVal) + '" data-on="' + (isOn ? '1' : '0') + '" title="' + ttl + '" ' + disabled + '>';
        tbodyHtml += '    <i class="bi ' + ico + '"></i>';
        tbodyHtml += '  </button>';
      } else {
        // Eliminación física bloqueada (producción)
        tbodyHtml += '  <button type="button" class="btn btn-sm btn-outline-danger btn-del" data-id="' + escapeHtml(idVal) + '" title="Eliminar" disabled>';
        tbodyHtml += '    <i class="bi bi-trash"></i>';
        tbodyHtml += '  </button>';
      }

      tbodyHtml += '</td>';
      tbodyHtml += '</tr>';
    });

    $table.find('tbody').html(tbodyHtml);

    dt = $table.DataTable({
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      language: { url: BASE_URL + 'public/js/datatables/spanish.json' }
    });
  }

  // -------------------------
  // Modal fields (dinámico)
  // -------------------------
  function inferInputType(col) {
    var lc = (col || '').toLowerCase();
    if (lc === 'activo') return 'switch';
    if (/(_id|^id_)/.test(lc)) return 'number';
    if (/(orden|indice|prioridad|nivel|dias|minutos|horas)/.test(lc)) return 'number';
    if (/(fecha)/.test(lc)) return 'date';
    if (/(hora)/.test(lc)) return 'time';
    if (/(descripcion|detalle|nota|observacion|comentario)/.test(lc)) return 'textarea';
    return 'text';
  }

  function generarCamposModal(values) {
    values = values || {};
    $fieldsWrapper.empty();

    if (!currentColumns || !currentColumns.length) {
      $fieldsWrapper.append('<div class="col-12 text-muted">Sin columnas definidas.</div>');
      return;
    }

    currentColumns.forEach(function (col) {
      if (col === 'id') return;

      var val   = (values[col] !== undefined && values[col] !== null) ? values[col] : '';
      var label = beautifyColumnName(col);
      var kind  = inferInputType(col);

      var controlHtml = '';

      if (kind === 'textarea') {
        controlHtml =
          '<textarea class="form-control" name="' + col + '" id="field_' + col + '" rows="3">'
          + escapeHtml(val) +
          '</textarea>';
      } else if (kind === 'switch') {
        var checked = boolish(val) ? 'checked' : '';
        controlHtml =
          '<div class="form-check form-switch mt-2">' +
          '  <input class="form-check-input" type="checkbox" role="switch" id="field_' + col + '" name="' + col + '" value="1" ' + checked + '>' +
          '  <label class="form-check-label small text-muted" for="field_' + col + '">Activo</label>' +
          '</div>';
      } else if (kind === 'number') {
        controlHtml =
          '<input type="number" class="form-control" name="' + col + '" id="field_' + col + '" value="' + escapeHtml(val) + '">';
      } else if (kind === 'date') {
        var v = String(val || '');
        if (v.length > 10) v = v.substring(0, 10);
        controlHtml =
          '<input type="date" class="form-control" name="' + col + '" id="field_' + col + '" value="' + escapeHtml(v) + '">';
      } else if (kind === 'time') {
        var t = String(val || '');
        if (t.length > 8) t = t.substring(0, 8);
        controlHtml =
          '<input type="time" class="form-control" name="' + col + '" id="field_' + col + '" value="' + escapeHtml(t) + '">';
      } else {
        var ph = (col === 'clave') ? 'Ej. A1, B2, ...' : '';
        controlHtml =
          '<input type="text" class="form-control" name="' + col + '" id="field_' + col + '" value="' + escapeHtml(val) + '" placeholder="' + escapeHtml(ph) + '">';
      }

      var req = (col === 'nombre' || col === 'clave') ? '<span class="text-danger">*</span>' : '';

      var html =
        '<div class="col-12 col-md-6">' +
        '  <label class="form-label" for="field_' + col + '">' + label + ' ' + req + '</label>' +
           controlHtml +
        '</div>';

      $fieldsWrapper.append(html);
    });
  }

  function openNuevo() {
    if (!currentSlug) return;

    if (PROTECTED_SLUGS.has(currentSlug)) {
      showAlert('warning', 'Este catálogo está protegido.');
      return;
    }

    $form[0].reset();
    $slugInput.val(currentSlug);
    $idInput.val('');

    $('#catalogoModalLabel').html('<i class="bi bi-plus-circle me-1"></i> Nuevo registro');
    $('#catalogoModalSub').text('Completa los campos y guarda.');

    generarCamposModal({});
    $formMeta.addClass('d-none');
    $btnGuardar.prop('disabled', false);

    if (modalInstance) modalInstance.show(); else $modal.modal('show');
  }

  function openEditar(id) {
    if (!id || !currentSlug) return;

    if (PROTECTED_SLUGS.has(currentSlug)) {
      showAlert('warning', 'Este catálogo está protegido.');
      return;
    }

    var item = null;
    (currentRows || []).forEach(function (r) {
      if (String(r.id) === String(id)) item = r;
    });

    if (!item) {
      showAlert('warning', 'No se encontró el registro seleccionado (refresca la tabla).');
      return;
    }

    $form[0].reset();
    $slugInput.val(currentSlug);
    $idInput.val(item.id || '');

    $('#catalogoModalLabel').html('<i class="bi bi-pencil-square me-1"></i> Editar #' + escapeHtml(item.id));
    $('#catalogoModalSub').text('Actualiza los campos y guarda cambios.');

    generarCamposModal(item);

    $formMeta.removeClass('d-none');
    $formMetaText.text('Catálogo: ' + (currentLabel || currentSlug) + ' · ID: ' + (item.id || ''));

    $btnGuardar.prop('disabled', false);

    if (modalInstance) modalInstance.show(); else $modal.modal('show');
  }

  // -------------------------
  // Toggle activo / desactivar (AdminLTE confirm)
  // -------------------------
  function toggleActivo(id, isCurrentlyOn) {
    if (!features.has_activo) {
      showAlert('warning', 'Este catálogo no soporta activo.');
      return;
    }
    if (PROTECTED_SLUGS.has(currentSlug)) {
      showAlert('warning', 'Este catálogo está protegido.');
      return;
    }

    var goingTo = isCurrentlyOn ? 0 : 1;

    var title = goingTo ? 'Activar registro' : 'Desactivar registro';
    var text  = (goingTo ? '¿Activar el registro #' : '¿Desactivar el registro #') + id + '?';

    confirmAdminLTE({
      title: title,
      text: text,
      icon: goingTo ? 'question' : 'warning',
      confirmText: goingTo ? 'Activar' : 'Desactivar',
      cancelText: 'Cancelar',
      danger: !goingTo
    }).then(function (ok) {
      if (!ok) return;

      $.post(BASE_URL + 'admin/catalogos/guardar', {
        slug: currentSlug,
        id: id,
        activo: goingTo
      }, function (resp) {
        if (!resp || !resp.ok) {
          showAlert('danger', (resp && resp.message) ? resp.message : 'No se pudo actualizar el estado.');
          return;
        }
        showAlert('success', goingTo ? 'Registro activado.' : 'Registro desactivado.');
        cargarDatosCatalogo(currentSlug);
      }, 'json').fail(function (jqXHR) {
        showAlert('danger', ajaxErrorMsg(jqXHR));
      });
    });
  }

  // -------------------------
  // Guardar modal
  // -------------------------
  $form.on('submit', function (e) {
    e.preventDefault();
    if (!currentSlug) return;

    var $clave = $form.find('[name="clave"]');
    if ($clave.length && String($clave.val() || '').trim() === '') {
      showAlert('warning', 'La clave es obligatoria.');
      $clave.focus();
      return;
    }

    var $nombre = $form.find('[name="nombre"]');
    if ($nombre.length && String($nombre.val() || '').trim() === '') {
      showAlert('warning', 'El nombre es obligatorio.');
      $nombre.focus();
      return;
    }

    // Switch activo: si está unchecked, serialize() no lo manda.
    var activoField = $form.find('[name="activo"]');
    var payload = $form.serializeArray();
    if (activoField.length) {
      var checked = $('#field_activo').is(':checked');
      payload = payload.filter(function (p) { return p.name !== 'activo'; });
      payload.push({ name: 'activo', value: checked ? '1' : '0' });
    }

    $btnGuardar.prop('disabled', true);

    $.post(BASE_URL + 'admin/catalogos/guardar', $.param(payload), function (resp) {
      if (!resp || !resp.ok) {
        showAlert('danger', (resp && resp.message) ? resp.message : 'No se pudo guardar el registro.');
        $btnGuardar.prop('disabled', false);
        return;
      }

      showAlert('success', resp.message || 'Guardado correctamente.');
      if (modalInstance) modalInstance.hide(); else $modal.modal('hide');

      cargarDatosCatalogo(currentSlug);

    }, 'json').fail(function (jqXHR) {
      showAlert('danger', ajaxErrorMsg(jqXHR));
    }).always(function () {
      $btnGuardar.prop('disabled', false);
    });
  });

  // -------------------------
  // Eventos tabla
  // -------------------------
  $table.on('click', '.btn-edit', function () {
    var id = $(this).data('id');
    openEditar(id);
  });

  $table.on('click', '.btn-toggle', function () {
    var id = $(this).data('id');
    var on = String($(this).data('on')) === '1';
    toggleActivo(id, on);
  });

  // -------------------------
  // Toolbar events
  // -------------------------
  $btnNuevo.on('click', openNuevo);

  $btnRefreshTable.on('click', function () {
    if (currentSlug) cargarDatosCatalogo(currentSlug);
  });

  $select.on('change', function () {
    var slug = $(this).val();
    currentSlug = null;
    resetTable();
    updateHeaderState();

    if (!slug) return;
    cargarDatosCatalogo(slug);
  });

  $btnReload.on('click', function () {
    cargarCatalogos();
  });

  // quick search
  $quickSearch.on('input', function () {
    if (!dt) return;
    dt.search($(this).val()).draw();
  });

  $btnClearSearch.on('click', function () {
    $quickSearch.val('');
    if (dt) dt.search('').draw();
  });

  // export JSON
  $btnExportJson.on('click', function () {
    if (!currentSlug) return;
    var blob = new Blob([JSON.stringify(currentRows || [], null, 2)], { type: 'application/json;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (currentSlug || 'catalogo') + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  });

  // export CSV (simple)
  function toCsvValue(v) {
    var s = (v === null || v === undefined) ? '' : String(v);
    s = s.replace(/"/g, '""');
    return '"' + s + '"';
  }

  $btnExportCsv.on('click', function () {
    if (!currentSlug) return;

    var cols = currentColumns || [];
    if (!cols.length) cols = ['id'];

    var lines = [];
    lines.push(cols.map(toCsvValue).join(','));

    (currentRows || []).forEach(function (r) {
      var row = cols.map(function (c) { return toCsvValue(r[c]); }).join(',');
      lines.push(row);
    });

    var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (currentSlug || 'catalogo') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  });

  // Ver estructura (por ahora: solo columnas visibles)
  $btnVerEstructura.on('click', function () {
    if (!currentSlug) return;

    $estructuraBody.empty();

    if (!currentColumns || !currentColumns.length) {
      $estructuraBody.append('<tr><td colspan="4" class="text-muted">Sin columnas disponibles.</td></tr>');
    } else {
      currentColumns.forEach(function (c) {
        $estructuraBody.append(
          '<tr>' +
          ' <td>' + escapeHtml(c) + '</td>' +
          ' <td class="text-muted">—</td>' +
          ' <td class="text-muted">—</td>' +
          ' <td class="text-muted">—</td>' +
          '</tr>'
        );
      });
    }

    $estructuraSub.text(currentLabel ? ('Catálogo: ' + currentLabel) : 'Estructura');
    if (estructuraInstance) estructuraInstance.show(); else $estructuraModal.modal('show');
  });

  // -------------------------
  // Init
  // -------------------------
  updateHeaderState();
  cargarCatalogos();

});
