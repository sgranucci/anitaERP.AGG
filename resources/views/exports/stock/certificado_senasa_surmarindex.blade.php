<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="5" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado certificado SENASA Surmar</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Nº</th>
			<th>Fecha</th>
			<th>Estado</th>
			<th>Remito AFIP</th>
			<th>Ítems</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $item)
			@php
				$estado = (string) ($item->estado ?? '');
				$estadoLabel = match ($estado) {
					'BORRADOR' => 'Provisorio',
					'CONFIRMADO' => 'Confirmado',
					'ANULADO' => 'Anulado',
					default => ($estado !== '' ? $estado : '—'),
				};
			@endphp
			<tr>
				<td>{{ $item->etiqueta }}</td>
				<td>{{ optional($item->fecha)->format('d/m/Y') }}</td>
				<td>{{ $estadoLabel }}</td>
				<td>{{ $item->cod_remito ?: '' }}</td>
				<td>{{ $item->articulos_count ?? '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
