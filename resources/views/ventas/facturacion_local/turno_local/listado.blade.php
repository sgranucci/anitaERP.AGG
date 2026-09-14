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
	<title>Turnos Facturación Local</title>
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
				<strong style="font-size: 14px;">Listado de turnos — Facturación Local</strong>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				<div class="meta">{{ $totalFilas }} registro(s)</div>
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width:6%;">ID</th>
				<th style="width:10%;">Código</th>
				<th style="width:22%;">Nombre</th>
				<th style="width:22%;">Empresa</th>
				<th style="width:20%;">Horario</th>
				<th style="width:10%;">Orden</th>
				<th style="width:10%;">Activo</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->codigo ?? '—' }}</td>
					<td>{{ $data->nombre }}</td>
					<td>{{ $data->empresa->nombre ?? '' }}</td>
					<td>{{ $data->etiquetaHorario() }}</td>
					<td>{{ $data->orden }}</td>
					<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
