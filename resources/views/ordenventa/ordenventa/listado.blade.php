<!DOCTYPE html>
<html>
	<title>Tickets</title>
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
		<h2>Tickets</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Fecha</th>
					<th>Nro. Orden Vta.</th>
					<th>Empresa</th>
					<th>Tratamiento</th>
					<th>Centro de Costo</th>
					<th>Cliente</th>
					<th>Monto</th>
					<th>Moneda</th>
					<th>Estado</th>
					<th>Detalle</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($ticket as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
					<td>{{$data->numeroordenventa ?? ''}}</td>
					<td>{{$data->nombreempresa ?? ''}}</td>
					<td>{{$data->tratamiento ?? ''}}</td>
					<td>{{$data->nombrecentrocosto ?? '' }}</td>
					<td>{{$data->nombrecliente ?? ''}}</td>
					<td>{{number_format($data->monto,2) ?? ''}}</td>
					<td>{{$data->abreviaturamoneda}}</td>
					<td>{{$data->estado}}</td>
					<td>{{$data->detalle}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>