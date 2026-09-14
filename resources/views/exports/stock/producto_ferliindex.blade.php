@php
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="6" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de artículos</h2></td>
		</tr>
		<tr>
			<td colspan="6">Generado {{ date('d/m/Y H:i') }}@if ($totalFilas > 0) — Registros: {{ $totalFilas }}@endif</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Código</th>
			<th>Descripción</th>
			<th>Categoría</th>
			<th>Marca</th>
			<th>Línea</th>
			<th>Facturable</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->stkm_articulo ?? '' }}</td>
				<td>{{ $data->stkm_desc ?? '' }}</td>
				<td>{{ $data->stkm_agrupacion ?? '' }}</td>
				<td>{{ $data->stkm_marca ?? '' }}</td>
				<td>{{ $data->stkm_linea ?? '' }}</td>
				<td>{{ ($data->nofactura ?? '') == '0' ? 'Facturable' : 'No facturable' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
