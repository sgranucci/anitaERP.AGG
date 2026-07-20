@php
    use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
    $estados = Solicitud_Prenda_Sueldos::ESTADOS;
@endphp
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
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Solicitudes de indumentaria</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>#</th>
			<th>Fecha</th>
			<th>Legajo</th>
			<th>Empleado</th>
			<th>Estado</th>
			<th>Nivel</th>
			<th>Prendas</th>
			<th>Solicitante</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($solicitudes as $s)
			<tr>
				<td>{{ $s->id }}</td>
				<td>{{ optional($s->fecha)->format('d/m/Y') }}</td>
				<td>{{ optional($s->empleado)->legajo }}</td>
				<td>{{ optional($s->empleado)->nombre }}</td>
				<td>{{ $estados[$s->estado] ?? $s->estado }}</td>
				<td>{{ $s->estado === Solicitud_Prenda_Sueldos::PENDIENTE ? $s->nivel_actual : '' }}</td>
				<td>@foreach ($s->articulos as $a){{ optional($a->prenda)->descripcion }} {{ optional($a->color)->nombre }} {{ optional($a->talle)->nombre }} x{{ rtrim(rtrim(number_format((float)$a->cantidad,2,'.',''),'0'),'.') }}@if(! $loop->last); @endif @endforeach</td>
				<td>{{ optional($s->solicitante)->nombre }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
