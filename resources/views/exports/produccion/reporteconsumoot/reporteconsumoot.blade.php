<h2> Reporte de Consumos de OT </h2>
<h1><strong>Ordenes de trabajo: {{$ordenestrabajo ?? '- - -'}}</strong>&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>
</h1>
<table>
	<thead>
    <tr>
       	<th>Material</th>
       	<th>Total</th>
       	<th>Avio</th>
       	<th>Total</th>
	</tr>
  	</thead>
    <tbody>
	
	@php $maxItem = count($datacapellada) > count($dataavio) ? count($datacapellada) : count($dataavio); @endphp
	
	@for ($i = 0; $i < $maxItem; $i++)
		@php
			$materialCapellada = $i < count($datacapellada) ? $datacapellada[$i]['nombrematerial'] : '';
			$consumoCapellada = $i < count($datacapellada) ? $datacapellada[$i]['consumo'] : '';
			$materialAvio = $i < count($dataavio) ? $dataavio[$i]['nombrematerial'] : '';
			$consumoAvio = $i < count($dataavio) ? $dataavio[$i]['consumo'] : '';
		@endphp
		@include('exports.produccion.reporteconsumoot.imprimeunrenglon')
			
	@endfor

	</tbody>
</table>
