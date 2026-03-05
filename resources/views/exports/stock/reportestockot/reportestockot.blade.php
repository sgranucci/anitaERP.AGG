<h2> Stock por OT </h2>
<h1><strong>Estado de combinaciones: {{$estado}}</strong>&nbsp;Marca: {{$nombremarca}}</h1>
<h1><strong>Desde artículo: {{$desdearticulo}} Hasta articulo: {{$hastaarticulo}} </strong></h1>
<h1><strong>Desde línea: {{$desdelinea}} Hasta linea: {{$hastalinea}} </strong></h1>
<h1><strong>Desde categoría: {{$desdecategoria}} Hasta linea: {{$hastacategoria}} </strong></h1>
<h1><strong>Desde lote: {{$desdelote}} Hasta lote: {{$hastalote}} - {{$deposito}}</strong></h1>
<table>
	<thead>
		<tr>
			@if ($imprimefoto == 'CON_FOTO')
				<th>Foto</th>
			@endif
			<th>L&iacute;nea</th>
			<th>Art&iacute;culo</th>
			<th>Combinaci&oacute;n</th>
			<th>Descripci&oacute;n</th>
			<th>Pedido</th>
			<th>Ot</th>
			@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
				<th>{{$ii}}</th>
			@endfor
			<th>Mod</th>
			<th>M</th>
			<th>Total</th>
			@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
				<th>{{$ii}}</th>
			@endfor
			<th>Precio</th>
			<th>Situaci&oacute;n</th>
			<th>Numero OT (lote)</th>
		</tr>
	</thead>
	<tbody>
	@foreach ($data as $lote)

		@include('exports.stock.reportestockot.imprimeunrenglon')
			
	@endforeach

	</tbody>
</table>
