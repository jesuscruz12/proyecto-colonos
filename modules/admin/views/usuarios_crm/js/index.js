app.globales.cv_temporal = 0;
app.core.index = {
  modelo: function () {
    llenar_tabla = function () {
      var oTable = $("#recursos-table").dataTable();
      $.ajax({
        url: BASE_URL + "admin/usuarios_crm/recursos_list/",
        dataType: "json",
        success: function (s) {
          oTable.fnClearTable();
          var acciones = "";
          var nombre_usuario = "";
          var nombre_completo = "";
          var apellidop = "";
          var apellidom = "";

          var data = [];
          for (var i = 0; i < s.length; i++) {
            acciones =
              '<button data-toggle="tooltip" data-placement="top" data-original-title="Editar" type="button" data-clave="' +
              s[i]["cv_usuario"] +
              '" data-accion="editar" class="accion_user"><i class="fas fa-edit"></i></button>';

            nombre_usuario =
              s[i]["nombre_usuario"] != null
                ? s[i]["nombre_usuario"]
                : nombre_usuario;

            apellidop =
              s[i]["apellido_paterno"] != null
                ? s[i]["apellido_paterno"]
                : apellidop;

            apellidom =
              s[i]["apellido_materno"] != null
                ? s[i]["apellido_materno"]
                : apellidom;

            nombre_completo =
              nombre_usuario + " " + apellidop + " " + apellidom;

            data.push([
              s[i]["cv_usuario"],
              nombre_completo,
              s[i]["email"],
              s[i]["telefono"],
              s[i]["tipo_usuario"],
              s[i]["razon_social"],
              acciones,
            ]);
          }
          if (s.length > 0) {
            oTable.fnAddData(data);
            $('[data-toggle="tooltip"]').tooltip();
          }
        },
      });
    };

    datos_show = function (clave) {
      var dataString = "clave=" + clave;
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios_crm/datos_show/",
        data: dataString,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          $("#cv_usuario").val(obj[0].cv_usuario);
          $("#usuario").val(obj[0].nombre_usuario);
          $("#editar-recurso").modal("show");
        },
      });
    };

    editar = function () {
      $("#frm_editar .spinner-border").css("display", "block");
      var data = new FormData();
      data.append("clave", app.globales.cv_temporal);
      var password = $('#password').val();
      data.append("password", password);

      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/usuarios_crm/editar_recurso",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          if (obj.alert == "info") {
            alertify.success(obj.mensaje);
            llenar_tabla();
          } else {
            alertify.error(obj.mensaje);
          }
          $("#frm_editar .spinner-border").css("display", "none");
        },
      });
    };

    // API publica
    return {
      llenar_tabla: llenar_tabla,
      datos_show: datos_show,
      editar: editar,
    };
  },

  controlador: function () {
    var frot = $("#recursos-table")
      .DataTable({
        lengthChange: !1,
        buttons: ["copy", "excel", "pdf", "colvis"],
        language: {
          buttons: {
            colvis: "Ocultar columna",
            copy: "Copiar",
            copyTitle: "Copiado",
            copyKeys: "",
            copySuccess: {
              _: "%d filas copiadas",
              1: "1 ligne copiÃ©e",
            },
          },
          sProcessing: "Procesando...",
          sLengthMenu: "Mostrar _MENU_ registros",
          sZeroRecords: "No se encontraron resultados",
          sEmptyTable: "Ningún dato disponible en esta tabla",
          sInfo:
            "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
          sInfoEmpty:
            "Mostrando registros del 0 al 0 de un total de 0 registros",
          sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
          sInfoPostFix: "",
          sSearch: "Buscar:",
          sUrl: "",
          sInfoThousands: ",",
          sLoadingRecords: "Cargando...",
          oPaginate: {
            sFirst: "Primero",
            sLast: "Ãšltimo",
            sNext: "Siguiente",
            sPrevious: "Anterior",
          },
          oAria: {
            sSortAscending:
              ": Activar para ordenar la columna de manera ascendente",
            sSortDescending:
              ": Activar para ordenar la columna de manera descendente",
          },
        },
      })
      .buttons()
      .container()
      .appendTo("#recursos-table_wrapper .col-md-6:eq(0)");

    $("#recursos-table tbody").on("click", "tr", function () {
      if ($(this).hasClass("selected")) {
        $(this).removeClass("selected");
      } else {
        var table = $("#recursos-table").DataTable();
        table.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
      }
    });

    app.core.index.modelo().llenar_tabla();

    $("#recursos-table").delegate(".accion_user", "click", function () {
      var accion = $(this).data("accion");
      var clave = $(this).data("clave");
      app.globales.cv_temporal = clave;

      if (accion == "editar") {
        $(".spinner-border").css("display", "none");
        app.core.index.modelo().datos_show(clave);
      }
    });

    $("#frm_editar").submit(function (e) {
      app.core.index.modelo().editar();
      e.preventDefault();
    });
  },
};

$(document).ready(function () {
  app.core.index.controlador();
});
