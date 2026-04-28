@extends("theme.$theme.layout")
@section('titulo')
    Saldos de Cuentas Interbanking
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script>
    var ptrRenglon;

    $(document).ready(function(){
        $('.enviaconsulta').click(function(e){
            e.preventDefault(); // Previene que el enlace recargue la página

            ptrRenglon = this;

            enviaConsulta();
        });
    });    

    function enviaConsulta()
    {
		$("#movimientodiarioModal").modal('show');
	}

	// Controla apertura modal de movimientodiario
	$('#movimientodiarioModal').on('show.bs.modal', function (event) {
		var modal = $(this);
		let tituloModal = "Movimientos de Cuentas Interbanking ";
        let bank_number = $(ptrRenglon).parents("tr").find(".bank_number").text();
        let account_number = $(ptrRenglon).parents("tr").find(".account_number").text();
        let account_name = $(ptrRenglon).parents("tr").find(".account_name").text();
        let account_type = $(ptrRenglon).parents("tr").find(".account_type").text();
        let account_label = $(ptrRenglon).parents("tr").find(".account_label").text();
        let currency = $(ptrRenglon).parents("tr").find(".currency").text();
        let historical_balances = $(ptrRenglon).parents("tr").find(".historical_balances").val();

        let data = JSON.parse(historical_balances);

		modal.find('.modal-title').text(tituloModal);
		modal.find('#movimientodiarioModal').empty();
		modal.find('#movimientodiarioModal').append('');

		var wrapper = $("#tbody-movimientodiario");

        $(wrapper).empty();

        $.each(data, function(index,value){
            let debito = fNumero(parseFloat(value.total_debits), 2)
            let credito = fNumero(parseFloat(value.total_credits), 2)
            let saldo = fNumero(parseFloat(value.day_balance), 2)

            $(wrapper).append('<tr>'+
                        '<td>'+
                            '<input type="text" class="form-control historiafecha" value="'+new Date(value.operation_date).toLocaleString("es-AR")+'" readonly>'+
                        '</td>'+
                        '<td>'+
                            '<input type="text" style="text-align: right;" class="form-control historiadebito" value="'+debito+'" readonly>'+
                        '</td>'+
                        '<td>'+
                            '<input type="text" style="text-align: right;" class="form-control historiacredito" value="'+credito+'" readonly>'+
                        '</td>'+
                        '<td>'+
                            '<input type="text" style="text-align: right;" class="form-control historiasaldo" value="'+saldo+'" readonly>'+
                        '</td>'+
                    '</tr>');
        });
	});

	$('#aceptamovimientodiarioModal').on('click', function () {
		$('#movimientodiarioModal').modal('hide');
	});

	$('#movimientodiarioModal').on('hidden.bs.modal', function () {
	});

</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuentas Interbanking</h3>
                <div class="card-tools">
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">Banco</th>
                            <th>Moneda</th>
                            <th>Numero de Cuenta</th>
                            <th>Tipo Cuenta</th>
                            <th>Etiqueta Cuenta</th>
                            <th>Nombre Cuenta</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Balance Contable</th>
                            <th>Balance Inicial</th>
                            <th>Balance Actual</th>
                            <th>Balance 24hs</th>
                            <th>Balance 48hs</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuentas as $data)
                        <tr>
                            <td class="bank_number">{{$data['bank_number'] ?? ''}}</td>
                            <td class="currency">{{$data['currency'] ?? ''}}</td>
                            <td class="account_number">{{$data['account_number'] ?? ''}}</td>
                            <td class="account_type">{{$data['account_type'] ?? ''}}</td>
                            <td class="account_label">{{$data['account_label'] ?? ''}}</td>
                            <td class="account_name">{{$data['account_name'] ?? ''}}</td>
                            <td>REBISCO</td>
                            <td>{{\Carbon\Carbon::parse($data['row_date'])->format('d/m/Y')}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['countable_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['initial_operating_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['current_operating_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['projected_balance_24hs'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['projected_balance_48hs'] ?? 0, 2)}}</td>
                            <input type="hidden" class="historical_balances" value="{{json_encode($data['historical_balances'])}}">
                            <td>
                       			@if (can('ver-movimientos-cuenta-interbanking', false))
                                    <a href="#" class="btn-accion-tabla enviaconsulta tooltipsC" title="Ver movimientos de la cuenta">
                                        <i class="fa fa-edit"></i>
                                    </a>
								@endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@include('caja.interbanking.modalmovimientodiario')

