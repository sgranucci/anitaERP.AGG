<h2> Maestro de Clientes </h2>
<h1><strong>Desde: {{$desdecliente}} </strong>&nbsp;
	<strong>Hasta: {{$hastacliente}} </strong>&nbsp;
	<strong>Estado de clientes: {{$estado}}</strong>
</h1>
<h1><strong>Desde provincia: {{$descripcionprovinciadesde}} </strong>&nbsp;
	<strong>Hasta provincia: {{$descripcionprovinciahasta}}</strong>
</h1>
<table>
	<thead>
    <tr>
       	<th>Cliente</th>
       	<th>Nombre</th>
       	<th>Nombre de fantas&iacute;a</th>
       	<th>Direcc&oacute;n</th>
       	<th>Provincia</th>
       	<th>Localidad</th>
       	<th>Tel&eacute;fono</th>
		<th>E-mail</th>
       	<th>Estado</th>
		<th>Tipo de suspensi&oacute;n</th>
		<th>Leyenda</th>
    </tr>
  	</thead>
    <tbody>
	@foreach ($clientes as $data)
		@include('exports.ventas.reportecliente.imprimeunrenglon')
	@endforeach
	</tbody>
</table>
