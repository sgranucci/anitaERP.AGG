@extends("theme.$theme.layout")
@section('titulo')
    Suscripción {{ $oc->suscripcion_nombre }}
@endsection

@section('contenido')
@php
    use App\Support\Compras\SuscripcionSupport;
    $moneda = optional($oc->contrato_monedas)->nombre ?? optional($oc->contrato_monedas)->abreviatura ?? '';
    $tope = SuscripcionSupport::topeAutorizado(
        (float) ($oc->suscripcion_monto_periodo ?? 0),
        (float) ($oc->suscripcion_tolerancia_pct ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT)
    );
    $cargos = $oc->suscripcion_cargos->sortByDesc('fecha');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $oc->suscripcion_nombre ?: $oc->detalle }}</h3>
                <div class="card-tools">
                    <span class="{{ SuscripcionSupport::clasePillEstado($estado) }} mr-2">{{ SuscripcionSupport::etiquetaEstado($estado) }}</span>
                    @if ($oc->numeroordencompra)
                        <a href="{{ url('compras/ordencompra/'.$oc->id.'/imprimir-pdf') }}" class="btn btn-outline-light btn-sm" target="_blank"><i class="fa fa-file-pdf-o"></i> PDF</a>
                        <a href="{{ url('compras/ordencompra/'.$oc->id.'/editar') }}" class="btn btn-outline-light btn-sm" target="_blank">Abrir OC</a>
                    @endif
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm">Listado</a>
                </div>
            </div>
            <div class="card-body">
                @if ($estado === SuscripcionSupport::ESTADO_DESVIO)
                    <div class="alert alert-warning py-2">
                        <i class="fa fa-exclamation-triangle"></i>
                        Hay cargos por encima del tope autorizado sin resolver. Se revalidan desde
                        <a href="{{ route('conciliacion_suscripcion') }}">Conciliación mensual</a>.
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tr><th style="width:40%">OC N°</th><td>{{ $oc->numeroordencompra ?: '—' }}</td></tr>
                            <tr><th>Proveedor</th><td>{{ optional($oc->proveedores)->nombre ?? '—' }}</td></tr>
                            <tr><th>Empresa</th><td>{{ optional($oc->empresas)->nombre ?? '—' }}</td></tr>
                            <tr><th>Área</th><td>{{ $oc->suscripcion_area ?: '—' }}</td></tr>
                            <tr><th>Centro de costo</th><td>{{ trim((optional($oc->centrocostos)->codigo ?? '').' '.(optional($oc->centrocostos)->nombre ?? '')) }}</td></tr>
                            <tr><th>Cuenta contable</th><td>{{ optional($oc->contrato_cuentacontables)->codigo }} {{ optional($oc->contrato_cuentacontables)->nombre }}</td></tr>
                            <tr><th>Dueño del servicio</th><td>{{ optional($oc->suscripcion_owners)->nombre ?: '—' }}</td></tr>
                            <tr><th>Solicitante</th><td>{{ $oc->suscripcion_solicitante ?: '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tr><th style="width:40%">Monto período</th><td>{{ $moneda }} {{ number_format((float)$oc->suscripcion_monto_periodo, 2, ',', '.') }}</td></tr>
                            <tr><th>Periodicidad</th><td>{{ SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad) }}</td></tr>
                            <tr><th>Tolerancia</th><td>{{ number_format((float)$oc->suscripcion_tolerancia_pct, 2, ',', '.') }} %</td></tr>
                            <tr><th>Tope autorizado</th><td><strong>{{ $moneda }} {{ number_format($tope, 2, ',', '.') }}</strong></td></tr>
                            <tr>
                                <th>Tarjeta</th>
                                <td>
                                    {{ optional($oc->suscripcion_tarjetas)->etiqueta }}
                                    ••{{ $oc->suscripcion_tarjeta_ult4 }}
                                </td>
                            </tr>
                            <tr><th>Vigencia hasta</th><td>{{ $oc->contrato_vigencia_hasta ? $oc->contrato_vigencia_hasta->format('d/m/Y') : '—' }}</td></tr>
                            <tr><th>Auto-renovable</th><td>{{ $oc->contrato_auto_renovable ? 'Sí' : 'No' }}@if($oc->contrato_auto_renovable) (preaviso {{ $oc->contrato_dias_preaviso }} d)@endif</td></tr>
                            <tr><th>Estado OC</th><td>{{ $oc->estadoordencompra }}</td></tr>
                        </table>
                    </div>
                </div>

                @if ($impacto)
                    <table class="table table-sm table-bordered mb-0">
                        <tr>
                            <th style="width:20%">Presupuesto {{ date('Y') }}</th>
                            <td>
                                {{ $moneda }} {{ number_format($impacto['presupuesto_anual'], 2, ',', '.') }} anual
                                <small class="text-muted">· {{ $impacto['nombre'] }}</small>
                            </td>
                            <th style="width:20%">Recurrente comprometido</th>
                            <td>
                                <span class="{{ \App\Support\Compras\SuscripcionPresupuestoSupport::clasePct($impacto['pct']) }}">
                                    {{ number_format($impacto['pct'], 1, ',', '.') }}%
                                </span>
                                <small class="text-muted">
                                    · esta suscripción aporta {{ number_format($impacto['propio_pct'], 1, ',', '.') }}%
                                </small>
                            </td>
                        </tr>
                    </table>
                @endif

                @if ($estado === 'borrador' && can('crear-suscripcion', false))
                    <form method="post" action="{{ route('enviar_borrador_suscripcion', $oc->id) }}" onsubmit="return confirm('¿Enviar a aprobación?');">
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fa fa-paper-plane"></i> Enviar a aprobación</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><h3 class="card-title">Historia de aprobación</h3></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Nivel</th><th>Destinatario</th><th>Estado</th><th>Fecha</th><th>Observación</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($historia as $mov)
                                    <tr>
                                        <td>{{ $mov->nivel }}</td>
                                        <td>{{ optional($mov->destinatariousuarios)->nombre ?: '—' }}</td>
                                        <td>{{ $mov->estado }}</td>
                                        <td class="text-nowrap">
                                            {{ $mov->fecharespuesta
                                                ? \Carbon\Carbon::parse($mov->fecharespuesta)->format('d/m/Y H:i')
                                                : ($mov->fechaenvio ? \Carbon\Carbon::parse($mov->fechaenvio)->format('d/m/Y H:i') : '—') }}
                                        </td>
                                        <td class="small">{{ $mov->observacion ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Todavía no pasó por el circuito.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (! $gerente_id)
                        <div class="card-footer py-2 small text-danger">
                            El centro de costo no tiene gerente configurado en el árbol de Suscripciones.
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><h3 class="card-title">Archivos adjuntos</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse ($oc->ordencompra_archivos as $archivo)
                                <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                                    <span>{{ $archivo->nombrearchivo }}</span>
                                    <a href="{{ route('ordencompra_archivo', ['id' => $oc->id, 'archivo' => $archivo->id]) }}"
                                       class="btn btn-xs btn-outline-secondary" target="_blank">
                                        <i class="fa fa-download"></i>
                                    </a>
                                </li>
                            @empty
                                <li class="list-group-item text-muted text-center py-3">Sin archivos adjuntos.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h3 class="card-title">Cargos conciliados</h3>
                <div class="card-tools">
                    <a href="{{ route('conciliacion_suscripcion') }}" class="btn btn-outline-secondary btn-xs">Ir a conciliación</a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Período</th><th>Fecha</th><th>Comercio</th>
                            <th class="text-right">Importe</th><th class="text-right">Desvío</th>
                            <th>Estado</th><th>Imputado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cargos as $cargo)
                            <tr>
                                <td>{{ optional($cargo->suscripcion_conciliaciones)->periodo ?: '—' }}</td>
                                <td class="text-nowrap">{{ optional($cargo->fecha)->format('d/m/Y') }}</td>
                                <td>{{ $cargo->comercio }}</td>
                                <td class="text-right">{{ number_format((float) $cargo->monto, 2, ',', '.') }}</td>
                                <td class="text-right">
                                    @if ($cargo->desvio_pct !== null)
                                        {{ $cargo->desvio_pct > 0 ? '+' : '' }}{{ number_format((float) $cargo->desvio_pct, 2, ',', '.') }}%
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ SuscripcionSupport::clasePillEstadoCargo($cargo->estado) }}">
                                        {{ SuscripcionSupport::etiquetaEstadoCargo($cargo->estado) }}
                                    </span>
                                </td>
                                <td>{{ $cargo->imputado() ? 'Sí' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">Todavía no se conciliaron cargos de esta suscripción.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
