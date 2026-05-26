@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Stock\FormulaArticuloNumero;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($formulas);
    $listadoMostrarCodigo = FormulaArticuloNumero::mostrarCodigo();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Fórmulas de artículos</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
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
		table.data tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #d4e6f1; }
		table.data th { font-size: 7px; font-weight: bold; color: #1a1a1a; }
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
		.cell-items { font-size: 7px; white-space: normal; }
		.num { text-align: right; white-space: nowrap; }
		a { color: #1a5276; text-decoration: none; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de fórmulas de artículos</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width:4%">{{ $listadoMostrarCodigo ? 'Cód.' : 'ID' }}</th>
				@unless ($listadoMostrarCodigo)
				<th style="width:6%">Cód. fórmula</th>
				@endunless
				<th style="width:5%">Id art.</th>
				<th style="width:8%">SKU</th>
				<th style="width:14%">Artículo</th>
				<th style="width:6%">Cant. u.</th>
				<th style="width:8%">Estado</th>
				<th style="width:12%">Detalle</th>
				<th style="width:8%">Usuario</th>
				<th style="width:35%">Ítems</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($formulas as $row)
				<tr>
					<td>{{ $listadoMostrarCodigo ? FormulaArticuloNumero::paraFormula($row) : $row->id }}</td>
					@unless ($listadoMostrarCodigo)
					<td><small>@if(! empty($row->codigo)){{ $row->codigo }}@else<span class="text-muted">&mdash;</span>@endif</small></td>
					@endunless
					<td>@if (! empty($row->articulo_id))<a href="{{ route('editar_articulo', ['id' => $row->articulo_id]) }}">{{ $row->articulo_id }}</a>@else<span class="text-muted">—</span>@endif</td>
					<td>{{ $row->articulo_sku ?? '' }}</td>
					<td>{{ $row->articulo_descripcion ?? '' }}</td>
					<td class="num">{{ number_format((float) ($row->cantidadunidad ?? 0), 2, ',', '.') }}</td>
					<td>{{ $row->estado }}</td>
					<td class="cell-items">{{ $row->detalle }}</td>
					<td><small>{{ $row->nombreusuario ?? '' }}</small></td>
					<td class="cell-items">@include('stock.formula_articulo.partials.export_lineas', ['data' => $row, 'separator' => '<br>', 'enlaces' => true])</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
