<h2> Reporte de Consumos de Cajas </h2>
<h1><strong>Ordenes de trabajo: {{$ordenestrabajo ?? '- - -'}}</strong>&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>
</h1>

<table>
	<thead>
	<tr>
		<th colspan="4">CAJAS PROPIAS</th>
		<th colspan="4">CAJAS ESPECIALES</th>
	</tr>
    <tr>
       	<th>Caja</th>
		<th>Medida</th>
		<th>Art&iacute;culo</th>
       	<th>Total</th>
    	<th>Caja</th>
		<th>Medida</th>
		<th>Art&iacute;culo</th>
       	<th>Total</th>
    </tr>
  	</thead>
    <tbody>
	@if (count($cajas) > count($cajasespeciales))
		@php $max = count($cajas); @endphp
	@else	
		@php $max = count($cajasespeciales); @endphp
	@endif

	@for ($i = 0; $i < $max; $i++)
		
		@include('exports.produccion.reporteconsumocaja.imprimeunrenglon')
			
	@endfor

	</tbody>
</table>
