<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr><td colspan="10" style="height: 52px;">&#160;</td></tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="10"><strong style="font-size: 16pt;">Sanciones de empleados</strong></td>
		</tr>
		<tr>
			<td colspan="10">Generado {{ date('d/m/Y H:i') }}@if (!empty($subtitulo)) — {{ $subtitulo }}@endif</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Legajo</th>
			<th>Nombre</th>
			<th>Fecha</th>
			<th>Tipo</th>
			<th>Motivo</th>
			<th>Días</th>
			<th>Recepción</th>
			<th>Estado</th>
			<th>Importe no cobrado</th>
			<th>Comentario</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $row)
			<tr>
				<td>{{ optional($row->empleado)->legajo }}</td>
				<td>{{ optional($row->empleado)->nombre }}</td>
				<td>{{ optional($row->fecha_hecho)->format('d/m/Y') }}</td>
				<td>{{ optional($row->tipo)->nombre }}</td>
				<td>{{ optional($row->motivo)->nombre }}</td>
				<td>{{ $row->cant_dias }}</td>
				<td>{{ optional($row->fecha_recepcion)->format('d/m/Y') }}</td>
				<td>{{ \App\Support\Sueldos\EmpleadoSancionSupport::etiquetaEstado($row->estado) }}</td>
				<td>{{ number_format((float) $row->importe_perdida, 2, ',', '.') }}</td>
				<td>{{ $row->comentario }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
