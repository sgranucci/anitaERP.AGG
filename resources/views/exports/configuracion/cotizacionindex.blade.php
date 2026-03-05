<h2> Vouchers </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Cotizaciones</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($cotizaciones as $data)
			<tr data-entry-id="{{ $data->id }}">
				<td>{{$data->id}}</td>
				<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
				<td>
					<ul>
					@foreach($data->cotizacion_monedas as $moneda)
						<li>{{ $moneda->monedas->nombre }} Venta {{ number_format($moneda->cotizacionventa,4) }} Compra {{ number_format($moneda->cotizacioncompra,4) }}</li>
					@endforeach
					</ul>
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
