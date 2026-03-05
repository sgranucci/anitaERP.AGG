<tr>
	<td>{{$articulo['sku']}}</td>
	<td>{{$articulo['descripcion']}}</td>
	<td>{{$articulo['marca']}}</td>
	<td>{{$articulo['categoria']}}</td>
	@foreach ($listasprecio as $lista)
		@php $flImprimio = false; @endphp
		@foreach ($articulo['precios'] as $precio)
			
			@if ($precio['listaprecio_id'] == $lista['id'])
				<td align="right">{{number_format(floatval($precio['precio']), 2)}}</td>
				@php $flImprimio = true; @endphp
			@endif

		@endforeach
		@if (!$flImprimio)
			<td></td>
		@endif
	@endforeach
</tr>

