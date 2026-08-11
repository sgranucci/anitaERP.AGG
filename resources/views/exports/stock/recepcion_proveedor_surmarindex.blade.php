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
			<td colspan="7"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado recepción Surmar</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Nº</th>
			<th>Fecha</th>
			<th>OC</th>
			<th>Proveedor</th>
			<th>Origen</th>
			<th>Estado</th>
			<th>Ítems</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $item)
			@php
				$origen = (string) ($item->origen_carga ?? '');
				$origenLabel = $origen === 'ANITA_IMPORT' ? 'Anita' : ($origen === 'SURMAR' ? 'ERP' : ($origen !== '' ? $origen : '—'));
				$estado = (string) ($item->estado ?? '');
				$estadoLabel = $estado === 'BORRADOR' ? 'Provisorio' : ($estado === 'CONFIRMADA' ? 'Confirmada' : ($estado !== '' ? $estado : '—'));
			@endphp
			<tr>
				<td>{{ $item->numerorecepcion }}</td>
				<td>{{ optional($item->fecha)->format('d/m/Y') }}</td>
				<td>{{ $item->numeroordencompra ?? '' }}</td>
				<td>{{ $item->nombreproveedor }}</td>
				<td>{{ $origenLabel }}</td>
				<td>{{ $estadoLabel }}</td>
				<td>{{ $item->recepcion_proveedor_articulos_count ?? '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
