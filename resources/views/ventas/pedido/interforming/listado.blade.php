@php
	use App\Support\Configuracion\EmpresaLogoArchivo;
	use App\Support\Ventas\PedidoInterformingListadoFiltros;
	$empresa = (string) config('app.empresa');
	foreach ($datas as $row) {
		$row->nombreempresa = $empresa;
	}
	$logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
	$totalFilas = is_countable($datas) ? count($datas) : 0;
	$subtitulo = $subtituloFiltros ?? PedidoInterformingListadoFiltros::subtituloFiltros($filtros ?? []);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Pedidos Interforming</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 4px;
			vertical-align: top;
			word-wrap: break-word;
		}
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th {
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
		}
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 35%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de pedidos Interforming</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				@if ($subtitulo !== '')
					<div class="meta">{{ $subtitulo }}</div>
				@endif
			</td>
			<td style="width: 25%; text-align: right; font-size: 8px;">
				@if ($totalFilas > 0)
					Registros: {{ $totalFilas }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 5%;">ID</th>
				<th style="width: 8%;">C&oacute;digo</th>
				<th style="width: 8%;">Fecha</th>
				<th style="width: 8%;">Entrega</th>
				<th style="width: 24%;">Cliente</th>
				<th style="width: 10%;">O. Compra</th>
				<th style="width: 12%;">Estado</th>
				<th style="width: 12%;">Vendedor</th>
				<th style="width: 13%;">Expreso</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->codigo }}</td>
					<td>{{ optional($data->fecha)->format('d/m/Y') ?? substr((string) $data->fecha, 0, 10) }}</td>
					<td>{{ optional($data->fechaentrega)->format('d/m/Y') ?? substr((string) $data->fechaentrega, 0, 10) }}</td>
					<td>{{ trim(($data->clientes->codigo ?? '').' — '.($data->clientes->nombre ?? ''), " —") }}</td>
					<td>{{ $data->orden_compra }}</td>
					<td>{{ $data->etiquetaEstado() }}</td>
					<td>{{ $data->vendedores->nombre ?? '' }}</td>
					<td>{{ $data->transportes->nombre ?? '' }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
