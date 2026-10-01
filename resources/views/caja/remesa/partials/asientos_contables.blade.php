@php
    $asientosContables = $asientosContables ?? collect();
    $puedeAbrirAsiento = can('listar-asiento', false) || can('editar-asiento', false);
    $puedeAbrirCuenta = can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false);
    $cajaRemesaId = (int) ($remesa->caja_movimiento_id ?? 0);
@endphp
<div class="card card-outline card-primary mt-3" id="remesa-contabilidad">
    <div class="card-header py-2">
        <strong>Contabilidad</strong>
        <span class="text-muted small ml-2">Asientos de la remesa, con todas las cuentas</span>
    </div>
    <div class="card-body py-2">
        @forelse ($asientosContables as $asiento)
            @php
                $movimientos = $asiento->asiento_movimientos ?? collect();
                $totalDebe = 0.0;
                $totalHaber = 0.0;
                foreach ($movimientos as $movimiento) {
                    $monto = (float) ($movimiento->monto ?? 0);
                    if ($monto > 0) {
                        $totalDebe += $monto;
                    } elseif ($monto < 0) {
                        $totalHaber += abs($monto);
                    }
                }
                $numero = trim((string) ($asiento->numeroasiento ?? ''));
                $etiqueta = $numero !== '' ? $numero : ('#'.$asiento->id);
                $esReverso = $cajaRemesaId > 0 && (int) ($asiento->caja_movimiento_id ?? 0) !== $cajaRemesaId;
                $fechaAsiento = $asiento->fecha
                    ? \Illuminate\Support\Carbon::parse($asiento->fecha)->format('d/m/Y')
                    : '';
            @endphp
            <div class="mb-3">
                <div class="d-flex flex-wrap align-items-center mb-1" style="gap: .35rem .75rem;">
                    <strong>Asiento {{ $etiqueta }}</strong>
                    @if ($esReverso)
                        <span class="badge badge-warning">Reversi&oacute;n</span>
                    @endif
                    <span class="text-muted small">{{ $fechaAsiento }}</span>
                    @if ($asiento->tipoasientos)
                        <span class="text-muted small">{{ $asiento->tipoasientos->nombre }}</span>
                    @endif
                    @if ($puedeAbrirAsiento)
                        <a href="{{ route('editar_asiento', ['id' => $asiento->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="text-primary small" target="_blank" rel="noopener">
                            Abrir asiento
                        </a>
                    @endif
                </div>
                @if (! empty($asiento->observacion))
                    <p class="text-muted small mb-1">{{ $asiento->observacion }}</p>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>C&oacute;digo</th>
                                <th>Cuenta contable</th>
                                <th>Moneda</th>
                                <th class="text-right">Cotizaci&oacute;n</th>
                                <th class="text-right">Debe</th>
                                <th class="text-right">Haber</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movimientos as $movimiento)
                                @php
                                    $monto = (float) ($movimiento->monto ?? 0);
                                    $debe = $monto > 0 ? $monto : 0.0;
                                    $haber = $monto < 0 ? abs($monto) : 0.0;
                                    $cuenta = $movimiento->cuentacontables;
                                    $cuentaId = (int) ($movimiento->cuentacontable_id ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        @if ($puedeAbrirCuenta && $cuentaId > 0)
                                            <a href="{{ route('editar_cuentacontable', ['id' => $cuentaId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               class="text-primary" target="_blank" rel="noopener">
                                                {{ $cuenta->codigo ?? $cuentaId }}
                                            </a>
                                        @else
                                            {{ $cuenta->codigo ?? ($cuentaId > 0 ? $cuentaId : '') }}
                                        @endif
                                    </td>
                                    <td>{{ $cuenta->nombre ?? '' }}</td>
                                    <td>{{ $movimiento->monedas->abreviatura ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($movimiento->cotizacion ?? 0), 4, ',', '.') }}</td>
                                    <td class="text-right">{{ $debe > 0 ? number_format($debe, 2, ',', '.') : '' }}</td>
                                    <td class="text-right">{{ $haber > 0 ? number_format($haber, 2, ',', '.') : '' }}</td>
                                    <td>{{ $movimiento->observacion ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted">Este asiento no tiene movimientos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($movimientos->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                                    <td class="text-right"><strong>{{ number_format($totalDebe, 2, ',', '.') }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($totalHaber, 2, ',', '.') }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">
                @if (! empty($remesa) && method_exists($remesa, 'esInterna') && $remesa->esInterna())
                    Remesa interna: no genera asiento contable.
                @else
                    Esta remesa no tiene asientos contables grabados.
                @endif
            </p>
        @endforelse
    </div>
</div>
