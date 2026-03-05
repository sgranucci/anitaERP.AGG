<h2> Ordenes de Trabajo </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Cliente</th>
		<th>Artículo</th>
		<th>Combinacion</th>
		<th>Pares</th>
		<th>Estado</th>
	</tr>
  	</thead>
    <tbody>
	@foreach ($ordentrabajo as $data)
		<tr>
			<td>{{str_pad($data->codigo, 4, "0", STR_PAD_LEFT)}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
			<td>
				@php
					$clientes = [];
				@endphp
				@if (isset($data->ordentrabajo_combinacion_talles))
					@foreach ($data->ordentrabajo_combinacion_talles as $item)
						@php
							if (!in_array($item->clientes->nombre, $clientes))
								$clientes[] = $item->clientes->nombre;
						@endphp
					@endforeach
				@endif
				{{ count($clientes) > 1 ? "BOLETAS JUNTAS" : $clientes[0] ?? '' }}
			</td>
			<td>{{$data->ordentrabajo_combinacion_talles[0]->pedido_combinacion_talles->pedidos_combinacion->articulos->descripcion ?? ''}}</td>
			<td>{{$data->ordentrabajo_combinacion_talles[0]->pedido_combinacion_talles->pedidos_combinacion->combinaciones->nombre ?? ''}}</td>
			<td>
				@php
					$pares = 0.;
				@endphp
				@if (isset($data->ordentrabajo_combinacion_talles))
					@foreach ($data->ordentrabajo_combinacion_talles as $item)
						@php
							$pares += $item->pedido_combinacion_talles->cantidad;
						@endphp
					@endforeach
				@endif
				{{ $pares ?? '' }}
			</td>
			<td>
				@php $ultimaTarea = ""; @endphp
				@foreach ($data->ordentrabajo_tareas as $tarea)
					@php
						$ultimaTarea = $tarea->tareas->nombre;
					@endphp
				@endforeach
				{{$ultimaTarea}}
			</td>
		</tr>
		@endforeach		
	</tbody>
</table>
