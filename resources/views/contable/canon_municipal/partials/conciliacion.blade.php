@php
    $resumen = $resultado['resumen'] ?? [];
    $cuadra = ! empty($resultado['cuadra']);
    $filas = $resultado['filas'] ?? [];
    $ficha = $resultado['ficha'] ?? [];
    $alicuotaPct = round(((float) ($resumen['alicuota'] ?? 0.04)) * 100, 2);
@endphp

<div class="card card-outline {{ $cuadra ? 'card-success' : 'card-danger' }} mt-3" id="canon-municipal-conciliacion-panel">
    <div class="card-header">
        <h3 class="card-title">
            Conciliación Flash × Posición
            @if ($cuadra)
                <span class="badge badge-success ml-2">Cuadra</span>
            @else
                <span class="badge badge-danger ml-2">No cuadra</span>
            @endif
        </h3>
        @if ($periodo_texto ?? '')
            <span class="text-muted small ml-2">{{ $periodo_texto }}</span>
        @endif
        @if (! empty($ficha['municipio']))
            <span class="text-muted small ml-2">· {{ $ficha['municipio'] }} · Legajo {{ $ficha['legajo'] ?? '' }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:2rem;"></th>
                        <th class="text-right">Total Flash</th>
                        <th class="text-right">Total Posición</th>
                        <th class="text-right">Diferencia</th>
                        <th class="text-center">Estado</th>
                        <th class="text-right">Ventas</th>
                        <th class="text-right">Canon {{ $alicuotaPct }}%</th>
                        <th class="text-center">Nota</th>
                        <th class="text-center">Días</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="{{ $cuadra ? 'table-success' : 'table-danger' }}">
                        <td class="text-center align-middle">
                            <button type="button"
                                class="btn btn-xs btn-outline-secondary"
                                data-toggle="collapse"
                                data-target="#canon-detalle-diario"
                                aria-expanded="true"
                                aria-controls="canon-detalle-diario"
                                title="Ver detalle diario">
                                <i class="fa fa-chevron-up" id="canon-detalle-icon"></i>
                            </button>
                        </td>
                        <td class="text-right">{{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($resumen['total_posicion'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($resumen['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($cuadra)
                                <span class="badge badge-success">Cuadra</span>
                            @else
                                <span class="badge badge-danger">No cuadra</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right font-weight-bold">{{ number_format((float) ($resumen['canon_4'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-center">
                            @if (! empty($resultado['puede_emitir_nota']))
                                <span class="badge badge-success">A presentar</span>
                            @else
                                <span class="badge badge-secondary">Bloqueada</span>
                            @endif
                        </td>
                        <td class="text-center">
                            {{ (int) ($resumen['dias_con_venta'] ?? 0) }}/{{ (int) ($resumen['dias_rango'] ?? 0) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="canon-detalle-diario" class="collapse show border-top bg-light">
            <div class="px-3 py-3">
                @if (! empty($resumen['desvios']))
                    <div class="alert alert-danger py-2 small">
                        Desvío en jornada(s):
                        @foreach ($resumen['desvios'] as $d)
                            <strong>{{ date('d/m/Y', strtotime($d)) }}</strong>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 bg-white" id="tabla-paginada" style="font-size:0.85rem;">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-right">Flash (Ventas Bingo)</th>
                                <th class="text-right">Posición (VENTA BINGO)</th>
                                <th class="text-right">Diferencia</th>
                                <th class="text-center">Estado</th>
                                <th class="text-right">Canon {{ $alicuotaPct }}%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr class="{{ ! empty($fila['cuadra']) ? '' : 'table-danger' }}">
                                    <td>{{ date('d/m/Y', strtotime($fila['fecha'])) }}</td>
                                    <td class="text-right">
                                        @if (abs((float) $fila['venta_flash']) < 0.01)
                                            <span class="text-muted">—</span>
                                        @else
                                            {{ number_format((float) $fila['venta_flash'], 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if (abs((float) $fila['venta_posicion']) < 0.01)
                                            <span class="text-muted">—</span>
                                        @else
                                            {{ number_format((float) $fila['venta_posicion'], 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) $fila['diferencia'], 2, ',', '.') }}</td>
                                    <td class="text-center">
                                        @if (! empty($fila['cuadra']))
                                            <span class="badge badge-success">OK</span>
                                        @else
                                            <span class="badge badge-danger">Desvío</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if (abs((float) $fila['canon']) < 0.01)
                                            <span class="text-muted">—</span>
                                        @else
                                            {{ number_format((float) $fila['canon'], 2, ',', '.') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td>TOTALES</td>
                                <td class="text-right">{{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) ($resumen['total_posicion'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) ($resumen['diferencia'] ?? 0), 2, ',', '.') }}</td>
                                <td></td>
                                <td class="text-right">{{ number_format((float) ($resumen['canon_4'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <p class="small text-muted px-3 py-2 mb-0 border-top">
            Fuente A: Flash contable · Ventas Bingo.
            Fuente B: Posición financiera · VENTA BINGO.
            Tolerancia: {{ number_format((float) ($resumen['tolerancia'] ?? 0.05), 2, ',', '.') }}.
            Canon = suma diaria de (venta Flash × {{ number_format((float) ($resumen['alicuota'] ?? 0.04), 4, ',', '.') }}).
        </p>
    </div>
</div>

<script>
(function () {
    var panel = document.getElementById('canon-municipal-conciliacion-panel');
    if (!panel || typeof window.jQuery === 'undefined') {
        return;
    }
    window.jQuery(panel).on('show.bs.collapse', '#canon-detalle-diario', function () {
        window.jQuery('#canon-detalle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }).on('hide.bs.collapse', '#canon-detalle-diario', function () {
        window.jQuery('#canon-detalle-icon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });
})();
</script>
