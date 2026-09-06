@extends("theme.$theme.layout")
@section('titulo')
    Aprobación de suscripciones
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Bandeja de aprobación — Suscripciones</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm">Listado</a>
                    <a href="{{ url('mis-aprobaciones') }}" class="btn btn-outline-light btn-sm">Mis aprobaciones (todas)</a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Pendientes del árbol de OC donde el documento es una suscripción y vos sos el destinatario.
                    Autorizar avanza el árbol; al completar niveles la OC queda <strong>APROBADA</strong> (Vigente en el módulo).
                </p>

                @forelse ($pendientes as $mov)
                    @php
                        $oc = $mov->ordencompras;
                        $moneda = optional(optional($oc)->contrato_monedas)->nombre
                            ?? optional(optional($oc)->contrato_monedas)->abreviatura
                            ?? '';
                        $periodo = \App\Support\Compras\SuscripcionSupport::etiquetaPeriodicidad(optional($oc)->suscripcion_periodicidad);
                    @endphp
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header">
                            <strong>{{ optional($oc)->suscripcion_nombre ?: optional($oc)->detalle }}</strong>
                            <span class="text-muted ml-2">OC {{ optional($oc)->numeroordencompra }}</span>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-3"><small class="text-muted">Proveedor</small><div>{{ optional(optional($oc)->proveedores)->nombre ?? '—' }}</div></div>
                                <div class="col-md-2"><small class="text-muted">Área</small><div>{{ optional($oc)->suscripcion_area ?: '—' }}</div></div>
                                <div class="col-md-2"><small class="text-muted">CC</small><div>{{ optional(optional($oc)->centrocostos)->codigo }} {{ optional(optional($oc)->centrocostos)->nombre }}</div></div>
                                <div class="col-md-2"><small class="text-muted">Monto</small><div>{{ $moneda }} {{ number_format((float) optional($oc)->suscripcion_monto_periodo, 2, ',', '.') }} <small>/ {{ strtolower($periodo) }}</small></div></div>
                                <div class="col-md-1"><small class="text-muted">Tol.</small><div>{{ number_format((float) optional($oc)->suscripcion_tolerancia_pct, 0) }}%</div></div>
                                <div class="col-md-2"><small class="text-muted">Enviado</small><div>{{ $mov->fechaenvio ? \Carbon\Carbon::parse($mov->fechaenvio)->format('d/m/Y H:i') : '—' }}</div></div>
                            </div>

                            @php $impacto = $impactos[$mov->id] ?? null; @endphp
                            @if ($impacto)
                                <div class="mt-2 p-2 border rounded bg-light small">
                                    <strong>Impacto en el presupuesto</strong>
                                    <span class="text-muted">
                                        · {{ optional(optional($oc)->contrato_cuentacontables)->codigo }}
                                        {{ optional(optional($oc)->contrato_cuentacontables)->nombre }}
                                        · {{ $impacto['nombre'] }}
                                    </span>
                                    <div class="mt-1">
                                        Esta suscripción se lleva
                                        <strong>{{ number_format($impacto['propio_pct'], 1, ',', '.') }}%</strong>
                                        del presupuesto anual de la cuenta
                                        ({{ $moneda }} {{ number_format($impacto['presupuesto_anual'], 2, ',', '.') }}).
                                        @if ($impacto['suscripciones_vigentes'] > 0)
                                            Con las {{ $impacto['suscripciones_vigentes'] }} ya vigentes del mismo centro de costo,
                                            el recurrente pasaría a
                                            <span class="{{ \App\Support\Compras\SuscripcionPresupuestoSupport::clasePct($impacto['pct']) }}">
                                                {{ number_format($impacto['pct'], 1, ',', '.') }}%
                                            </span>.
                                        @else
                                            Es la primera suscripción de esa cuenta y centro de costo.
                                        @endif
                                        @if ($impacto['disponible_mensual'] < 0)
                                            <span class="text-danger d-block mt-1">
                                                Queda por encima del presupuesto mensual en
                                                {{ number_format(abs($impacto['disponible_mensual']), 2, ',', '.') }}.
                                            </span>
                                        @endif
                                        @unless ($impacto['moneda_coincide'])
                                            <span class="text-warning d-block mt-1">
                                                La partida está presupuestada en otra moneda: el porcentaje es orientativo.
                                            </span>
                                        @endunless
                                    </div>
                                </div>
                            @elseif ($oc)
                                <div class="mt-2 small text-muted">
                                    Sin partida presupuestaria activa para esa cuenta y centro de costo: no se puede
                                    medir el impacto contra presupuesto.
                                </div>
                            @endif

                            <div class="mt-3 d-flex flex-wrap align-items-start">
                                <form method="post" action="{{ route('aprobar_suscripcion', $mov->id) }}" class="mr-2 mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">✓ Autorizar</button>
                                </form>
                                <form method="post" action="{{ route('rechazar_suscripcion', $mov->id) }}" class="flex-grow-1" style="max-width:420px;"
                                    onsubmit="return this.observacion.value.trim() !== '' || (alert('Comentario obligatorio'), false);">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="observacion" class="form-control" placeholder="Motivo del rechazo (obligatorio)" required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-danger">✕ Rechazar</button>
                                        </div>
                                    </div>
                                </form>
                                @if ($oc)
                                    <a href="{{ route('ver_suscripcion', $oc->id) }}" class="btn btn-outline-secondary btn-sm ml-2 mb-2">Ver detalle</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-light border mb-0">No tenés suscripciones pendientes de autorización.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
