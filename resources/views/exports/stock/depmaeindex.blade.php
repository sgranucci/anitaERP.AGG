@php use App\Models\Stock\Depmae; @endphp
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
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de dep&oacute;sitos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Descripci&oacute;n</th>
			<th>Empresa</th>
			<th>Tipo de dep&oacute;sito</th>
			<th>C&oacute;digo ANITA</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->empresas->nombre ?? '' }}</td>
				<td>{{ Depmae::etiquetaTipoDeposito($data->tipodeposito) }}</td>
				<td>{{ $data->codigo }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
