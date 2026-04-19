<!DOCTYPE html>
<html>
	<title>Ordenes de Producción</title>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<style>
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				boder: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}
			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>
	</head>
	<body>
		<h2>Ordenes de Producción</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Inicio</th>
					<th>Finalización</th>
					<th>Responsable</th>
					<th>Linea de Llenado</th>
					<th>Nro.Orden Prod.</th>
					<th>Tipo de Producto</th>
					<th>Líquido de Freno Tipo</th>
					<th>Capacidad</th>
					<th>Marca</th>
					<th>Tipo de Color</th>
					<th>Cantidad</th>
					<th>Proviene de Bins</th>
					<th>Lote</th>
					<th>Observaciones</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
			@foreach ($ordenesproduccion as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td><small>{{\Carbon\Carbon::parse($data->fechainicio)->format('d-m-Y H:i')}}</small></td>
					<td><small>{{\Carbon\Carbon::parse($data->fechafinalizacion)->format('d-m-Y H:i')}}</small></td>
					<td><small>{{$data->nombreusuario}}</small></td>
					<td><small>{{$data->nombrelineallenado??''}}</small></td>
					<td><small>{{$data->numeroordenproduccion}}</small></td>
					<td><small>{{$data->nombretipoproducto??''}}</small></td>
					<td><small>{{$data->nombretipoliquidofreno??''}}</small></td>
					<td><small>{{$data->nombrecapacidad??''}}</small></td>
					<td><small>{{$data->nombremarca??''}}</small></td>
					<td><small>{{$data->nombrecolor??''}}</small></td>
					<td><small>{{$data->cantidad}}</small></td>
					<td><small>{{$data->nombreprovienebin??''}}</small></td>
					<td><small>{{$data->lote}}</small></td>
					<td><small>{{$data->observacion}}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>