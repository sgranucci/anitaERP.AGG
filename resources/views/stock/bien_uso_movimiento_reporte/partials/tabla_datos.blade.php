<table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Bien de uso</th>
            <th>Efecto</th>
            <th>SKU</th>
            <th>Art&iacute;culo</th>
            <th class="text-right">Cantidad</th>
            <th>Tipo trans.</th>
            <th>Mov. stock</th>
            <th>Transferencia</th>
            <th>Concepto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $row)
            @php
                $cantidad = (float) ($row->cantidad ?? 0);
                $efecto = \App\Support\Stock\BienUsoAsignacionSupport::etiquetaEfecto($cantidad);
                $bienLabel = \App\Support\Stock\BienUsoAsignacionSupport::etiquetaBien($row);
            @endphp
            <tr>
                <td>{{ $row->fecha ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y') : '' }}</td>
                <td>
                    @if (($puede_ver_bien_uso ?? false) && ! empty($row->bien_uso_id))
                        <a href="{{ route('editar_bien_uso', ['id' => $row->bien_uso_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">{{ $bienLabel }}</a>
                    @else
                        {{ $bienLabel }}
                    @endif
                </td>
                <td>
                    @if ($cantidad >= 0)
                        <span class="badge badge-success">{{ $efecto }}</span>
                    @else
                        <span class="badge badge-warning">{{ $efecto }}</span>
                    @endif
                </td>
                <td>{{ $row->sku }}</td>
                <td>
                    @if (($puede_ver_articulo ?? false) && ! empty($row->articulo_id))
                        <a href="{{ route('editar_articulo', ['id' => $row->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">{{ $row->articulo_descripcion }}</a>
                    @else
                        {{ $row->articulo_descripcion }}
                    @endif
                </td>
                <td class="text-right">{{ number_format(abs($cantidad), 4, ',', '.') }}</td>
                <td>{{ $row->tipo_transaccion }}</td>
                <td>{{ $row->movimiento_codigo }}</td>
                <td>{{ $row->transferencia_codigo ?? '—' }}</td>
                <td>{{ $row->concepto }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center text-muted">Sin movimientos para los filtros indicados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
