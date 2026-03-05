<tr>
	<td>
		<img src="{{ asset('storage/imagenes/fotos_articulos/'.$foto[0]) }}" width="200" height="200" />
	</td>
	<td>
		@if (($foto[1] ?? '') != '')
			<img src="{{ asset('storage/imagenes/fotos_articulos/'.$foto[1]) }}" width="200" height="200" />
		@endif
	</td>
</tr>
<tr>
	<td>
		<small>
			{{$sku[0] ?? ''}}-{{$codigo[0] ?? ''}}
		</small>
		<font size=1>
			{{$nombre[0] ?? ''}}<br>
			@if ($precio1[0] != 0)
				{{ $nombrelista1[0] }} {{ number_format($precio1[0],2) }} 
			@endif
			@if ($precio2[0] != 0)
				{{ $nombrelista2[0] }} {{ number_format($precio2[0],2) }} 
			@endif
			@if ($precio3[0] != 0)
				{{ $nombrelista3[0] }} {{ number_format($precio3[0],2) }} 
			@endif
			@if ($precio4[0] != 0)
				{{ $nombrelista4[0] }} {{ number_format($precio4[0],2) }} 
			@endif
		</font>
	</td>
	<td>
		<small>
			{{$sku[1] ?? ''}}-{{$codigo[1] ?? ''}}
		</small>
		<font size=1>
			{{$nombre[1] ?? ''}}<br>
			@if ($precio1[1] != 0)
				{{ $nombrelista1[1] }} {{ number_format($precio1[1],2) }} 
			@endif
			@if ($precio2[1] != 0)
				{{ $nombrelista2[1] }} {{ number_format($precio2[1],2) }} 
			@endif
			@if ($precio3[1] != 0)
				{{ $nombrelista3[1] }} {{ number_format($precio3[1],2) }} 
			@endif
			@if ($precio4[1] != 0)
				{{ $nombrelista4[1] }} {{ number_format($precio4[1],2) }} 
			@endif
		</font>
	</td>
</tr>
