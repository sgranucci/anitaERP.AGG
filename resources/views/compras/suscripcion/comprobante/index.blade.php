@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes pendientes
@endsection

@section('contenido')
@php
    use App\Models\Compras\Suscripcion_Comprobante;
    use App\Services\Compras\SuscripcionComprobanteService;
    use App\Support\Compras\SuscripcionSupport;
    $servicio = app(SuscripcionComprobanteService::class);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de portal pendientes</h3>
                <div class="card-tools">
                    @include('includes.compras.boton-manual-suscripciones')
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm ml-1">← Suscripciones</a>
                </div>
            </div>

            <div class="card-body py-2 border-bottom">
                <form method="get" class="form-row align-items-end mb-0">
                    @if (($empresa_query ?? collect())->count() > 1)
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-1">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)$empresa_id === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-1">Área</label>
                        <select name="area" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            @foreach ($areas as $a)
                                <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (! empty($puedeFiltrarOwner) && $owner_usuario_id)
                        <input type="hidden" name="owner_usuario_id" value="{{ $owner_usuario_id }}">
                    @endif
                </form>
                <p class="text-muted small mb-0 mt-1">
                    El pendiente nace cuando el cargo del resumen se asocia a la suscripción.
                    La imputación no se bloquea; si al {{ $servicio->etiquetaUmbralEscalamiento() }} del período sigue sin PDF, escala a gerencia.
                </p>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Período</th>
                            <th>Suscripción</th>
                            <th>Área</th>
                            <th>Dueño</th>
                            <th>Comercio / cargo</th>
                            <th class="text-right">Importe</th>
                            <th>Estado</th>
                            <th>Escalamiento</th>
                            <th class="width120"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendientes as $c)
                            @php
                                $oc = $c->ordencompras;
                                $cargo = $c->suscripcion_cargos;
                                $escala = $servicio->debeEscalar($c);
                            @endphp
                            <tr class="{{ $escala ? 'table-warning' : '' }}">
                                <td class="text-nowrap">{{ $c->periodo }}</td>
                                <td>
                                    <a href="{{ route('ver_suscripcion', $oc->id) }}">
                                        {{ $oc->suscripcion_nombre ?: $oc->detalle }}
                                    </a>
                                    <small class="text-muted d-block">OC {{ $oc->numeroordencompra ?: '—' }}</small>
                                </td>
                                <td>{{ $oc->suscripcion_area ?: '—' }}</td>
                                <td>{{ optional($oc->suscripcion_owners)->nombre ?: '—' }}</td>
                                <td>
                                    {{ optional($cargo)->comercio ?: '—' }}
                                    @if ($cargo?->fecha)
                                        <small class="text-muted d-block">{{ $cargo->fecha->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{ $cargo ? number_format((float) $cargo->monto, 2, ',', '.') : '—' }}
                                </td>
                                <td>
                                    <span class="{{ Suscripcion_Comprobante::clasePillEstado($c->estado) }}">
                                        {{ Suscripcion_Comprobante::etiquetaEstado($c->estado) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($escala)
                                        <span class="badge badge-danger">Escala</span>
                                    @else
                                        <span class="text-muted small">Desde {{ $servicio->etiquetaUmbralEscalamiento() }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($servicio->puedeGestionar($c))
                                        <form method="post" action="{{ route('subir_comprobante_suscripcion', $c->id) }}" enctype="multipart/form-data" class="d-inline">
                                            @csrf
                                            <input type="file" name="archivo" accept=".pdf,.png,.jpg,.jpeg" required
                                                   class="form-control-file form-control-sm d-inline-block" style="max-width:9rem;"
                                                   onchange="this.form.submit()">
                                        </form>
                                    @endif
                                    @if (can('configurar-suscripcion', false))
                                        <form method="post" action="{{ route('noaplica_comprobante_suscripcion', $c->id) }}" class="d-inline"
                                              onsubmit="return confirm('¿Marcar como no aplica?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-secondary" title="No aplica">N/A</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No hay comprobantes pendientes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
