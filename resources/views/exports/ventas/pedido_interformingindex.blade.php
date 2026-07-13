<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9">
				<h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de pedidos Interforming</h2>
				@if (!empty($subtituloFiltros))
					<div style="font-size: 9pt; color: #444;">{{ $subtituloFiltros }}</div>
				@endif
			</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Fecha</th>
			<th>Entrega</th>
			<th>Cliente</th>
			<th>O. Compra</th>
			<th>Estado</th>
			<th>Vendedor</th>
			<th>Expreso</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ optional($data->fecha)->format('d/m/Y') ?? substr((string) $data->fecha, 0, 10) }}</td>
				<td>{{ optional($data->fechaentrega)->format('d/m/Y') ?? substr((string) $data->fechaentrega, 0, 10) }}</td>
				<td>{{ trim(($data->clientes->codigo ?? '').' — '.($data->clientes->nombre ?? ''), " —") }}</td>
				<td>{{ $data->orden_compra }}</td>
				<td>{{ $data->etiquetaEstado() }}</td>
				<td>{{ $data->vendedores->nombre ?? '' }}</td>
				<td>{{ $data->transportes->nombre ?? '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
