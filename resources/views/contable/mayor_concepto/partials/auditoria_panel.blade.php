@php
    $formatearMonto = static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $panel = $auditoria_panel ?? null;
    $auditoriaDisp = $panel['disponibilidad'] ?? ($auditoria ?? null);
    $auditoriaContra = $panel['contrapartidas'] ?? null;
    $tieneDisp = ! empty($auditoriaDisp['filas']);
    $tieneContra = ! empty($auditoriaContra['filas']);
@endphp
@if ($tieneDisp || $tieneContra)
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-auditoria-mayor-concepto" aria-expanded="false">
                <i class="fa fa-chevron-down"></i> Auditoría vs mayor plano
            </button>
            <div class="d-flex flex-wrap align-items-center mt-1 mt-md-0">
                @if (! empty($panel['cuadra']))
                    <span class="badge badge-success mr-1">Todo cuadra</span>
                @else
                    <span class="badge badge-warning mr-1">Con diferencias</span>
                @endif
                @if ($tieneDisp)
                    @if (! empty($auditoriaDisp['cuadra']))
                        <span class="badge badge-success mr-1">Caja/banco OK</span>
                    @else
                        <span class="badge badge-danger mr-1">Caja/banco Δ</span>
                    @endif
                @endif
                @if ($tieneContra)
                    @if (! empty($auditoriaContra['cuadra']))
                        <span class="badge badge-success">Contrapartidas OK</span>
                    @else
                        <span class="badge badge-danger">Contrapartidas Δ ({{ (int) ($auditoriaContra['cuentas_descuadradas'] ?? 0) }})</span>
                    @endif
                @endif
            </div>
        </div>

        <div class="collapse" id="panel-auditoria-mayor-concepto">
            @if ($tieneDisp)
                <h6 class="font-weight-bold mb-2 mt-1">Disponibilidad (caja / banco)</h6>
                <p class="small text-muted mb-2">
                    <strong>Plano Debe/Haber:</strong> movimientos reales de la cuenta caja/banco en subdiario + ctamov.<br>
                    <strong>Imput. Debe/Haber:</strong> mayor por concepto totalizado por esa cuenta (todos los conceptos), con el signo del banco — no el Debe/Haber visible de la contrapartida.
                </p>
                <div class="table-responsive mb-3">
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
                                <th class="text-right">Conceptos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditoriaDisp['filas'] as $fila)
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
                                    <td class="text-right">{{ (int) ($fila['conceptos_imputados'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($tieneContra)
                <h6 class="font-weight-bold mb-2">Contrapartidas desde operaciones de disponibilidad</h6>
                <p class="small text-muted mb-2">
                    Solo cuentas &gt; 114000000 generadas al procesar operaciones que tocan caja/banco.
                    No incluye el plano analítico completo del mes ni líneas de remanente.
                </p>
                @php
                    $diffsContra = array_values(array_filter(
                        $auditoriaContra['filas'] ?? [],
                        fn ($f) => empty($f['cuadra']),
                    ));
                @endphp
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                        <thead>
                            <tr style="background-color: #fdebd0;">
                                <th>Cuenta</th>
                                <th>Descripción</th>
                                <th class="text-right">Plano Debe</th>
                                <th class="text-right">Plano Haber</th>
                                <th class="text-right">Imput. Debe</th>
                                <th class="text-right">Imput. Haber</th>
                                <th class="text-right">Dif. Debe</th>
                                <th class="text-right">Dif. Haber</th>
                                <th class="text-right">Lín. imput.</th>
                                <th class="text-right">Mov. plano</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($diffsContra !== [] ? $diffsContra : array_slice($auditoriaContra['filas'] ?? [], 0, 25) as $fila)
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
                                    <td class="text-right">{{ (int) ($fila['movimientos_plano'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($diffsContra === [] && ! empty($auditoriaContra['cuadra']))
                    <p class="small text-success mb-0 mt-2">Todas las contrapartidas cuadran con el plano acotado.</p>
                @elseif (count($auditoriaContra['filas'] ?? []) > 25 && $diffsContra === [])
                    <p class="small text-muted mb-0 mt-2">Mostrando las primeras 25 cuentas (todas cuadran).</p>
                @endif
            @endif
        </div>
    </div>
@endif
