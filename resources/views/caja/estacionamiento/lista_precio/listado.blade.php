@php
    use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoVigenteSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;

    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $filasDetalle = ListaPrecioEstacionamientoVigenteSupport::filasExportDetalladas($datas);
    $totalFilas = $filasDetalle->count();
    $fechaRef = $fechaReferencia ?? request('fecha_referencia', date('Y-m-d'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Listas de precios de estacionamiento</title>
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
		.text-right { text-align: right; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de listas de precios de estacionamiento</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }} &middot; Precios vigentes al {{ \Carbon\Carbon::parse($fechaRef)->format('d/m/Y') }}</div>
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
				<th style="width: 5%;">ID lista</th>
				<th style="width: 16%;">Empresa</th>
				<th style="width: 14%;">Categor&iacute;a</th>
				<th style="width: 6%;">Mon.</th>
				<th style="width: 22%;">&Iacute;tem</th>
				<th style="width: 10%;">Precio vigente</th>
				<th style="width: 10%;">Vigente desde</th>
				<th style="width: 8%;">Cant. vigentes</th>
				<th style="width: 9%;">&Uacute;lt. vigencia lista</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filasDetalle as $fila)
				<tr>
					<td>{{ $fila->lista_id }}</td>
					<td>{{ $fila->empresa }}</td>
					<td>{{ $fila->categoria }}</td>
					<td>{{ $fila->moneda }}</td>
					<td>{{ $fila->item_nombre }}</td>
					<td class="text-right">
						@if ($fila->precio !== null && $fila->precio !== '')
							{{ number_format((float) $fila->precio, 2, ',', '.') }}
						@endif
					</td>
					<td>
						@if (!empty($fila->fecha_vigencia_item))
							{{ \Carbon\Carbon::parse($fila->fecha_vigencia_item)->format('d/m/Y') }}
						@endif
					</td>
					<td>{{ $fila->precios_vigentes_count }}</td>
					<td>
						@if (!empty($fila->ultima_vigencia))
							{{ \Carbon\Carbon::parse($fila->ultima_vigencia)->format('d/m/Y') }}
						@endif
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
