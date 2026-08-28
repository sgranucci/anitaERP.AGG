@php
    use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Numerador fiscal local</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>PV</th>
			<th>Nombre</th>
			<th>Modo</th>
			<th>Tipo ARCA</th>
			<th>Serie</th>
			<th>Último</th>
			<th>Piso</th>
			<th>Próximo</th>
			<th>Máx. venta</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->puntoventa->codigo ?? $data->puntoventa_id }}</td>
				<td>{{ $data->puntoventa->nombre ?? '' }}</td>
				<td>{{ $data->puntoventa->modofacturacion ?? '' }}</td>
				<td>{{ str_pad((string) $data->codigo_afip, 3, '0', STR_PAD_LEFT) }}</td>
				<td>{{ TipotransaccionCodigoAfipSupport::etiqueta((int) $data->codigo_afip) }}</td>
				<td>{{ (int) $data->ultimo_numero }}</td>
				<td>{{ (int) $data->piso }}</td>
				<td>{{ (int) $data->proximo }}</td>
				<td>{{ $data->max_venta !== null ? (int) $data->max_venta : '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
