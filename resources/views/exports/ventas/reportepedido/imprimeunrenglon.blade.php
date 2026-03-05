<tr>
	<td>
		@if ($fl_primer_mov)
			{{$cliente_actual}}
		@endif
	</td>
	<td>
		@if ($fl_primer_mov)
			{{$nombre_actual}}
		@endif
		@php $fl_primer_mov = false; @endphp
	</td>
	<td>{{$tipo_actual}}&nbsp;{{$letra_actual}}{{$sucursal_actual}}{{-$numero_actual}}</td>
	@if (strpos($fecha_actual, '-') !== false)
		<td>{{date("d/m/Y", strtotime($fecha_actual))}}</td>
	@else
		<td>{{substr($fecha_actual,6,2)}}-{{substr($fecha_actual,4,2)}}-{{substr($fecha_actual,0,4)}}</td>
	@endif
	<td align="right">
		{{number_format(floatval($totp_pedido), 0)}}
	</td>
	<td align="right">
		{{number_format(floatval($totp_produccion), 0)}}
	</td>
	<td align="right">
		{{number_format(floatval($totp_facturado), 0)}}
	</td>
	<td align="right">
		{{number_format(floatval($totp_pendiente), 0)}}
	</td>
	<td> </td>
</tr>
