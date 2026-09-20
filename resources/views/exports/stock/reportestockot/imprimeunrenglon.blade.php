<tr @if(!empty($lote['en_produccion'])) style="color:#FF0000;" @endif>
	@if ($imprimefoto == 'CON_FOTO')
		{{-- Celda reservada: la foto se embebe como Drawing en StockOtExport@AfterSheet --}}
		<td></td>
	@endif
	<td class="align-middle">{{$lote['nombrelinea']}}</td>
	<td>{{$lote['sku']}}</td>
	<td>{{$lote['codigo']}}</td>
	<td>{{$lote['nombrecombinacion']}}</td>
	<td>{{$lote['pedido']}}</td>
	<td>{{$lote['ordencompra']}}</td>
	@php $totalLineaPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php $flEncontro = false; @endphp
		@foreach($lote['medidas'] as $medida)
			@if ($ii == $medida['medida'])
				<td align="right">{{number_format(floatval($medida['cantidad']), 0)}}</td>
				@php 
					$totalLineaPares += $medida['cantidad']; 
					$flEncontro = true; 
				@endphp
			@endif
		@endforeach
		@if (!$flEncontro)
			<td></td>
		@endif
	@endfor
	@if ($lote['cantidadmodulo'] == 0)
		<td align="right">{{$totalLineaPares}}</td>
	@else
		<td align="right">{{$lote['cantidadmodulo']}}</td>
	@endif
	<td>
		@if ($lote['cantidadmodulo'] != 0)
			{{abs($totalLineaPares) / abs($lote['cantidadmodulo'])}}
		@else
			{{1}}
		@endif
	</td>
	<td align="right">{{$totalLineaPares}}</td>

	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php $flEncontro = false; @endphp
		@foreach($lote['modulo'] as $medida)
			@if ($ii == $medida['medida'])
				<td align="right">{{number_format(floatval($medida['cantidad']), 0)}}</td>
				@php 
					$flEncontro = true; 
				@endphp
			@endif
		@endforeach
		@if (!$flEncontro)
			<td></td>
		@endif
	@endfor

	<td>${{number_format($lote['precio'],2)}}</td>
    <td>{{$lote['situacion']}} </td>
	<td>{{$lote['lote']}}</td>
</tr>

