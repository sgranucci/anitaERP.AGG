@php use App\Support\Sueldos\ConceptoTipo; @endphp
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
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Acumuladores de liquidaci&oacute;n</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Tipos incluidos</th>
			<th>Signo</th>
			<th>Activo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$tiposTexto = collect($data->tipos_incluye ?? [])
					->map(fn ($t) => ConceptoTipo::etiquetaTipo($t))
					->implode(', ');
			@endphp
			<tr>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ $tiposTexto }}</td>
				<td>{{ (int) $data->signo === -1 ? '-1' : '+1' }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
