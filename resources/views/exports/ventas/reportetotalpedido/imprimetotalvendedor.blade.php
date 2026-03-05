
<tr>
 	<td>{{$vendedor_actual}}</td>
	<td>{{$nombre_actual}}</td>
    <td align="right">{{number_format(floatval($totv_pedido), 0)}}</td>
    <td align="right">{{number_format(floatval($totv_produccion), 0)}}</td>
    <td align="right">{{number_format(floatval($totv_facturado), 0)}}</td>
    <td align="right">{{number_format(floatval($totv_pendiente), 0)}}</td>
    <td align="right">
		@if ($totv_pedido != 0)
			{{number_format(floatval($totv_facturado/$totv_pedido*100.), 0)}}%
		@endif
	</td>
</tr>

