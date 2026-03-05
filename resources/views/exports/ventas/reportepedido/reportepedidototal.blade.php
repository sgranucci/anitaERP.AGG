<h2> Totales de Pedidos de Ventas </h2>
<h1><strong>Vendedor: {{$vendedor ?? ''}}-{{$nombre_vendedor}}</strong>&nbsp;&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>
</h1>
<table>
	<thead>
    <tr>
       	<th>Cliente</th>
       	<th>Nombre</th>
       	<th>Pedido</th>
       	<th>Fecha</th>
       	<th>Pedidos</th>
       	<th>Produccion</th>
       	<th>Facturados</th>
       	<th>Pendientes</th>
       	<th>Efectividad</th>
    </tr>
  	</thead>
    <tbody>
	@php
		$cliente_actual = '';
		$nombre_actual = '';
		$vendedor_actual = '';
		$nombrevendedor_actual = '';
		$totv_pedido = $totc_pedido = $totp_pedido = 0;
		$totv_produccion = $totc_produccion = $totp_produccion = 0;
		$totv_facturado = $totc_facturado = $totp_facturado = 0;
		$totv_pendiente = $totc_pendiente = $totp_pendiente = 0;
		$id_actual = 0;
		$fecha_actual = 0;
		$tipo_actual = $letra_actual = '';
		$sucursal_actual = $numero_actual = 0;
		$fl_primer_mov = true;
	@endphp

    @foreach ($comprobantes as $data)
		@if ($id_actual != $data->numero)
			@if ($id_actual != 0)
				@include('exports.ventas.reportepedido.imprimeunrenglon')
				@php 
					$totc_pedido += $totp_pedido;
					$totc_produccion += $totp_produccion;
					$totc_facturado += $totp_facturado;
					$totc_pendiente += $totp_pendiente;
					$totv_pedido += $totp_pedido;
					$totv_produccion += $totp_produccion;
					$totv_facturado += $totp_facturado;
					$totv_pendiente += $totp_pendiente;
				@endphp
			@endif
			@php 
				$id_actual = $data->numero;
				$totp_pedido = $totp_produccion = $totp_facturado = $totp_pendiente = 0;
			@endphp
		@endif

		@if ($data->cliente != $cliente_actual)
			@if ($cliente_actual != '')
				@include('exports.ventas.reportepedido.imprimetotalcliente')
			@endif
			@php 
				$cliente_actual = $data->cliente; 
				$nombre_actual = $data->nombre; 
	
				$totc_pedido = $totc_produccion = $totc_facturado = $totc_pendiente = 0;
			@endphp
		@endif

		@if ($data->vendedor != $vendedor_actual)
			@if ($vendedor_actual != '')
				@include('exports.ventas.reportepedido.imprimetotalvendedor')
			@endif
			@php 
				$vendedor_actual = $data->vendedor; 
				$nombrevendedor_actual = $data->nombre_vendedor; 
	
				$totv_pedido = $totv_produccion = $totv_facturado = $totv_pendiente = 0;
			@endphp
		@endif
		@php
			$fecha_actual = $data->fecha;
			$tipo_actual = $data->tipo;
			$letra_actual = $data->letra;
			$sucursal_actual = $data->sucursal;
			$numero_actual = $data->numero;

			$totp_pedido += $data->cantidad; 
		@endphp

		@if ($data->nro_orden != 0 && $data->nro_orden != -1 && ($data->cantfact == 0 || $data->cantfact == null))
			@php $totp_produccion += $data->cantidad; @endphp
		@endif
		@if ($data->cantfact > 0)
			@php $totp_facturado += $data->cantidad; @endphp
		@endif
		@if (($data->cantfact == 0 || $data->cantfact == null) && ($data->nro_orden == 0 || $data->nro_orden == -1))
			@php $totp_pendiente += $data->cantidad; @endphp
		@endif

    @endforeach

	@if ($id_actual != 0)
		@include('exports.ventas.reportepedido.imprimeunrenglon')

		@php 
			$totc_pedido += $totp_pedido;
			$totc_produccion += $totp_produccion;
			$totc_facturado += $totp_facturado;
			$totc_pendiente += $totp_pendiente;
			$totv_pedido += $totp_pedido;
			$totv_produccion += $totp_produccion;
			$totv_facturado += $totp_facturado;
			$totv_pendiente += $totp_pendiente;
		@endphp

		@include('exports.ventas.reportepedido.imprimetotalcliente')
	@endif
	@include('exports.ventas.reportepedido.imprimetotalvendedor')

	</tbody>
</table>
