<h2> Totales de Pedidos de Ventas </h2>
<h1><strong>Desde Vendedor: {{$desdevendedor ?? ''}}-{{$nombre_desdevendedor}}</strong>&nbsp;
	<strong>Hasta Vendedor: {{$hastavendedor ?? ''}}-{{$nombre_hastavendedor}}</strong>&nbsp;&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>
</h1>
<table>
	<thead>
    <tr>
       	<th>Vendedor</th>
       	<th>Nombre</th>
       	<th>Pedidos</th>
       	<th>Produccion</th>
       	<th>Facturados</th>
       	<th>Pendientes</th>
       	<th>Efectividad</th>
    </tr>
  	</thead>
    <tbody>
	@php
		$vendedor_actual = '';
		$nombre_actual = '';
		$totv_pedido = $totf_pedido = 0;
		$totv_produccion = $totf_produccion = 0;
		$totv_facturado = $totf_facturado = 0;
		$totv_pendiente = $totf_pendiente = 0;
		$id_actual = 0;
	@endphp

    @foreach ($comprobantes as $data)

		@if ($data->vendedor != $vendedor_actual)
			@if ($vendedor_actual != '')
				@include('exports.ventas.reportetotalpedido.imprimetotalvendedor')
			@endif
			@php 
				$vendedor_actual = $data->vendedor; 
				$nombre_actual = $data->nombre; 
				$totf_pedido += $totv_pedido;
				$totf_produccion += $totv_produccion;
				$totf_facturado += $totv_facturado;
				$totf_pendiente += $totv_pendiente;
	
				$totv_pedido = $totv_produccion = $totv_facturado = $totv_pendiente = 0;
			@endphp
		@endif

		@php
			$totv_pedido += $data->cantidad; 
		@endphp

		@if ($data->nro_orden != 0 && $data->nro_orden != -1 && $data->cantfact == 0)
			@php $totv_produccion += $data->cantidad; @endphp
		@endif
		@if ($data->cantfact > 0)
			@php $totv_facturado += $data->cantidad; @endphp
		@endif
		@if ($data->cantfact <= 0 && ($data->nro_orden == 0 || $data->nro_orden == -1))
			@php $totv_pendiente += $data->cantidad; @endphp
		@endif

    @endforeach

	@if ($vendedor_actual != 0)
		@include('exports.ventas.reportetotalpedido.imprimetotalvendedor')
		@php 
			$totf_pedido += $totv_pedido;
			$totf_produccion += $totv_produccion;
			$totf_facturado += $totv_facturado;
			$totf_pendiente += $totv_pendiente;
		@endphp
	@endif
	@include('exports.ventas.reportetotalpedido.imprimetotalfinal')
	</tbody>
</table>
