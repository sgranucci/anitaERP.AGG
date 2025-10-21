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
        	<img style="margin: 12px;" width=300px src="{{ "storage/imagenes/logos/logoAguas.jpg" }}">
			<div>
				<strong>IDENTIFICACION DE CLIENTES GANADORES DE PREMIOS</strong><br>
			</div>
		</th>
		<th>
			<strong>Premio Nro.: {{$cliente_premio_uif->id ?? ''}}</strong><br>
			<strong style="font-size: 12px">Fecha emisi&oacute;n: {{date("d/m/Y")}} </strong><br>
			<strong style="font-size: 12px">Fecha entrega: {{date("d/m/Y", strtotime($cliente_premio_uif->fechaentrega ?? ''))}} </strong>
		</th>
	</tr>
</table>
<div class="row">
<div class="card-body">
    <div class="mt-5">
		<strong>Tipo y nro. de documento: {{$cliente_premio_uif->clientes_uif->numerodocumento}}</strong><br>
		<strong>CUIT/CUIL: {{ $cliente_premio_uif->clientes_uif->cuit ?? ''}}</strong><br>
	</div>
	<header>Formas de pago</header>
	<table class="table table-sm table-bordered table-striped" style="font-size: 10px;">
		<thead>
    	<tr>
       		<th>Cuenta</th>
       		<th>Descripción</th>
       		<th>Moneda</th>
       		<th style="text-align: right;">Monto</th>
			<th style="text-align: right;">Cotización</th>
    	</tr>
  		</thead>
    	<tbody>
		</tbody>
	</table>
    <div class="form-group">
    	<label>Observaciones</label>
       	<textarea name="observacion" class="form-control" rows="3" value="{{old('leyenda', $cliente_premio_uif->observacion ?? '')}}"></textarea>
    </div>
</div>
</div>
</body>
</html>
