<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de usuarios de vianda</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Empresa</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
			<th>Centro de costo</th>
			<th>Tipo</th>
			<th>Tipo men&uacute;</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td>{{ $data->codigo_usuario }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->centrocosto ? trim($data->centrocosto->codigo.' - '.$data->centrocosto->nombre, ' -') : '' }}</td>
				<td>{{ \App\Support\Ventas\ViandaUsuarioTipoSupport::etiqueta($data->tipo_usuario) }}</td>
				<td>{{ $data->tipoMenu->nombre ?? '' }}</td>
				<td>{{ $data->etiquetaEstado() }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
