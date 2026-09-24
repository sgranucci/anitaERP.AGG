<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Puntos de venta</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Código</th>
			<th>Empresa</th>
			<th>Domicilio</th>
			<th>Localidad</th>
			<th>Provincia</th>
			<th>Modo facturación</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ $data->domicilio }}</td>
				<td>{{ $data->localidades->nombre ?? '' }}</td>
				<td>{{ $data->provincias->nombre ?? '' }}</td>
				<td>{{ \App\Models\Ventas\Puntoventa::$enumModoFacturacion[$data->modofacturacion] ?? $data->modofacturacion }}</td>
				<td>{{ \App\Models\Ventas\Puntoventa::$enumEstado[$data->estado] ?? $data->estado }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
