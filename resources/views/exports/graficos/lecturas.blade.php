<h2> Lecturas /ESU22 </h2>
<h3> Desde: {{date('d-m-Y', ceil($desdefecha/1000))}} {{$desdehora}} hasta: {{date('d-m-Y', ceil($hastafecha/1000))}} {{$hastahora}} </h3>
<h3> Compresi&oacute;n: {{$compresiontxt}} </h3>
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
        </tr>
    @endforeach
	</tbody>
</table>
