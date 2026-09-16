<table>
@if (!empty($reservarFilaLogoExcel))
	<tbody>
		<tr>
			<td colspan="7" style="height: 52px;">&#160;</td>
		</tr>
	</tbody>
@endif
	<tbody>
		<tr>
			<td colspan="7">
				<strong style="font-size: 16pt;">Listado de órdenes de trabajo</strong>
				@if (!empty($totalRegistros))
					<br><span style="font-size: 10pt;">Generado {{ date('d/m/Y H:i') }} — Registros: {{ $totalRegistros }}</span>
				@endif
			</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Nro.OT</th>
			<th>Fecha</th>
			<th>Cliente</th>
			<th>Artículo</th>
			<th>Combinación</th>
			<th>Pares</th>
			<th>Estado</th>
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
