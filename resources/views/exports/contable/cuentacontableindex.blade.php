@php
    use App\Support\Contable\CuentacontableArbolSupport;
    use App\Support\Contable\CuentacontableCentrosAsociadosSupport;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="11" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="11"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Plan de cuentas</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Empresa</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
			<th>Nivel</th>
			<th>Tipo</th>
			<th>Rubro</th>
			<th>C.Costo</th>
			<th>Centros asociados</th>
			<th>Concepto</th>
			<th>Inhabilitada</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ CuentacontableArbolSupport::formatearCodigo((string) $data->codigo) }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->nivel }}</td>
				<td>{{ CuentacontableArbolSupport::etiquetaTipo((string) $data->tipocuenta) }}</td>
				<td>{{ $data->rubrocontables->nombre ?? '' }}</td>
				<td>{{ $data->manejaccosto == 'S' ? 'Sí' : 'No' }}</td>
				<td>{{ CuentacontableCentrosAsociadosSupport::desdeCuenta($data) }}</td>
				<td>{{ $data->conceptogastos->nombre ?? '' }}</td>
				<td>{{ ! empty($data->inhabilitada) ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
