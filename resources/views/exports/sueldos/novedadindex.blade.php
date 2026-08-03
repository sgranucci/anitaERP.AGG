@php
    use App\Support\Sueldos\NovedadSueldosCatalogo;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="13" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="13"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Novedades de liquidaci&oacute;n</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Empresa</th>
			<th>Corrida</th>
			<th>Per&iacute;odo</th>
			<th>Legajo</th>
			<th>Empleado</th>
			<th>Concepto</th>
			<th>Valor 1</th>
			<th>Valor 2</th>
			<th>Estado</th>
			<th>Vigencia</th>
			<th>Origen</th>
			<th>Vto.</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombreempresa ?? optional($data->empresa)->nombre }}</td>
				<td>{{ optional($data->liquidacion)->numero }}</td>
				<td>{{ $data->periodo }}</td>
				<td>{{ optional($data->empleado)->legajo }}</td>
				<td>{{ optional($data->empleado)->nombre }}</td>
				<td>{{ optional($data->concepto)->codigo }} — {{ optional($data->concepto)->descripcion }}</td>
				<td>{{ $data->valor1 }}</td>
				<td>{{ $data->valor2 }}</td>
				<td>{{ NovedadSueldosCatalogo::etiquetaEstado($data->estado) }}</td>
				<td>
					@if ($data->fecha_desde)
						{{ \Illuminate\Support\Carbon::parse($data->fecha_desde)->format('d/m/Y') }} — {{ $data->fecha_hasta ? \Illuminate\Support\Carbon::parse($data->fecha_hasta)->format('d/m/Y') : '∞' }}
					@else
						one-shot
					@endif
				</td>
				<td>{{ NovedadSueldosCatalogo::etiquetaOrigen($data->origen) }}</td>
				<td>{{ $data->fecha_vto ? \Illuminate\Support\Carbon::parse($data->fecha_vto)->format('d/m/Y') : '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
