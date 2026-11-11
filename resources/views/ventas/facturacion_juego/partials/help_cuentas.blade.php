{{-- Help: cuentas contables y centros de costo de facturación juego --}}
@php
    $helpSalas = $helpSalas ?? [];
    $helpModalId = $helpModalId ?? 'modal-help-facturacion-juego-cuentas';
    $helpBtnClass = $helpBtnClass ?? 'btn btn-outline-secondary btn-sm';
    $soloBoton = ! empty($soloBoton);
    $soloModal = ! empty($soloModal);
    $helpPrimera = collect($helpSalas)->first() ?? [];
    $helpPvDelRio = (int) (($helpPrimera['sucursal_del_rio'] ?? 67) ?: 67);
    $helpPvBetsur = (int) (($helpPrimera['sucursal_betsur'] ?? 68) ?: 68);
@endphp
@if (! $soloModal)
<button type="button" class="{{ $helpBtnClass }}" data-toggle="modal" data-target="#{{ $helpModalId }}"
    title="Cuentas contables y centros de costo">
    <i class="fa fa-question-circle"></i> Cuentas / CC
</button>
@endif

@if (! $soloBoton)
<div class="modal fade" id="{{ $helpModalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $helpModalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $helpModalId }}-label">
                    Cuentas contables y centros de costo — Facturaci&oacute;n juego
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Los valores de Contab. ERP y Anita se arman con estas cuentas de resultado por rubro.
                    El centro de costo aplica a ambas piernas del asiento ERP (ventas y caja).
                    La configuraci&oacute;n se edita en la pantalla de configuraci&oacute;n del m&oacute;dulo.
                </p>
                @if (count($helpSalas) === 0)
                    <div class="alert alert-warning mb-0">
                        No hay salas activas con configuraci&oacute;n de facturaci&oacute;n juego.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Sala</th>
                                    <th>PV Anita</th>
                                    <th>Caja (tesorer&iacute;a)</th>
                                    <th>Cta. contable caja</th>
                                    <th>Mesas</th>
                                    <th>Slot</th>
                                    <th>Ruleta el.</th>
                                    <th>Del R&iacute;o (PV {{ $helpPvDelRio }})</th>
                                    <th>Betsur (PV {{ $helpPvBetsur }})</th>
                                    <th>Centro de costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($helpSalas as $h)
                                    <tr>
                                        <td>
                                            <strong>{{ $h['sala_codigo'] }}</strong>
                                            <span class="text-muted">{{ $h['sala_nombre'] }}</span>
                                        </td>
                                        <td>{{ $h['sucursal_anita'] ?: '—' }}</td>
                                        <td>
                                            @if (($h['cuentacaja_codigo'] ?? '') !== '')
                                                {{ $h['cuentacaja_codigo'] }} — {{ $h['cuentacaja_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_caja_contable_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_caja_contable_codigo'] }} — {{ $h['cuenta_caja_contable_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_mesa_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_mesa_codigo'] }} — {{ $h['cuenta_mesa_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_slot_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_slot_codigo'] }} — {{ $h['cuenta_slot_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_ruleta_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_ruleta_codigo'] }} — {{ $h['cuenta_ruleta_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_del_rio_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_del_rio_codigo'] }} — {{ $h['cuenta_del_rio_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['cuenta_betsur_codigo'] ?? '') !== '')
                                                {{ $h['cuenta_betsur_codigo'] }} — {{ $h['cuenta_betsur_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($h['centrocosto_codigo'] ?? '') !== '')
                                                {{ $h['centrocosto_codigo'] }} — {{ $h['centrocosto_nombre'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                @if (can('configurar-facturacion-juego', false))
                    <a href="{{ route('facturacion_juego_configuracion') }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-cogs"></i> Ir a configuraci&oacute;n
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
