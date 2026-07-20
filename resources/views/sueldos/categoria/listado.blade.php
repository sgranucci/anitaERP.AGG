@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Categorías de sueldos</title>
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
		.bases-lista { margin: 0; padding: 0; list-style: none; }
		.bases-lista li { margin-bottom: 1px; }
		.bases-lista .bcod { font-weight: bold; }
		.bases-lista .bval { color: #145a32; font-weight: bold; }
		.bases-lista .bfec { color: #666; }
		.sin-bases { color: #999; font-style: italic; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de categorías de sueldos</h2>
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
				<th style="width: 5%;">ID</th>
				<th style="width: 8%;">C&oacute;digo</th>
				<th style="width: 27%;">Descripci&oacute;n</th>
				<th style="width: 20%;">Origen de bases</th>
				<th style="width: 40%;">Bases vigentes</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				@php $bases = $data->bases_vigentes ?? []; @endphp
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->codigo }}</td>
					<td>{{ $data->descripcion }}</td>
					<td>{{ $origenLabels[$data->origen_bases] ?? $data->origen_bases }}</td>
					<td>
						@if (count($bases))
							<ul class="bases-lista">
								@foreach ($bases as $b)
									<li>
										<span class="bcod">{{ $b['nombrebase_codigo'] }} {{ $b['nombrebase_descripcion'] }}:</span>
										<span class="bval">{{ $b['valor_fmt'] }}</span>
										<span class="bfec">(desde {{ $b['fecha_vigencia_fmt'] }})</span>
									</li>
								@endforeach
							</ul>
						@else
							<span class="sin-bases">Sin bases vigentes</span>
						@endif
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
