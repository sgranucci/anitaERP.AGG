<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="12" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="12"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de cheques</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>N&uacute;mero</th>
			<th>Int. Anita</th>
			<th>Origen</th>
			<th>Estado</th>
			<th>Fecha emisi&oacute;n</th>
			<th>Fecha pago</th>
			<th>Cuenta / Banco</th>
			<th>Empresa</th>
			<th>Monto</th>
			<th>Moneda</th>
			<th>Beneficiario</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$origenLabel = collect($origen_enum ?? [])->firstWhere('valor', $data->origen);
				$estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
			@endphp
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->numerocheque }}</td>
				<td>{{ $data->nro_interno_anita }}</td>
				<td>{{ $origenLabel['nombre'] ?? $data->origen }}</td>
				<td>{{ $estadoLabel['nombre'] ?? $data->estado }}</td>
				<td>{{ $data->fechaemision }}</td>
				<td>{{ $data->fechapago }}</td>
				<td>
					@if (($data->origen ?? '') === 'E')
						{{ $data->cuentacajas->nombre ?? '' }}
					@else
						{{ $data->bancos->nombre ?? '' }}
					@endif
				</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
				<td>{{ $data->monedas->abreviatura ?? ($data->monedas->nombre ?? '') }}</td>
				<td>{{ $data->entregado ?? $data->anombrede }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
