@php
	$esExcel = ! empty($esExcel);
	$cliente_uifs = $cliente_uifs ?? collect();
	$lineaGenerado = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($cliente_uifs) ? count($cliente_uifs) : 0).' registro(s)';
	$subtituloFiltros = trim((string) ($subtituloFiltros ?? ''));
	$fmtTextoExcel = function ($v) use ($esExcel) {
		$s = trim((string) ($v ?? ''));
		if ($s === '') {
			return '';
		}

		return $esExcel ? "\t".$s : $s;
	};
@endphp
<table>
	@if (! empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="11" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="11"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de clientes UIF</h2></td>
		</tr>
		<tr>
			<td colspan="11"><strong>{{ $lineaGenerado }}</strong></td>
		</tr>
		@if ($subtituloFiltros !== '')
			<tr>
				<td colspan="11"><strong>Filtros: {{ $subtituloFiltros }}</strong></td>
			</tr>
		@endif
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Origen</th>
			<th>Nombre</th>
			<th>Tipo doc.</th>
			<th>N&uacute;mero de doc.</th>
			<th>Domicilio</th>
			<th>Localidad</th>
			<th>Provincia</th>
			<th>Pa&iacute;s</th>
			<th>Tel&eacute;fono</th>
			<th>Email</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($cliente_uifs as $data)
			<tr>
				<td>{{ $fmtTextoExcel($data->id) }}</td>
				<td>{{ \App\Support\Uif\ClienteUifOrigenPcSupport::codigoOrigen((string) ($data->anita_origen ?? '')) }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->abreviaturatipodocumento }}</td>
				<td>{{ $fmtTextoExcel($data->numerodocumento) }}</td>
				<td>{{ $data->domicilio }}</td>
				<td>{{ $data->nombrelocalidad ?? '' }}</td>
				<td>{{ $data->nombreprovincia ?? '' }}</td>
				<td>{{ $data->nombrepais ?? '' }}</td>
				<td>{{ $fmtTextoExcel($data->telefono) }}</td>
				<td>{{ $data->email }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
