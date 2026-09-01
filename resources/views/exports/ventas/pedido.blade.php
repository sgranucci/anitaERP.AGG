@php
	use App\Support\Ventas\FacturaPdfPaginacionSupport;

	$montosReparto101 = \App\Support\Ventas\VillafrancaFacturacionSupport::montosPedidoDesdeFactura($pedido);
	$codigoClientePedido = trim((string) ($pedido->clientes->codigo ?? ''));
	$nombreClientePedido = trim((string) ($pedido->clientes->nombre ?? ''));
	$clientePedidoDisplay = $codigoClientePedido !== '' && $nombreClientePedido !== ''
		? $codigoClientePedido.' - '.$nombreClientePedido
		: ($nombreClientePedido !== '' ? $nombreClientePedido : $codigoClientePedido);
	$numeroPedido = trim((string) ($pedido->codigo ?? ''));
	if ($numeroPedido === '') {
		$numeroPedido = (string) ($pedido->id ?? '');
	}
	$repartoNombre = trim((string) ($pedido->transportes->nombre ?? ''));

	$lineasPdf = \App\Support\Ventas\PedidoRemitoPdfLineasSupport::armar($pedido->pedido_articulos);
	$filas = $lineasPdf['filas'];
	$totales = $lineasPdf['totales'];
	$totalKilosPesadaBonificacion = (float) ($totales['pesada'] ?? 0) + (float) ($totales['bonificacion'] ?? 0);
	$paginasPedido = FacturaPdfPaginacionSupport::paginas($filas, 'pedido');

	$logoPedidoPath = public_path('storage/imagenes/logos/logo-bierzo.png');
	$logoPedidoDataUri = is_file($logoPedidoPath)
		? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPedidoPath))
		: '';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pedido {{ $pedido->codigo ?? $pedido->id ?? '' }}</title>
	<style type="text/css">
		@page { margin: 10mm 10mm 12mm 10mm; }
		html, body { height: auto; margin: 0; padding: 0; }
		body {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			font-size: 13px;
			color: #1a1a1a;
		}
		.pedido-pagina { page-break-inside: auto; }
		.salto-pagina { page-break-before: always; }
		table.pedido-header {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 8px;
		}
		table.pedido-header td { border: none; vertical-align: top; padding: 0 4px 0 0; }
		.pedido-logo { width: 50%; }
		.pedido-meta {
			width: 50%;
			font-size: 13px;
			text-align: right;
			line-height: 1.4;
		}
		.pedido-cliente { margin-top: 8px; font-size: 13px; line-height: 1.35; }
		.pedido-totales-division {
			font-size: 10px;
			color: #555;
			text-align: right;
			line-height: 1.4;
			margin-top: 8px;
			font-variant-numeric: tabular-nums;
		}
		table.pedido-items {
			border-collapse: collapse;
			width: 100%;
			font-size: 10px;
			table-layout: fixed;
		}
		table.pedido-items td,
		table.pedido-items th {
			border: 1px solid #cccccc;
			padding: 3px 2px;
			vertical-align: middle;
			word-wrap: break-word;
		}
		table.pedido-items thead { display: table-header-group; }
		table.pedido-items thead tr.pedido-encabezado-doc td {
			border: none;
			padding: 0 0 8px 0;
			vertical-align: top;
		}
		table.pedido-items thead tr.pedido-items-head { background-color: #e8e8e8; }
		table.pedido-items thead tr.pedido-items-head th {
			font-size: 10px;
			font-weight: bold;
		}
		table.pedido-items tbody tr:nth-child(even) { background-color: #f5f5f5; }
		.pedido-continua { font-size: 11px; text-align: right; margin: 4px 0 0 0; }
		.pedido-leyenda { font-size: 12px; margin-top: 8px; }
		.pedido-leyenda label { font-weight: bold; }
		.pedido-espacio-superior { height: 16mm; width: 100%; }
		table.pedido-kilos-cuadro {
			border-collapse: collapse;
			width: 42%;
			margin: 8px 0 0 auto;
			font-size: 11px;
			page-break-inside: avoid;
		}
		table.pedido-kilos-cuadro td {
			border: 1px solid #1a1a1a;
			padding: 4px 6px;
			vertical-align: middle;
		}
		table.pedido-kilos-cuadro td.pedido-kilos-cuadro-etiq {
			width: 68%;
			background-color: #e8e8e8;
			font-weight: bold;
		}
		table.pedido-kilos-cuadro td.pedido-kilos-cuadro-valor {
			width: 32%;
			text-align: right;
		}
		table.pedido-kilos-cuadro tr.pedido-kilos-cuadro-total td {
			background-color: #d6eaf8;
			font-weight: bold;
			font-size: 12px;
		}
	</style>
</head>
<body>
<div class="pedido-espacio-superior"></div>
@foreach ($paginasPedido as $pagIdx => $itemsPagina)
	@php
		$esUltima = $pagIdx === array_key_last($paginasPedido);
	@endphp
	<div class="pedido-pagina {{ $pagIdx > 0 ? 'salto-pagina' : '' }}">
		<table class="pedido-items">
			<thead>
				<tr class="pedido-encabezado-doc">
					<td colspan="10">
						<table class="pedido-header">
							<tr>
								<td class="pedido-logo">
									@if ($logoPedidoDataUri !== '')
										<img style="margin: 4px 0;" width="160" height="70" src="{{ $logoPedidoDataUri }}">
									@endif
									<div class="pedido-cliente">
										<strong>Cliente: {{ $clientePedidoDisplay }}</strong><br>
										<strong>Zona de Vta.: {{ $pedido->zonavtas->nombre ?? '' }}</strong>
									</div>
								</td>
								<td class="pedido-meta">
									<strong>Pedido Nro.: {{ $numeroPedido }}</strong><br>
									<strong>Fecha: {{ date('d/m/Y', strtotime($pedido->fecha ?? '')) }}</strong><br>
									<strong>Fecha de Entrega: {{ date('d/m/Y', strtotime($pedido->fechaentrega ?? '')) }}</strong><br>
									<strong>Reparto: {{ $repartoNombre }}</strong><br>
									<strong>Lugar de entrega: {{ $pedido->lugarentrega ?? '' }}</strong>
									@if ($montosReparto101)
										<div class="pedido-totales-division">
											{{ number_format($montosReparto101['neto'], 2, ',', '.') }}<br>
											{{ number_format($montosReparto101['recargo'], 2, ',', '.') }}<br>
											{{ number_format($montosReparto101['total'], 2, ',', '.') }}
										</div>
									@endif
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr class="pedido-items-head">
					<th style="width: 8%;">Sku</th>
					<th style="width: 8%;">Unid.</th>
					<th style="width: 8%;">Kg.</th>
					<th style="width: 11%;">Descuento</th>
					<th style="width: 22%;">Descripción</th>
					<th style="width: 6%;">UMD</th>
					<th style="width: 8%;">Cajas</th>
					<th style="width: 10%;">Precio</th>
					<th style="width: 9%;">Pesada</th>
					<th style="width: 10%;">Bonificación</th>
				</tr>
			</thead>
			<tbody>
			@foreach ($itemsPagina as $item)
				<tr>
					<td>{{ $item['sku'] }}</td>
					<td>{{ number_format($item['pieza'], 2) }}</td>
					<td>{{ number_format($item['kilo'], 2) }}</td>
					<td>{{ $item['descuento'] }}</td>
					<td>{{ $item['descripcion'] }}</td>
					<td>{{ $item['umd'] }}</td>
					<td>{{ number_format($item['caja'], 2) }}</td>
					<td>{{ number_format($item['precio'], 2) }}</td>
					<td>
						@if ($item['pesada'] > 0)
							{{ number_format($item['pesada'], 2) }}
						@endif
					</td>
					<td>
						@if ($item['bonificacion'] > 0)
							{{ number_format($item['bonificacion'], 1) }}
						@endif
					</td>
				</tr>
			@endforeach
			@if ($esUltima)
				<tr>
					<td><strong>Totales</strong></td>
					<td><strong>{{ number_format($totales['pieza'], 2) }}</strong></td>
					<td><strong>{{ number_format($totales['kilo'], 2) }}</strong></td>
					<td></td>
					<td></td>
					<td></td>
					<td><strong>{{ number_format($totales['caja'], 2) }}</strong></td>
					<td></td>
					<td>
						@if ($totales['pesada'] > 0)
							<strong>{{ number_format($totales['pesada'], 2) }}</strong>
						@endif
					</td>
					<td>
						@if ($totales['bonificacion'] > 0)
							<strong>{{ number_format($totales['bonificacion'], 1) }}</strong>
						@endif
					</td>
				</tr>
			@endif
			</tbody>
		</table>
		@if ($esUltima)
			<table class="pedido-kilos-cuadro">
				<tr>
					<td class="pedido-kilos-cuadro-etiq">Kilos pesada</td>
					<td class="pedido-kilos-cuadro-valor">{{ number_format((float) ($totales['pesada'] ?? 0), 2) }}</td>
				</tr>
				<tr>
					<td class="pedido-kilos-cuadro-etiq">Kilos bonificación</td>
					<td class="pedido-kilos-cuadro-valor">{{ number_format((float) ($totales['bonificacion'] ?? 0), 1) }}</td>
				</tr>
				<tr class="pedido-kilos-cuadro-total">
					<td class="pedido-kilos-cuadro-etiq">Total kilos (pesada + bonificación)</td>
					<td class="pedido-kilos-cuadro-valor">{{ number_format($totalKilosPesadaBonificacion, 2) }}</td>
				</tr>
			</table>
		@endif
		@if (! $esUltima)
			<p class="pedido-continua">Continúa en la página siguiente…</p>
		@elseif (trim((string) ($pedido->leyenda ?? '')) !== '')
			<div class="pedido-leyenda">
				<label>Leyendas</label>
				<p>{{ $pedido->leyenda }}</p>
			</div>
		@endif
	</div>
@endforeach
</body>
</html>
