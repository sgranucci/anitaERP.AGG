@php
    use App\Exports\Sueldos\ParametroSueldosListadoExport;
    use App\Models\Sueldos\Parametro_Sueldos;
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
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Par&aacute;metros de liquidaci&oacute;n</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Tipo</th>
			<th>Unidad</th>
			<th>Activo</th>
			<th>Valor vigente</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ Parametro_Sueldos::TIPOS[$data->tipo] ?? $data->tipo }}</td>
				<td>{{ $data->unidad }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
				<td>{{ ParametroSueldosListadoExport::valorVigenteEtiqueta($data) }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
