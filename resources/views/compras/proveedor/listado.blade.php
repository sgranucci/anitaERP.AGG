<!DOCTYPE html>
<html>
	<title>Proveedores</title>
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
		<h2>Proveedores</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width10">ID</th>
					<th>Nombre</th>
					<th>Nombre de Fantas&iacute;a</th>
					<th>C.U.I.T.</th>
					<th>Domicilio</th>
					<th>Localidad</th>
					<th>Provincia</th>
					<th class="width10">C&oacute;d.</th>
					<th>Estado</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($proveedores as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{$data->nombre}}</td>
					<td>{{$data->fantasia}}</td>
					<td><small>{{$data->numerodocumento}}</small></td>
					<td><small>{{$data->domicilio}}</small></td>
					<td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
					<td><small>{{$data->nombreprovincia ?? ''}}</small></td>
					<td><small>{{$data->codigo}}</small></td>
					<td><small>{{$data->estado}}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>