@php
    use App\Support\Configuracion\ProvinciaListadoFiltros;
@endphp
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
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de provincias</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Abrev.</th>
			<th>Juris.</th>
			<th>Código</th>
			<th>País</th>
			<th>Mínimo Coef. CM05</th>
			<th>Tasas por Condición IIBB</th>
			<th>Cuentas Contables</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->abreviatura }}</td>
				<td>{{ $data->jurisdiccion }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->paises->nombre ?? '' }}</td>
				<td>{{ $data->minimocoeficientecm05 }}</td>
				<td>{{ ProvinciaListadoFiltros::textoTasas($data) }}</td>
				<td>{{ ProvinciaListadoFiltros::textoCuentas($data) }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
