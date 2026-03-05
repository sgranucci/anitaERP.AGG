<!DOCTYPE html>
<html>
	<title>Localidades</title>
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
		<h2>Localidades</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Nombre</th>
					<th>Código Postal</th>
					<th>Código Anita</th>
					<th>Provincia</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($localidades as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{$data->nombre}}</td>
					<td>{{$data->codigopostal}}</td>
					<td>{{$data->codigo}}</td>
					<td>{{$data->nombreprovincia??''}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>