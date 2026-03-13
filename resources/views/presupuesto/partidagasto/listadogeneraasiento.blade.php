<!DOCTYPE html>
<html>
	<title>Asientos de Partidas de Gastos {{$asientos[0]['nombreempresa']}} {{$asientos[0]['nombrepresupuesto']}}</title>
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
		<h2>Asientos de Partidas de Gastos {{$asientos[0]['nombreempresa']}} {{$asientos[0]['nombrepresupuesto']}}</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">Nro.Asiento</th>
					<th>Centro de Costo</th>
					<th>Cuenta Contable</th>
					<th>Descripción</th>
					<th>Fecha</th>
					<th>Moneda</th>
					<th>Monto</th>
					<th>Nro. de Partida</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($asientos as $data)
				<tr>
					<td>{{$data['id']}}</td>
					<td>{{$data['nombrecentrocosto'] ?? '' }}</td>
					<td>{{$data['codigocuentacontable'] ?? ''}}</td>
					<td>{{$data['nombrecuentacontable'] ?? ''}}</td>
					<td>{{date('d-m-Y', strtotime($data['fecha']))}}</td>
					<td>{{$data['abreviaturamoneda']}}</td>
					<td>{{$data['monto']}}</td>
					<td>{{$data['codigopartida']}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>