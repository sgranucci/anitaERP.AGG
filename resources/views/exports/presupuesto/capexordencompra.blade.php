@php
	$esExcel = ! empty($esExcel);
	$ordencompra = $ordencompra ?? collect();
	$subtitulo = 'Proyecto: '.($codigoproyecto ?? '').' · Generado '.date('d/m/Y H:i').' — '.(is_countable($ordencompra) ? count($ordencompra) : 0).' registro(s)';
	$formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
	$autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
	$fmtNum = function ($v, $dec = 2) use ($esExcel, $formatoNumero, $autoExcelNum) {
		$n = (float) $v;
		if ($esExcel && $autoExcelNum) {
			return number_format($n, $dec, '.', '');
		}
		if ($esExcel) {
			return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, $dec);
		}
		return number_format($n, $dec, ',', '.');
	};
@endphp
<table>
	@if (! empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Ordenes de Compra</h2></td>
		</tr>
		<tr>
			<td colspan="8"><strong>{{ $subtitulo }}</strong></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Fecha OC</th>
			<th>Nro. de OC</th>
			<th>Proveedor</th>
			<th>Mes</th>
			<th>Moneda</th>
			<th>Cotizaci&oacute;n</th>
			<th>Monto</th>
			<th>Detalle</th>
		</tr>
  	</thead>
    <tbody>
		@foreach ($ordencompra as $data)
			<tr>
				<td>{{\Carbon\Carbon::parse($data->fechaordencompra)->format('d-m-Y')}}</td>
				<td>{{$data->movp_tipo}}-{{$data->movp_nro}}</td>
				<td>{{$data->nombreproveedor ?? '' }}</td>
				<td>{{$data->mes ?? ''}}</td>
				<td>
					@switch($data->moneda_id)
					@case('1')
						@php $nombremoneda = 'PESOS'; @endphp
						@break;
					@case('2')
						@php $nombremoneda = 'DOLARES'; @endphp
						@break;						
					@case('3')
						@php $nombremoneda = 'EUROS'; @endphp
						@break;
					@default
						@php $nombremoneda = 'PESOS'; @endphp
						@break;
					@endswitch
					{{$nombremoneda}}
				</td>
				<td style="text-align: right;">{{ $fmtNum($data->cotizacion, 4) }}</td>
				<td style="text-align: right;">{{ $fmtNum($data->total, 2) }}</td>
				<td>{{$data->stkm_desc}}</td>
			</tr>
		@endforeach
	</tbody>
</table>
