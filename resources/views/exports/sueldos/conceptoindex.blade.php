<?php use App\Support\Sueldos\ConceptoTipo; ?>
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
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de conceptos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Tipo</th>
			<th>Momento</th>
			<th>Recibo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ ConceptoTipo::etiquetaTipo($data->tipo) }}</td>
				<td>{{ ConceptoTipo::etiquetaMomento($data->momento) }}</td>
				<td>{{ $data->va_recibo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
