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
		</th>
		<th>
			<strong>Pedido Nro.: {{$pedido->id ?? ''}}</strong><br>
			<strong>Fecha: {{date("d/m/Y", strtotime($pedido->fecha ?? ''))}} </strong>
		</th>
	</tr>
	</thead>
</table>
<div class="row">
<div class="card-body">
    <div class="mt-5">
		<strong>Cliente: {{ $pedido->clientes->nombre ?? ''}}</strong><br>
		<strong>Transporte: {{ $pedido->transportes->nombre ?? ''}}</strong><br>
		<strong>Lugar de entrega: {{ $pedido->lugarentrega ?? ''}}</strong><br>
	</div>
	<table class="table table-sm table-bordered table-striped">
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
		@foreach ($pedido->pedido_combinaciones as $item)
        	<tr>
				<td>{{ $item->articulos->sku }}</td>
				<td>{{ $item->articulos->descripcion }}</td>
				<td>{{ $item->combinaciones->codigo }}-{{ $item->combinaciones->nombre }}</td>
				<td>{{ $item->modulos->nombre }}</td>
				<td>{{ number_format($item->cantidad, 0) }}</td>
				<td>{{ number_format($item->precio, 2) }}</td>
        	</tr>
    	@endforeach
        	<tr>
				@php
					$pares = 0.;
				@endphp
				@foreach ($pedido->pedido_combinaciones as $item)
				@php
					$pares += ($item->cantidad);
				@endphp
            	@endforeach
				<td><strong>Total pares</strong></td><td><strong>{{ $pares }}</strong></td>
        	</tr>
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
