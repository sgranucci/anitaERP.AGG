<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="21" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="21"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Requisiciones</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Número</th>
			<th>Fecha</th>
			<th>Fecha entrega</th>
			<th>Empresa</th>
			<th>Centro costo</th>
			<th>Oficina compra</th>
			<th>Cód. proveedor</th>
			<th>Proveedor</th>
			<th>Forma de pago</th>
			<th>Mon. cab.</th>
			<th>Monto</th>
			<th>Tratamiento</th>
			<th>Motivo tratamiento</th>
			<th>Contratación directa</th>
			<th>Estado</th>
			<th>Usuario alta</th>
			<th>Comentario</th>
			<th>Detalle cabecera</th>
			<th>Nro inscripción</th>
			<th>Ítems (detalle líneas)</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($requisicion as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->numerorequisicion }}</td>
				<td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
				<td>{{ $data->fechaentrega ? date('d/m/Y', strtotime($data->fechaentrega)) : '' }}</td>
				<td>{{ $data->nombreempresa }}</td>
				<td>{{ trim(($data->codigocentrocosto ?? '').' '.$data->nombrecentrocosto) }}</td>
				<td>{{ $data->nombreoficinacompra ?? '' }}</td>
				<td>{{ $data->codigoproveedor ?? '' }}</td>
				<td>{{ $data->nombreproveedor ?? '' }}</td>
				<td>{{ $data->nombreformapago ?? '' }}</td>
				<td>{{ $data->monedacabecera_abreviatura ?? '' }}</td>
				<td>{{ (float) ($data->monto ?? 0) }}</td>
				<td>{{ $data->tratamiento }}</td>
				<td>{{ $data->motivotratamiento ?? '' }}</td>
				<td>{{ $data->contrataciondirecta ?? '' }}</td>
				<td>{{ $data->estado }}</td>
				<td>{{ $data->nombreusuario }}</td>
				<td>{{ $data->comentario }}</td>
				<td>{{ $data->detalle }}</td>
				<td>{{ $data->nroinscripcion ?? '' }}</td>
				<td>@include('compras.requisicion.partials.export_items_detalle', ['data' => $data, 'separator' => "\n"])</td>
			</tr>
		@endforeach
	</tbody>
</table>
