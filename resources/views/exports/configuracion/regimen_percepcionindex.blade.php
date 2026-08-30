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
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Regímenes de percepción</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Agente</th>
			<th>Alícuota</th>
			<th>Mín. gravado</th>
			<th>Mín. percepción</th>
			<th>Vigencia</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->habilitado ? 'Sí' : 'No' }}</td>
				<td>{{ number_format((float) $data->tasa, 2, ',', '.') }}</td>
				<td>{{ number_format((float) $data->minimo_base, 2, ',', '.') }}</td>
				<td>{{ number_format((float) $data->minimo_importe, 2, ',', '.') }}</td>
				<td>
                    @if ($data->vigencia_desde){{ $data->vigencia_desde->format('d/m/Y') }}@endif
                    @if ($data->vigencia_hasta) — {{ $data->vigencia_hasta->format('d/m/Y') }}@endif
                </td>
			</tr>
		@endforeach
	</tbody>
</table>
