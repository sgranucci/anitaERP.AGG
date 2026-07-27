@php
    $ctrl = $control_contable ?? null;
    $columnas = $ctrl['columnas_dias'] ?? [];
    $cuentaTabaco = $ctrl['cuenta_tabaco_codigo'] ?? '414020001';
    $tol = (float) ($ctrl['tolerancia'] ?? 0.1);
@endphp
@if (! empty($ctrl) && count($columnas) > 0)
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <div>
                <strong>Control contable cigarrillos</strong>
                <span class="text-muted small ml-1">
                    Sumatoria (IMP INTERNO + NETO) vs Mayor Anita {{ $cuentaTabaco }}
                </span>
            </div>
            <div>
                <a class="btn btn-outline-success btn-sm"
                   href="{{ route('listar_gastronomia_control_contable_cigarrillos', ['formato' => 'EXCEL'] + ($filtrosQuery ?? [])) }}"
                   title="Exportar planilla Contaduría + conciliación">
                    <i class="fa fa-file-excel"></i> Excel control Contaduría
                </a>
            </div>
        </div>

        @if (! empty($ctrl['hay_diferencias']))
            <div class="alert alert-warning py-2 px-3 small mb-2">
                Hay diferencias por encima de la tolerancia ({{ number_format($tol, 2, ',', '.') }}).
                Revisar días marcados en rojo.
            </div>
        @else
            <div class="alert alert-success py-2 px-3 small mb-2">
                Sin diferencias relevantes vs mayor {{ $cuentaTabaco }} en el período.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Concepto</th>
                        @foreach ($columnas as $col)
                            <th class="text-right text-nowrap">{{ $col['label'] ?? '' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sumatoria (IMP INTERNO + NETO)</td>
                        @foreach ($columnas as $col)
                            <td class="text-right">{{ number_format((float) ($ctrl['sumatoria_por_dia'][$col['ymd']] ?? 0), 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Mayor {{ $cuentaTabaco }}</td>
                        @foreach ($columnas as $col)
                            <td class="text-right">{{ number_format((float) ($ctrl['mayor_por_dia'][$col['ymd']] ?? 0), 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td><strong>Diferencias</strong></td>
                        @foreach ($columnas as $col)
                            @php $dif = (float) ($ctrl['diferencia_por_dia'][$col['ymd']] ?? 0); @endphp
                            <td class="text-right{{ abs($dif) > $tol ? ' text-danger font-weight-bold' : '' }}">
                                {{ number_format($dif, 2, ',', '.') }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0 mt-2">
            Precio: histórico de factura (línea menú cigarrillos). Impuesto interno unitario: lista de coeficientes
            por vigencia (misma que facturación gastronomía). El Excel incluye el detalle por SKU como Contaduría.
        </p>
    </div>
@endif
