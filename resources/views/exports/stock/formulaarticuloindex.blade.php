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
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">F&oacute;rmulas de art&iacute;culos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID f&oacute;rmula</th>
			<th>C&oacute;digo f&oacute;rmula</th>
			<th>ID art&iacute;culo</th>
			<th>SKU art&iacute;culo</th>
			<th>Descripci&oacute;n art&iacute;culo</th>
			<th>Cant. unidad</th>
			<th>Estado</th>
			<th>Detalle cabecera</th>
			<th>Usuario alta</th>
			<th>&Iacute;tems (l&iacute;neas)</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($formulas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->articulo_id }}</td>
				<td>{{ $data->articulo_sku ?? '' }}</td>
				<td>{{ $data->articulo_descripcion ?? '' }}</td>
				<td>{{ number_format((float) ($data->cantidadunidad ?? 0), 2, ',', '.') }}</td>
				<td>{{ $data->estado }}</td>
				<td>{{ $data->detalle }}</td>
				<td>{{ $data->nombreusuario ?? '' }}</td>
				<td>@include('stock.formula_articulo.partials.export_lineas', ['data' => $data, 'separator' => "\n", 'enlaces' => false])</td>
			</tr>
		@endforeach
	</tbody>
</table>
