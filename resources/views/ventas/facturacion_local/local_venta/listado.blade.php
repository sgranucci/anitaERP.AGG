@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Locales de venta</title>
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de locales de venta</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
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
				<th style="width: 6%;">ID</th>
				<th style="width: 10%;">C&oacute;digo</th>
				<th style="width: 20%;">Nombre</th>
				<th style="width: 22%;">Puntos de venta</th>
				<th style="width: 18%;">Dep&oacute;sito</th>
				<th style="width: 16%;">Lista</th>
				<th style="width: 8%;">Activo</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				@php
					$pvs = $data->puntoventas->isNotEmpty()
						? $data->puntoventas->map(fn ($p) => trim(($p->codigo ?? '').' '.($p->nombre ?? '')))->implode(', ')
						: trim(($data->puntoventa->codigo ?? '').' '.($data->puntoventa->nombre ?? ''));
				@endphp
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->codigo }}</td>
					<td>{{ $data->nombre }}</td>
					<td>{{ $pvs }}</td>
					<td>{{ trim(($data->deposito->codigo ?? '').' '.($data->deposito->nombre ?? '')) }}</td>
					<td>{{ $data->listaprecio->nombre ?? '' }}</td>
					<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
