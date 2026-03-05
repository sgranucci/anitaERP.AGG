<h2> Listas de Precios </h2>
<h1><strong>Estado de combinaciones: {{$estado}}</strong>&nbsp;Marca: {{$nombremarca}}</h1>
<h1><strong>Desde artículo: {{$desdearticulo}} Hasta articulo: {{$hastaarticulo}} </strong></h1>
<h1><strong>Desde categoría: {{$desdecategoria}} Hasta categoría: {{$hastacategoria}} - {{$nofactura}}</strong></h1>
<table>
	<thead>
		<tr>
			<th>Art&iacute;culo</th>
			<th>Descripci&oacute;n</th>
			<th>Marca</th>
			<th>Categoría</th>
			@foreach ($listasprecio as $lista)
				<th>{{$lista['nombre']}}</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
	@foreach ($data as $articulo)

		@include('exports.stock.reportelistaprecio.imprimeunrenglon')
			
	@endforeach

	</tbody>
</table>
