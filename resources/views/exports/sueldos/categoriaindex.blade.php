<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="5" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de categorías de sueldos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Origen de bases</th>
			<th>Bases vigentes</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$bases = $data->bases_vigentes ?? [];
				$textoBases = collect($bases)->map(function ($b) {
					return $b['nombrebase_codigo'].' '.$b['nombrebase_descripcion'].': '.$b['valor_fmt'].' (desde '.$b['fecha_vigencia_fmt'].')';
				})->implode("\n");
			@endphp
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ $origenLabels[$data->origen_bases] ?? $data->origen_bases }}</td>
				<td>{{ $textoBases }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
