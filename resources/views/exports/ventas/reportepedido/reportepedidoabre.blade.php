<h2> Pedidos de Ventas </h2>
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
       	<th>Nro.OT</th>
       	<th>Estado</th>
       	<th>Fecha</th>
       	<th>Marca</th>
       	<th>Articulo</th>
       	<th>Combinacion</th>
       	<th>Linea</th>
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
		$sku_actual = '';
		$totv_pedido = $tot_pedido = $tota_pedido = 0;
		$totv_produccion = $tot_produccion = $tota_produccion = 0;
		$totv_facturado = $tot_facturado = $tota_facturado = 0;
		$totv_pendiente = $tot_pendiente = $tota_pendiente = 0;
		$fl_primer_item = false;
	@endphp
    @foreach ($comprobantes as $data)
		@if ($data->articulo.'-'.$data->combinacion != $sku_actual)
			@if ($sku_actual != '')
        	<tr>
           		<td>
					@if ($fl_primer_mov)
						{{$cliente_actual}}
					@endif
				</td>
           		<td>
					@if ($fl_primer_mov)
						{{$nombre_actual}}
					@endif
					@php $fl_primer_mov = false; @endphp
				</td>
           		<td>{{$tipo}}</td>
           		<td>{{$nro_orden}}</td>
           		<td>{{$estado}}</td>
			   	@if (strpos($fecha, '-') !== false)
				    <td>{{date("d/m/Y", strtotime($data['fecha']))}}</td>
				@else
					<td>{{$fecha}}</td>
				@endif
           		<td>{{$marca}}</td>
           		<td>{{$articulo}}</td>
           		<td>{{$desc_combinacion}}</td>
           		<td>{{$linea}}</td>

           		<td align="right">{{number_format(floatval($tota_pedido), 0)}}</td>
           		<td align="right">{{number_format(floatval($tota_produccion), 0)}}</td>
           		<td align="right">{{number_format(floatval($tota_facturado), 0)}}</td>
           		<td align="right">{{number_format(floatval($tota_pendiente), 0)}}</td>
				<td> </td>
        	</tr>
			@endif
			@php 
				$fl_primer_item = true;
				$tota_produccion = $tota_pedido = $tota_facturado = $tota_pendiente = 0;
				$sku_actual = $data->articulo.'-'.$data->combinacion;
			@endphp 
		@endif
		@if ($data->cliente != $cliente_actual)
			@if ($cliente_actual != '')
				<tr>
           			<td>TOTAL CLIENTE {{$cliente_actual}}-{{$nombre_actual}}</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
           			<td align="right">{{number_format(floatval($tot_pedido), 0)}}</td>
           			<td align="right">{{number_format(floatval($tot_produccion), 0)}}</td>
           			<td align="right">{{number_format(floatval($tot_facturado), 0)}}</td>
           			<td align="right">{{number_format(floatval($tot_pendiente), 0)}}</td>
					@if ($tot_pedido != 0)
           				<td align="right">{{number_format(floatval($tot_facturado/$tot_pedido*100.), 0)}}%</td>
					@else
						<td align="right">0 %</td>
					@endif
				</tr>
			@endif
			@php 
				$cliente_actual = $data->cliente; 
				$nombre_actual = $data->nombre; 
				$tot_pedido = 0;
				$tot_produccion = 0;
				$tot_facturado = 0;
				$tot_pendiente = 0;
				$fl_primer_mov = true;
			@endphp
		@endif
		@if ($data->vendedor != $vendedor_actual)
			@if ($vendedor_actual != '')
				<tr>
           			<td>TOTAL VENDEDOR {{$vendedor_actual}}-{{$nombrevendedor_actual}}</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
           			<td align="right">{{number_format(floatval($totv_pedido), 0)}}</td>
           			<td align="right">{{number_format(floatval($totv_produccion), 0)}}</td>
           			<td align="right">{{number_format(floatval($totv_facturado), 0)}}</td>
           			<td align="right">{{number_format(floatval($totv_pendiente), 0)}}</td>
					@if ($totv_pedido != 0)
           				<td align="right">{{number_format(floatval($totv_facturado/$totv_pedido*100.), 0)}}%</td>
					@else
						<td align="right">0 %</td>
					@endif
				</tr>
			@endif
			@php 
				$vendedor_actual = $data->vendedor; 
				$nombrevendedor_actual = $data->nombre_vendedor; 
				$totv_pedido = 0;
				$totv_produccion = 0;
				$totv_facturado = 0;
				$totv_pendiente = 0;
				$fl_primer_mov = true;
			@endphp
		@endif
		@php 
           	$tipo = $data->tipo.' '.$data->letra.$data->sucursal.'-'.$data->numero;
			$estado = "Pendiente"; 
		@endphp
		@if ($data->nro_orden != 0 && $data->nro_orden != -1)
			@php $estado = "Produccion"; @endphp
		@endif
		@if ($data->cantfact > 0)
			@php $estado = "Facturado"; @endphp
		@endif
		@if ($data->estado == '3')
			@php $estado = "Facturado"; @endphp
		@endif
		@if ($data->estado == '3')
			@php $estado = "Anulado"; @endphp
		@endif
		@if ($data->estado == '4')
			@php $estado = "Suspendido"; @endphp
		@endif
		@if ($data->estado == 'C')
			@php $estado = "Cerrado"; @endphp
		@endif
		@php
         	$fecha = substr($data->fecha,6,2).'-'.substr($data->fecha,4,2).'-'.substr($data->fecha,0,4);
          	$marca = $data->marca;
          	$desc_combinacion = $data->desc_combinacion;
          	$linea = $data->linea;
			$fl_primer_item = false; 
			$articulo = $data->articulo;
			$combinacion = $data->combinacion;
			$nro_orden = $data->nro_orden;
		@endphp

		@php $tot_pedido += $data->cantidad; @endphp
		@php $totv_pedido += $data->cantidad; @endphp
		@php $tota_pedido += $data->cantidad; @endphp
		@if ($data->nro_orden != 0 && $data->nro_orden != -1 &&
			$data->cantfact == 0)
			@php $tot_produccion += $data->cantidad; @endphp
			@php $totv_produccion += $data->cantidad; @endphp
			@php $tota_produccion += $data->cantidad; @endphp
		@endif
		@if ($data->cantfact > 0)
			@php $tot_facturado += $data->cantidad; @endphp
			@php $totv_facturado += $data->cantidad; @endphp
			@php $tota_facturado += $data->cantidad; @endphp
		@endif
		@if ($data->cantfact <= 0 && 
			($data->nro_orden == 0 || $data->nro_orden == -1))
			@php $tot_pendiente += $data->cantidad; @endphp
			@php $totv_pendiente += $data->cantidad; @endphp
			@php $tota_pendiente += $data->cantidad; @endphp
		@endif
    @endforeach
	@if ($cliente_actual != '')
		<tr>
       		<td>TOTAL CLIENTE {{$cliente_actual}}-{{$nombre_actual}}</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
       		<td align="right">{{number_format(floatval($tot_pedido), 0)}}</td>
       		<td align="right">{{number_format(floatval($tot_produccion), 0)}}</td>
       		<td align="right">{{number_format(floatval($tot_facturado), 0)}}</td>
       		<td align="right">{{number_format(floatval($tot_pendiente), 0)}}</td>
			@if ($tot_pedido != 0)
				<td align="right">{{number_format(floatval($tot_facturado/$tot_pedido*100.), 0)}}%</td>
			@else
				<td align="right">0 %</td>
			@endif
		</tr>
	@endif
	<tr>
      	<td>TOTAL VENDEDOR {{$vendedor_actual}}-{{$nombrevendedor_actual}}</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
       	<td align="right">{{number_format(floatval($totv_pedido), 0)}}</td>
       	<td align="right">{{number_format(floatval($totv_produccion), 0)}}</td>
       	<td align="right">{{number_format(floatval($totv_facturado), 0)}}</td>
       	<td align="right">{{number_format(floatval($totv_pendiente), 0)}}</td>
		@if ($totv_pedido != 0)
			<td align="right">{{number_format(floatval($totv_facturado/$totv_pedido*100.), 0)}}%</td>
		@else
			<td align="right">0 %</td>
		@endif
           	

	</tr>
	</tbody>
</table>
