@php
    use App\Support\Stock\ArticuloSaldosDepositoSupport;

    $depositos = $depositos ?? collect();
    $filasLista = $filas ?? [];
    if ($filasLista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasLista = $filasLista->items();
    }
    $tableClass = $table_class ?? 'table table-striped table-bordered table-hover mb-0';
    $tableId = empty($solo_thead_tbody) ? 'tabla-paginada' : '';
    $mostrarTotales = ! empty($totales) && empty($solo_thead_tbody);
@endphp
@if (empty($solo_thead_tbody))
    <table class="{{ $tableClass }}" @if ($tableId) id="{{ $tableId }}" @endif>
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>Categor&iacute;a</th>
            <th>Uso</th>
            <th>Tipo</th>
            @foreach ($depositos as $dep)
                <th class="text-right" title="{{ $dep->nombre }}">
                    {{ $dep->codigo }}
                </th>
            @endforeach
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasLista as $fila)
            @php
                $articuloId = (int) ($fila['articulo_id'] ?? 0);
                $saldos = $fila['saldos'] ?? [];
            @endphp
            <tr>
                <td>
                    @if (($puede_ver_articulo ?? false) && $articuloId > 0)
                        <a href="{{ route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">
                            {{ $fila['sku'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['categoria'] ?? '' }}</td>
                <td>{{ $fila['uso'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                @foreach ($depositos as $dep)
                    @php
                        $depId = (int) $dep->id;
                        $saldo = (float) ($saldos[$depId] ?? 0);
                    @endphp
                    <td class="text-right">
                        @if (abs($saldo) >= 0.0000001)
                            @if (! empty($exportar_numeros_excel))
                                {{ $saldo }}
                            @else
                                {{ ArticuloSaldosDepositoSupport::formatSaldo($saldo) }}
                            @endif
                        @endif
                    </td>
                @endforeach
                <td class="text-right">
                    @if (abs((float) ($fila['total'] ?? 0)) >= 0.0000001)
                        @if (! empty($exportar_numeros_excel))
                            {{ (float) ($fila['total'] ?? 0) }}
                        @else
                            {{ $fila['total_fmt'] ?? ArticuloSaldosDepositoSupport::formatSaldo((float) ($fila['total'] ?? 0)) }}
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 5 + $depositos->count() + 1 }}" class="text-center text-muted">
                    No hay art&iacute;culos para los filtros indicados.
                </td>
            </tr>
        @endforelse
        @if ($mostrarTotales && ($totales['totales_deposito'] ?? []) !== [])
            <tr style="background:#e8f4fc;font-weight:bold;">
                <td colspan="5">Totales</td>
                @foreach ($depositos as $dep)
                    @php
                        $depId = (int) $dep->id;
                        $saldoTotal = (float) (($totales['totales_deposito'][$depId] ?? 0));
                    @endphp
                    <td class="text-right">{{ ArticuloSaldosDepositoSupport::formatSaldo($saldoTotal) }}</td>
                @endforeach
                <td class="text-right">
                    {{ ArticuloSaldosDepositoSupport::formatSaldo((float) ($totales['total_general'] ?? 0)) }}
                </td>
            </tr>
        @endif
    </tbody>
@if (empty($solo_thead_tbody))
    </table>
@endif
