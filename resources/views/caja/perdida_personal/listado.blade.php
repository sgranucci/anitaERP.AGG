@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filasExport = [];
    foreach ($datas as $row) {
        $filasExport[] = (object) [
            'id' => $row->id,
            'numero' => $row->numero,
            'fecha' => optional($row->fecha)->format('d/m/Y'),
            'empresa' => $row->empresa->nombre ?? '',
            'empleado' => $row->empleado
                ? ($row->empleado->legajo.' — '.$row->empleado->nombre)
                : '',
            'supervisor' => $row->supervisor
                ? ($row->supervisor->legajo.' — '.$row->supervisor->nombre)
                : '',
            'concepto' => $row->conceptoPerdida
                ? ($row->conceptoPerdida->codigo.' — '.$row->conceptoPerdida->nombre)
                : '',
            'imputacion' => $row->imputacionPerdida
                ? ($row->imputacionPerdida->codigo.' — '.$row->imputacionPerdida->nombre)
                : '',
            'centrocosto' => $row->centrocosto
                ? ($row->centrocosto->codigo.' — '.$row->centrocosto->nombre)
                : '',
            'turno' => $row->turno_label,
            'maquina' => $row->maquina ?? '',
            'importe' => number_format((float) $row->importe, 2, ',', '.'),
            'estado' => $row->estado_label,
            'leyenda' => $row->leyenda ?? '',
            'nombreempresa' => $row->empresa->nombre ?? '',
        ];
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasExport));
    $totalFilas = count($filasExport);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>P&eacute;rdidas de personal</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 3px;
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de p&eacute;rdidas de personal</h2>
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
				<th style="width: 6%;">Nro</th>
				<th style="width: 7%;">Fecha</th>
				<th style="width: 10%;">Empresa</th>
				<th style="width: 12%;">Empleado</th>
				<th style="width: 10%;">Concepto</th>
				<th style="width: 10%;">Imputaci&oacute;n</th>
				<th style="width: 6%;">Turno</th>
				<th style="width: 6%;">M&aacute;quina</th>
				<th style="width: 7%;">Importe</th>
				<th style="width: 6%;">Estado</th>
				<th style="width: 20%;">Leyenda</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filasExport as $fila)
				<tr>
					<td>{{ $fila->numero }}</td>
					<td>{{ $fila->fecha }}</td>
					<td>{{ $fila->empresa }}</td>
					<td>{{ $fila->empleado }}</td>
					<td>{{ $fila->concepto }}</td>
					<td>{{ $fila->imputacion }}</td>
					<td>{{ $fila->turno }}</td>
					<td>{{ $fila->maquina }}</td>
					<td class="text-right">{{ $fila->importe }}</td>
					<td>{{ $fila->estado }}</td>
					<td>{{ $fila->leyenda }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
