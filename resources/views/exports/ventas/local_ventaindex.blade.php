<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="7" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="7"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de locales de venta</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Puntos de venta</th>
			<th>Depósito</th>
			<th>Lista</th>
			<th>Activo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$pvs = $data->puntoventas->isNotEmpty()
					? $data->puntoventas->map(fn ($p) => trim(($p->codigo ?? '').' '.($p->nombre ?? '')))->implode(', ')
					: trim(($data->puntoventa->codigo ?? '').' '.($data->puntoventa->nombre ?? ''));
			@endphp
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $pvs }}</td>
				<td>{{ trim(($data->deposito->codigo ?? '').' '.($data->deposito->nombre ?? '')) }}</td>
				<td>{{ $data->listaprecio->nombre ?? '' }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
