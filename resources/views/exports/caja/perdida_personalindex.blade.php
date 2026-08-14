<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="14" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="14"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de p&eacute;rdidas de personal</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>N&uacute;mero</th>
			<th>Fecha</th>
			<th>Empresa</th>
			<th>Empleado</th>
			<th>Supervisor</th>
			<th>Concepto</th>
			<th>Imputaci&oacute;n</th>
			<th>Centro costo</th>
			<th>Turno</th>
			<th>M&aacute;quina</th>
			<th>Importe</th>
			<th>Estado</th>
			<th>Leyenda</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $fila)
			<tr>
				<td>{{ $fila->id }}</td>
				<td>{{ $fila->numero }}</td>
				<td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
				<td>{{ $fila->empresa->nombre ?? '' }}</td>
				<td>
					@if ($fila->empleado)
						{{ $fila->empleado->legajo }} — {{ $fila->empleado->nombre }}
					@endif
				</td>
				<td>
					@if ($fila->supervisor)
						{{ $fila->supervisor->legajo }} — {{ $fila->supervisor->nombre }}
					@endif
				</td>
				<td>
					@if ($fila->conceptoPerdida)
						{{ $fila->conceptoPerdida->codigo }} — {{ $fila->conceptoPerdida->nombre }}
					@endif
				</td>
				<td>
					@if ($fila->imputacionPerdida)
						{{ $fila->imputacionPerdida->codigo }} — {{ $fila->imputacionPerdida->nombre }}
					@endif
				</td>
				<td>
					@if ($fila->centrocosto)
						{{ $fila->centrocosto->codigo }} — {{ $fila->centrocosto->nombre }}
					@endif
				</td>
				<td>{{ $fila->turno_label }}</td>
				<td>{{ $fila->maquina }}</td>
				<td>{{ number_format((float) $fila->importe, 2, '.', '') }}</td>
				<td>{{ $fila->estado_label }}</td>
				<td>{{ $fila->leyenda }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
