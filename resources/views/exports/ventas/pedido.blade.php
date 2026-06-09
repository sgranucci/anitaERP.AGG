<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pedido {{ $pedido->id ?? '' }}</title>
	<style type="text/css">
		body {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			font-size: 15px;
			color: #1a1a1a;
			margin: 8px;
		}
		table.pedido-header {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 10px;
		}
		table.pedido-header td,
		table.pedido-header th {
			border: none;
			vertical-align: middle;
		}
		.pedido-meta {
			font-size: 14px;
			text-align: right;
			line-height: 1.4;
		}
		.pedido-cliente {
			font-size: 15px;
			line-height: 1.5;
			margin-bottom: 12px;
		}
		table.pedido-items {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			font-size: 15px;
			table-layout: fixed;
		}
		table.pedido-items td,
		table.pedido-items th {
			border: 1px solid #cccccc;
			padding: 5px 4px;
			vertical-align: middle;
			word-wrap: break-word;
		}
		table.pedido-items thead tr {
			background-color: #e8e8e8;
		}
		table.pedido-items th {
			font-size: 14px;
			font-weight: bold;
		}
		table.pedido-items tbody tr:nth-child(even) {
			background-color: #f5f5f5;
		}
		.pedido-leyenda {
			font-size: 14px;
			margin-top: 14px;
		}
		.pedido-leyenda label {
			font-weight: bold;
			font-size: 15px;
		}
	</style>
</head>
<body>
<table class="pedido-header">
	<thead>
		<tr>
			<th style="width: 50%; text-align: left;">
				<img style="margin: 8px 0;" width="180" height="80" src="data:image/png;base64,{{ base64_encode(file_get_contents('/var/www/html/anitaERP/public/storage/imagenes/logos/logo-bierzo.png')) }}">
			</th>
			<th class="pedido-meta">
				<strong>Pedido Nro.: {{ $pedido->id ?? '' }}</strong><br>
				<strong>Fecha: {{ date('d/m/Y', strtotime($pedido->fecha ?? '')) }}</strong><br>
				<strong>Fecha de Entrega: {{ date('d/m/Y', strtotime($pedido->fechaentrega ?? '')) }}</strong>
			</th>
		</tr>
	</thead>
</table>
<div class="pedido-cliente">
	<strong>Cliente: {{ $pedido->clientes->nombre ?? '' }}</strong><br>
	<strong>Reparto: {{ $pedido->transportes->nombre ?? '' }}</strong><br>
	<strong>Zona de Vta.: {{ $pedido->zonavtas->nombre ?? '' }}</strong><br>
	<strong>Lugar de entrega: {{ $pedido->lugarentrega ?? '' }}</strong>
</div>
<table class="pedido-items">
	<thead>
		<tr>
			<th style="width: 7%;">Sku</th>
			<th style="width: 7%;">Unid.</th>
			<th style="width: 7%;">Kg.</th>
			<th style="width: 10%;">Descuento</th>
			<th style="width: 22%;">Descripción</th>
			<th style="width: 6%;">UMD</th>
			<th style="width: 8%;">Cajas</th>
			<th style="width: 9%;">Precio</th>
			<th style="width: 9%;">Pesada</th>
			<th style="width: 9%;">Bonificación</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($pedido->pedido_articulos as $item)
			@php
				$pesadaBruta = (float) ($item->pesada ?? 0);
				$pctBonificacion = (float) optional($item->descuentoventa_ids)->porcentajedescuento ?? 0;
				$bonificacion = 0.;
				$pesadaNeta = $pesadaBruta;
				if ($pctBonificacion > 0 && $pesadaBruta > 0) {
					$bonificacion = round($pesadaBruta * $pctBonificacion / 100, 1);
					$pesadaNeta = $pesadaBruta - $bonificacion;
				}
			@endphp
			<tr>
				<td>{{ $item->articulos->sku }}</td>
				<td>{{ number_format($item->pieza, 2) }}</td>
				<td>{{ number_format($item->kilo, 2) }}</td>
				<td>{{ $item->descuentoventa_ids->nombre ?? '' }}</td>
				<td>{{ $item->articulos->descripcion }}</td>
				<td>{{ $item->articulos->unidadesdemedidas->abreviatura }}</td>
				<td>{{ number_format($item->caja, 2) }}</td>
				<td>{{ number_format($item->precio, 2) }}</td>
				<td>{{ number_format($pesadaNeta, 2) }}</td>
				<td>
					@if ($bonificacion > 0)
						{{ number_format($bonificacion, 1) }}
					@endif
				</td>
			</tr>
		@endforeach
		<tr>
			<td><strong>Totales</strong></td>
			@php
				$kilos = 0.;
				$cajas = 0.;
				$piezas = 0.;
				$pesadaNetaTotal = 0.;
				$bonificacionTotal = 0.;
			@endphp
			@foreach ($pedido->pedido_articulos as $item)
				@php
					$pesadaBruta = (float) ($item->pesada ?? 0);
					$pctBonificacion = (float) optional($item->descuentoventa_ids)->porcentajedescuento ?? 0;
					$bonificacion = 0.;
					$pesadaNeta = $pesadaBruta;
					if ($pctBonificacion > 0 && $pesadaBruta > 0) {
						$bonificacion = round($pesadaBruta * $pctBonificacion / 100, 1);
						$pesadaNeta = $pesadaBruta - $bonificacion;
					}
					$kilos += ($item->kilo);
					$piezas += ($item->pieza);
					$cajas += ($item->caja);
					$pesadaNetaTotal += $pesadaNeta;
					$bonificacionTotal += $bonificacion;
				@endphp
			@endforeach
			<td><strong>{{ number_format($piezas, 2) }}</strong></td>
			<td><strong>{{ number_format($kilos, 2) }}</strong></td>
			<td></td>
			<td></td>
			<td></td>
			<td><strong>{{ number_format($cajas, 2) }}</strong></td>
			<td></td>
			<td><strong>{{ number_format($pesadaNetaTotal, 2) }}</strong></td>
			<td>
				@if ($bonificacionTotal > 0)
					<strong>{{ number_format($bonificacionTotal, 1) }}</strong>
				@endif
			</td>
		</tr>
	</tbody>
</table>
<div class="pedido-leyenda">
	<label>Leyendas</label>
	<p>{{ $pedido->leyenda }}</p>
</div>
</body>
</html>
