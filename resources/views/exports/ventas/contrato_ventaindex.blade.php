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
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Abonos / contratos de venta</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Cliente</th>
			<th>Concepto</th>
			<th>Estado</th>
			<th>Vigencia desde</th>
			<th>Vigencia hasta</th>
			<th>Precio</th>
			<th>Empresa</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->cliente->nombre ?? '' }}</td>
				<td>{{ $data->conceptoVenta->codigo ?? '' }} — {{ $data->conceptoVenta->nombre ?? '' }}</td>
				<td>{{ $data->estado }}</td>
				<td>{{ optional($data->vigencia_desde)->format('Y-m-d') }}</td>
				<td>{{ optional($data->vigencia_hasta)->format('Y-m-d') }}</td>
				<td>{{ $data->precio !== null ? number_format((float) $data->precio, 4, '.', '') : '' }}</td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
