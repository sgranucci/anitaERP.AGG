<h1> Kilos Pedidos Totalizado por Pedido </h1>
<h2><strong>Reparto: {{$transporte ?? ''}}</strong>&nbsp;&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>
</h2>
<table>
	<thead>
    <tr>
		<th>Reparto</th>
		<th>Nombre Reparto</th>
       	<th>Cliente</th>
       	<th>Nombre</th>
       	<th>Pedido</th>
       	<th>Fecha</th>
		<th>Localidad</th>
		<th>Provincia</th>
       	<th>Piezas</th>
		<th>Kilos Teóricos</th>
       	<th>Kilos Pesados</th>
       	<th>Cajas</th>
    </tr>
  	</thead>
    <tbody>
	@php
		$repartoActual = '';
		$nombreRepartoActual = '';
		$totalPieza = $totalCaja = $totalKiloPesado = $totalKiloTeorico = 0;
		$totalFinalPieza = $totalFinalCaja = $totalFinalKiloPesado = $totalFinalKiloTeorico = 0;
		$fl_primer_item = false;
	@endphp
    @foreach ($comprobantes as $data)
		@if ($data->codigotransporte != $repartoActual)
			@if ($repartoActual != '')
        	<tr>
           		<td>
					@if ($fl_primer_item)
						{{$repartoActual}}
					@endif
				</td>
           		<td>
					@if ($fl_primer_item)
						{{$nombreRepartoActual}}
					@endif
					@php $fl_primer_item = false; @endphp
				</td>
           		<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
           		<td></td>
           		<td align="right">{{number_format(floatval($totalPieza), 2)}}</td>
           		<td align="right">{{number_format(floatval($totalKiloTeorico), 2)}}</td>
				<td align="right">{{number_format(floatval($totalKiloPesado), 2)}}</td>
           		<td align="right">{{number_format(floatval($totalCaja), 2)}}</td>
        	</tr>
			@endif
			@php 
				$fl_primer_item = true;
				$totalPieza = $totalCaja = $totalKiloPesado = $totalKiloTeorico = 0;
				$repartoActual = $data->codigotransporte;
				$nombreRepartoActual = $data->nombretransporte;
			@endphp 
		@endif

		@php 
			$totalPieza += $data->total_pieza;
			$totalKiloTeorico += $data->total_kilo; 
			$totalKiloPesado += $data->total_pesada; 
			$totalCaja += $data->total_caja; 

			$totalFinalPieza += $data->total_pieza;
			$totalFinalKiloTeorico += $data->total_kilo; 
			$totalFinalKiloPesado += $data->total_pesada; 
			$totalFinalCaja += $data->total_caja; 
		@endphp

		<tr>
			<td></td>
			<td></td>
			<td>{{$data->codigocliente}}</td>
			<td>{{$data->nombrecliente}}</td>
			<td>{{$data->codigopedido}}</td>
			<td>{{$data->fechaentrega->format('d-m-Y H:i')}}</td>
			<td>{{$data->nombrelocalidad}}</td>
			<td>{{$data->nombreprovincia}}</td>
			<td align="right">{{number_format(floatval($data->total_pieza), 2)}}</td>
			<td align="right">{{number_format(floatval($data->total_kilo), 2)}}</td>
			<td align="right">{{number_format(floatval($data->total_pesada), 2)}}</td>
			<td align="right">{{number_format(floatval($data->total_caja), 2)}}</td>
			<td> </td>
		</tr>		
    @endforeach
	<tr>
		<td>
			@if ($fl_primer_item)
				{{$repartoActual}}
			@endif
		</td>
		<td>
			@if ($fl_primer_item)
				{{$nombreRepartoActual}}
			@endif
			@php $fl_primer_item = false; @endphp
		</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td align="right">{{number_format(floatval($totalPieza), 2)}}</td>
		<td align="right">{{number_format(floatval($totalKiloTeorico), 2)}}</td>
		<td align="right">{{number_format(floatval($totalKiloPesado), 2)}}</td>
		<td align="right">{{number_format(floatval($totalCaja), 2)}}</td>
		<td> </td>
	</tr>	
	<tr>
		<td>TOTAL FINAL</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td align="right">{{number_format(floatval($totalFinalPieza), 2)}}</td>
		<td align="right">{{number_format(floatval($totalFinalKiloTeorico), 2)}}</td>
		<td align="right">{{number_format(floatval($totalFinalKiloPesado), 2)}}</td>
		<td align="right">{{number_format(floatval($totalFinalCaja), 2)}}</td>
	</tr>
	</tbody>
</table>
