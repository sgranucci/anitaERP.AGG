<h2> Reporte de Combinaciones </h2>
<h1><strong>Estado de combinaciones: {{$estado}}</strong>&nbsp;Marca: {{$nombremarca}}</h1>
<h1><strong>Desde articulo: {{$desdearticulo}} Hasta articulo: {{$hastaarticulo}} </strong></h1>
<h1><strong>Desde linea: {{$desdelinea}} Hasta linea: {{$hastalinea}} </strong></h1>
<table>
	<thead>
    <tr>
       	<th>Sku</th>
		<th>Art&iacute;culo</th>
		<th>Combinaci&oacute;n</th>
		<th>Marca</th>
       	<th>Estado</th>
    </tr>
  	</thead>
    <tbody>
	
	@foreach ($data as $combinacion)
		
		@include('exports.stock.reportecombinacion.imprimeunrenglon')
			
	@endforeach

	</tbody>
</table>
