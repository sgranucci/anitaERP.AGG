<!DOCTYPE html>
<html>
	<title>Retenciones Impositivas ARCA</title>
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
		<h2>Retenciones Impositivas ARCA</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Empresa</th>
					<th>Nombre</th>
					<th>CUIT</th>
					<th>Impuesto</th>
					<th>Fecha de Retención</th>
					<th>Certificado</th>
					<th>Monto Retención</th>
					<th>Fecha de Comprobante</th>
					<th>Nro. de Comprobante</th>
					<th>Descripcion Comprobante</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($retencionimpositiva_arca as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{$data->nombreempresa}}</td>
					<td>{{$data->nombre}}</td>
					<td>{{$data->cuit}}</td>
					<td>{{$data->descripcionimpuesto}}</td>
					<td>{{date("d/m/Y", strtotime($data->fecharetencion ?? ''))}}</td>
					<td>{{$data->numerocertificado}}</td>
					<td>{{$data->montoretencion}}</td>
					<td>{{date("d/m/Y", strtotime($data->fechacomprobante ?? ''))}}</td>
					<td>{{$data->numerocomprobante}}</td>
					<td>{{$data->descripcioncomprobante}}</td>
				@endforeach
			</tbody>
		</table>
	</body>
</html>