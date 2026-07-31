<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="12" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="12"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de cuentas de caja</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Desc. operaciones</th>
			<th>C&oacute;digo</th>
			<th>Tipo cuenta</th>
			<th>Banco</th>
			<th>Empresa</th>
			<th>Cuenta contable</th>
			<th>Moneda</th>
			<th>CBU</th>
			<th>Cuenta Interbanking</th>
			<th>Usos</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->descripcion_operaciones }}</td>
				<td>{{ $data->codigo }}</td>
				<td>
					@foreach($tipocuenta_enum as $tipocuenta)
						@if ($tipocuenta['valor'] == $data->tipocuenta)
							{{ $tipocuenta['nombre'] }}
						@endif
					@endforeach
				</td>
				<td>{{ $data->bancos->nombre ?? '' }}</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ $data->cuentacontables->codigo ?? '' }}-{{ $data->cuentacontables->nombre ?? '' }}</td>
				<td>{{ $data->monedas->nombre ?? '' }}</td>
				<td>{{ $data->cbu }}</td>
				<td>{{ $data->cuenta_interbanking }}</td>
				<td>{{ $data->usocuentacajas->pluck('nombre')->implode(', ') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
