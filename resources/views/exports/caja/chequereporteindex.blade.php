@php
	use App\Support\Caja\ChequeDepositoComprobanteSupport;
	use App\Support\Caja\ChequeReporteSupport;
	$colspan = 13;
	$esRecibido = (($tipo ?? 'E') === 'R');
	$textoCero = static function ($valor): string {
		$t = trim((string) ($valor ?? ''));
		if ($t === '' || $t === '0' || preg_match('/^0+$/', $t)) {
			return '';
		}

		return $t;
	};
	/** Valor numérico puro para que Excel lo tome como número (no texto). */
	$importeExcel = static function ($valor): string {
		return number_format((float) $valor, 2, '.', '');
	};
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspan }}"><strong>{{ $titulo ?? 'Cheques' }}</strong></td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">{{ $subtitulo ?? '' }}</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Int.</th>
			<th>{{ $esRecibido ? 'Fec.Ing.' : 'Fec.Emis.' }}</th>
			<th>Fec.Che.</th>
			<th>Importe</th>
			<th>N.Cli.</th>
			<th>Cliente</th>
			<th>Destino</th>
			<th>Nro. cheque</th>
			<th>{{ $esRecibido ? 'Banco' : 'Cuenta' }}</th>
			<th>Suc</th>
			<th>Cta.libr.</th>
			<th>{{ $esRecibido ? 'N.rec.' : 'N.OP' }}</th>
			<th>Empresa</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas ?? [] as $fila)
			@if (($fila['tipo'] ?? '') === 'cheque')
				@php $data = $fila['cheque']; @endphp
				<tr>
					<td>{{ $data->nro_interno_anita }}</td>
					<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
					<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
					<td>{{ $importeExcel($data->monto) }}</td>
					<td>{{ ChequeReporteSupport::codigoCliente($data) }}</td>
					<td>{{ ChequeReporteSupport::nombreCliente($data) }}</td>
					<td>{{ ChequeReporteSupport::destino($data) }}</td>
					<td>{{ $data->numerocheque }}</td>
					<td>{{ ChequeReporteSupport::bancoOCuenta($data) }}</td>
					<td>{{ $textoCero($data->sucursalpago) }}</td>
					<td>{{ $textoCero($data->cuentalibradora) }}</td>
					<td>{{ ChequeReporteSupport::nroDocumentoOrigen($data) }}</td>
					<td>{{ $data->empresas->nombre ?? '' }}</td>
				</tr>
			@elseif (($fila['tipo'] ?? '') === 'total_dia')
				<tr>
					<td>{{ $fila['etiqueta'] ?? '' }}</td>
					<td></td>
					<td></td>
					<td>{{ $importeExcel($fila['monto'] ?? 0) }}</td>
					<td></td>
					<td>{{ (int) ($fila['cantidad'] ?? 0) }} cheq.</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			@elseif (($fila['tipo'] ?? '') === 'total_general')
				<tr>
					<td>{{ $fila['etiqueta'] ?? 'Total general' }}</td>
					<td></td>
					<td></td>
					<td>{{ $importeExcel($fila['monto'] ?? 0) }}</td>
					<td></td>
					<td>{{ (int) ($fila['cantidad'] ?? 0) }} cheq.</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			@endif
		@endforeach
	</tbody>
</table>
