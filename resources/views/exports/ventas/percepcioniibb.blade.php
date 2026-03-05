<h2> Control de Percepciones de IIBB </h2>
<table> 
	<thead>
    <tr>
       	<th>Fecha</th>
       	<th>Tipo</th>
       	<th>Numero</th>
       	<th>Cliente</th>
       	<th>CUIT</th>
       	<th>Total gravado</th>
       	<th>Perc. facturada CABA</th>
       	<th>Tasa padron CABA</th>
       	<th>Perc. padron CABA</th>
       	<th>Perc. facturada BSAS</th>
       	<th>Tasa padron BSAS</th>
       	<th>Perc. padron BSAS</th>
    </tr>
  	</thead>
    <tbody>
    @foreach ($comprobantes as $data)
        <tr>
           	<td>{{substr($data->fecha,6,2)}}-{{substr($data->fecha,4,2)}}-{{substr($data->fecha,0,4)}}</td>
           	<td>{{$data->tipo}}</td>
           	<td>{{$data->letra}}{{$data->sucursal}}{{-$data->numero}}</td>
           	<td>{{$data->nombre}}</td>
           	<td>{{$data->cuit}}</td>
			@php
				if (substr($data->tipo, 0, 2) == "NC" || $data->tipo == "CIM")
					$coef = -1;
				else
					$coef = 1;

				$gravado = $data->gravado * $coef;
				if (is_numeric($data->monto_caba))
					$monto_caba = $data->monto_caba * $coef;
				else
					$monto_caba = 0;
				if (is_numeric($data->monto_bsas))
					$monto_bsas = $data->monto_bsas * $coef;
				else
					$monto_bsas = 0;
			@endphp
           	<td align="right">{{round(floatval($gravado), 2)}}</td>
           	<td align="right">{{round(floatval($monto_caba), 2)}}</td>
           	<td align="right">{{round(floatval($data->tasa_padron_caba), 2)}}</td>
			@php if (is_numeric($data->tasa_padron_caba))
					$monto_padron_caba = $gravado * $data->tasa_padron_caba / 100; 
				 else
				 	$monto_padron_caba = 0;
			@endphp
           	<td align="right">{{round($monto_padron_caba, 2)}}</td>
           	<td align="right">{{round(floatval($monto_bsas), 2)}}</td>
           	<td align="right">{{round(floatval($data->tasa_padron_bsas), 2)}}</td>
			@php if (is_numeric($data->tasa_padron_bsas))
					$monto_padron_bsas = $gravado * $data->tasa_padron_bsas / 100; 
				 else
					$monto_padron_bsas = 0;
			@endphp
           	<td align="right">{{round($monto_padron_bsas, 2)}}</td>
        </tr>
    @endforeach
	</tbody>
</table>
