
<tr>
 	<td>TOTAL CLIENTE {{$cliente_actual}}-{{$nombre_actual}}</td>
	<td></td>
	<td></td>
	<td></td>
    <td align="right">{{number_format(floatval($totc_pedido), 0)}}</td>
    <td align="right">{{number_format(floatval($totc_produccion), 0)}}</td>
    <td align="right">{{number_format(floatval($totc_facturado), 0)}}</td>
    <td align="right">{{number_format(floatval($totc_pendiente), 0)}}</td>
    <td align="right">
		@if ($totc_pedido != 0)
			{{number_format(floatval($totc_facturado/$totc_pedido*100.), 0)}}%
		@endif
	</td>
</tr>

