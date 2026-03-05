<tr>
 	<td>TOTAL FINAL</td>
	<td></td>
    <td align="right">{{number_format(floatval($totf_pedido), 0)}}</td>
    <td align="right">{{number_format(floatval($totf_produccion), 0)}}</td>
    <td align="right">{{number_format(floatval($totf_facturado), 0)}}</td>
    <td align="right">{{number_format(floatval($totf_pendiente), 0)}}</td>
    <td align="right">
		@if ($totf_pedido != 0)
			{{number_format(floatval($totf_facturado/$totf_pedido*100.), 0)}}%
		@endif
	</td>
</tr>
