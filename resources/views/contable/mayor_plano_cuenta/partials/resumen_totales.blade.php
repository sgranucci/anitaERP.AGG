@php
    $formatearMonto = static function ($valor) {
        return number_format((float) ($valor ?? 0), 2, ',', '.');
    };
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
@endphp
@if (! empty($resumen))
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-resumen-mayor-plano" aria-expanded="false">
                <i class="fa fa-chevron-down"></i> Totales por cuenta
                <span class="text-muted">({{ count($resumen) }} cuentas)</span>
            </button>
        </div>

        <div class="collapse" id="panel-resumen-mayor-plano">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr style="background-color: #85C1E9; color: #17202A;">
                            <th>Cuenta</th>
                            <th>Nombre</th>
                            <th class="text-right">Saldo inicial</th>
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
                                <td class="text-right">{{ $formatearMonto($row['saldo_inicial'] ?? 0) }}</td>
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
