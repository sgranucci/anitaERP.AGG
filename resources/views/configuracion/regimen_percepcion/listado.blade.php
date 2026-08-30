@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = (string) config('app.empresa');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Regímenes de percepción</title>
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Regímenes de percepción</h2>
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
				<th style="width: 10%;">Código</th>
				<th style="width: 28%;">Nombre</th>
				<th style="width: 8%;">Agente</th>
				<th style="width: 10%;">Alícuota</th>
				<th style="width: 14%;">Mín. gravado</th>
				<th style="width: 14%;">Mín. perc.</th>
				<th style="width: 10%;">Vigencia</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->codigo }}</td>
					<td>{{ $data->nombre }}</td>
					<td>{{ $data->habilitado ? 'Sí' : 'No' }}</td>
					<td>{{ number_format((float) $data->tasa, 2, ',', '.') }}%</td>
					<td>{{ number_format((float) $data->minimo_base, 2, ',', '.') }}</td>
					<td>{{ number_format((float) $data->minimo_importe, 2, ',', '.') }}</td>
					<td>
                        @if ($data->vigencia_desde){{ $data->vigencia_desde->format('d/m/Y') }}@endif
                        @if ($data->vigencia_hasta) — {{ $data->vigencia_hasta->format('d/m/Y') }}@endif
                    </td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
