@php
	use App\Support\Configuracion\CotizacionListadoColumnas;
	$totalColumnas = $totalColumnas ?? (2 + (($monedasColumnas ?? collect())->count() * 2));
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $totalColumnas }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $totalColumnas }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de cotizaciones</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Fecha</th>
			@foreach ($monedasColumnas as $moneda)
				<th>{{ $moneda->nombre }}</th>
				<th></th>
			@endforeach
		</tr>
		<tr>
			<th></th>
			<th></th>
			@foreach ($monedasColumnas as $moneda)
				<th>Compra</th>
				<th>Venta</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$mapa = CotizacionListadoColumnas::mapaPorMoneda($data);
			@endphp
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('d/m/Y') : '' }}</td>
				@foreach ($monedasColumnas as $moneda)
					@php
						$vals = $mapa[(int) $moneda->id] ?? ['compra' => null, 'venta' => null];
					@endphp
					<td>{{ CotizacionListadoColumnas::formatear($vals['compra']) }}</td>
					<td>{{ CotizacionListadoColumnas::formatear($vals['venta']) }}</td>
				@endforeach
			</tr>
		@endforeach
	</tbody>
</table>
