<!DOCTYPE html>
<html>
	<title>Premios</title>
	<head>
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
		<h2>Premios UIF</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Nombre</th>
					<th>Sala</th>
					<th>Juego</th>
					<th>Fecha Entrega</th>
					<th>Monto</th>
					<th>Posición</th>
					<th>Número TITO</th>
					<th>Forma de Pago</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($cliente_premio_uifs as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{$data->nombrecliente}}</td>
					<td>{{$data->nombresala}}</td>
					<td><small>{{$data->nombrejuego}}</small></td>
					<td><small>{{$data->fechaentrega}}</small></td>
					<td><small>{{$data->monto ?? ''}}</small></td>
					<td><small>{{$data->posicion ?? ''}}</small></td>
					<td><small>{{$data->numerotito ?? ''}}</small></td>
					<td><small>{{$data->nombreformapago}}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>