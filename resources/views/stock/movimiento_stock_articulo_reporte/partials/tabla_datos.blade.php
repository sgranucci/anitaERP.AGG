@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Stock\ArticuloConsultaDesdeModal;
    use App\Support\Stock\KardexMovimientoComprobanteSupport;

    $excel = ! empty($excel);
    $pdf = ! empty($pdf);
    $filasVista = $filas ?? [];
    if ($filasVista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasVista = $filasVista->items();
    }
    $puedeArticulo = ! $excel && ! $pdf && ($puede_ver_articulo ?? false);
    $colspan = 16;
    $fmt = function ($n) use ($excel) {
        if ($n === null || $n === '') {
            return '';
        }
        if ($excel) {
            return number_format((float) $n, 2, '.', '');
        }

        return number_format((float) $n, 2, ',', '.');
    };
    $logosCabecera = $pdf
        ? EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasVista))
        : [];
    $totalFilas = (int) ($totales['movimientos'] ?? 0);
@endphp
<table class="{{ $pdf ? 'data' : 'table table-sm table-bordered mb-0 stkmov-tabla' }}" @if (! $excel && ! $pdf) id="tabla-paginada" @endif>
    @if ($excel)
        <tr>
            <td colspan="{{ $colspan }}"><strong>{{ $titulo ?? 'Movimientos de stock' }}</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo ?? '' }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Movimientos: {{ $totalFilas }}
                · Entrada {{ number_format((float) ($totales['entrada'] ?? 0), 2, '.', '') }}
                · Salida {{ number_format((float) ($totales['salida'] ?? 0), 2, '.', '') }}
                · Saldo {{ number_format((float) ($totales['saldo'] ?? 0), 2, '.', '') }}
                · Importe {{ number_format((float) ($totales['importe'] ?? 0), 2, '.', '') }}
            </td>
        </tr>
    @endif
    <thead>
        @if ($pdf)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo ?? 'Movimientos de stock',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => $colspan,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas" style="background:#85C1E9;color:#17202A;">
            <th>Fecha</th>
            <th>Tip</th>
            <th>Número</th>
            <th>Comb.</th>
            <th>Color</th>
            <th>Talle</th>
            <th class="text-right">Entrada</th>
            <th class="text-right">Salida</th>
            <th class="text-right">Saldo</th>
            <th>UM</th>
            <th class="text-right">Importe</th>
            <th>N.Cli.</th>
            <th>Nombre</th>
            <th>Depósito</th>
            <th>Partida</th>
            <th>Concepto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasVista as $fila)
            @php $tipo = $fila->tipo ?? 'movimiento'; @endphp
            @if ($tipo === 'encabezado')
                <tr class="stkmov-corte {{ ! empty($fila->salto) ? 'salto-articulo' : '' }}" style="background:#D6EAF8;font-weight:bold;">
                    <td colspan="{{ $colspan }}">
                        @if ($puedeArticulo && (int) ($fila->articulo_id ?? 0) > 0)
                            <a class="text-primary" target="_blank" rel="noopener"
                                href="{{ ArticuloConsultaDesdeModal::urlEditar((int) $fila->articulo_id) }}">{{ $fila->texto }}</a>
                        @else
                            {{ $fila->texto }}
                        @endif
                    </td>
                </tr>
            @elseif ($tipo === 'saldo_inicial')
                <tr class="stkmov-corte" style="background:#EAF2F8;">
                    <td colspan="6">{{ $fila->texto }}</td>
                    <td></td>
                    <td></td>
                    <td class="text-right">{{ $fmt($fila->saldo ?? null) }}</td>
                    <td></td>
                    <td class="text-right">{{ $fmt($fila->importe ?? null) }}</td>
                    <td colspan="5"></td>
                </tr>
            @elseif (in_array($tipo, ['total_dia', 'total_articulo', 'total_general'], true))
                @php
                    $fondo = $tipo === 'total_general' ? '#AED6F1' : '#EAF2F8';
                @endphp
                <tr class="stkmov-corte" style="background:{{ $fondo }};font-weight:bold;">
                    <td colspan="6">{{ $fila->texto }}</td>
                    <td class="text-right">{{ $fmt($fila->entrada ?? 0) }}</td>
                    <td class="text-right">{{ $fmt($fila->salida ?? 0) }}</td>
                    <td class="text-right">{{ $fmt($fila->saldo ?? 0) }}</td>
                    <td></td>
                    <td class="text-right">{{ $fmt($fila->importe ?? 0) }}</td>
                    <td colspan="5"></td>
                </tr>
            @else
                @php
                    $urlNumero = null;
                    if (! $excel && ! $pdf) {
                        $urlNumero = KardexMovimientoComprobanteSupport::urlFactura((int) ($fila->venta_id ?? 0))
                            ?: KardexMovimientoComprobanteSupport::urlMovimientoStock((int) ($fila->movimientostock_id ?? 0));
                    }
                    $fechaTxt = '';
                    if (! empty($fila->fecha)) {
                        $fechaTxt = \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y');
                    }
                @endphp
                <tr>
                    <td>{{ $fechaTxt }}</td>
                    <td>{{ $fila->tip ?? '' }}</td>
                    <td>
                        @if ($urlNumero)
                            <a class="text-primary" target="_blank" rel="noopener" href="{{ $urlNumero }}">{{ $fila->numero }}</a>
                        @else
                            {{ $fila->numero }}
                        @endif
                    </td>
                    <td>{{ $fila->combinacion ?? '' }}</td>
                    <td>{{ $fila->color ?? '' }}</td>
                    <td>{{ $fila->talle ?? '' }}</td>
                    <td class="text-right">{{ $fmt($fila->entrada ?? null) }}</td>
                    <td class="text-right">{{ $fmt($fila->salida ?? null) }}</td>
                    <td class="text-right">{{ $fmt($fila->saldo ?? null) }}</td>
                    <td>{{ $fila->umd ?? '' }}</td>
                    <td class="text-right">{{ $fmt($fila->importe ?? null) }}</td>
                    <td>{{ $fila->cliente_codigo ?? '' }}</td>
                    <td>{{ $fila->cliente_nombre ?? '' }}</td>
                    <td>{{ $fila->deposito ?? '' }}</td>
                    <td>{{ $fila->partida ?? '' }}</td>
                    <td>{{ $fila->concepto ?? '' }}</td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="{{ $colspan }}" class="text-center text-muted">No hay movimientos para el filtro.</td>
            </tr>
        @endforelse
    </tbody>
</table>
