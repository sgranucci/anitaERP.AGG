@php
    use App\Models\Stock\Depmae;
    use App\Support\Stock\ArticuloSaldosDepositoSupport;
    use App\Support\Stock\UnidadesCajaPiezaSupport;
    use Illuminate\Support\Str;

    $mostrarCajaPieza = UnidadesCajaPiezaSupport::mostrarEnExistencias();
    $sepUnidades = ! empty($exportar_numeros_excel) ? "\n" : '<br>';

    $depositos = $depositos ?? collect();
    $puedeVerKardex = (bool) ($puede_ver_kardex ?? false);
    $columnasFijas = 5 + ($puedeVerKardex ? 1 : 0);
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
            @if ($puedeVerKardex)
                <th class="text-center" style="width:2.5rem;">Kardex</th>
            @endif
            @foreach ($depositos as $dep)
                @php
                    $etiquetaDep = Depmae::etiquetaDesdePartes(
                        (string) ($dep->codigo ?? ''),
                        (string) ($dep->nombre ?? ''),
                        (int) ($dep->id ?? 0)
                    );
                    $etiquetaCorta = Str::limit($etiquetaDep, 32);
                @endphp
                <th class="text-right" style="max-width:7rem;white-space:normal;font-size:0.85em;line-height:1.2;"
                    title="{{ $etiquetaDep }}">
                    {{ $etiquetaCorta }}
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
                @if ($puedeVerKardex)
                    <td class="text-center">
                        @if ($articuloId > 0)
                            <button type="button"
                                class="btn-accion-tabla btn-kardex-existencias-deposito"
                                title="Kardex de stock"
                                data-articulo-id="{{ $articuloId }}"
                                data-articulo-sku="{{ $fila['sku'] ?? '' }}"
                                data-articulo-descripcion="{{ $fila['descripcion'] ?? '' }}">
                                <i class="fa fa-list-alt text-info"></i>
                            </button>
                        @endif
                    </td>
                @endif
                @foreach ($depositos as $dep)
                    @php
                        $depId = (int) $dep->id;
                        $saldo = (float) ($saldos[$depId] ?? 0);
                        $caja = (float) (($fila['saldos_caja'][$depId] ?? 0));
                        $pieza = (float) (($fila['saldos_pieza'][$depId] ?? 0));
                        $tieneSaldo = abs($saldo) >= 0.0000001
                            || ($mostrarCajaPieza && (abs($caja) >= 0.0000001 || abs($pieza) >= 0.0000001));
                        $saldoFmt = $tieneSaldo
                            ? ($mostrarCajaPieza
                                ? ArticuloSaldosDepositoSupport::formatTriple($saldo, $caja, $pieza, $sepUnidades)
                                : ArticuloSaldosDepositoSupport::formatSaldo($saldo))
                            : '';
                    @endphp
                    <td class="text-right{{ ($puedeVerKardex && $tieneSaldo && $articuloId > 0) ? ' celda-saldo-kardex' : '' }}"
                        @if ($puedeVerKardex && $tieneSaldo && $articuloId > 0)
                            data-articulo-id="{{ $articuloId }}"
                            data-deposito-id="{{ $depId }}"
                            data-articulo-sku="{{ $fila['sku'] ?? '' }}"
                            data-articulo-descripcion="{{ $fila['descripcion'] ?? '' }}"
                            title="Ver kardex en {{ Depmae::etiquetaDesdePartes((string) ($dep->codigo ?? ''), (string) ($dep->nombre ?? ''), $depId) }}"
                            style="cursor:pointer;"
                        @endif>
                        @if ($tieneSaldo)
                            @if (! empty($exportar_numeros_excel) && ! $mostrarCajaPieza)
                                {{ $saldo }}
                            @elseif ($mostrarCajaPieza && empty($exportar_numeros_excel))
                                {!! $saldoFmt !!}
                            @else
                                {{ $saldoFmt }}
                            @endif
                        @endif
                    </td>
                @endforeach
                <td class="text-right">
                    @php
                        $totalKg = (float) ($fila['total'] ?? 0);
                        $totalCaja = (float) ($fila['total_caja'] ?? 0);
                        $totalPieza = (float) ($fila['total_pieza'] ?? 0);
                        $tieneTotal = abs($totalKg) >= 0.0000001
                            || ($mostrarCajaPieza && (abs($totalCaja) >= 0.0000001 || abs($totalPieza) >= 0.0000001));
                    @endphp
                    @if ($tieneTotal)
                        @if ($mostrarCajaPieza)
                            @if (! empty($exportar_numeros_excel))
                                {{ ArticuloSaldosDepositoSupport::formatTriple($totalKg, $totalCaja, $totalPieza, "\n") }}
                            @else
                                {!! ArticuloSaldosDepositoSupport::formatTriple($totalKg, $totalCaja, $totalPieza, '<br>') !!}
                            @endif
                        @elseif (! empty($exportar_numeros_excel))
                            {{ $totalKg }}
                        @else
                            {{ $fila['total_fmt'] ?? ArticuloSaldosDepositoSupport::formatSaldo($totalKg) }}
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $columnasFijas + $depositos->count() + 1 }}" class="text-center text-muted">
                    No hay art&iacute;culos para los filtros indicados.
                </td>
            </tr>
        @endforelse
        @if ($mostrarTotales && ($totales['totales_deposito'] ?? []) !== [])
            <tr style="background:#e8f4fc;font-weight:bold;">
                <td colspan="{{ $columnasFijas }}">Totales</td>
                @foreach ($depositos as $dep)
                    @php
                        $depId = (int) $dep->id;
                        $saldoTotal = (float) (($totales['totales_deposito'][$depId] ?? 0));
                        $cajaTotal = (float) (($totales['totales_deposito_caja'][$depId] ?? 0));
                        $piezaTotal = (float) (($totales['totales_deposito_pieza'][$depId] ?? 0));
                    @endphp
                    <td class="text-right">
                        @if ($mostrarCajaPieza)
                            {!! ArticuloSaldosDepositoSupport::formatTriple($saldoTotal, $cajaTotal, $piezaTotal, $sepUnidades) !!}
                        @else
                            {{ ArticuloSaldosDepositoSupport::formatSaldo($saldoTotal) }}
                        @endif
                    </td>
                @endforeach
                <td class="text-right">
                    @if ($mostrarCajaPieza)
                        {!! ArticuloSaldosDepositoSupport::formatTriple(
                            (float) ($totales['total_general'] ?? 0),
                            (float) ($totales['total_general_caja'] ?? 0),
                            (float) ($totales['total_general_pieza'] ?? 0),
                            $sepUnidades
                        ) !!}
                    @else
                        {{ ArticuloSaldosDepositoSupport::formatSaldo((float) ($totales['total_general'] ?? 0)) }}
                    @endif
                </td>
            </tr>
        @endif
    </tbody>
@if (empty($solo_thead_tbody))
    </table>
@endif
