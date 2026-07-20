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
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Planificación de indumentaria</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Código</th>
			<th>Prenda</th>
			<th>EPP</th>
			<th>Empleados</th>
			<th>Cupo</th>
			<th>Entregado</th>
			<th>Pendiente</th>
			<th>Stock</th>
			<th>% Pedido</th>
			<th>Sugerido a comprar</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $f)
			<tr>
				<td>{{ $f['codigo'] }}</td>
				<td>{{ $f['descripcion'] }}</td>
				<td>{{ ! empty($f['es_seguridad']) ? 'Sí' : '' }}</td>
				<td>{{ $f['empleados'] }}</td>
				<td>{{ number_format((float) $f['cupo'], 2, '.', '') }}</td>
				<td>{{ number_format((float) $f['entregado'], 2, '.', '') }}</td>
				<td>{{ number_format((float) $f['pendiente'], 2, '.', '') }}</td>
				<td>{{ number_format((float) $f['stock'], 2, '.', '') }}</td>
				<td>{{ number_format((float) $f['porcentaje_pedido'], 2, '.', '') }}</td>
				<td>{{ $f['sugerido'] }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
