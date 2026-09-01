@php
    $formatearMonto = static function ($valor) {
        return number_format((float) ($valor ?? 0), 2, ',', '.');
    };
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $mostrarCc = collect($resumen ?? [])->contains(fn ($row) => array_key_exists('centrocosto_codigo', $row));
    $expandido = ! empty($expandido);
@endphp
@if (! empty($resumen))
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-resumen-mayor-plano" aria-expanded="{{ $expandido ? 'true' : 'false' }}">
                <i class="fa fa-chevron-down"></i> {{ $mostrarCc ? 'Totales por cuenta y CC' : 'Totales por cuenta' }}
                <span class="text-muted">({{ count($resumen) }} cuentas)</span>
            </button>
        </div>

        <div class="collapse{{ $expandido ? ' show' : '' }}" id="panel-resumen-mayor-plano">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr style="background-color: #85C1E9; color: #17202A;">
                            <th>Cuenta</th>
                            <th>Nombre</th>
                            @if ($mostrarCc)
                                <th>Centro de costo</th>
                            @endif
                            @if (! $expandido)
                                <th class="text-right">Saldo inicial</th>
                            @endif
                            <th class="text-right">Debe</th>
                            <th class="text-right">Haber</th>
                            <th class="text-right">Líneas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumen as $row)
                            <tr>
                                <td>
                                    @if ($puedeVerCuenta && (int) ($row['cuentacontable_id'] ?? 0) > 0)
                                        <a href="{{ route('editar_cuentacontable', ['id' => $row['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                           target="_blank" rel="noopener" class="text-primary">
                                            {{ $row['cuenta_codigo'] ?? '' }}
                                        </a>
                                    @else
                                        {{ $row['cuenta_codigo'] ?? '' }}
                                    @endif
                                </td>
                                <td>{{ $row['cuenta_nombre'] ?? '' }}</td>
                                @if ($mostrarCc)
                                    <td>
                                        {{ ($row['centrocosto_codigo'] ?? '') !== '' ? $row['centrocosto_codigo'] : 'Sin CC' }}
                                        @if (! empty($row['centrocosto_nombre']))
                                            — {{ $row['centrocosto_nombre'] }}
                                        @endif
                                    </td>
                                @endif
                                @if (! $expandido)
                                    <td class="text-right">{{ $formatearMonto($row['saldo_inicial'] ?? 0) }}</td>
                                @endif
                                <td class="text-right">{{ $formatearMonto($row['total_debe'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatearMonto($row['total_haber'] ?? 0) }}</td>
                                <td class="text-right">{{ (int) ($row['cantidad_lineas'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if (! empty($resumen_cc))
    <div class="px-3 py-2 border-bottom">
        <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-toggle="collapse"
            data-target="#panel-resumen-cc-mayor-plano" aria-expanded="{{ $expandido ? 'true' : 'false' }}">
            <i class="fa fa-chevron-down"></i> Totales consolidados por centro de costo
            <span class="text-muted">({{ count($resumen_cc) }} CC)</span>
        </button>
        <div class="collapse{{ $expandido ? ' show' : '' }}" id="panel-resumen-cc-mayor-plano">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr style="background-color: #85C1E9; color: #17202A;">
                            <th>Centro de costo</th>
                            @if (! $expandido)
                                <th class="text-right">Saldo inicial</th>
                            @endif
                            <th class="text-right">Debe</th>
                            <th class="text-right">Haber</th>
                            <th class="text-right">Cuentas</th>
                            <th class="text-right">L&iacute;neas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumen_cc as $rowCc)
                            <tr>
                                <td>
                                    {{ ($rowCc['centrocosto_codigo'] ?? '') !== '' ? $rowCc['centrocosto_codigo'] : 'Sin CC' }}
                                    @if (! empty($rowCc['centrocosto_nombre']))
                                        — {{ $rowCc['centrocosto_nombre'] }}
                                    @endif
                                </td>
                                @if (! $expandido)
                                    <td class="text-right">{{ $formatearMonto($rowCc['saldo_inicial'] ?? 0) }}</td>
                                @endif
                                <td class="text-right">{{ $formatearMonto($rowCc['total_debe'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatearMonto($rowCc['total_haber'] ?? 0) }}</td>
                                <td class="text-right">{{ (int) ($rowCc['cantidad_cuentas'] ?? 0) }}</td>
                                <td class="text-right">{{ (int) ($rowCc['cantidad_lineas'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
