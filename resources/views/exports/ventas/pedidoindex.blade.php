<h4> Pedidos de Ventas </h4>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Fecha entrega</th>
		<th class="width50">Cliente</th>
		<th>Cajas</th>
		<th>Piezas</th>
		<th>Kilos</th>
		<th>Pesada</th>
		<th>Reparto</th>
		<th class="width60">Estado</th>
	</tr>
  	</thead>
    <tbody>
		@php $totalKilo = 0; $totalCaja = 0; $totalPieza = 0; $totalPesada = 0; $totalPesada = 0; @endphp
		@foreach($pedidos as $pedido)
			<tr data-entry-id="{{ $pedido['id'] }}">
				<td>
					{{ $pedido['id'] ?? '' }}
				</td>
				<td>
					{{date("d-m-Y", strtotime($pedido['fecha'] ?? ''))}} 
				</td>
				<td>
					{{date("d-m-Y", strtotime($pedido['fechaentrega'] ?? ''))}} 
				</td>								
				<td>
					<b>{{ $pedido['nombrecliente'] ?? '' }}</b>
				</td>
				<td>
					@php $caja = 0; @endphp
					@foreach ($pedido->pedido_articulos as $item)
						@php $caja = $caja + $item->caja; $totalCaja += $item->caja @endphp
					@endforeach
					{{$caja}}
				</td>		
				<td>
					@php $pieza = 0; @endphp
					@foreach ($pedido->pedido_articulos as $item)
						@php $pieza = $pieza + $item->pieza; $totalPieza += $item->pieza @endphp
					@endforeach
					{{$pieza}}
				</td>															
				<td>
					@php $kilo = 0; @endphp
					@foreach ($pedido->pedido_articulos as $item)
						@php $kilo = $kilo + $item->kilo; $totalKilo += $item->kilo @endphp
					@endforeach
					{{$kilo}}
				</td>
				<td>
					@php $pesada = 0; @endphp
					@foreach ($pedido->pedido_articulos as $item)
						@php $pesada = $pesada + $item->pesada; $totalPesada += $item->pesada @endphp
					@endforeach
					{{$pesada}}
				</td>									
				<td>{{ $pedido->nombretransporte ?? ''}}</td>
				<td>
					{{ $pedido['estado'] }}
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
