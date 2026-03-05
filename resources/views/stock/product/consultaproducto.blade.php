<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="{{asset("assets/$theme/dist/css/adminlte.min.css")}}">
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Cat&aacute;logo de productos Calzados Ferli S.A.</title>
	<h1>L&iacute;nea: {{$items[0]['linea'] ?? ''}}</h1>
	<style type="text/css">
	</style>
</head>
<body>
<div class="row">
<div class="card-body table-responsive p-0">
	@php
		$linea_actual = '';
	@endphp
    @foreach ($combinacion ?? '' as $data)
		@if ($data['linea_id'] != $linea_actual)
			@if ($linea_actual != '')
    			<tbody>
				</table>
				<div style="page-break-after:always;"></div>
			@endif
			@php $linea_actual = $data['linea_id']; @endphp
            <div class="mt-5">
				<strong>Numeraci&oacute;n: {{$data['numeracion'] ?? ''}}</strong><br>
				<strong>Capellada: {{$data['material'] ?? ''}}</strong><br>
				<strong>Forro: {{$data['forro'] ?? ''}}</strong><br>
				<strong>Fondo: {{$data['fondo'] ?? ''}}</strong>
			</div>
			<table class="table table-sm table-bordered table-striped">
    		@foreach ($modulos ?? '' as $mod)
    				<tr>
						<th scope="row">M&oacute;dulo</th>
					@php $tit = ""; $cant = ""; @endphp
					@foreach ($mod as $talles)
						<th width="10">{{$talles->talle}}</th>
						@php $modulo_nombre = $talles->modulo_nombre; @endphp
					@endforeach
					</tr>
					<tr>
						<th>{{$modulo_nombre}}</th>
					@foreach ($mod as $talles)
						<th width="10">{{$talles->cantidad}}</th>
					@endforeach
					</tr>
			@endforeach
			</table>

			<table class="table table-bordered table-striped">
			<thead>
    			<tr>
					<th>Foto</th>
					<th>C&oacute;digo</th>
					<th colspan="0">Descripci&oacute;n</th>
					<th>Precios</th>
				</tr>
			</thead>
    		<tbody>
		@endif
        <tr>
			<td>
				<img src="{{ asset('storage/imagenes/fotos_articulos/'.$data['foto']) }}" width="220" height="220" />
			</td>
			<td>
			<small>
			{{$data['sku'] ?? ''}}-{{$data['codigo'] ?? ''}}</td>
			</small>
			<td>
			<small>
			{{$data['nombre'] ?? ''}}</td>
			</small>
			<td>
			<small>
			@if ($data['precio1'] != 0)
				{{ $data['nombrelista1'] }} {{ number_format($data['precio1'],2) }}<br> 
			@endif
			@if ($data['precio2'] != 0)
				{{ $data['nombrelista2'] }} {{ number_format($data['precio2'], 2)}}<br> 
			@endif
			@if ($data['precio3'] != 0)
				{{ $data['nombrelista3'] }} {{ number_format($data['precio3'], 2)}}<br> 
			@endif
			@if ($data['precio4'] != 0)
				{{ $data['nombrelista4'] }} {{ number_format($data['precio4'], 2)}}
			@endif
			</small>
			</td>
        </tr>
	@endforeach
    <tbody>
	</table>
</div>
</div>
</body>
</html>
