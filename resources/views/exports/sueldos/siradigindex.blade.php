<?php use App\Support\Sueldos\SiradigTablas; ?>
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
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">SiRADIG - F572 (deducciones Ganancias)</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Per&iacute;odo</th>
			<th>Secci&oacute;n</th>
			<th>Nro</th>
			<th>Fecha pres.</th>
			<th>CUIL</th>
			<th>Empleado</th>
			<th>Empresa</th>
			<th>Agente retenci&oacute;n</th>
			<th>Vigente</th>
			<th>Deducciones ($)</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $p)
			@php
				$totalDed = $p->conceptos->where('grupo', SiradigTablas::GRUPO_DEDUCCION)->sum('monto_total');
			@endphp
			<tr>
				<td>{{ $p->periodo }}</td>
				<td>{{ $p->seccion }}</td>
				<td>{{ $p->nro_presentacion }}</td>
				<td>{{ optional($p->fecha_presentacion)->format('d/m/Y') }}</td>
				<td>{{ $p->empleado_cuit }}</td>
				<td>{{ trim(($p->empleado_apellido ?? '').' '.($p->empleado_nombre ?? '')) }}</td>
				<td>{{ $p->nombreempresa ?? optional($p->empresa)->nombre }}</td>
				<td>{{ $p->agente_retencion_denominacion }}</td>
				<td>{{ $p->vigente ? 'S&iacute;' : 'No' }}</td>
				<td>{{ number_format((float) $totalDed, 2, ',', '.') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
