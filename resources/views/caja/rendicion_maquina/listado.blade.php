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
	<title>Rendiciones de máquinas</title>
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
		table.data thead th { background-color: #85C1E9; color: #17202A; font-weight: bold; }
		.cabecera { margin-bottom: 12px; }
		.cabecera h1 { font-size: 14px; margin: 0 0 4px 0; }
		.cabecera .meta { font-size: 8px; color: #444; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
	<div class="cabecera">
		@if (count($logosCabecera) > 0)
			@foreach ($logosCabecera as $logo)
				<img src="{{ $logo }}" alt="Logo" style="height: 40px; margin-right: 8px;">
			@endforeach
		@endif
		<h1>Listado de rendiciones de máquinas</h1>
		<div class="meta">Generado {{ date('d/m/Y H:i') }} · {{ $totalFilas }} registro(s)</div>
	</div>
	<table class="data">
		<thead>
			<tr>
				<th style="width:5%">ID</th>
				<th style="width:12%">Código</th>
				<th style="width:8%">Fecha</th>
				<th style="width:6%">Turno</th>
				<th style="width:18%">Empresa</th>
				<th style="width:10%" class="text-right">Resultado</th>
				<th style="width:10%" class="text-right">Transferencia</th>
				<th style="width:8%">Estado</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
				<td>{{ $data->turno_label }}</td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td class="text-right">{{ number_format((float) $data->resultado_turno, 2, ',', '.') }}</td>
				<td class="text-right">{{ number_format((float) $data->transferencia, 2, ',', '.') }}</td>
				<td>{{ $data->estado_label }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
