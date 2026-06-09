@php
    use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoVigenteSupport;
    $filasDetalle = ListaPrecioEstacionamientoVigenteSupport::filasExportDetalladas($datas);
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
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de listas de precios de estacionamiento</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID lista</th>
			<th>Empresa</th>
			<th>Categoría</th>
			<th>Moneda</th>
			<th>Ítem</th>
			<th>Precio vigente</th>
			<th>Vigente desde</th>
			<th>Cant. vigentes</th>
			<th>Últ. vigencia lista</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filasDetalle as $fila)
			<tr>
				<td>{{ $fila->lista_id }}</td>
				<td>{{ $fila->empresa }}</td>
				<td>{{ $fila->categoria }}</td>
				<td>{{ $fila->moneda }}</td>
				<td>{{ $fila->item_nombre }}</td>
				<td>
					@if ($fila->precio !== null && $fila->precio !== '')
						{{ number_format((float) $fila->precio, 2, '.', '') }}
					@endif
				</td>
				<td>
					@if (!empty($fila->fecha_vigencia_item))
						{{ \Carbon\Carbon::parse($fila->fecha_vigencia_item)->format('d/m/Y') }}
					@endif
				</td>
				<td>{{ $fila->precios_vigentes_count }}</td>
				<td>
					@if (!empty($fila->ultima_vigencia))
						{{ \Carbon\Carbon::parse($fila->ultima_vigencia)->format('d/m/Y') }}
					@endif
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
