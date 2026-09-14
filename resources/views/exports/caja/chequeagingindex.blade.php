<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="10" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Aging cheques en cartera</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>N&uacute;mero</th>
			<th>Int.</th>
			<th>Pago</th>
			<th>D&iacute;as</th>
			<th>Bucket</th>
			<th>Monto</th>
			<th>Banco</th>
			<th>Cliente</th>
			<th>Empresa</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $f)
			<tr>
				<td>{{ $f['id'] }}</td>
				<td>{{ $f['numerocheque'] }}</td>
				<td>{{ $f['nro_interno_anita'] }}</td>
				<td>{{ $f['fechapago'] }}</td>
				<td>{{ $f['dias'] }}</td>
				<td>{{ $f['bucket_label'] }}</td>
				<td>{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
				<td>{{ $f['banco'] }}</td>
				<td>{{ $f['cliente'] }}</td>
				<td>{{ $f['empresa'] }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
