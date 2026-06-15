@php
    $formatearMonto = static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
@endphp
@if (! empty($auditoria) && ! empty($auditoria['filas']))
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-auditoria-mayor-plano" aria-expanded="false">
                <i class="fa fa-chevron-down"></i> Auditoría vs mayor plano (disponibilidad)
            </button>
            @if (! empty($auditoria['cuadra']))
                <span class="badge badge-success">Cuadra</span>
            @else
                <span class="badge badge-warning">Con diferencias</span>
            @endif
        </div>

        <div class="collapse" id="panel-auditoria-mayor-plano">
            <p class="small text-muted mb-2">
                <strong>Plano Debe/Haber:</strong> movimientos reales de la cuenta caja/banco en subdiario + ctamov (mayor analítico plano).<br>
                <strong>Imput. Debe/Haber:</strong> mayor por concepto totalizado por esa misma cuenta (suma de todos los conceptos), con el signo del banco — no el Debe/Haber visible de la contrapartida (gasto). Ej.: un OPP imputa Debe al gasto, pero el banco sale en Haber.<br>
                Debe cuadrar con diferencia &lt; 0,05 por cuenta.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                    <thead>
                        <tr style="background-color: #f8d7da;">
                            <th>Cuenta disp.</th>
                            <th>Descripción</th>
                            <th class="text-right">Plano Debe</th>
                            <th class="text-right">Plano Haber</th>
                            <th class="text-right">Imput. Debe</th>
                            <th class="text-right">Imput. Haber</th>
                            <th class="text-right">Dif. Debe</th>
                            <th class="text-right">Dif. Haber</th>
                            <th class="text-right">Líneas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($auditoria['filas'] as $fila)
                            <tr class="{{ empty($fila['cuadra']) ? 'table-warning' : '' }}">
                                <td>{{ $fila['cuenta_codigo'] ?? '' }}</td>
                                <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['plano_debe'] ?? null) }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['plano_haber'] ?? null) }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['imputado_debe'] ?? null) }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['imputado_haber'] ?? null) }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['diferencia_debe'] ?? null) }}</td>
                                <td class="text-right">{{ $formatearMonto($fila['diferencia_haber'] ?? null) }}</td>
                                <td class="text-right">{{ (int) ($fila['lineas_imputadas'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
