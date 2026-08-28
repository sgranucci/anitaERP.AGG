<!DOCTYPE html>
<html>
	<title>Pedidos de Ventas</title>
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
		<h2>Pedidos de Ventas</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Marca</th>
					<th>Pares</th>
					<th>Estado</th>
				</tr>
			</thead>
			<tbody>
				@foreach($pedidos as $pedido)
				<tr data-entry-id="{{ $pedido->id }}">
					<td>
						{{ $pedido->id ?? '' }}
					</td>
					<td>
						{{date("d/m/Y", strtotime($pedido->fecha ?? ''))}} 
					</td>
					<td>{{ $pedido->nombrecliente ?? '' }}</td>
					<td>{{ $pedido->pedido_combinaciones[0]->articulos->mventas->nombre ?? '' }}</td>
					<td>
						@php
							$pares = 0.;
						@endphp
						@foreach($pedido->pedido_combinaciones as $item)
							@php
								$pares += ($item->cantidad);
							@endphp
						@endforeach
						{{ $pares ?? '' }}
					</td>
					<td>
						{{ $pedido->estado }}
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>