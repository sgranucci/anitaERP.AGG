<!DOCTYPE html>
<html>
	<title>Cuenta Corriente de Clientes</title>
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
		<h2>Cuenta Corriente Cliente {{$nombrecliente}}</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Fecha</th>
					<th>Vencimiento</th>
					<th>Comprobante</th>
					<th>Moneda</th>
					<th style="width: 12%; text-align: right;">Debe</th>
					<th style="width: 12%; text-align: right;">Haber</th>
					<th style="width: 12%; text-align: right;">Saldo</th>
				</tr>
			</thead>
			<tbody>
				@php $saldo = 0; @endphp
				@foreach ($cuentacorriente as $data)
					@php $saldo += $data->total; @endphp
				<tr>
					<td>{{$data->id}}</td>
					<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
					<td>{{date("d/m/Y", strtotime($data->fechavencimiento ?? ''))}}</td>
					<td>{{$data->ventas->codigo}}</td>
					<td>{{$data->monedas->abreviatura}}</td>
					<td style="text-align: right;">
						@if ($data->total >= 0)
							{{number_format($data->total, 2)}}
						@endif
					</td>
					<td style="text-align: right;">
						@if ($data->total < 0)
							{{number_format(abs($data->total), 2)}}
						@endif
					</td>
					<td style="text-align: right;">
						{{number_format($saldo, 2)}}
					</td>
					<td>
						@if (can('editar-coeficiente', false))
							<a href="{{route('editar_coeficiente', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
							<i class="fa fa-edit"></i>
							</a>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>