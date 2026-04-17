var ptrcliente_id;
var ptrnombrecliente;

$(function () {
    $('.veraplicaciones').on('click', function (event) {
        let cuentacorriente_id = $(this).parents("tr").find('.cuentacorriente_id').text();
        let comprobante = $(this).parents("tr").find('.comprobante').text();
        let total = $(this).parents("tr").find('.total').val();
        let monedaComprobante = $(this).parents("tr").find('.moneda').val();
        var wrapper = $("#tbody-tabla-aplicacion");
        ptrcliente_id = $(this).parents("tr").find(".cliente_id");
		ptrnombrecliente = $(this).parents("tr").find(".nombrecliente");

        // Abre modal de consulta
        $("#aplicacionModal").modal('show');

        // Lee aplicaciones del comprobante
        $("#comprobanteaplicado").val(comprobante);

		let url = carpetaBase+'/ventas/cliente/leercuentacorrienteaplicacion/'+cuentacorriente_id;

        $(wrapper).empty();

		$.get(url, function(aplicacion){
            let saldo = parseFloat(total);

            var aplic = $.map(aplicacion, function(value, index){
				return [value];
			});
			$.each(aplic, function(index,value){
				let fecha = value.fechaaplicacion;
                let monto = parseFloat(value.total);
                let cotizacion = parseFloat(value.cotizacion);
                let coef = calculaCoeficienteMoneda(monedaComprobante, value.moneda_id, cotizacion);

                saldo += monto * coef;
                agregaRenglonAplicacion();

				$('#aplicacionpedido-table').find('tr').last().find('.id').val(value.id);
                $('#aplicacionpedido-table').find('tr').last().find('.cuentacorriente_id').val(value.cuentacorriente_id);
                $('#aplicacionpedido-table').find('tr').last().find('.cobranza_id').val(value.cobranza_id);
                $('#aplicacionpedido-table').find('tr').last().find('.ventaaplicado_id').val(value.ventaaplicado_id);
                $('#aplicacionpedido-table').find('tr').last().find('.fechaaplicacion').val(fecha);
                $('#aplicacionpedido-table').find('tr').last().find('.comprobanteaplicado').val(value.comprobante);
                $('#aplicacionpedido-table').find('tr').last().find('.monedaaplicacion').val(value.moneda_id);
                $('#aplicacionpedido-table').find('tr').last().find('.cotizacionaplicacion').val(cotizacion.toFixed(4));
                $('#aplicacionpedido-table').find('tr').last().find('.montoaplicacion').val(monto.toFixed(2));
                $('#aplicacionpedido-table').find('tr').last().find('.saldoaplicacion').val(saldo.toFixed(2));

                // Asigna atributo para editar registro
				let urlEditarComprobante = route('editar_cuentacorriente_cliente', ':id');

				let url = urlEditarComprobante;
           	    url = url.replace(':id', value.aplicado_id);

                $(".editarcomprobante").attr("href", url);
			});
		});        
    });

    $('#aplicacionModal').on('shown.bs.modal', function () {

        $(this).find('[autofocus]').focus();
    })

    $('#aplicacionModal').on('click', function () {
        $('#aplicacionModal').modal('hide');
    });
});

// Agrega renglon aplicacion
function agregaRenglonAplicacion(){
    var renglon = $('#template-renglon-aplicacion').html();

    $("#tbody-tabla-aplicacion").append(renglon);
}





