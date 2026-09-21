<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="11" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="11"><strong>{{ $titulo ?? 'Cheques' }}</strong></td>
		</tr>
		<tr>
			<td colspan="11">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		<tr>
			<td colspan="11">{{ $subtitulo ?? '' }}</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>N&uacute;mero</th>
			<th>Int. Anita</th>
			<th>Estado</th>
			<th>{{ $etiquetaFechaDoc ?? 'Emisi&oacute;n' }}</th>
			<th>Fecha de cheque</th>
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
				$estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
			@endphp
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->numerocheque }}</td>
				<td>{{ $data->nro_interno_anita }}</td>
				<td>{{ $estadoLabel['nombre'] ?? $data->estado }}</td>
				<td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
				<td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
				<td>
					@if (($data->origen ?? '') === 'E')
						{{ $data->cuentacajas->nombre ?? '' }}
					@else
						{{ $data->bancos->nombre ?? '' }}
					@endif
				</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
				<td>{{ $data->monedas->abreviatura ?? '' }}</td>
				<td>{{ $data->entregado ?? $data->anombrede }}</td>
			</tr>
		@endforeach
		@foreach ($totales ?? [] as $tot)
			<tr>
				<td colspan="8" style="text-align:right;font-weight:bold;">Total ({{ (int) ($tot->cantidad ?? 0) }})</td>
				<td style="font-weight:bold;">{{ number_format((float) ($tot->monto ?? 0), 2, ',', '.') }}</td>
				<td style="font-weight:bold;">{{ $tot->moneda ?? '' }}</td>
				<td></td>
			</tr>
		@endforeach
	</tbody>
</table>
