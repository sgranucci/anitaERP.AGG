<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="{{"assets/$theme/dist/css/adminlte.min.css"}}">
    <meta charset="UTF-8">
    <meta name="viewport"
	    content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
	<style type="text/css">
		.page {
            border: 1px solid #ccc;
            background: white;
            box-sizing: border-box;
        }
        /* Forzar salto de página en la impresión y en el PDF */
        .salto-pagina {
            page-break-before: always;
        }
        //@media print {
        //    body { -webkit-print-color-adjust: exact; }
        //}
	</style>
</head>
<body>
<div id="area-pdf">
	<div class="page">
		<div class="row" id="area-pdf" style="height: 300px; padding: 1px; margin: 0px; border: none;">
			<table style="width=4500px;" class="table borderless">
				<thead>
				<tr style="height: 100px;">
					<th style="width=150px; word-wrap: break-word;">
						<img style="margin: 1px;" width="180" height="80" src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/imagenes/logos/".$venta->puntoventas->empresas->nombre.".png")) }}">
						<div>
							<strong style="font-size: 20px;">{{$venta->puntoventas->empresas->nombre}}</strong>
							<p style="font-size: 16px;">
								{{$venta->puntoventas->domicilio}}<br>
								{{$venta->puntoventas->localidades->nombre}} ({{$venta->puntoventas->codigopostal}})<br>
								{{$venta->puntoventas->provincias->nombre}}<br><br>
							</p>
							<p style="font-size: 16px; text-align: left;">IVA REPONSABLE INSCRIPTO</p>
						</div>
					</th>
					<th style="width=100px;">
						<div style="border: 1px solid black; height: 60px; position:relative; width: 50px; left:50px;">
							<strong style="font-size: 26px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">{{$letra}}</strong><br>
						</div>
						<div style="height: 40px; position:relative; width: 100px; left:20px;">
							<strong style="font-size: 12px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">Código {{$codigoTipoTransaccion}}</strong><br>
						</div>				
					</th>
					<th style="width=300px; text-align: right; font-size: 22px;">
						<strong>{{$venta->tipotransacciones->nombre ?? ''}}</strong><br>
						<strong>Nro. {{$venta->codigo}}</strong><br>
						<p style="font-size: 16px">
							Fecha emisi&oacute;n: {{date("d/m/Y", strtotime($venta->fecha ?? ''))}} <br>
							C.U.I.T.: {{$venta->puntoventas->empresas->nroinscripcion}}<br>
							Ingresos Brutos: {{$venta->puntoventas->empresas->numeroiibb}}<br>
							Inicio de Actividades: {{date("d/m/Y", strtotime($venta->puntoventas->empresas->fechainicioactividad))}}
						</p>
						<p style="font-size: 16px">ORIGINAL</p>
					</th>
				</tr>
				<tr>
					<th style="font-size: 16px; text-align: left;">
						Remito: {{$venta->numeroremito}}
					</th>
					@if (isset($venta->transportes->codigo))
						<th>Reparto: {{$venta->transportes->codigo??''}}</th>
					@else
						<th></th>
					@endif
					<th style="font-size: 16px; text-align: right;">
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
							<strong>Cliente: {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreClientePie($venta) }}</strong><br>
							<p style="font-size: 16px"> 
								{{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::domicilioReceptorFactura($venta) }}<br>
								@if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								{{ $venta->clientes->localidades->nombre ?? ''}} ({{$venta->clientes->codigopostal ?? ''}})<br>
								{{ $venta->clientes->provincias->nombre ?? ''}} {{ $venta->clientes->paises->nombre ?? ''}}<br>
								@endif
								@if (isset($venta->transportes->nombre))
									Transporte: {{ $venta->transportes->nombre ?? ''}}<br>
								@endif
								@if (isset($venta->lugarentrega))
									Lugar de entrega: {{ $venta->lugarentrega ?? ''}}<br>
								@endif
								@php $lineaWaitryPdf = \App\Support\Ventas\GastronomiaVentaDisplaySupport::lineaOrdenWaitry($venta); @endphp
								@if ($lineaWaitryPdf !== null)
									{{ $lineaWaitryPdf }}<br>
								@endif
							</p>
						</th>
						<th style="width=150px; word-wrap: break-word; text-align: right;">
							<p style="font-size: 16px">
								@php $codCli = \App\Support\Ventas\GastronomiaVentaDisplaySupport::codigoClienteMaestro($venta); @endphp
								@if ($codCli !== '')
								Código: {{ $codCli }}<br>
								@endif
								@if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								Teléfono: {{$venta->clientes->telefono}}<br>
								@endif
								I.V.A.: {{$venta->clientes->condicionivas->nombre}}<br>
								@if (\App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								Doc.: {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta) }}<br>
								@else
								{{$venta->clientes->tipodocumentos->abreviatura}}: {{$venta->clientes->numerodocumento}}<br>
								Ingresos Brutos: {{$venta->clientes->condicioniibbs->nombre}} {{$venta->clientes->nroiibb}}<br>
								@endif
							</p>
						</th>
					</tr=>
				</thead>
			</table>
			<table class="table table-sm table-bordered table-striped" style="font-size: 16px; margin: 5px 0;">
				<thead>
					<tr>
						<th>Artículo</th>
						<th>Descripción</th>
						<th style="text-align: center;">Cantidad</th>
						@if (config('app.empresa') == 'EL BIERZO')
							<th style="text-align: center;">Bonificación</th>
						@endif
						<th style="text-align: right;">Precio</th>
						<th style="text-align: right;">Total Item</th>
					</tr>
				</thead>
				<tbody>
					@php $totalCantidad = 0; $totalCaja = 0; $totalPieza = 0; $totalKiloDescuento = 0; @endphp
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
							@if (config('app.empresa') == 'EL BIERZO')
								<td align="center">{{ number_format($item['kilodescuento'], config('facturacion.DECIMAL_CANTIDAD')) }}</td>
							@endif
							@if (config('app.empresa') == 'EL BIERZO')
								<td align="right">{{ number_format($item['preciosindescuento'], 2) }}</td>
								<td align="right">{{ number_format($item['preciosindescuento']*$item['cantidad'], 2) }}</td>
							@else
								<td align="right">{{ number_format($item['precio'], 2) }}</td>
								<td align="right">{{ number_format(round($item['preciosindescuento'],2)*round($item['cantidad'],2), 2) }}</td>
							@endif
						</tr>

						@php 
							$totalCantidad += $item['cantidad']; 
							$totalKiloDescuento += $item['kilodescuento']; 
							$totalCaja += $item['caja']; 
							$totalPieza += $item['pieza']; 
						@endphp

					@endforeach
					<tr>
						<td> </td>
						<td>TOTALES</td>
						<td align="center"><strong>{{number_format($totalCantidad, config('facturacion.DECIMAL_CANTIDAD'))}}</td>
						@if (config('app.empresa') == 'EL BIERZO')
							<td align="center"><strong>{{number_format($totalKiloDescuento, config('facturacion.DECIMAL_CANTIDAD'))}}</td>
						@endif
						<td> </td>
						@if (config('app.empresa') == 'EL BIERZO')
							<td> </td>
						@endif
					</tr>
				</tbody>
			</table>
			<div class="col-sm-6">
				<table style="font-size: 16px; position:relative; left:508px;" class="table table-sm table-bordered table-striped">
					<thead>
						<th style="width: 25%;"></th>
						<th style="width: 10%;"></th>
						<th style="width: 15%;"></th>
					</thead>
					<tbody>
						@php $iva = 0; $impuestoInterno = 0; @endphp
						@foreach ($conceptosTotales as $itemTotal)
							@if (strpos($itemTotal['concepto'], 'Iva') !== false)
								@php $iva += $itemTotal['importe']; @endphp
							@endif
							@if ($itemTotal['concepto'] == 'Impuesto Interno')
								@php $impuestoInterno += $itemTotal['importe']; @endphp
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
								RÉGIMEN DE TRANSPARENCIA FISCAL AL CONSUMIDOR (Ley 27.743) <br>
								IVA Contenido {{ $venta->monedas->abreviatura ?? '' }} {{ number_format($iva, 2) }} <br>
								Otros Tributos Nac. que inciden en el precio
								@if ($impuestoInterno > 0)
									<br>
									&nbsp;&nbsp;&nbsp;Impuesto Interno {{ $venta->monedas->abreviatura ?? '' }} {{ number_format($impuestoInterno, 2) }}
								@endif
								<br>
							</th>
						@endif
						@if (config('app.empresa') == 'EL BIERZO')
							<th style="font-size: 10px; text-align: right">
								EMITIR LOS CHEQUES A LA ORDEN DE FRIGORIFICO EL BIERZO <br>
								CONTROLE EL PESO DE LA MERCADERIA <br>
								NO SE ACEPTAN RECLAMOS <br>
								<p style="font-size: 14px; text-align: right">
									CAE: {{ $venta->cae }}<br>
									Fecha Vencimiento CAE: {{date("d/m/Y", strtotime($venta->fechavencimientocae ?? ''))}} 
								</p>		
							</th>
						@else
							<th style="font-size: 10px; text-align: right">
								<p style="font-size: 14px; text-align: right">
									CAE: {{ $venta->cae }}<br>
									Fecha Vencimiento CAE: {{date("d/m/Y", strtotime($venta->fechavencimientocae ?? ''))}} 
								</p>		
							</th>				
						@endif
					</tr>
				</thead>
			</table>
			<div class="qr-container">
			<!-- Asegúrate de que la imagen exista o se genere correctamente -->
			<img src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/".$output_file)) }}" width="100" height="100" alt="Codigo QR">	
		</div>
	</div>
	@if (config('app.empresa') == 'EL BIERZO')
    <div class="page salto-pagina">
		<div class="row" id="area-pdf" style="height: 300px; padding: 1px; margin: 0px; border: none;">
			<table style="width=4500px;" class="table borderless">
				<thead>
				<tr style="height: 100px;">
					<th style="width=150px; word-wrap: break-word;">
						<img style="margin: 1px;" width="180" height="80" src="data:image/png;base64,{{ base64_encode(file_get_contents("/var/www/html/anitaERP/public/storage/imagenes/logos/".$venta->puntoventas->empresas->nombre.".png")) }}">
						<div>
							<strong style="font-size: 20px;">{{$venta->puntoventas->empresas->nombre}}</strong>
							<p style="font-size: 16px;">
								{{$venta->puntoventas->domicilio}}<br>
								{{$venta->puntoventas->localidades->nombre}} ({{$venta->puntoventas->codigopostal}})<br>
								{{$venta->puntoventas->provincias->nombre}}<br><br>
							</p>
							<p style="font-size: 16px; text-align: left;">IVA REPONSABLE INSCRIPTO</p>
						</div>
					</th>
					<th style="width=100px;">
						<div style="border: 1px solid black; height: 60px; position:relative; width: 50px; left:50px;">
							<strong style="font-size: 26px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">R</strong><br>
						</div>
						<div style="height: 40px; position:relative; width: 100px; left:20px;">
							<strong style="font-size: 12px; position:absolute; top: 50%; transform: translateY(-50%); left: 50%; transform: translate(-50%, -50%);">Código 091</strong><br>
						</div>				
					</th>
					<th style="width=300px; text-align: right; font-size: 22px;">
						<strong>REMITO</strong><br>
						<strong>Nro. {{$venta->numeroremito}}</strong><br>
						<p style="font-size: 16px">
							Fecha emisi&oacute;n: {{date("d/m/Y", strtotime($venta->fecha ?? ''))}} <br>
							C.U.I.T.: {{$venta->puntoventas->empresas->nroinscripcion}}<br>
							Ingresos Brutos: {{$venta->puntoventas->empresas->numeroiibb}}<br>
							Inicio de Actividades: {{date("d/m/Y", strtotime($venta->puntoventas->empresas->fechainicioactividad))}}
						</p>
						<p style="font-size: 16px">ORIGINAL</p>
					</th>
				</tr>
				<tr>
					<th style="font-size: 16px; text-align: left;">
						Factura: {{$venta->codigo}}
					</th>
					@if (isset($venta->transportes->codigo))
						<th>Reparto: {{$venta->transportes->codigo??''}}</th>
					@else
						<th></th>
					@endif
					<th></th>
				</tr>		
				</thead>
			</table>
		</div>
		<div style="height: 500px; margin: 0px; padding: 0px;">
			<table class="table borderless" style="margin: 28px 0;">
				<thead>
					<tr>
						<th style="width=150px; word-wrap: break-word; text-align: left;">
							<strong>Cliente: {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreClientePie($venta) }}</strong><br>
							<p style="font-size: 16px"> 
								{{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::domicilioReceptorFactura($venta) }}<br>
								@if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								{{ $venta->clientes->localidades->nombre ?? ''}} ({{$venta->clientes->codigopostal ?? ''}})<br>
								{{ $venta->clientes->provincias->nombre ?? ''}} {{ $venta->clientes->paises->nombre ?? ''}}<br>
								@endif
								@if (isset($venta->transportes->nombre))
									Transporte: {{ $venta->transportes->nombre ?? ''}}<br>
								@endif
								@if (isset($venta->lugarentrega))
									Lugar de entrega: {{ $venta->lugarentrega ?? ''}}<br>
								@endif
								@php $lineaWaitryPdf = \App\Support\Ventas\GastronomiaVentaDisplaySupport::lineaOrdenWaitry($venta); @endphp
								@if ($lineaWaitryPdf !== null)
									{{ $lineaWaitryPdf }}<br>
								@endif
							</p>
						</th>
						<th style="width=150px; word-wrap: break-word; text-align: right;">
							<p style="font-size: 16px">
								@php $codCli = \App\Support\Ventas\GastronomiaVentaDisplaySupport::codigoClienteMaestro($venta); @endphp
								@if ($codCli !== '')
								Código: {{ $codCli }}<br>
								@endif
								@if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								Teléfono: {{$venta->clientes->telefono}}<br>
								@endif
								I.V.A.: {{$venta->clientes->condicionivas->nombre}}<br>
								@if (\App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
								Doc.: {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta) }}<br>
								@else
								{{$venta->clientes->tipodocumentos->abreviatura}}: {{$venta->clientes->numerodocumento}}<br>
								Ingresos Brutos: {{$venta->clientes->condicioniibbs->nombre}} {{$venta->clientes->nroiibb}}<br>
								@endif
							</p>
						</th>
					</tr=>
				</thead>
			</table>
			<table class="table table-sm table-bordered table-striped" style="font-size: 16px; margin: 5px 0;">
				<thead>
					<tr>
						<th>Artículo</th>
						<th>Descripción</th>
						<th style="text-align: center;">Cantidad</th>
						@if (config('app.empresa') == 'EL BIERZO')
							<th style="text-align: center;">Bonificación</th>
						@endif
					</tr>
				</thead>
				<tbody>
					@php $totalCantidad = 0; $totalCaja = 0; $totalPieza = 0; $totalKiloDescuento = 0; @endphp
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
							@if (config('app.empresa') == 'EL BIERZO')
								<td align="center">{{ number_format($item['kilodescuento'], config('facturacion.DECIMAL_CANTIDAD')) }}</td>
							@endif
						</tr>

						@php 
							$totalCantidad += $item['cantidad']; 
							$totalKiloDescuento += $item['kilodescuento']; 
							$totalCaja += $item['caja']; 
							$totalPieza += $item['pieza']; 
						@endphp

					@endforeach
					<tr>
						<td> </td>
						<td>TOTALES</td>
						<td align="center"><strong>{{number_format($totalCantidad, config('facturacion.DECIMAL_CANTIDAD'))}}</td>
						@if (config('app.empresa') == 'EL BIERZO')
							<td align="center"><strong>{{number_format($totalKiloDescuento, config('facturacion.DECIMAL_CANTIDAD'))}}</td>
						@endif
					</tr>
				</tbody>
			</table>
			<table class="table">
				<thead>
					<tr>
						<th style="font-size: 10px; text-align: right">
							<p style="font-size: 14px; text-align: right">
								CAE: {{ $venta->cae }}<br>
								Fecha Vencimiento CAE: {{date("d/m/Y", strtotime($venta->fechavencimientocae ?? ''))}} 
							</p>		
						</th>				
					</tr>
				</thead>
			</table>
		</div>
    </div>
	@endif
</div>
@if ($venta->leyenda != '')
	<div class="form-group">
		<label>Leyendas</label>
		<p>{{$venta->leyenda}}</p>
	</div>
@endif
</body>
</html>
