@switch($tipolistado)
	@case('CAPELLADA')
		<h2> Reporte de Consumo de Materiales de Capellada</h2>
		@php
			$campoIdData = 'materialcapellada_id';
			$campoNombreData = 'nombrematerialcapellada';
			$tipomaterial = $tipocapellada;
		@endphp
		@break
    @case('AVIO')
		<h2> Reporte de Consumo de Materiales de Avio</h2>
		@php
			$campoIdData = 'materialavio_id';
			$campoNombreData = 'nombrematerialavio';
			$tipomaterial = $tipoavio;
		@endphp
        @break
@endswitch
@if ($desdecolor_id == 0 && $hastacolor_id == 99999999)
	@php $color = 'TODOS'; @endphp
@else	
	@php $color = 'Desde color: '.$desdecolor_id.' hasta: '.$hastacolor_id; @endphp
@endif
		
<h1>
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>&nbsp;
	<strong>Filtra pedidos: {{$estado}}</strong>&nbsp;
	<strong>Tipo de material: {{$tipomaterial}}</strong>&nbsp;
	<strong>Filtra colores: {{$color}}</strong>
</h1>
<table>
	<thead>
	<tr>
		<th colspan="12"></th>
		
		<th colspan="28">CONSUMOS</th>
		<th colspan="28">PARES</th>
	</tr>
    <tr>
       	<th>Pedido</th>
       	<th>Estado</th>
       	<th>Fecha</th>
		<th>Nro.OT</th>
       	<th>Vendedor</th>
       	<th>Cliente</th>
       	<th>Art&iacute;culo</th>
		<th>Sku</th>
		<th>Combinaci&oacute;n</th>
		<th>Fondo</th>
		<th>Material</th>
		<th>Color</th>
		@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
			<th>{{$ii}}</th>
		@endfor
		<th align="right">Total</th>
		@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
			<th>{{$ii}}</th>
		@endfor
		<th align="right">Total</th>
	</tr>
  	</thead>
    <tbody>
	@php
		$idCorteActual = '';
		$colorActual = '';
		$nombreCorteActual = '';
		$totalPares[$tipolistado] = array();
	@endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php
			$totalPares[$tipolistado][$ii] = 0;
			$totalPares['final'][$ii] = 0;
			$totalConsumo[$tipolistado][$ii] = 0;
			$totalConsumo['final'][$ii] = 0;
		@endphp
	@endfor
	
    @foreach ($comprobantes as $renglon)
		
		@foreach ($renglon as $data)
			@if ($data[$campoIdData] != $idCorteActual || $data['nombrecolor'] != $colorActual)
				@if ($idCorteActual != '')
					@include('exports.ventas.reporteconsumomaterial.imprimetotalcorte')
				@endif
				@php 
					$idCorteActual = $data[$campoIdData]; 
					$colorActual = $data['nombrecolor'];
					$nombreCorteActual = $data[$campoNombreData].' '.$data['nombrecolor']; 
				@endphp
				@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
					@php
						$totalPares['final'][$ii] += $totalPares[$tipolistado][$ii];
						$totalPares[$tipolistado][$ii] = 0;
						$totalConsumo['final'][$ii] += $totalConsumo[$tipolistado][$ii];
						$totalConsumo[$tipolistado][$ii] = 0;
					@endphp
				@endfor
			@endif

			@include('exports.ventas.reporteconsumomaterial.imprimeunitem')
			@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
				@foreach($data['medidas'] as $medida)
					@if ($ii == $medida['medida'])
						@php 
							$totalPares[$tipolistado][$ii] += $medida['cantidad']; 
							$totalConsumo[$tipolistado][$ii] += $medida['consumo'];
							$flEncontro = true; 
						@endphp
					@endif
				@endforeach
			@endfor
		@endforeach
	@endforeach

	@if ($idCorteActual != '')
		@include('exports.ventas.reporteconsumomaterial.imprimetotalcorte')
		@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
			@php
				$totalPares['final'][$ii] += $totalPares[$tipolistado][$ii];
				$totalConsumo['final'][$ii] += $totalConsumo[$tipolistado][$ii];
			@endphp
		@endfor
	@endif
	@include('exports.ventas.reporteconsumomaterial.imprimetotalfinal')
	</tbody>
</table>
