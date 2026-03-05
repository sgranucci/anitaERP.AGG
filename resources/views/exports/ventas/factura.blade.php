<h2> Comprobantes de Ventas </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Comprobante</th>
		<th>Cliente</th>
		<th>Total</th>
	</tr>
  	</thead>
    <tbody>
    @foreach($ventas as $comprobante)
        <tr>
			<td>
				{{ $comprobante->id ?? '' }}
			</td>
			<td>
				{{date("d/m/Y", strtotime($comprobante->fecha ?? ''))}} 
			</td>
			<td>
				{{ $comprobante->tipotransacciones->nombre ?? '' }}&nbsp;
				{{ $comprobante->clientes->condicionivas->letra ?? '' }}
				{{ $comprobante->puntoventas->codigo }}-{{ $comprobante->numerocomprobante }}
			</td>
			<td>
				{{ $comprobante->clientes->nombre ?? '' }}
			</td>
			<td>
				{{ number_format($comprobante->total, 2) }}
			</td>
		</tr>
    @endforeach
	</tbody>
</table>
