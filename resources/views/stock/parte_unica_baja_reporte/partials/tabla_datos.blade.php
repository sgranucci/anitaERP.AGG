@php
    use App\Support\Stock\ArticuloParteUnicaEstados;
@endphp
<table class="table table-sm table-bordered table-hover" id="tabla-paginada">
    <thead>
        <tr style="background-color:#85C1E9;color:#17202A;">
            <th>NPU</th>
            <th>Estado</th>
            <th>SKU</th>
            <th>Art&iacute;culo</th>
            <th>Fecha alta</th>
            <th>Fecha baja</th>
            <th>Motivo baja</th>
            <th>Mov. stock</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas ?? [] as $fila)
            @php
                $art = $fila->articulos;
                $estado = $fila->estado ?? ArticuloParteUnicaEstados::ACTIVO;
            @endphp
            <tr>
                <td><strong>{{ $fila->numeroparte }}</strong></td>
                <td>
                    @if (ArticuloParteUnicaEstados::esBaja($estado))
                        <span class="badge badge-danger">{{ ArticuloParteUnicaEstados::etiqueta($estado) }}</span>
                    @else
                        <span class="badge badge-success">{{ ArticuloParteUnicaEstados::etiqueta($estado) }}</span>
                    @endif
                </td>
                <td>
                    @if (!empty($puede_ver_articulo) && $art)
                        <a href="{{ route('editar_articulo', ['id' => $art->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $art->sku }}</a>
                    @else
                        {{ $art->sku ?? '' }}
                    @endif
                </td>
                <td>{{ $art->descripcion ?? $art->nombre ?? '' }}</td>
                <td>{{ optional($fila->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ optional($fila->fecha_baja)->format('d/m/Y H:i') }}</td>
                <td>{{ $fila->motivo_baja ?? '' }}</td>
                <td>
                    @if (!empty($puede_ver_movimiento) && (int) ($fila->movimientostock_id ?? 0) > 0)
                        <a href="{{ route('editar_movimientostock', ['id' => $fila->movimientostock_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="text-primary" target="_blank" rel="noopener">#{{ $fila->movimientostock_id }}</a>
                    @else
                        {{ $fila->movimientostock_id ? '#'.$fila->movimientostock_id : '' }}
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-muted text-center">Sin registros para los filtros indicados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
