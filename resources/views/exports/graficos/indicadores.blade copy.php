<h2> EWO - Pivot Fibonacci - VMA - CCI - XTL {{$especie}} </h2>
@if ($administracionposicion == 'B')
	<h3> Desde: {{date('d-m-Y', ceil($desdefecha/1000))}} {{$desdehora}} hasta: {{date('d-m-Y', ceil($hastafecha/1000))}} {{$hastahora}} Administración: Con filtro de tiempo de {{$tiempo}} minutos</h3>
@else
	<h3> Desde: {{date('d-m-Y', ceil($desdefecha/1000))}} {{$desdehora}} hasta: {{date('d-m-Y', ceil($hastafecha/1000))}} {{$hastahora}} Administración: Sin filtro de tiempo</h3>
@endif
<h3> Compresi&oacute;n: {{$compresiontxt}} MM Corta: {{$mmcorta}} MM Larga: {{$mmlarga}} Calculo Base: {{$calculobasetxt}} Largo Periodo VMA {{$largovma}} Largo Periodo CCI: {{$largocci}} Largo Periodo XTL: {{$largoxtl}} Umbral XTL: {{$umbralxtl}} SwingSize: {{$swingsize}} Cantidad Contratos: {{$cantidadcontratos}}</h3>
<table> 
	<thead>
    <tr>
       	<th>Fecha</th>
       	<th>Desde Hora</th>
		<th>Hasta Hora</th>
       	<th>Open</th>
       	<th>High</th>
       	<th>Low</th>
       	<th>Close</th>
       	<th>Volume</th>
		<th>EWO</th>
		<th>Banda Sup</th>
		<th>Banda Inf</th>
		<th>W4up1</th>
		<th>W4up2</th>
		<th>W4dw1</th>
		<th>W4dw2</th>
		<th>RF Lim</th>
		<th>RFE (Ext)</th>
		<th>RFE (Int)</th>
		<th>RFI (Ext)</th>
		<th>RFI (Int)</th>
		<th>PP1</th>
		<th>POC</th>
		<th>PP2</th>
		<th>SFI (Int)</th>
		<th>SFI (Ext)</th>
		<th>SFE (Int)</th>
		<th>SFE (Ext)</th>
		<th>SF Lim</th>
		<th>CCIA TRadj</th>
		<th>obb</th>
		<th>osb</th>
		<th>Reg.de volatilidad</th>
		<th>INERTIA</th>
		<th>Precio tipico</th>
		<th>Estado</th>
		<th>TQR Verde</th>
		<th>Stop TQR Verde </th>
		<th>Tgt TQR Verde </th>
		<th>TQR Rojo</th>
		<th>Stop TQR Rojo</th>
		<th>Tgt TQR Rojo</th>
		<th>Prov.min.</th>
		<th>Prov.max.</th>
		<th>Prov.ret.</th>
		<th>Barras</th>
		<th>Min</th>
		<th>Max</th>
		<th>Tendencia</th>
		<th>Trend bars</th>
		<th>Swing bars</th>
		<th>Swing bars prev.</th>
		<th>Pivot 0</th>
		<th>Pivot 1</th>
		<th>Pivot 2</th>
		<th>Pivot 3</th>
		<th>Pivot 4</th>
		<th>Retroceso</th>
		<th>Ext. T1</th>
		<th>Ext. T2</th>
		<th>Ext. T3</th>
		<th>Ext. T4</th>
		<th>Volumen</th>
		<th>Volumen x swing</th>
		<th>Setup</th>
		<th>T1 hit (b)</th>
		<th>T2 hit (b)</th>
		<th>T3 hit (b)</th>
		<th>T4 hit (b)</th>
		<th>ENTRADA</th>
		<th>E</th>
		<th>SL</th>
		<th>T1</th>
		<th>P</th>
		<th>EVENTO</th>
		<th>ZONA</th>
	</tr>
  	</thead>
    <tbody>
    @foreach ($comprobantes as $data)
        <tr>
           	<td>{{$data['fechastr']}}</td>
			<td>{{$data['horainicio']}}
           	<td>{{date('H:i:s', ceil($data['fecha']/1000))}}</td>
			<td align="right">{{number_format(floatval($data['open']), 2, ",", ".")}}</td>
           	<td align="right">{{number_format(floatval($data['high']), 2, ",", ".")}}</td>
           	<td align="right">{{number_format(floatval($data['low']), 2, ",", ".")}}</td>
           	<td align="right">{{number_format(floatval($data['close']), 2, ",", ".")}}</td>
           	<td align="right">{{number_format(floatval($data['volume']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['ewo']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['bandaSup']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['bandaInf']), 4, ",", ".")}}</td>	
			<td align="right">{{number_format(floatval($data['w4Up1']), 5, ",", ".")}}</td>	
			<td align="right">{{number_format(floatval($data['w4Up2']), 5, ",", ".")}}</td>	
			<td align="right">{{number_format(floatval($data['w4Dw1']), 5, ",", ".")}}</td>	
			<td align="right">{{number_format(floatval($data['w4Dw2']), 5, ",", ".")}}</td>	
			<td align="right">{{number_format(floatval($data['rfLim']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['rfeExt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['rfeInt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['rfiExt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['rfiInt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pp1']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['poc']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pp2']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['sfiInt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['sfiExt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['sfeInt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['sfeExt']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['sfLim']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['cciaTRadj']), 3, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['obb']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['osb']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['regimenVolatilidad']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['inertia']), 9, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['precioTipico']), 4, ",", ".")}}</td>
			<td align="right">{{$data['estado']}}</td>
			<td align="right">{{number_format(floatval($data['TQRVerde']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['stopTQRVerde']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['tgtTQRVerde']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['TQRRojo']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['stopTQRRojo']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['tgtTQRRojo']), 4, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['provMin']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['provMax']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['provRet']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['barras']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['min']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['max']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['tendencia']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['trendBars']), 0, ",", ".")}}</td>
			@if ($data['min'] != 0 || $data['max'] != 0)
				<td align="right">{{number_format(floatval($data['swingBars']), 0, ",", ".")}}</td>
				<td align="right">{{number_format(floatval($data['swingBarsPrev']), 0, ",", ".")}}</td>
			@else
				<td align="right">0</td>
				<td align="right">0</td>
			@endif
			<td align="right">{{number_format(floatval($data['pivot0']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pivot1']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pivot2']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pivot3']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['pivot4']), 2, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['retroceso']), 5, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['extT1']), 3, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['extT2']), 3, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['extT3']), 3, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['extT4']), 3, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['volumen']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['volumenPorSwing']), 0, ",", ".")}}</td>
			<td align="right">{{$data['setup']}}</td>
			<td align="right">{{number_format(floatval($data['t1Hit']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['t2Hit']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['t3Hit']), 0, ",", ".")}}</td>
			<td align="right">{{number_format(floatval($data['t4Hit']), 0, ",", ".")}}</td>
			<td align="left">{{$data['entrada']}}</td>
			<td align="right">{{$data['e']}}</td>
			<td align="right">{{$data['stoploss']}}</td>
			<td align="right">{{$data['t1']}}</td>
			<td align="right">{{$data['p']}}</td>
			<td align="left">{{$data['evento']}}</td>
			<td align="left">{{$data['zona']}}</td>
        </tr>
    @endforeach
	</tbody>
</table>
