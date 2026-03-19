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
<div class="row" style="height: 260px; padding: 1px; margin: 0px; border: none;">
	<table style="width=4500px;" class="table borderless">
		<thead>
		<tr style="height: 100px;">
			<th style="width=150px; word-wrap: break-word;">
				<img style="margin: 1px;" width="180" height="80" src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/imagenes/logos/".$venta->puntoventas->empresas->nombre.".png")) }}">
				<div>
					<strong style="font-size: 12px;">{{$venta->puntoventas->empresas->nombre}}</strong>
					<p style="font-size: 12px;">
						{{$venta->puntoventas->domicilio}}<br>
						{{$venta->puntoventas->localidades->nombre}} ({{$venta->puntoventas->codigopostal}})<br>
						{{$venta->puntoventas->provincias->nombre}}<br><br>
					</p>
					<p style="font-size: 10px; text-align: left;">IVA REPONSABLE INSCRIPTO</p>
				</div>
			</th>
			<th style="width=100px;">
				<div style="border: 1px solid black; height: 60px; position:relative; width: 50px; left:50px;">
					<strong style="font-size: 22px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">{{$letra}}</strong><br>
				</div>
				<div style="height: 40px; position:relative; width: 100px; left:20px;">
					<strong style="font-size: 12px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">Código {{$codigoTipoTransaccion}}</strong><br>
				</div>				
			</th>
			<th style="width=300px; text-align: right;">
				<strong>{{$venta->tipotransacciones->nombre ?? ''}}</strong><br>
				<strong>Nro. {{$venta->codigo}}</strong><br>
				<p style="font-size: 12px">
					Fecha emisi&oacute;n: {{date("d/m/Y", strtotime($venta->fecha ?? ''))}} <br>
					C.U.I.T.: {{$venta->puntoventas->empresas->nroinscripcion}}<br>
					Ingresos Brutos: {{$venta->puntoventas->empresas->numeroiibb}}<br>
					Inicio de Actividades: {{date("d/m/Y", strtotime($venta->puntoventas->empresas->fechainicioactividad))}}
				</p>
				<p style="font-size: 12px">ORIGINAL</p>
			</th>
		</tr>
		<tr>
			<th style="font-size: 12px; text-align: left;">
				Fecha de Vencimiento: {{date("d/m/Y", strtotime($venta->cliente_cuentacorrientes[0]->fechavencimiento)) }}
			</th>
			<th></th>
			<th style="font-size: 12px; text-align: right;">
				Condicion de Venta: {{ $venta->clientes->condicionesventa->nombre ?? 'CONTADO' }}
			</th>
		</tr>		
		</thead>
	</table>
