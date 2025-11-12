<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="{{"assets/$theme/dist/css/adminlte.min.css"}}">
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
				<h5><strong>EL BIERZO</strong></h5>
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
	<table class="table table-sm table-bordered table-striped" style="font-size: 12px;">> 
		<thead>
    	<tr>
       		<th style="width: 6%;">Sku</th>
       		<th style="width: 25%;">Descripción</th>
       		<th>UMD</th>
       		<th style="width: 9%;">Cajas</th>
       		<th style="width: 9%;">Piezas</th>
			<th style="width: 9%;">Kilos</th>
			<th style="width: 9%;">Peso Real</th>
			<th>Descuento</th>
       		<th>Precio</th>
    	</tr>
  		</thead>
    	<tbody>
		@foreach ($pedido->pedido_articulos as $item)
        	<tr>
				<td>{{ $item->articulos->sku }}</td>
				<td>{{ $item->articulos->descripcion }}</td>
				<td>{{ $item->articulos->unidadesdemedidas->abreviatura}}</td>
				<td>{{ number_format($item->caja, 2) }}</td>
				<td>{{ number_format($item->pieza, 2) }}</td>
				<td>{{ number_format($item->kilo, 2) }}</td>
				<td>{{ number_format($item->pesada, 2) }}</td>
				<td>{{ $item->descuentoventa_ids->nombre??'' }}</td>
				<td>{{ number_format($item->precio, 2) }}</td>
        	</tr>
    	@endforeach
        	<tr>
				<td><strong>Totales</strong></td>
				@php
					$kilos = 0.;
					$cajas = 0.;
					$piezas = 0.;
					$pesada = 0.;
				@endphp
				@foreach ($pedido->pedido_articulos as $item)
				@php
					$kilos += ($item->kilo);
					$piezas += ($item->pieza);
					$cajas += ($item->caja);
					$pesada += ($item->pesada);
				@endphp
            	@endforeach
				<td></td>
				<td></td>
				<td><strong>{{number_format($cajas,2)}}</strong></td>
				<td><strong>{{number_format($piezas,2)}}</strong></td>
				<td><strong>{{number_format($kilos,2)}}</strong></td>
				<td><strong>{{number_format($pesada,2)}}</strong></td>
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
