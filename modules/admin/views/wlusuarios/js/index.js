app.globales.cv_usuario = 0;
app.core.wlusuarios = {
    modelo: function () {
        // 🔹 Llenar tabla
        llenar_tabla = function () {
            $('#wlusuarios-table').DataTable().ajax.reload(null, false);
        };

        // 🔹 Crear usuario
        crear = function () {
            $('#frm_nuevo').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: BASE_URL + 'admin/wlusuarios/crear',
                    type: 'POST',
                    data: $(this).serialize(),
                    beforeSend: function () {
                        $('#frm_nuevo button[type=submit]').prop('disabled', true);
                    },
                    success: function (resp) {
                        if (resp) {
                            $('#modal-crear').modal('hide');
                            llenar_tabla();
                            Swal.fire('Éxito', 'Usuario creado correctamente', 'success');
                        } else {
                            Swal.fire('Error', resp.message || 'Ocurrió un error', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error en la petición AJAX', 'error');
                    },
                    complete: function () {
                        $('#frm_nuevo button[type=submit]').prop('disabled', false);
                    }
                });
            });
        };

        // 🔹 Mostrar datos para edición
        datos_show = function (id) {
            var dataString = "clave=" + id;
            $.ajax({
                type: "POST",
                url: BASE_URL + "admin/wlusuarios/datos_show_usuario/",
                data: dataString,
                success: function (data) {
                    var obj = jQuery.parseJSON(data);
                    
                    if (obj.length >0) {
                        $("#id_usuario_editar").val(obj[0].id_usuario);
                        $("#nombre_editar").val(obj[0].nombre);
                        $("#email_editar").val(obj[0].email);
                        $("#rol_editar").val(obj[0].rol);
                        $("#estatus_editar").val(obj[0].estatus);
                        // Bootstrap 5: mostrar modal con la API nativa
                        var modalEl = document.getElementById("modal-editar");
                        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    } else {
                        Swal.fire("Error", obj.message || "No se pudo cargar el usuario", "error");
                    }
                },
                error: function () {
                    Swal.fire("Error", "No se pudo conectar con el servidor", "error");
                }
            });
        };

        // 🔹 Editar usuario
        editar = function () {
            $('#frm_editar').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: BASE_URL + 'admin/wlusuarios/editar',
                    type: 'POST',
                    data: $(this).serialize(),
                    beforeSend: function () {
                        $('#frm_editar button[type=submit]').prop('disabled', true);
                    },
                    success: function (resp) {
                        if (resp.success) {
                            $('#modal-editar').modal('hide');
                            llenar_tabla();
                            Swal.fire('Éxito', 'Usuario actualizado correctamente', 'success');
                        } else {
                            Swal.fire('Error', resp.message || 'Ocurrió un error', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error en la petición AJAX', 'error');
                    },
                    complete: function () {
                        $('#frm_editar button[type=submit]').prop('disabled', false);
                    }
                });
            });
        };

        // 🔹 Eliminar usuario
        eliminar = function (id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Este usuario será eliminado permanentemente',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(BASE_URL + 'admin/wlusuarios/eliminar_usuario', { id_usuario: id }, function (resp) {
                        
                        if (resp==="ok") {
                            llenar_tabla();
                            Swal.fire('Éxito', 'Usuario eliminado correctamente', 'success');
                        } else {
                            Swal.fire('Error', resp.message || 'No se pudo eliminar el usuario', 'error');
                        }
                    });
                }
            });
        };

        return {
            llenar_tabla: llenar_tabla,
            crear: crear,
            editar: editar,
            eliminar: eliminar,
            datos_show: datos_show
        };
    },

    controlador: function () {
        // 🔹 Inicializar DataTable
        $('#wlusuarios-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: BASE_URL + 'admin/wlusuarios/usuarios_list',
            responsive: true,
            order: [[0, 'desc']],
            columns: [
                { data: 'id_usuario' },
                { data: 'nombre' },
                { data: 'email' },
                { data: 'telefono' },
                {
                    data: 'rol',
                    render: function (data) {
                        switch (parseInt(data)) {
                            case 1: return 'Administrador';
                            case 2: return 'Operativo';
                            case 3: return 'Consulta';
                            default: return 'Desconocido';
                        }
                    }
                },
                {
                    data: 'estatus',
                    render: function (data) {
                        return data == 1
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
                            <button class="btn btn-sm btn-warning btn-editar" data-clave="${row.id_usuario}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-eliminar" data-clave="${row.id_usuario}">
                                <i class="bi bi-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: {
                url: BASE_URL + 'public/plugins/datatables/es-ES.json'
            }
        });

        // 🔹 Recargar tabla
        $('#btn_recargar').on('click', function () {
            app.core.wlusuarios.modelo().llenar_tabla();
        });

        // Inicializar CRUD
        app.core.wlusuarios.modelo().crear();
        app.core.wlusuarios.modelo().editar();

        // Eventos de botones
        $('#wlusuarios-table').on('click', '.btn-editar', function () {
            let id = $(this).data('clave');
            app.globales.cv_usuario = id;
            app.core.wlusuarios.modelo().datos_show(id);
        });

        $('#wlusuarios-table').on('click', '.btn-eliminar', function () {
            let id = $(this).data('clave');
            app.globales.cv_usuario = id;
            app.core.wlusuarios.modelo().eliminar(id);
        });
    }
};

$(document).ready(function () {
    app.core.wlusuarios.controlador();
});
