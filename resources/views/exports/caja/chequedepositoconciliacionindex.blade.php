<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="12" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="12"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Conciliaci&oacute;n dep&oacute;sitos CHT</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>N&uacute;mero</th>
			<th>Int.</th>
			<th>Dep&oacute;sito</th>
			<th>Acreditaci&oacute;n</th>
			<th>Boleta</th>
			<th>Monto</th>
			<th>Mon</th>
			<th>Banco</th>
			<th>Cliente</th>
			<th>Cuenta</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $f)
			<tr>
				<td>{{ $f['id'] }}</td>
				<td>{{ $f['numerocheque'] }}</td>
				<td>{{ $f['nro_interno_anita'] }}</td>
				<td>{{ $f['fecha_deposito'] }}</td>
				<td>{{ $f['fecha_acreditacion'] }}</td>
				<td>{{ $f['nro_boleta'] }}</td>
				<td>{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
				<td>{{ $f['moneda'] }}</td>
				<td>{{ $f['banco'] }}</td>
				<td>{{ $f['cliente'] }}</td>
				<td>{{ $f['cuenta'] }}</td>
				<td>{{ $f['estado_label'] }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
