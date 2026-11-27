@php
    use App\Support\Compras\AplicacionCcProveedorComprobanteListadoColumnas;
    $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? AplicacionCcProveedorComprobanteListadoColumnas::defaultsVisibles();
    $etiquetas = $etiquetasColumnas ?? [];
    $colspan = max(1, count($columnas));
    $totales = [];
    foreach ($columnas as $key) {
        if (in_array($key, ['total', 'diferencia_cambio'], true)) {
            $totales[$key] = 0.0;
        }
    }
    foreach ($datas as $data) {
        foreach (array_keys($totales) as $key) {
            $totales[$key] += (float) (AplicacionCcProveedorComprobanteListadoColumnas::valorNumerico($data, $key) ?? 0);
        }
    }
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspan }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Comprobantes de aplicación CC proveedores</h2></td>
		</tr>
		@if (!empty($subtitulo))
			<tr>
				<td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
			</tr>
		@endif
	</tbody>
	<thead>
		<tr>
			@foreach ($columnas as $key)
				<th>{{ $etiquetas[$key] ?? ($catalogo[$key]['label'] ?? $key) }}</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
		@foreach ($filasSegmentadas as $filaVista)
			@if (($filaVista['type'] ?? '') === 'header')
				@php $fondoGrupo = (int) ($filaVista['nivel'] ?? 0) === 0 ? '#D6EAF8' : '#EAF2F8'; @endphp
				<tr>
					<td colspan="{{ $colspan }}" style="background-color: {{ $fondoGrupo }}; font-weight: bold;">
						{{ $filaVista['label'] ?? '' }}: {{ ($filaVista['valor'] ?? '') !== '' ? $filaVista['valor'] : '(vacío)' }}
						({{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }})
					</td>
				</tr>
			@else
				@php $data = $filaVista['row']; @endphp
				<tr>
					@foreach ($columnas as $key)
						@php $numero = AplicacionCcProveedorComprobanteListadoColumnas::valorNumerico($data, $key); @endphp
						@if ($numero !== null)
							<td>{{ $numero }}</td>
						@else
							<td>{{ AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($data, $key) }}</td>
						@endif
					@endforeach
				</tr>
			@endif
		@endforeach
		@if ($totales !== [])
			<tr>
				@foreach ($columnas as $i => $key)
					@if (array_key_exists($key, $totales))
						<td style="font-weight: bold;">{{ round($totales[$key], 2) }}</td>
					@elseif ($i === 0)
						<td style="font-weight: bold;">Total ({{ count($datas) }})</td>
					@else
						<td></td>
					@endif
				@endforeach
			</tr>
		@endif
	</tbody>
</table>
