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
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de solicitudes de pago</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>C&oacute;digo</th>
			<th>Fecha</th>
			<th>Concepto</th>
			<th>Proveedor / Beneficiario</th>
			<th>Monto</th>
			<th>Tratamiento</th>
			<th>Estado</th>
			<th>SP madre</th>
			<th>Cuotas pend.</th>
			<th>Empresa</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$estadoNombre = collect($estado_enum)->firstWhere('valor', $data->estado)['nombre'] ?? $data->estado;
				$tratNombre = collect($tratamiento_enum)->firstWhere('valor', $data->tratamiento)['nombre'] ?? $data->tratamiento;
			@endphp
			<tr>
				<td>{{ $data->codigo }}</td>
				<td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
				<td>{{ optional($data->conceptos)->nombre ?? '' }}</td>
				<td>{{ optional($data->proveedores)->nombre ?? ($data->beneficiario ?? '') }}</td>
				<td>{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
				<td>{{ $tratNombre }}</td>
				<td>{{ $estadoNombre }}</td>
				<td>{{ optional($data->madre)->codigo ?? '' }}</td>
				<td>{{ $data->cuotas_pendientes_count ?? 0 }}</td>
				<td>{{ $data->nombreempresa ?? (optional($data->empresas)->nombre ?? '') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
