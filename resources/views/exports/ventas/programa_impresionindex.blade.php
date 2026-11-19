@php
	$esElBierzoExport = \App\Support\Configuracion\EntornoEmpresaSupport::esElBierzo();
	$colspanExport = $esElBierzoExport ? 10 : 9;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $colspanExport }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspanExport }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de programas de impresión</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Empresa</th>
			<th>Formularios</th>
			<th>Reglas</th>
			<th>Disparo al grabar</th>
			<th>Auto impresora al facturar</th>
			<th>Plan con envíos</th>
			@if ($esElBierzoExport)
			<th>Pedido c/coef.</th>
			@endif
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->empresa->nombre ?? 'Todas' }}</td>
				<td>{{ $data->formularios_count ?? '' }}</td>
				<td>{{ $data->reglas_count ?? '' }}</td>
				<td>{{ $data->permite_disparo_al_grabar ? 'Sí' : 'No' }}</td>
				<td>{{ ($data->enviar_automatico_al_facturar ?? true) ? 'Sí' : 'No' }}</td>
				<td>{{ ($data->plan_con_envios ?? false) ? 'Sí' : 'No' }}</td>
				@if ($esElBierzoExport)
				<td>{{ ($data->pedido_solo_si_cliente_divide ?? true) ? 'Sí' : 'No' }}</td>
				@endif
			</tr>
		@endforeach
	</tbody>
</table>
