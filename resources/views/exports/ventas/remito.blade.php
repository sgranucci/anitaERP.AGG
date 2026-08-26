<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Remito {{ $remito->codigo ?? $remito->id ?? '' }}</title>
	<style type="text/css">
		@page { margin: 10mm; }
		html, body { height: auto; margin: 0; padding: 0; }
		body {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			font-size: 13px;
			color: #1a1a1a;
		}
		.remito-pagina { page-break-inside: avoid; }
		.salto-pagina { page-break-before: always; }
		table.remito-header {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 8px;
		}
		table.remito-header td { border: none; vertical-align: top; padding: 0 4px 0 0; }
		.remito-logo { width: 50%; }
		.remito-meta {
			width: 50%;
			font-size: 13px;
			text-align: right;
			line-height: 1.4;
		}
		.remito-cliente { margin-top: 8px; font-size: 13px; line-height: 1.35; }
		.remito-totales-division {
			font-size: 12px;
			text-align: right;
			line-height: 1.4;
			margin-top: 4px;
		}
		table.remito-items {
			border-collapse: collapse;
			width: 100%;
			font-size: 12px;
			table-layout: fixed;
		}
		table.remito-items td,
		table.remito-items th {
			border: 1px solid #cccccc;
			padding: 4px 3px;
			vertical-align: middle;
			word-wrap: break-word;
		}
		table.remito-items thead { display: table-row-group; }
		table.remito-items tr:nth-child(even) { background-color: #f5f5f5; }
		table.remito-items tr.remito-items-head { background-color: #e8e8e8; }
		table.remito-items tr.remito-items-head td { font-size: 12px; font-weight: bold; }
		.remito-continua { font-size: 11px; text-align: right; margin: 4px 0 0 0; }
		.remito-leyenda { font-size: 12px; margin-top: 8px; }
		.remito-leyenda label { font-weight: bold; }
		.remito-cai { font-size: 13px; text-align: right; margin-top: 8px; line-height: 1.35; }
		.remito-valor-asegurado {
			font-size: 13px;
			text-align: right;
			margin-top: 8px;
			font-weight: bold;
		}
	</style>
</head>
<body>
@php
	use App\Support\Ventas\FacturaPdfPaginacionSupport;

	$mostrarTotalesDivision = config('app.empresa') === 'EL BIERZO'
		&& optional($remito->transportes)->tipoexpreso === '4';
	$totalNetoDivision = 0.;
	$ajusteDivision = 0.;
	$totalDivision = 0.;
	$codigoClienteRemito = trim((string) ($remito->clientes->codigo ?? ''));
	$nombreClienteRemito = trim((string) ($remito->clientes->nombre ?? ''));
	$clienteRemitoDisplay = $codigoClienteRemito !== '' && $nombreClienteRemito !== ''
		? $codigoClienteRemito.' - '.$nombreClienteRemito
		: ($nombreClienteRemito !== '' ? $nombreClienteRemito : $codigoClienteRemito);
	$numeroRemito = \App\Support\Ventas\VentaNumeracionEmpresaSupport::formatearPuntoVentaNumero(
		(string) ($remito->puntoventas->codigo ?? ''),
		(int) ($remito->numero ?? 0)
	);
	if ($numeroRemito === '') {
		$numeroRemito = trim((string) ($remito->codigo ?? $remito->id ?? ''));
	}
	$repartoNombre = trim((string) ($remito->transportes->nombre ?? ''));

	$lineasPdf = \App\Support\Ventas\PedidoRemitoPdfLineasSupport::armar($remito->remito_articulos);
	$filas = $lineasPdf['filas'];
	$totales = $lineasPdf['totales'];
	$valorAsegurado = \App\Support\Ventas\RemitoValorAseguradoSupport::desdeArticulos(
		$remito->remito_articulos ?? []
	);
	if ($mostrarTotalesDivision) {
		$coeficienteDivision = (float) config('facturacion.COEFICIENTE_EXTRA_REPARTO_101', 1.10);
		foreach ($filas as $filaDiv) {
			$totalNetoDivision += (float) $filaDiv['precio'] * (float) $filaDiv['pesada'];
		}
		$totalNetoDivision = round($totalNetoDivision, 2);
		$ajusteDivision = round($totalNetoDivision * ($coeficienteDivision - 1), 2);
		$totalDivision = round($totalNetoDivision + $ajusteDivision, 2);
	}
	$paginasRemito = FacturaPdfPaginacionSupport::paginas($filas, 'remito_horizontal');
	$caiRemito = $caiRemito ?? \App\Support\Ventas\CaiRemitoVigenteSupport::paraRemito($remito);
@endphp
@foreach ($paginasRemito as $pagIdx => $itemsPagina)
	@php
		$esUltima = $pagIdx === array_key_last($paginasRemito);
	@endphp
	<div class="remito-pagina {{ $pagIdx > 0 ? 'salto-pagina' : '' }}">
		<table class="remito-header">
			<tr>
				<td class="remito-logo">
					<img style="margin: 4px 0;" width="160" height="70" src="data:image/png;base64,{{ base64_encode(file_get_contents('/var/www/html/anitaERP/public/storage/imagenes/logos/logo-bierzo.png')) }}">
					<div class="remito-cliente">
						<strong>Cliente: {{ $clienteRemitoDisplay }}</strong><br>
						<strong>Zona de Vta.: {{ $remito->zonavtas->nombre ?? '' }}</strong>
					</div>
				</td>
				<td class="remito-meta">
					<strong>Remito Nro.: {{ $numeroRemito }}</strong><br>
					<strong>Fecha: {{ date('d/m/Y', strtotime($remito->fecha ?? '')) }}</strong><br>
					<strong>Fecha de Entrega: {{ date('d/m/Y', strtotime($remito->fechaentrega ?? '')) }}</strong><br>
					<strong>Reparto: {{ $repartoNombre }}</strong><br>
					<strong>Lugar de entrega: {{ $remito->lugarentrega ?? '' }}</strong>
					<br>
					<strong>Valor asegurado: {{ number_format($valorAsegurado, 2) }}</strong>
					@if ($mostrarTotalesDivision)
						<div class="remito-totales-division">
							{{ number_format($totalNetoDivision, 2) }}<br>
							{{ number_format($ajusteDivision, 2) }}<br>
							{{ number_format($totalDivision, 2) }}
						</div>
					@endif
				</td>
			</tr>
		</table>
		<table class="remito-items">
			<tr class="remito-items-head">
				<td style="width: 7%;">Sku</td>
				<td style="width: 7%;">Unid.</td>
				<td style="width: 7%;">Kg.</td>
				<td style="width: 10%;">Descuento</td>
				<td style="width: 22%;">Descripción</td>
				<td style="width: 6%;">UMD</td>
				<td style="width: 8%;">Cajas</td>
				<td style="width: 9%;">Precio</td>
				<td style="width: 9%;">Pesada</td>
				<td style="width: 9%;">Bonificación</td>
			</tr>
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
					<td>@if ($item['pesada'] > 0){{ number_format($item['pesada'], 2) }}@endif</td>
					<td>@if ($item['bonificacion'] > 0){{ number_format($item['bonificacion'], 1) }}@endif</td>
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
					<td>@if ($totales['pesada'] > 0)<strong>{{ number_format($totales['pesada'], 2) }}</strong>@endif</td>
					<td>@if ($totales['bonificacion'] > 0)<strong>{{ number_format($totales['bonificacion'], 1) }}</strong>@endif</td>
				</tr>
			@endif
		</table>
		@if (! $esUltima)
			<p class="remito-continua">Continúa en la página siguiente…</p>
		@else
			<div class="remito-valor-asegurado">
				Valor asegurado: {{ number_format($valorAsegurado, 2) }}
			</div>
			@if (trim((string) ($remito->leyenda ?? '')) !== '')
				<div class="remito-leyenda">
					<label>Leyendas</label>
					<p>{{ $remito->leyenda }}</p>
				</div>
			@endif
			@if ($caiRemito && $caiRemito->numero_cai)
				<div class="remito-cai">
					CAI: {{ $caiRemito->numero_cai }}<br>
					Fecha Vencimiento CAI: {{ optional($caiRemito->fecha_vencimiento)->format('d/m/Y') }}
				</div>
			@endif
		@endif
	</div>
@endforeach
</body>
</html>
