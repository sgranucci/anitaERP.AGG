@php
    use App\Exports\Configuracion\ArbolaprobacionListadoExport;
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = ArbolaprobacionListadoExport::aplanarFilas($datas);
    foreach ($datas as $row) {
        $row->nombreempresa = (string) (optional($row->empresas)->nombre ?? config('app.empresa'));
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = count($filas);
    $subtituloFiltros = [];
    if (! empty($filtros['tipoarbol'])) {
        $subtituloFiltros[] = 'Tipo: '.$filtros['tipoarbol'];
    }
    if (! empty($filtros['estado'])) {
        $subtituloFiltros[] = 'Estado: '.$filtros['estado'];
    }
    if (! empty($filtros['empresa_id']) && ($filtros['empresa_scope'] ?? '') !== 'todas') {
        $empNombre = optional(optional($datas->first())->empresas)->nombre;
        if ($empNombre) {
            $subtituloFiltros[] = 'Empresa: '.$empNombre;
        }
    } elseif (($filtros['empresa_scope'] ?? '') === 'todas') {
        $subtituloFiltros[] = 'Empresa: todas';
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Árboles de aprobación</title>
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
			font-size: 6.5px;
			font-weight: bold;
			color: #17202A;
		}
		.listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 7px; color: #444; margin-top: 3px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 30%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 45%; text-align: center;">
				<h2 style="margin: 0; font-size: 16px; font-weight: bold;">Listado de árboles de aprobación</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				@if (count($subtituloFiltros) > 0)
					<div class="meta">{{ implode(' · ', $subtituloFiltros) }}</div>
				@endif
			</td>
			<td style="width: 25%; text-align: right; font-size: 7px;">
				@if ($totalFilas > 0)
					Filas: {{ $totalFilas }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 4%;">ID</th>
				<th style="width: 12%;">Nombre</th>
				<th style="width: 10%;">Empresa</th>
				<th style="width: 9%;">Tipo</th>
				<th style="width: 6%;">Estado</th>
				<th style="width: 5%;">Rec.</th>
				<th style="width: 4%;">Niv.</th>
				<th style="width: 4%;">Rama</th>
				<th style="width: 12%;">Centro costo</th>
				<th style="width: 12%;">Firmante</th>
				<th style="width: 7%;">Desde</th>
				<th style="width: 7%;">Hasta</th>
				<th style="width: 5%;">Mon.</th>
				<th style="width: 8%;">Est. doc.</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filas as $fila)
				<tr>
					<td>{{ $fila['id'] }}</td>
					<td>{{ $fila['nombre'] }}</td>
					<td>{{ $fila['empresa'] }}</td>
					<td>{{ $fila['tipo'] }}</td>
					<td>{{ $fila['estado'] }}</td>
					<td>{{ $fila['recordatorio'] }}</td>
					<td>{{ $fila['nivel'] }}</td>
					<td>{{ $fila['rama'] }}</td>
					<td>{{ $fila['centrocosto'] }}</td>
					<td>{{ $fila['firmante'] }}</td>
					<td>{{ $fila['desde_monto'] }}</td>
					<td>{{ $fila['hasta_monto'] }}</td>
					<td>{{ $fila['moneda'] }}</td>
					<td>{{ $fila['estado_doc'] }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
