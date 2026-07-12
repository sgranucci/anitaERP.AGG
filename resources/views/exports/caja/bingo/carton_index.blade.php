<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de cartones de bingo</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
			<th>Precio</th>
			<th>L&iacute;neas</th>
			<th>Azar</th>
			<th>Empresa</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ number_format((float) $data->precio_unitario, 2, ',', '.') }}</td>
				<td>{{ $data->lineas }}</td>
				<td>
                    @if ($data->es_azar)
                        S&iacute;
                    @else
                        No
                    @endif
                </td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td>{{ $data->estado_label }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
