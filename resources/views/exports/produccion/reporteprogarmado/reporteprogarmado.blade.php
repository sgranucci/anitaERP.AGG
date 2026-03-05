<h2> Reporte de Programaci&oacute;n de armado</h2>
<h1><strong>Fecha {{ date("d/m/Y", strtotime($fecha)) }}</strong></h1>
<table>
	<thead>
    <tr>
       	<th>Orden</th>
		<th>Nro.OT</th>
		<th>L&iacute;nea</th>
		<th>Art&iacute;culo</th>
		<th>Material</th>
		<th>Pares</th>
       	<th>Cliente</th>
		<th>Pl-Ar</th>
		<th>Pl-Vi</th>
		<th>Cord</th>
    </tr>
  	</thead>
    <tbody>
	
	@foreach ($data as $ot)
		
		@include('exports.produccion.reporteprogarmado.imprimeunrenglon')
			
	@endforeach

	</tbody>
</table>