</div>
<div style="height: 500px; margin: 0px; padding: 0px;">
	<table class="table borderless" style="margin: 28px 0;">
		<thead>
			<tr>
				<th style="width=150px; word-wrap: break-word; text-align: left;">
					<strong>Cliente: {{ $venta->clientes->nombre ?? ''}}</strong><br>
					<p style="font-size: 12px"> 
						{{ $venta->clientes->domicilio ?? ''}}<br>
						{{ $venta->clientes->localidades->nombre ?? ''}} ({{$venta->clientes->codigopostal ?? ''}})<br>
						{{ $venta->clientes->provincias->nombre ?? ''}} {{ $venta->clientes->paises->nombre ?? ''}}<br>
						@if (isset($venta->transportes->nombre))
							Transporte: {{ $venta->transportes->nombre ?? ''}}<br>
						@endif
						@if (isset($venta->lugarentrega))
							Lugar de entrega: {{ $venta->lugarentrega ?? ''}}<br>
						@endif
					</p>
				</th>
				<th style="width=150px; word-wrap: break-word; text-align: right;">
					<p style="font-size: 12px">
						Código: {{ $venta->clientes->codigo ?? ''}}<br>
						Teléfono: {{$venta->clientes->telefono}}<br>
						I.V.A.: {{$venta->clientes->condicionivas->nombre}}<br>
						{{$venta->clientes->tipodocumentos->abreviatura}}: {{$venta->clientes->numerodocumento}}<br>
						Ingresos Brutos: {{$venta->clientes->condicioniibbs->nombre}} {{$venta->clientes->nroiibb}}<br>
					</p>
				</th>
			</tr=>
		</thead>
	</table>
	<table class="table table-sm table-bordered table-striped" style="font-size: 12px; margin: 5px 0;">
		<thead>
			<tr>
				<th>Artículo</th>
				<th>Descripción</th>
				<th style="text-align: center;">Cantidad</th>
				<th style="text-align: right;">Precio</th>
			</tr>
		</thead>
		<tbody>
			@php $totalCantidad = 0; $totalCaja = 0; $totalPieza = 0; @endphp
			@foreach ($tblItem as $item)
				<tr>
					@if (isset($item['sku']))
						<td>{{ $item['sku'] }}</td>
						<td>{{ $item['detalle'] }}</td>
					@else
						<td></td>
						<td>{{ $item['detalle'] }}</td>
					@endif
					<td align="center">{{ number_format($item['cantidad'], config('facturacion.DECIMAL_CANTIDAD')) }}</td>
					<td align="right">{{ number_format($item['precio'], 2) }}</td>
				</tr>

				@php 
					$totalCantidad += $item['cantidad']; 
					$totalCaja += $item['caja']; 
					$totalPieza += $item['pieza']; 
				@endphp

			@endforeach
			<tr>
				<td> </td>
				<td>TOTALES</td>
				<td align="center"><strong>{{number_format($totalCantidad, config('facturacion.DECIMAL_CANTIDAD'))}}</td>
				<td> </td>
			</tr>
		</tbody>
	</table>
	<div class="col-sm-6">
		<table style="font-size: 12px; position:relative; left:508px;" class="table table-sm table-bordered table-striped">
			<thead>
				<th style="width: 25%;"></th>
				<th style="width: 10%;"></th>
				<th style="width: 15%;"></th>
			</thead>
			<tbody>
				@php $iva = 0; @endphp
				@foreach ($conceptosTotales as $itemTotal)
					@if (strpos($itemTotal['concepto'], 'Iva') !== false)
						@php $iva += $itemTotal['importe']; @endphp
					@endif
					@if ($letra == 'A')
						<tr>
							<td>
							@if ($itemTotal['concepto'] == "Total")
								<strong>{{ $itemTotal['concepto'] }}</strong>
							@else
								{{ $itemTotal['concepto'] }}
							@endif
							</td>
							<td>
							@if ($itemTotal['tasa'] != 0)
								{{number_format($itemTotal['tasa'], 2)}}
							@endif
							</td>
							<td align="right">
							@if ($itemTotal['concepto'] == "Total")
								<strong>{{$venta->monedas->abreviatura}} {{ number_format($itemTotal['importe'], 2) }}</strong>
							@else
								{{ number_format($itemTotal['importe'], 2) }}
							@endif
							</td>
						</tr>
					@else
						@if ($itemTotal['concepto'] == "Total")
						<tr>
							<td>
								<strong>{{ $itemTotal['concepto'] }}</strong>
							</td>
							<td>
							</td>
							<td align="right">
								<strong>{{$venta->monedas->abreviatura}} {{ number_format($itemTotal['importe'], 2) }}</strong>
							</td>
						</tr>
						@endif
					@endif
				@endforeach
			</tbody>
		</table>
	</div>
	<table class="table">
		<thead>
			<tr>
				@if ($letra == 'B')
					<th style="font-size: 10px; text-align: left">
						Transparencia Fiscal (Ley 27.743) <br>
						IVA Contenido {{ number_format($iva, 2) }} <br>
						Otros Impuestos Nacionales Indirectos {{0}} <br>
					</th>
				@endif
				<th style="font-size: 10px; text-align: right">
					CAE: {{ $venta->cae }}<br>
					Fecha Vencimiento CAE: {{date("d/m/Y", strtotime($venta->fechavencimientocae ?? ''))}} 
				</th>
			</tr>
		</thead>
	</table>
</div>
@if ($venta->leyenda != '')
	<div class="form-group">
		<label>Leyendas</label>
		<p>{{$venta->leyenda}}</p>
	</div>
@endif
<div class="qr-container">
	<!-- Asegúrate de que la imagen exista o se genere correctamente -->
	<img src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/".$output_file)) }}" width="100" height="100" alt="Codigo QR">	
</div>
</body>
</html>
