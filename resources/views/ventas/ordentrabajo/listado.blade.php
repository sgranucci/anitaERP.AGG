@php
	use App\Support\Configuracion\EmpresaLogoArchivo;
	$nombreEmpresa = (string) config('app.empresa');
	foreach ($ordentrabajo as $row) {
		$row->nombreempresa = $nombreEmpresa;
	}
	$logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($ordentrabajo);
	$totalFilas = is_countable($ordentrabajo) ? count($ordentrabajo) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Órdenes de trabajo</title>
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de órdenes de trabajo</h2>
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
				<th style="width: 8%;">Nro.OT</th>
				<th style="width: 10%;">Fecha</th>
				<th style="width: 18%;">Cliente</th>
				<th style="width: 20%;">Artículo</th>
				<th style="width: 18%;">Combinación</th>
				<th style="width: 8%;">Pares</th>
				<th style="width: 18%;">Estado</th>
			</tr>
		</thead>
		<tbody>
		@foreach ($ordentrabajo as $data)
			@php
				$clientes = [];
				$pares = 0.;
				if (isset($data->ordentrabajo_combinacion_talles)) {
					foreach ($data->ordentrabajo_combinacion_talles as $item) {
						$nombreCliente = $item->clientes->nombre ?? null;
						if ($nombreCliente !== null && ! in_array($nombreCliente, $clientes, true)) {
							$clientes[] = $nombreCliente;
						}
						$pares += $item->pedido_combinacion_talles->cantidad ?? 0;
					}
				}
				$pedidoCombinacionOt = $data->pedidoCombinacionVigente();
				$ultimaTarea = '';
				foreach ($data->ordentrabajo_tareas as $tarea) {
					$ultimaTarea = $tarea->tareas->nombre ?? $ultimaTarea;
				}
			@endphp
			<tr>
				<td>{{ str_pad($data->codigo, 4, '0', STR_PAD_LEFT) }}</td>
				<td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
				<td>{{ count($clientes) > 1 ? 'BOLETAS JUNTAS' : ($clientes[0] ?? '') }}</td>
				<td>{{ $pedidoCombinacionOt?->articulos->descripcion ?? '' }}</td>
				<td>{{ $pedidoCombinacionOt?->combinaciones->nombre ?? '' }}</td>
				<td>{{ $pares }}</td>
				<td>{{ $ultimaTarea }}</td>
			</tr>
		@endforeach
		</tbody>
	</table>
</body>
</html>
