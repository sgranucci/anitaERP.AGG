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
	<table style="width=5500px;  position:relative; left:16px;" class="table borderless">
		<thead>
		<tr style="height: 100px;">
			<th style="width=150px; word-wrap: break-word;">
				<img style="margin: 1px;" width="180" height="80" src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/imagenes/logos/".$cobranza->empresas->nombre.".png")) }}">
				<div>
					<p style="font-size: 8px;">
						{{$datosEmpresa['domicilio']}}<br>
					</p>
					<p style="font-size: 8px; text-align: left;">IVA REPONSABLE INSCRIPTO</p>
				</div>
			</th>
			<th style="width=100px;">
				<div style="border: 1px solid black; height: 40px; position:relative; width: 30px; left:50px;">
					<strong style="position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">{{$letra}}</strong><br>
				</div>
				<div style="height: 40px; position:relative; width: 100px; left:18px;">
					<strong style="font-size: 8px; position:absolute; top: 55%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">DOCUMENTO NO VALIDO COMO FACTURA</strong><br>
				</div>				
			</th>
			<th style="width=300px; text-align: right;">
				<strong>{{$cobranza->tipotransaccioncajas->nombre ?? ''}}</strong><br>
				<strong>Nro. {{$cobranza->numerotransaccion}}</strong><br>
				<p style="font-size: 10px">
					Fecha emisi&oacute;n: {{date("d/m/Y", strtotime($cobranza->fecha ?? ''))}} <br>
					C.U.I.T.: {{$datosEmpresa['numeroinscripcion']}}<br>
					Ingresos Brutos: {{$datosEmpresa['numeroiibb']}}<br>
				</p>
				<p style="font-size: 8px">ORIGINAL</p>
			</th>
		</tr>
		</thead>
	</table>
		<div class="col-sm-12">
			<table class="table borderless" style="margin: 5px 0; position:relative; left:9px;">
				<thead>
					<tr>
						<th style="width=150px; word-wrap: break-word; text-align: left;">
							<strong>Cliente: {{ $datosCliente['nombre'] ?? ''}}</strong><br>
							<p style="font-size: 10px"> 
								{{ $datosCliente['domicilio'] ?? ''}}<br>
								{{ $datosCliente['localidad'] ?? ''}} ({{$datosCliente['codigopostal'] ?? ''}})<br>
								{{ $datosCliente['provincia'] ?? ''}} {{ $datosCliente['pais'] ?? ''}}<br>
							</p>
						</th>
						<th style="width=150px; word-wrap: break-word; text-align: right;">
							<p style="font-size: 10px">
								Código: {{ $datosCliente['codigo'] ?? ''}}<br>
								Teléfono: {{$datosCliente['telefono'] }}<br>
								I.V.A.: {{$datosCliente['condicioniva'] }}<br>
								{{$datosCliente['tipodocumento']}}: {{$datosCliente['numerodocumento']}}<br>
								Ingresos Brutos: {{$datosCliente['condicioniibb']}} {{$datosCliente['nroiibb']}}<br>
							</p>
						</th>
					</tr>
				</thead>
			</table>
			<table class="table table-sm table-bordered table-striped" style="font-size: 8px; margin: 5px 0; position:relative; left:9px;">
				<thead>
					<tr>
						<th style="text-align: center;">Comprobante</th>
						<th style="text-align: center;">Fecha</th>
						<th style="text-align: center;">Fecha de Vto.</th>
						<th style="text-align: center;">Mon</th>
						<th style="text-align: right;">Cotización</th>
						<th style="text-align: right;">Monto</th>
						<th style="text-align: right;">Aplicado</th>
						<th style="text-align: right;">Saldo</th>
					</tr>
				</thead>
				<tbody>
					@php $totalAplicado = 0; @endphp

					@foreach ($tblComprobante as $comprobante)
						<tr>
							<td align="center"><strong>{{ $comprobante['comprobante'] }}</strong></td>
							<td align="center">{{ date("d/m/Y", strtotime($comprobante['fecha'] ?? '')) }}</td>
							<td align="center">{{ date("d/m/Y", strtotime($comprobante['fechavencimiento'] ?? '')) }}</td>
							<td align="center">{{ $comprobante['moneda'] }}</td>
							<td align="right">{{ number_format($comprobante['cotizacion'], 4) }}</td>					
							<td align="right">{{ number_format($comprobante['monto'], 2) }}</td>
							<td align="right">{{ number_format($comprobante['aplicado'], 2) }}</td>
							<td align="right">{{ number_format($comprobante['saldo'], 2) }}</td>
						</tr>

						@php 
							$totalAplicado += $comprobante['aplicado']; 
						@endphp
					@endforeach

					<tr>
						<td colspan='6'><strong>TOTALES</strong></td>
						<td align="right"><strong>{{number_format($totalAplicado, 2)}}</td>
						<td> </td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="col-sm-12">
			@if (count($tblCheques) > 0)
				<div class="col-sm-8">
					<table style="font-size: 8px; position:relative; left:1px;" class="table table-sm table-bordered table-striped">
						<thead>
							<th style="width: 15%;">Fecha pago</th>
							<th style="width: 10%;">Nro. de Cheque</th>
							<th style="text-align: keft; width: 30%;">Banco</th>
							<th style="width: 5%;">Mon</th>
							<th style="text-align: right; width: 15%;">Cotización</th>
							<th style="text-align: right; width: 20%;">Monto</th>
						</thead>
						<tbody>
							@php 
								$totalCheque = []; 
								$moneda = [];
							@endphp
							@foreach ($tblCheques as $cheque)
								@php 
									if (!isset($totalCheque[$cheque['moneda_id']])) 
									{
										$totalCheque[$cheque['moneda_id']] = 0;
										$moneda[$cheque['moneda_id']] = $cheque['moneda'];
									}

									$totalCheque[$cheque['moneda_id']] += $cheque['monto']; 
								@endphp	
								<tr>
									<td align="center">{{date("d/m/Y", strtotime($cheque['fechapago'] ?? ''))}}</td>
									<td>{{$cheque['numerocheque']}}</td>
									<td align="left">
										<strong>{{ $cheque['banco'] }}</strong>
									</td>
									<td align="center">{{$cheque['moneda']}}</td>
									<td align="right"><strong>{{ number_format($cheque['cotizacion'], 4) }}</strong></td>
									<td align="right"><strong>{{ number_format($cheque['monto'], 2) }}</strong></td>
								</tr>						
							@endforeach		
							<tr>
								@for ($i = 1; $i <= count($totalCheque); $i++)
									@if (isset($totalCheque[$i]))
										<td colspan='3'><strong>TOTAL</strong></td>
										<td>{{$moneda[$i]}}</td>
										<td></td>
										<td align="right"><strong>{{number_format($totalCheque[$i], 2)}}</td>
									@endif
								@endfor
							</tr>										
						</tbody>
					</table>
				</div>	
			@endif
			@if (count($tblCuenta) > 0)
				<div class="col-sm-7">
					<table style="font-size: 8px; position:relative; left:1px;" class="table table-sm table-bordered table-striped">
						<thead>
							<th style="text-align: left; width: 30%;">Cuenta</th>
							<th style="width: 5%;">Mon</th>
							<th style="text-align: right; width: 15%;">Cotización</th>
							<th style="text-align: right; width: 20%;">Monto</th>
						</thead>
						<tbody>
							@php 
								$totalCuenta = []; 
								$moneda = [];
							@endphp
							@foreach ($tblCuenta as $cuenta)
								@php 
									// Si la clave no existe, la crea en 0 antes de sumar
									if (!isset($totalCuenta[$cuenta['moneda_id']])) 
									{
										$totalCuenta[$cuenta['moneda_id']] = 0;
										$moneda[$cuenta['moneda_id']] = $cuenta['moneda'];
									}

									$totalCuenta[$cuenta['moneda_id']] += $cuenta['monto']; 
								@endphp
								<tr>
									<td>
										<strong>{{ $cuenta['nombre'] }}</strong>
									</td>
									<td>{{$cuenta['moneda']}}</td>
									<td align="right"><strong>{{ number_format($cuenta['cotizacion'], 4) }}</strong></td>
									<td align="right"><strong>{{ number_format($cuenta['monto'], 2) }}</strong></td>
								</tr>
							@endforeach
							<tr>
								@for ($i = 1; $i <= count($totalCuenta); $i++)
									@if (isset($totalCuenta[$i]))
										<td><strong>TOTAL</strong></td>
										<td>{{$moneda[$i]}}</td>
										<td></td>
										<td align="right"><strong>{{number_format($totalCuenta[$i], 2)}}</td>
									@endif
								@endfor
							</tr>				
						</tbody>
					</table>
				</div>
			@endif
			@if (count($tblRetenciones) > 0)
				<div class="col-sm-7">
					<table style="font-size: 8px; position:relative; left:1px;" class="table table-sm table-bordered table-striped">
						<thead>
							<th style="text-align:left; width: 30%;">Retención</th>
							<th style="width: 10%;">Comprobante</th>
							<th style="width: 8%;">Tasa</th>
							<th style="width: 5%;">Mon</th>
							<th style="text-align:right; width: 15%;">Cotización</th>
							<th style="text-align:right; width: 20%;">Monto</th>
						</thead>
						<tbody>
							@php 
								$totalRetencion = []; 
								$moneda = [];
							@endphp
							@foreach ($tblRetenciones as $retencion)
								@php 
									// Si la clave no existe, la crea en 0 antes de sumar
									if (!isset($totalRetencion[$retencion['moneda_id']])) 
									{
										$totalRetencion[$retencion['moneda_id']] = 0;
										$moneda[$retencion['moneda_id']] = $retencion['moneda'];
									}

									$totalRetencion[$retencion['moneda_id']] += $retencion['monto']; 
								@endphp
								<tr>
									<td>
										<strong>{{ $retencion['retencion'] }}</strong>
									</td>
									<td>{{$retencion['comprobante']}}</td>
									<td>{{$retencion['tasa']}}</td>
									<td>{{$retencion['moneda']}}</td>
									<td align="right"><strong>{{ number_format($retencion['cotizacion'], 4) }}</strong></td>
									<td align="right"><strong>{{ number_format($retencion['monto'], 2) }}</strong></td>
								</tr>
							@endforeach
							<tr>
								@for ($i = 1; $i <= count($totalRetencion); $i++)
									@if (isset($totalRetencion[$i]))
										<td colspan='3'><strong>TOTAL</strong></td>
										<td>{{$moneda[$i]}}</td>
										<td></td>
										<td align="right"><strong>{{number_format($totalRetencion[$i], 2)}}</td>
									@endif
								@endfor
							</tr>				
						</tbody>
					</table>
				</div>
			@endif			
		</div>

@if ($cobranza->detalle != '')
	<table style="font-size: 10px; position:relative; left: 5px;" class="table borderless">
		<thead>
			<tr>
				<th style="text-align: left;">
					<label>Observaciones</label>
					<p>{{$cobranza->detalle}}</p>
				</th>
				<th style="width=500px; text-align: right;">
					<p style="font-size: 10px;" >Total cobranza: <spam style="font-size: 12px;"><strong>{{$totalCobranza['abreviatura']}} {{number_format($totalCobranza['monto'], 2)}}</strong></spam></p>
					@php $formatterES = new NumberFormatter("es", NumberFormatter::SPELLOUT); @endphp
					<p style="font-size: 8px;">Son {{$totalCobranza['moneda']}} {{$formatterES->format($totalCobranza['monto'])}}.-</p>
				</th>
			</tr>
	</thead>
	</table>
@endif
</body>
</html>
