<!DOCTYPE html>
<html>
	<title>Comprobantes de Ventas</title>
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
		<h2>Comprobantes de Ventas</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Fecha</th>
					<th>Comprobante</th>
					<th>Cliente</th>
					<th>Total</th>
				</tr>
			</thead>
			<tbody>
				@foreach($ventas as $comprobante)
				<tr data-entry-id="{{ $comprobante->id }}">
					<td>
						{{ $comprobante->id ?? '' }}
					</td>
					<td>
						{{date("d/m/Y", strtotime($comprobante->fecha ?? ''))}} 
					</td>
					<td>
						{{ $comprobante->tipotransacciones->nombre ?? '' }}&nbsp;
						{{ $comprobante->clientes->condicionivas->letra ?? '' }}
						{{ $comprobante->puntoventas->codigo }}-{{ $comprobante->numerocomprobante }}
					</td>
					<td>
						{{ $comprobante->clientes->nombre ?? '' }}
					</td>
					<td>
						{{ number_format($comprobante->total, 2) }}
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>