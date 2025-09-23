app.globales.cv_temporal = 0;
app.core.index = {
  modelo: function () {

    llenar_tabla = function () {
      var oTable = $('#recursos-table').dataTable();
      $.ajax({
        url: BASE_URL + "admin/usuarios/recursos_list/",
        dataType: 'json',
        success: function (s) {
          oTable.fnClearTable();
          var acciones = "";
          var tipo_user = "";
          var nombre = "";
          for (var i = 0; i < s.length; i++) {
            acciones = '<button data-toggle="tooltip" data-placement="top" data-original-title="Editar" type="button" data-clave="' + s[i]['cv_usuario'] + '" data-accion="editar" class="accion_user"><i class="fas fa-edit"></i></button>' +
              '<button data-toggle="tooltip" data-placement="top" data-original-title="Eliminar" type="button" data-clave="' + s[i]['cv_usuario'] + '" data-accion="eliminar"  class="accion_user"><i class="fas fa-times-circle"></i></button>';
            nombre = s[i]['nombre_usuario'];


            oTable.fnAddData([nombre, s[i]['email'], acciones]);
          }
          $('[data-toggle="tooltip"]').tooltip();
        }
      });
    };


    nuevo = function () {
      $("#frm_nuevo .spinner-border").css("display", "block");
      var data = new FormData();
      var other_data = $('#frm_nuevo').serializeArray();
      $.each(other_data, function (key, input) {
        data.append(input.name, input.value);
      });
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios/registrar_recurso",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          if (obj.alert == 'info') {
            llenar_tabla();
            $('#frm_nuevo').each(function () {
              this.reset();
            });
            alertify.success(obj.mensaje);
          } else {
            alertify.error(obj.mensaje);
          }
          $("#frm_nuevo .spinner-border").css("display", "none");

        }
      });

    };

    eliminar = function (clave) {
      var dataString = 'clave=' + clave;
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios/eliminar_recurso",
        data: dataString,
        success: function (data) {
          llenar_tabla();

        }
      });

    };


    datos_show = function (clave) {
      var dataString = 'clave=' + clave;
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios/datos_show_recurso/",
        data: dataString,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          $("#x1_x").val(obj[0].nombre_usuario);
          $("#x2_x").val(obj[0].email);
          $('#editar-recurso').modal('show');
        }
      });
    };



    editar = function () {
      $("#frm_editar .spinner-border").css("display", "block");
      var data = new FormData();
      data.append('clave', app.globales.cv_temporal);

      var other_data = $('#frm_editar').serializeArray();
      $.each(other_data, function (key, input) {
        data.append(input.name, input.value);
      });
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios/editar_recurso",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          if (obj.alert == 'info') {
            alertify.success(obj.mensaje);
            llenar_tabla();
          } else {
            alertify.error(obj.mensaje);
          }
          $("#frm_editar .spinner-border").css("display", "none");
        }
      });

    };


    // API publica
    return {
      llenar_tabla: llenar_tabla,
      nuevo: nuevo,
      datos_show: datos_show,
      editar: editar,
      eliminar: eliminar
    }
  },


  controlador: function () {
    var frot = $('#recursos-table').DataTable({
      lengthChange: !1,
      buttons: ["copy", "excel", "pdf", "colvis"],
      "language": {
        "buttons": {
          "colvis": "Ocultar columna",
          "copy": "Copiar",
          "copyTitle": "Copiado",
          "copyKeys": "",
          "copySuccess": {
            "_": "%d filas copiadas",
            "1": "1 ligne copiÃ©e"
          }
        },
        "sProcessing": "Procesando...",
        "sLengthMenu": "Mostrar _MENU_ registros",
        "sZeroRecords": "No se encontraron resultados",
        "sEmptyTable": "Ningún dato disponible en esta tabla",
        "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
        "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
        "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
        "sInfoPostFix": "",
        "sSearch": "Buscar:",
        "sUrl": "",
        "sInfoThousands": ",",
        "sLoadingRecords": "Cargando...",
        "oPaginate": {
          "sFirst": "Primero",
          "sLast": "Ãšltimo",
          "sNext": "Siguiente",
          "sPrevious": "Anterior"
        },
        "oAria": {
          "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
          "sSortDescending": ": Activar para ordenar la columna de manera descendente"
        }
      }
    }).buttons().container().appendTo("#recursos-table_wrapper .col-md-6:eq(0)");

    $('#recursos-table tbody').on('click', 'tr', function () {
      if ($(this).hasClass('selected')) {
        $(this).removeClass('selected');
      }
      else {
        var table = $('#recursos-table').DataTable();
        table.$('tr.selected').removeClass('selected');
        $(this).addClass('selected');
      }
    });


    app.core.index.modelo().llenar_tabla();
    $('#frm_nuevo').submit(function (e) {
      app.core.index.modelo().nuevo();
      e.preventDefault();
    });


    $('#recursos-table').delegate('.accion_user', 'click', function () {
      var accion = $(this).data('accion');
      var clave = $(this).data('clave');
      app.globales.cv_temporal = clave;
      if (accion == 'eliminar') {
        Swal.fire({
          title: "¿Deseas eliminar el registro?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#11c46e",
          cancelButtonColor: "#f46a6a",
          cancelButtonText: "Cancelar",
          confirmButtonText: "Si!"
        }).then(function (result) {
          if (result.value) {
            var table = $('#recursos-table').DataTable();
            table.row('.selected').remove().draw(false);
            app.core.index.modelo().eliminar(clave);
            Swal.fire("Eliminado!", "Se elimino correctamente.", "success");
          }
        });
      }
      if (accion == 'editar') {
        $(".spinner-border").css("display", "none");
        app.core.index.modelo().datos_show(clave);
      }
    });



    $('#frm_editar').submit(function (e) {
      app.core.index.modelo().editar();
      e.preventDefault();
    });

    $(".extend-new-recurso").click(function () {
      $('#frm_nuevo').each(function () {
        this.reset();
      });
      $(".spinner-border").css("display", "none");
    });
    //importar

  }
}

//has uppercase
window.Parsley.addValidator('uppercase', {
  requirementType: 'number',
  validateString: function (value, requirement) {
    var uppercases = value.match(/[A-Z]/g) || [];
    return uppercases.length >= requirement;
  },
  messages: {
    es: 'Su contraseña debe contener al menos (%s) letras mayúsculas.'
  }
});

//has lowercase
window.Parsley.addValidator('lowercase', {
  requirementType: 'number',
  validateString: function (value, requirement) {
    var lowecases = value.match(/[a-z]/g) || [];
    return lowecases.length >= requirement;
  },
  messages: {
    es: 'Su contraseña debe contener al menos (%s) letras minúsculas.'
  }
});

//has number
window.Parsley.addValidator('number', {
  requirementType: 'number',
  validateString: function (value, requirement) {
    var numbers = value.match(/[0-9]/g) || [];
    return numbers.length >= requirement;
  },
  messages: {
    es: 'Su contraseña debe contener al menos (%s) número.'
  }
});

//has special char
window.Parsley.addValidator('special', {
  requirementType: 'number',
  validateString: function (value, requirement) {
    var specials = value.match(/[^a-zA-Z0-9]/g) || [];
    return specials.length >= requirement;
  },
  messages: {
    es: 'Tu contraseña debe contener al menos (%s) caracteres especiales.'
  }
});

$(document).ready(function () {
  $('.custom-validation').parsley();
  app.core.index.controlador();
}); 