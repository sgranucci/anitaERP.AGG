@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Contable\FlashContableReporteSupport;
    use App\Support\Export\ExcelFormatoNumero;

    $excel = ! empty($modo_excel);
    $pdf = ! empty($modo_pdf);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $empresas = $reporte['empresas'] ?? [];
    $filas = $reporte['filas'] ?? [];
    $totales = $reporte['totales'] ?? [];
    $etiquetas = $reporte['etiquetas'] ?? FlashContableReporteSupport::ETIQUETAS;
    $metricas = $reporte['metricas'] ?? FlashContableReporteSupport::METRICAS;
    $colspan = max(2, (int) ($reporte['cantidad_columnas'] ?? (1 + count($empresas) * count($metricas))));

    $fmtEntero = function ($valor) use ($excel, $formatoNumero) {
        if ($excel) {
            return F::enteroExcelFormato($valor, $formatoNumero);
        }

        return F::entero($valor);
    };
    $fmtImporte = function ($valor) use ($excel, $formatoNumero) {
        if ($excel) {
            return F::nExcelFormato($valor, $formatoNumero, 2);
        }

        return F::n($valor, 2);
    };
    $pantalla = ! $excel && ! $pdf;
@endphp

@if ($pantalla)
<div class="tabla-ancha-grilla tabla-ancha--doble-cabecera tabla-ancha--una-fija" data-tabla-ancha style="--tabla-ancha-c1: 5.6rem;">
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
@else
<div class="table-responsive" style="overflow-x: auto;">
@endif
<table class="data table table-sm table-bordered flash-contable-table{{ $pantalla ? ' tabla-ancha' : '' }}" style="{{ $pantalla ? '' : 'width:100%; border-collapse:collapse; ' }}font-size: 11px;">
    <thead>
        <tr style="background:#85C1E9;color:#17202A;">
            <th rowspan="2" class="text-center align-middle{{ $pantalla ? ' col-fija-1' : '' }}">Fecha</th>
            @foreach ($empresas as $empresa)
                <th colspan="{{ count($metricas) }}" class="text-center">{{ $empresa['nombre'] ?? '' }}</th>
            @endforeach
        </tr>
        <tr style="background:#85C1E9;color:#17202A;">
            @foreach ($empresas as $empresa)
                @foreach ($metricas as $clave)
                    <th class="{{ FlashContableReporteSupport::esTilde($clave) ? 'text-center' : 'text-right' }}">
                        {{ $etiquetas[$clave] ?? $clave }}
                    </th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td class="text-center{{ $pantalla ? ' col-fija-1' : '' }}">{{ $fila['fecha'] ?? '' }}</td>
                @foreach ($empresas as $empresa)
                    @php $m = $fila['empresas'][$empresa['id']] ?? [] @endphp
                    @foreach ($metricas as $clave)
                        @if (FlashContableReporteSupport::esTilde($clave))
                            <td class="text-center">
                                @include('caja.flash.partials.tilde_validado', [
                                    'validado' => ! empty($m['flash_cerrado']),
                                    'titulo' => ! empty($m['flash_cerrado']) ? 'Flash cerrado' : 'Flash pendiente',
                                    'soloTexto' => $excel || $pdf,
                                ])
                            </td>
                        @elseif (FlashContableReporteSupport::esEntero($clave))
                            <td class="text-right">{{ $fmtEntero($m[$clave] ?? 0) }}</td>
                        @else
                            <td class="text-right">{{ $fmtImporte($m[$clave] ?? 0) }}</td>
                        @endif
                    @endforeach
                @endforeach
            </tr>
        @empty
            @if (! $excel)
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-muted">Sin datos flash en el mes seleccionado.</td>
                </tr>
            @endif
        @endforelse

    </tbody>
    @if (! empty($filas) && ! empty($totales))
        <tfoot>
            <tr class="font-weight-bold fila-total-flash" style="background:#D6EAF8;">
                <td class="text-center{{ $pantalla ? ' col-fija-1' : '' }}">Total</td>
                @foreach ($empresas as $empresa)
                    @php $m = $totales[$empresa['id']] ?? [] @endphp
                    @foreach ($metricas as $clave)
                        @if (FlashContableReporteSupport::esTilde($clave))
                            <td class="text-center">{{ $m['flash_cerrado_texto'] ?? '' }}</td>
                        @elseif (FlashContableReporteSupport::esEntero($clave))
                            <td class="text-right">{{ $fmtEntero($m[$clave] ?? 0) }}</td>
                        @else
                            <td class="text-right">{{ $fmtImporte($m[$clave] ?? 0) }}</td>
                        @endif
                    @endforeach
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
@if ($pantalla)
    </div>
</div>
@else
</div>
@endif
