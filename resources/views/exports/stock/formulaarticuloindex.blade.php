<table>
	@if (!empty($reservarFilaLogoExcel))
		<tr>
			<td colspan="{{ max(1, count($columnasVisibles ?? [])) }}" style="height: 52px;">&#160;</td>
		</tr>
	@endif
	<tr>
		<td colspan="{{ max(1, count($columnasVisibles ?? [])) }}"><strong>Fórmulas de artículos</strong></td>
	</tr>
	<tr>
		<td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">Generado {{ date('d/m/Y H:i') }}</td>
	</tr>
	@if (trim($subtitulo ?? '') !== '')
		<tr>
			<td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">{{ $subtitulo }}</td>
		</tr>
	@endif
	<tr>
		<td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">Registros: {{ number_format($totalFilas ?? 0, 0, ',', '.') }}</td>
	</tr>
	<thead>
		<tr>
			@foreach ($columnasVisibles as $keyColumna)
				<th>{{ ($etiquetasColumnas ?? [])[$keyColumna] ?? $keyColumna }}</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
		@foreach ($formulas as $data)
			<tr>
				@foreach ($columnasVisibles as $keyColumna)
					@if ($keyColumna === 'cantidadunidad')
						<td>{{ number_format((float) ($data->cantidadunidad ?? 0), 2, '.', '') }}</td>
					@elseif ($keyColumna === 'items')
						<td>@include('stock.formula_articulo.partials.export_lineas', ['data' => $data, 'separator' => "\n", 'enlaces' => false])</td>
					@else
						<td>{{ \App\Support\Stock\FormulaArticuloListadoColumnas::valorCelda($data, $keyColumna) }}</td>
					@endif
				@endforeach
			</tr>
		@endforeach
	</tbody>
</table>
