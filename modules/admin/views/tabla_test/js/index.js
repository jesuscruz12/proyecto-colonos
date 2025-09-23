app.globales.cv_temporal = 0;
app.core.index = {
  modelo: function () {
    llenar_tabla = function () {
      var oTable = $("#recursos-table").dataTable();
      $.ajax({
        url: BASE_URL + "admin/tabla_test/recursos_list/",
        dataType: "json",
        success: function (s) {
          oTable.fnClearTable();
          var acciones = "";

          for (var i = 0; i < s.length; i++) {
            acciones =
              '<button data-bs-toggle="tooltip" data-bs-placement="top" title="Editar" type="button" data-clave="' +
              s[i]["id"] +
              '" data-accion="editar" class="accion_user me-1"><i class="fas fa-edit"></i></button>' +
              '<button data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar" type="button" data-clave="' +
              s[i]["id"] +
              '" data-accion="eliminar" class="accion_user"><i class="fas fa-times-circle"></i></button>';

            oTable.fnAddData([
              s[i]["campo1"],
              s[i]["campo2"],
              s[i]["campo3"],
              s[i]["campo4"],
              "Datos bien estructurados facilitan reportes, auditorías, análisis, predicciones y decisiones.",
              "Prueba, mide, mejora.",
              "Programadores curiosos construyen soluciones elegantes, rápidas, seguras, resilientes y escalables.",
              "Respira, enfoca, ejecuta.",
              "Datos bien estructurados facilitan reportes, auditorías, análisis, predicciones y decisiones.",
              "Prueba, mide, mejora.",
              "Programadores curiosos construyen soluciones elegantes, rápidas, seguras, resilientes y escalables.",
              "Leer, aprender, aplicar.",
              "Interfaces limpias mejoran usabilidad, reducen errores, aumentan satisfacción y conversiones.",
              "Respira, enfoca, ejecuta.",
              "Datos bien estructurados facilitan reportes, auditorías, análisis, predicciones y decisiones.",
              "Leer, aprender, aplicar.",
              "Programadores curiosos construyen soluciones elegantes, rápidas, seguras, resilientes y escalables.",
              "Respira, enfoca, ejecuta.",
              acciones,
            ]);
          }

          // (Re)inicializa tooltips Bootstrap 5
          document
            .querySelectorAll('[data-bs-toggle="tooltip"]')
            .forEach(function (el) {
              // evita duplicados: destruye si ya tenía instancia
              if (bootstrap.Tooltip.getInstance(el)) {
                bootstrap.Tooltip.getInstance(el).dispose();
              }
              new bootstrap.Tooltip(el);
            });
        },
      });
    };

    nuevo = function () {
      $("#frm_nuevo .spinner-border").css("display", "block");
      var data = new FormData();
      var other_data = $("#frm_nuevo").serializeArray();
      $.each(other_data, function (key, input) {
        data.append(input.name, input.value);
      });
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/tabla_test/registrar_recurso",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          if (obj.alert == "info") {
            llenar_tabla();
            alertify.success(obj.mensaje);

            // Cerrar modal (Bootstrap 5)
            const modalEl = document.getElementById("modal-crear");
            const modal =
              bootstrap.Modal.getInstance(modalEl) ||
              bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();

            // Limpiar formulario
            $("#frm_nuevo")[0].reset();
          } else {
            alertify.error(obj.mensaje);
          }
          $("#frm_nuevo .spinner-border").css("display", "none");
        },
      });
    };

    eliminar = function (clave) {
      var dataString = "clave=" + clave;
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/tabla_test/eliminar_recurso",
        data: dataString,
        success: function (data) {
          llenar_tabla();
        },
      });
    };

    datos_show = function (clave) {
      var dataString = "clave=" + clave;
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/tabla_test/datos_show_recurso/",
        data: dataString,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          $("#campo1_editar").val(obj[0].campo1);
          $("#campo2_editar").val(obj[0].campo2);
          $("#campo3_editar").val(obj[0].campo3);

          // Bootstrap 5: mostrar modal con la API nativa
          var modalEl = document.getElementById("modal-editar");
          var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          modal.show();
        },
      });
    };

    editar = function () {
      $("#frm_editar .spinner-border").css("display", "block");
      var data = new FormData();
      data.append("clave", app.globales.cv_temporal);

      var other_data = $("#frm_editar").serializeArray();
      $.each(other_data, function (key, input) {
        data.append(input.name, input.value);
      });
      $.ajax({
        type: "POST",
        url: BASE_URL + "admin/tabla_test/editar_recurso",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        success: function (data) {
          var obj = jQuery.parseJSON(data);
          if (obj.alert == "info") {
            alertify.success(obj.mensaje);
            llenar_tabla();
            // Cierra el modal de edición (Bootstrap 5)
            const modalEl = document.getElementById("modal-editar");
            const modal =
              bootstrap.Modal.getInstance(modalEl) ||
              bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();

            // (Opcional) limpiar el formulario
            $("#frm_editar")[0].reset();
          } else {
            alertify.error(obj.mensaje);
          }
          $("#frm_editar .spinner-border").css("display", "none");
        },
      });
    };

    return {
      llenar_tabla: llenar_tabla,
      nuevo: nuevo,
      datos_show: datos_show,
      editar: editar,
      eliminar: eliminar,
    };
  },

  controlador: function () {
    var frot = $("#recursos-table")
      .DataTable({
        lengthChange: !1,
        // ✅ Responsive
        responsive: {
          details: {
            type: "inline", // muestra los detalles debajo del <tr>
            target: "tr", // al hacer click en toda la fila
            // Opcional: renderer en tabla
            // renderer: $.fn.dataTable.Responsive.renderer.tableAll({ tableClass: "table table-sm table-bordered" })
          },
        },
        buttons: ["copy", "excel", "pdf", "colvis"],
        language: {
          buttons: {
            colvis: "Ocultar columna",
            copy: "Copiar",
            copyTitle: "Copiado",
            copyKeys: "",
            copySuccess: { _: "%d filas copiadas", 1: "1 línea copiada" },
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
          sSearch: "Buscar:",
          oPaginate: {
            sFirst: "Primero",
            sLast: "Último",
            sNext: "Siguiente",
            sPrevious: "Anterior",
          },
        },
      })
      .buttons()
      .container()
      .appendTo("#recursos-table_wrapper .col-md-6:eq(0)");

    // Selección de filas (igual que antes)
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

    $("#frm_nuevo").submit(function (e) {
      app.core.index.modelo().nuevo();
      e.preventDefault();
    });

    $("#recursos-table").delegate(".accion_user", "click", function () {
      var accion = $(this).data("accion");
      var clave = $(this).data("clave");
      app.globales.cv_temporal = clave;

      if (accion == "eliminar") {
        Swal.fire({
          title: "¿Deseas eliminar el registro?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#11c46e",
          cancelButtonColor: "#f46a6a",
          cancelButtonText: "Cancelar",
          confirmButtonText: "¡Sí!",
        }).then(function (result) {
          // SweetAlert2 moderno:
          if (result.isConfirmed) {
            var table = $("#recursos-table").DataTable();
            table.row(".selected").remove().draw(false);
            app.core.index.modelo().eliminar(clave);
            Swal.fire("Eliminado!", "Se eliminó correctamente.", "success");
          }
        });
      }

      if (accion == "editar") {
        $(".spinner-border").css("display", "none");
        app.core.index.modelo().datos_show(clave);
      }
    });

    $("#frm_editar").submit(function (e) {
      app.core.index.modelo().editar();
      e.preventDefault();
    });

    $(".extend-new-recurso").click(function () {
      $("#frm_nuevo").each(function () {
        this.reset();
      });
      $(".spinner-border").css("display", "none");
    });
  },
};

$(document).ready(function () {
  //$(".custom-validation").parsley();
  app.core.index.controlador();
});
