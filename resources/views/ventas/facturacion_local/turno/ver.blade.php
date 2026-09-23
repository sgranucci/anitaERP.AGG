@extends("theme.$theme.layout")
@section('titulo')
    Cierre de turno — Facturación Local
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cierre_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/cierre_turno.js')) ?: time() }}"></script>
@if ($puedeMoverMedio)
    @include('ventas.facturacion_local.facturas.partials.script_cambiar_medio_pago')
@endif
@endsection

@section('contenido')
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $abierto = $turno->estaAbierto();
    $medios = $resumen['por_medio'] ?? [];
    $arqueo = is_array($turno->medios_contado_cierre_json) ? $turno->medios_contado_cierre_json : [];
    $arqueoPorCuenta = [];
    foreach ($arqueo as $fila) {
        $ccId = (int) ($fila['cuentacaja_id'] ?? 0);
        if ($ccId > 0) {
            $arqueoPorCuenta[$ccId] = $fila;
        }
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    Cierre de turno #{{ $turno->id }}
                    @if ($turno->turnoLocal)
                        · {{ $turno->turnoLocal->nombre }}
                    @endif
                    · {{ $turno->localVenta->codigo ?? '' }} {{ $turno->localVenta->nombre ?? '' }}
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_turnos') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-reply-all"></i> Cierres
                    </a>
                    @if ($abierto)
                        <a href="{{ route('facturacion_local_pos', ['local_id' => $turno->local_venta_id]) }}" class="btn btn-outline-light btn-sm mr-1">POS</a>
                    @endif
                    <a href="{{ route('facturacion_local_turno_pdf', $turno->id) }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm">PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Apertura</div>
                        <div>{{ optional($turno->apertura_en)->format('d/m/Y H:i') }}</div>
                        <div class="small">{{ $turno->usuarioApertura->nombre ?? '' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Estado</div>
                        <div>
                            @if ($abierto)
                                <span class="badge badge-warning">Abierto</span>
                            @else
                                <span class="badge badge-secondary">Cerrado</span>
                                <span class="small d-block">{{ optional($turno->cierre_en)->format('d/m/Y H:i') }} · {{ $turno->usuarioCierre->nombre ?? '' }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Fondo inicial</div>
                        <div class="font-weight-bold">${{ $fmt($turno->fondo_inicial) }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Facturado</div>
                        <div class="font-weight-bold">${{ $fmt($resumen['total_facturado'] ?? 0) }}</div>
                        <div class="small text-muted">
                            {{ (int) ($resumen['cantidad_facturas'] ?? 0) }} facturas
                            @if ((int) ($resumen['cantidad_nc'] ?? 0) > 0)
                                · {{ (int) $resumen['cantidad_nc'] }} NC
                            @endif
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Neto por medios</div>
                        <div class="font-weight-bold">${{ $fmt($resumen['neto_medios'] ?? 0) }}</div>
                        @if ((int) ($resumen['cantidad_nc'] ?? 0) > 0)
                            <div class="small text-danger">NC ${{ $fmt($resumen['total_nc'] ?? 0) }}</div>
                        @endif
                    </div>
                </div>

                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2">
                        <strong>Totales por medio de pago</strong>
                    </div>
                    <div class="card-body p-0">
                        <p class="small text-muted px-3 pt-3 mb-2">
                            Cada medio muestra lo cobrado en facturas menos lo devuelto en notas de crédito.
                            <strong>Facturas</strong> abre el listado de ese medio.
                            @if ($puedeMoverMedio)
                                Si un comprobante quedó en el medio equivocado, se puede mover mientras el turno sigue abierto.
                            @endif
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Medio de pago</th>
                                        <th class="text-right">Comprobantes</th>
                                        <th class="text-right">Cobrado</th>
                                        <th class="text-right">Devuelto NC</th>
                                        <th class="text-right">Neto</th>
                                        @if (! $abierto)
                                            <th class="text-right">Contado</th>
                                        @endif
                                        <th class="text-center" style="width:140px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($medios as $medio)
                                        @php
                                            $ccId = (int) $medio['cuentacaja_id'];
                                            $etiqueta = trim(($medio['codigo'] ?? '').' '.($medio['nombre'] ?? ''));
                                            $contado = $arqueoPorCuenta[$ccId]['contado'] ?? null;
                                        @endphp
                                        <tr>
                                            <td>{{ $etiqueta !== '' ? $etiqueta : '—' }}</td>
                                            <td class="text-right">{{ (int) $medio['cantidad'] }}</td>
                                            <td class="text-right">{{ $fmt($medio['cobrado']) }}</td>
                                            <td class="text-right">{{ $fmt($medio['devuelto']) }}</td>
                                            <td class="text-right font-weight-bold">{{ $fmt($medio['neto']) }}</td>
                                            @if (! $abierto)
                                                <td class="text-right">
                                                    @if ($contado !== null)
                                                        {{ $fmt($contado) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            @endif
                                            <td class="text-center">
                                                @if ($ccId > 0 || (int) $medio['cantidad'] > 0)
                                                    <button type="button"
                                                        class="btn btn-xs btn-outline-info js-fl-facturas-medio"
                                                        data-url="{{ route('facturacion_local_turno_facturas_medio', ['id' => $turno->id, 'cuentacaja_id' => $ccId]) }}"
                                                        data-medio="{{ $etiqueta }}">
                                                        <i class="fa fa-search"></i> Facturas
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $abierto ? 6 : 7 }}" class="text-muted text-center py-3">Sin facturas en este turno.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($medios !== [])
                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-right">{{ (int) ($resumen['cantidad_facturas'] ?? 0) }}</th>
                                            <th></th>
                                            <th></th>
                                            <th class="text-right">{{ $fmt($resumen['neto_medios'] ?? 0) }}</th>
                                            @if (! $abierto)
                                                <th></th>
                                            @endif
                                            <th></th>
                                        </tr>
                                        @if ((int) ($resumen['cantidad_nc'] ?? 0) > 0)
                                            <tr>
                                                <td class="text-danger">Notas de crédito</td>
                                                <td class="text-right text-danger">{{ (int) $resumen['cantidad_nc'] }}</td>
                                                <td></td>
                                                <td class="text-right text-danger">{{ $fmt($resumen['total_nc'] ?? 0) }}</td>
                                                <td></td>
                                                @if (! $abierto)
                                                    <td></td>
                                                @endif
                                                <td class="text-center">
                                                    <button type="button"
                                                        class="btn btn-xs btn-outline-danger js-fl-facturas-medio"
                                                        data-url="{{ route('facturacion_local_turno_facturas_medio', ['id' => $turno->id, 'notas_credito' => 1]) }}">
                                                        <i class="fa fa-search"></i> NC
                                                    </button>
                                                </td>
                                            </tr>
                                        @endif
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                @if ($puedeCerrar)
                    <div class="card card-outline card-danger">
                        <div class="card-header bg-danger text-white">
                            <i class="fa fa-lock"></i> Cerrar turno
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('facturacion_local_turno_cerrar', $turno->id) }}" id="form-cerrar-turno-local" onsubmit="return confirm('¿Cerrar este turno? Después no se puede mover el medio de pago de sus facturas.');">
                                @csrf
                                <p class="small text-muted">
                                    El contado arranca igual al neto del sistema. Ajustelo si el arqueo físico no cierra.
                                    La diferencia queda como sobrante o faltante.
                                </p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Medio</th>
                                                <th class="text-right">Esperado</th>
                                                <th class="text-right" style="width:180px;">Contado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($medios as $i => $medio)
                                                @if ((int) $medio['cuentacaja_id'] <= 0)
                                                    @continue
                                                @endif
                                                <tr>
                                                    <td>{{ trim(($medio['codigo'] ?? '').' '.($medio['nombre'] ?? '')) }}</td>
                                                    <td class="text-right">{{ $fmt($medio['neto']) }}</td>
                                                    <td>
                                                        <input type="hidden" name="medios_contado[{{ $i }}][cuentacaja_id]" value="{{ (int) $medio['cuentacaja_id'] }}">
                                                        <input type="hidden" name="medios_contado[{{ $i }}][esperado]" value="{{ number_format((float) $medio['neto'], 2, '.', '') }}">
                                                        <input type="number" step="0.01" class="form-control form-control-sm text-right"
                                                            name="medios_contado[{{ $i }}][contado]"
                                                            value="{{ number_format((float) $medio['neto'], 2, '.', '') }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-group row">
                                    <label for="observacion" class="col-lg-3 control-label text-right pr-2">Observación</label>
                                    <div class="col-lg-6">
                                        <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="2000"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fa fa-lock"></i> Confirmar cierre
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif (! $abierto && $turno->observacion_cierre)
                    <p class="mb-0"><strong>Observación:</strong> {{ $turno->observacion_cierre }}</p>
                    <p class="mb-0"><strong>Sobrante / faltante:</strong> {{ $fmt($turno->sobrante_faltante) }}</p>
                @elseif (! $abierto)
                    <p class="mb-0 text-muted">Sobrante / faltante: {{ $fmt($turno->sobrante_faltante) }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-fl-facturas-medio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modal-fl-facturas-medio-titulo">Facturas por medio de pago</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Este medio</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="modal-fl-facturas-medio-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@if ($puedeMoverMedio)
    @include('ventas.facturacion_local.facturas.partials.modal_cambiar_medio_pago')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@endsection
