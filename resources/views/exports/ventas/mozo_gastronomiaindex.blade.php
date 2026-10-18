@php
    $esCrown = $esCrown ?? false;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="4" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="4"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de mozos gastronomía</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Código Anita</th>
			<th>{{ $esCrown ? 'Sala' : 'Empresa' }}</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->codigo }}</td>
				<td>
					@if ($esCrown)
						{{ trim((optional($data->sala)->codigo ?? '').' '.(optional($data->sala)->nombre ?? '')) }}
					@else
						{{ optional($data->empresa)->nombre }}
					@endif
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
