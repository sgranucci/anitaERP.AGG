@switch($tipolistado)
	@case('CLIENTE')
		<h2> Reporte General de Pedidos de Ventas POR CLIENTE</h2>
		@php
			$campoIdData = 'cliente_id';
			$campoNombreData = 'nombrecliente';
			$campoIdData2 = '';
			$campoNombreData2 = '';
		@endphp
        @break
    @case('ARTICULO')
		<h2> Reporte General de Pedidos de Ventas POR ARTICULO Y COMBINACION</h2>
		@php
			$campoIdData = 'articulo_id';
			$campoNombreData = 'nombrearticulo';
			$campoIdData2 = 'combinacion';
			$campoNombreData2 = 'combinacion';
		@endphp
        @break
	@case('LINEA')
		<h2> Reporte General de Pedidos de Ventas POR LINEA</h2>
		@php
			$campoIdData = 'linea_id';
			$campoNombreData = 'nombrelinea';
			$campoIdData2 = '';
			$campoNombreData2 = '';
		@endphp
	@break
	@case('VENDEDOR')
		<h2> Reporte General de Pedidos de Ventas POR VENDEDOR</h2>
		@php
			$campoIdData = 'vendedor_id';
			$campoNombreData = 'nombrevendedor';
			$campoIdData2 = '';
			$campoNombreData2 = '';
		@endphp
	@break
	@case('FONDO')
		<h2> Reporte General de Pedidos de Ventas POR FONDO</h2>
		@php
			$campoIdData = 'fondo_id';
			$campoNombreData = 'nombrefondo';
			$campoIdData2 = 'colorfondo_id';
			$campoNombreData2 = 'nombrecolorfondo';
		@endphp
	@break
@endswitch
<h1>
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>&nbsp;
	<strong>Filtra pedidos: {{$estado}}</strong>&nbsp;
	<strong>Marca: {{$marca}}</strong>
</h1>
<table>
	<thead>
    <tr>
       	<th>Pedido</th>
       	<th>Estado</th>
       	<th>Fecha</th>
		<th>Nro.OT</th>
       	<th>Vendedor</th>
       	<th>Cliente</th>
		<th>Estado cliente</th>
		<th>Art&iacute;culo</th>
		<th>Sku</th>
		<th>Combinaci&oacute;n</th>
		<th>Fondo</th>
		<th>Color Fondo</th>
		@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
			<th>{{$ii}}</th>
		@endfor
		<th align="right">Total</th>
	</tr>
  	</thead>
    <tbody>
	@php
		$idCorteActual = '';
		$nombreCorteActual = '';
		$idCorteActual2 = '';
		$nombreCorteActual2 = '';
	@endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php
			$total[$tipolistado][$ii] = 0;
			$total['final'][$ii] = 0;
		@endphp
	@endfor
	
    @foreach ($comprobantes as $renglon)
		@php $totalPares = 0; @endphp

		@foreach ($renglon as $data)
			@if ($data[$campoIdData] != $idCorteActual ||
				($campoIdData2 != '' ? $data[$campoIdData2] != $idCorteActual2 : false))
				@if ($idCorteActual != '')
					@include('exports.ventas.reportegeneralpedido.imprimetotalcorte')
				@endif
				@php 
					$idCorteActual = $data[$campoIdData]; 
					$nombreCorteActual = $data[$campoNombreData]; 
					if ($campoIdData2 != '')
					{
						$idCorteActual2 = $data[$campoIdData2]; 
						$nombreCorteActual2 = $data[$campoNombreData2]; 
					}
				@endphp
				@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
					@php
						$total['final'][$ii] += $total[$tipolistado][$ii];
						$total[$tipolistado][$ii] = 0;
					@endphp
				@endfor
			@endif

			@php $totalPares = 0; @endphp

			@include('exports.ventas.reportegeneralpedido.imprimeunitem')
			
			@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
				@foreach($data['medidas'] as $medida)
					@if ($ii == $medida['medida'])
						@php 
							$total[$tipolistado][$ii] += $medida['cantidad']; 
							$flEncontro = true; 
						@endphp
					@endif
				@endforeach
			@endfor
		@endforeach
	@endforeach

	@if ($idCorteActual != '')
		@include('exports.ventas.reportegeneralpedido.imprimetotalcorte')
		@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
			@php
				$total['final'][$ii] += $total[$tipolistado][$ii];
			@endphp
		@endfor
	@endif
	@include('exports.ventas.reportegeneralpedido.imprimetotalfinal')
	</tbody>
</table>
