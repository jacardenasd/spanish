/**
 * Configuración global para DataTables en todo el sistema
 * Establece idioma en español y opciones por defecto
 */
if (typeof $ !== 'undefined' && $.fn.DataTable) {
  $.extend(true, $.fn.DataTable.defaults, {
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
    language: {
      sEmptyTable: "No hay datos disponibles en la tabla",
      sInfo: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
      sInfoEmpty: "Mostrando 0 a 0 de 0 entradas",
      sInfoFiltered: "(filtrado de _MAX_ entradas totales)",
      sInfoThousands: ",",
      sLengthMenu: "Mostrar _MENU_ entradas",
      sLoadingRecords: "Cargando...",
      sProcessing: "Procesando...",
      sSearch: "Buscar:",
      sSearchPlaceholder: "",
      sUrl: "",
      sZeroRecords: "No se encontraron registros coincidentes",
      oPaginate: {
        sFirst: "Primero",
        sLast: "Último",
        sNext: "Siguiente",
        sPrevious: "Anterior"
      },
      oAria: {
        sSortAscending: ": activar para ordenar la columna ascendente",
        sSortDescending: ": activar para ordenar la columna descendente"
      }
    }
  });
}

