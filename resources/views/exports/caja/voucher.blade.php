<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="{{"assets/$theme/dist/css/adminlte.min.css"}}">
    <meta charset="UTF-8">
    <meta name="viewport"
	    content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
	<style type="text/css">
	</style>
</head>
<body>
<table class="table borderless">
	<thead>
    <tr>
		<th>
        	<img style="margin: 12px;" width=300px src="{{ "storage/imagenes/logos/logoAguas.jpg" }}">
			<div>
				<strong>DOCUMENTO NO VALIDO COMO FACTURA</strong><br>
			</div>
		</th>
		<th>
			<strong>Voucher Nro.: {{$voucher->id ?? ''}}</strong><br>
			<strong style="font-size: 12px">Fecha emisi&oacute;n: {{date("d/m/Y")}} </strong><br>
			<strong style="font-size: 12px">Fecha voucher: {{date("d/m/Y", strtotime($voucher->fecha ?? ''))}} </strong>
		</th>
	</tr>
</table>
<div class="row">
<div class="card-body">
    <div class="mt-5">
		<strong>Servicio: {{$voucher->servicioterrestres->nombre}}</strong><br>
		<strong>Proveedor: {{ $voucher->proveedores->nombre ?? ''}}</strong><br>
	</div>
	<table class="table table-sm table-bordered table-striped" style="font-size: 10px;">
		<thead>
    	<tr>
       		<th>Reserva</th>
       		<th>Pasajero</th>
       		<th>Fecha arribo</th>
       		<th>Fecha partida</th>
       		<th style="width: 10%;">Pax</th>
       		<th style="width: 10%;">Free</th>
			<th style="width: 10%;">Incluido</th>
			<th style="width: 10%;">Opcional</th>
    	</tr>
  		</thead>
    	<tbody>
		@php 
			$totalPax = 0;
			$totalPaxFree = 0;
			$totalIncluido = 0;
			$totalOpcional = 0;
		@endphp
		@foreach ($voucher->voucher_reservas as $reserva)
			<tr>
				<td>{{ $reserva->reserva_id }}</td>
				<td>{{ $reserva->nombrepasajero }}</td>
				<td style="text-align: center;">{{ date("d/m/Y", strtotime($reserva->fechaarribo ?? '')) }}</td>
				<td style="text-align: center;">{{ date("d/m/Y", strtotime($reserva->fechapartida ?? '')) }}</td>
				<td style="text-align: center;">{{ $reserva->pax }}</td>
				<td style="text-align: center;">{{ $reserva->paxfree }}</td>
				<td style="text-align: center;">{{ $reserva->incluido }}</td>
				<td style="text-align: center;">{{ $reserva->opcional }}</td>
			</tr>
			@php 
				$totalPax += $reserva->pax;
				$totalPaxFree += $reserva->paxfree;
				$totalIncluido += $reserva->incluido;
				$totalOpcional += $reserva->opcional;
			@endphp
    	@endforeach
		<tr>
			<td> </td>
			<td> </td>
			<td> </td>
			<td> </td>
			<td style="text-align: center;">{{$totalPax}}</td>
			<td style="text-align: center;">{{$totalPaxFree}}</td>
			<td style="text-align: center;">{{$totalIncluido}}</td>
			<td style="text-align: center;">{{$totalOpcional}}</td>
		</tr>
		</tbody>
	</table>
	<header>Formas de pago</header>
	<table class="table table-sm table-bordered table-striped" style="font-size: 10px;">
		<thead>
    	<tr>
       		<th>Cuenta</th>
       		<th>Descripción</th>
       		<th>Moneda</th>
       		<th style="text-align: right;">Monto</th>
			<th style="text-align: right;">Cotización</th>
    	</tr>
  		</thead>
    	<tbody>
		@foreach ($voucher->voucher_formapagos as $pago)
			<tr>
				<td>{{ $pago->cuentacajas->codigo }}</td>
				<td>{{ $pago->cuentacajas->nombre }}</td>
				<td>{{ $pago->monedas->nombre }}</td>
				<td style="text-align: right;">{{ number_format($pago->monto,2) }}</td>
				<td style="text-align: right;">{{ number_format($pago->cotizacion,4) }}</td>
			</tr>
    	@endforeach
		</tbody>
	</table>
    <div class="form-group">
    	<label>Observaciones</label>
       	<textarea name="observacion" class="form-control" rows="3" value="{{old('leyenda', $voucher->observacion ?? '')}}"></textarea>
    </div>
</div>
</div>
</body>
</html>
