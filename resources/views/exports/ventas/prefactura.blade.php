<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="{{asset("assets/$theme/dist/css/adminlte.min.css")}}">
    <meta charset="UTF-8">
    <meta name="viewport"
	    content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
	<style type="text/css">
	</style>
</head>
<body>
<table class="table borderless">
	<thead>
    <tr>
		<th>
        	<img style="margin: 12px;" width=300px src="{{ asset("storage/imagenes/logos/logo".$pedido->mventas->nombre.".jpg") }}">
			<div>
				<strong>DOCUMENTO NO VALIDO COMO FACTURA</strong><br>
			</div>
		</th>
		<th>
			<strong>PRE-FACTURA</strong><br>
			<strong style="font-size: 12px">Fecha emisi&oacute;n: {{date("d/m/Y")}} </strong><br>
			<strong>Pedido Nro.: {{$pedido->id ?? ''}}</strong><br>
			<strong>{{$pedido->codigo}}</strong><br>
			<strong style="font-size: 12px">Fecha pedido: {{date("d/m/Y", strtotime($pedido->fecha ?? ''))}} </strong>
		</th>
	</tr>
</table>
<div class="row">
<div class="card-body">
    <div class="mt-5">
		<strong>Cliente: {{ $pedido->clientes->nombre ?? ''}}</strong><br>
		<strong>Transporte: {{ $pedido->transportes->nombre ?? ''}}</strong><br>
		<strong>Lugar de entrega: {{ $pedido->lugarentrega ?? ''}}</strong><br>
	</div>
	<table class="table table-sm table-bordered table-striped" style="font-size: 8px;">
		<thead>
    	<tr>
       		<th>Sku</th>
       		<th>Descripcion</th>
       		<th>Combinacion</th>
       		<th>Modulo</th>
       		<th>Pares</th>
       		<th>Precio</th>
    	</tr>
  		</thead>
    	<tbody>
		@php $totalPares = 0; @endphp
		@foreach ($pedido->pedido_combinaciones as $item)
			@if (in_array($item->id, $itemsId))
				@for ($ii = 0; $ii < count($tblImpuesto); $ii++)
					@if ($item->id == $tblImpuesto[$ii]['id'])
        				<tr>
							<td>{{ $item->articulos->sku }}</td>
							<td>{{ $item->articulos->descripcion }}</td>
							<td>{{ $item->combinaciones->codigo }}-{{ $item->combinaciones->nombre }}</td>
							<td>{{ $item->modulos->nombre }}</td>
							<td>{{ number_format($tblImpuesto[$ii]['cantidad'], 0) }}</td>
							<td align="right">{{ number_format($tblImpuesto[$ii]['precio'], 2) }}</td>
        				</tr>
					@endif
    			@endfor

				@php $totalPares += $item->cantidad; @endphp

			@endif
    	@endforeach
		<tr>
			<td> </td>
			<td> </td>
			<td> </td>
			<td>TOTAL PARES</td>
			<td>{{$totalPares}}</td>
			<td> </td>
		</tr>
		@foreach ($conceptosTotales as $itemTotal)
        	<tr>
				<td> </td>
				<td> </td>
				<td> </td>
				<td> </td>
				<td>
				@if ($itemTotal['concepto'] == "Total")
					<strong>{{ $itemTotal['concepto'] }}</strong>
				@else
					{{ $itemTotal['concepto'] }}
				@endif
				</td>
				<td align="right">
				@if ($itemTotal['concepto'] == "Total")
					<strong>{{ number_format($itemTotal['importe'], 2) }}</strong>
				@else
					{{ number_format($itemTotal['importe'], 2) }}
				@endif
				</td>
        	</tr>
    	@endforeach
		</tbody>
	</table>
    <div class="form-group">
    	<label>Leyendas</label>
       	<textarea name="leyenda" class="form-control" rows="3" placeholder="Leyendas ..." value="{{old('leyenda', $pedido->leyenda ?? '')}}"></textarea>
    </div>
</div>
</div>
</body>
</html>
