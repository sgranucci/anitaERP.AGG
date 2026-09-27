@php
    use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
    $catalogo = EmpleadoSueldosListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? EmpleadoSueldosListadoColumnas::defaultsVisibles();
    $columnas = array_values(array_filter(
        $columnas,
        static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
    ));
    if ($columnas === []) {
        $columnas = EmpleadoSueldosListadoColumnas::defaultsVisibles();
    }
    $etiquetas = $etiquetasColumnas ?? [];
    $colspan = max(1, count($columnas));
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
			<td colspan="{{ $colspan }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de empleados</h2></td>
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
		@foreach ($datas as $data)
			<tr>
				@foreach ($columnas as $key)
					@php
						$metaCol = $catalogo[$key] ?? [];
						$attrCol = (string) ($metaCol['attr'] ?? $key);
						$crudo = $data->{$attrCol} ?? null;
					@endphp
					@if (($metaCol['type'] ?? '') === 'decimal' && $crudo !== null && $crudo !== '')
						<td>{{ (float) $crudo }}</td>
					@else
						<td>{{ EmpleadoSueldosListadoColumnas::valorCelda($data, $key) }}</td>
					@endif
				@endforeach
			</tr>
		@endforeach
	</tbody>
</table>
