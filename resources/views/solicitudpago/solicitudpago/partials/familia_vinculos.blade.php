{{-- Panel / modal de vínculos madre / hijas / cuotas --}}
@php
    use App\Support\Solicitudpago\SolicitudpagoEstados;
    $sp = $data ?? null;
    if (! $sp) {
        return;
    }
    $modoModal = ! empty($modo_modal);
    $consultaQuery = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $madre = $sp->madre;
    $cuotas = collect($sp->cuotas ?? []);
    $esMadre = $cuotas->isNotEmpty();
    $esHija = (int) ($sp->solicitudpago_madre_id ?? 0) > 0;
    if (! $esMadre && ! $esHija) {
        return;
    }
    $cuotasPagadas = $cuotas->filter(fn ($c) => (int) ($c->solicitudpago_hija_id ?? 0) > 0)->count();
    $cuotasTotal = $cuotas->count();
    $pct = $cuotasTotal > 0 ? (int) round(($cuotasPagadas / $cuotasTotal) * 100) : 0;
    $urlMadrePlan = route('editar_solicitudpago', ['id' => $sp->id] + $consultaQuery);
@endphp
<div class="card border-0 shadow-sm mb-0 sp-familia-card">
    <div class="card-body py-3">
        @if ($esHija && $madre)
            <div class="d-flex flex-wrap align-items-center mb-2">
                <span class="badge badge-light border mr-2 px-2 py-1">
                    <i class="fa fa-link text-primary"></i> SP hija
                </span>
                <span class="text-muted mr-2">Pertenece al plan</span>
                <a href="{{ route('editar_solicitudpago', ['id' => $madre->id] + $consultaQuery) }}"
                   class="btn btn-sm btn-outline-primary font-weight-bold"
                   target="_blank" rel="noopener"
                   title="Abrir SP madre en solapa de consulta (sin menú)">
                    #{{ $madre->codigo }}
                    <span class="ml-1">{{ SolicitudpagoEstados::label($madre->estado) }}</span>
                </a>
                <span class="text-muted ml-2 small">
                    Monto madre {{ number_format((float) $madre->monto, 2, ',', '.') }}
                </span>
            </div>
        @endif

        @if ($esMadre)
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <div>
                    <span class="badge badge-primary mr-2 px-2 py-1">
                        <i class="fa fa-sitemap"></i> SP madre / plan
                    </span>
                    <strong class="mr-2">#{{ $sp->codigo }}</strong>
                    <strong class="mr-2">Cuotas generadas {{ $cuotasPagadas }}/{{ $cuotasTotal }}</strong>
                    <span class="text-muted small">{{ $pct }}% del plan con SP hija</span>
                </div>
                <div class="d-flex flex-wrap align-items-center">
                    @if ($modoModal)
                        <a href="{{ $urlMadrePlan }}"
                           class="btn btn-sm btn-outline-primary mr-1"
                           target="_blank" rel="noopener"
                           title="Abrir SP madre en solapa de consulta (sin menú)">
                            <i class="fa fa-external-link-alt"></i> Abrir madre
                        </a>
                    @else
                        <a href="#tab-cuotas" class="btn btn-sm btn-outline-secondary"
                           onclick="document.getElementById('tab-cuotas-link')?.click(); return false;">
                            <i class="fa fa-calendar"></i> Ir a cuotas
                        </a>
                    @endif
                </div>
            </div>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;"
                     aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="text-right" style="width:8%;">Cuota</th>
                            <th style="width:14%;">Vencimiento</th>
                            <th class="text-right" style="width:16%;">Monto</th>
                            <th style="width:16%;">SP hija</th>
                            <th style="width:14%;">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuotas->sortBy('nro_cuota') as $cuota)
                            @php
                                $hija = $cuota->hijas ?? null;
                                $urlHija = $hija
                                    ? route('editar_solicitudpago', ['id' => $hija->id] + $consultaQuery)
                                    : null;
                            @endphp
                            <tr>
                                <td class="text-right">{{ $cuota->nro_cuota }}</td>
                                <td>{{ optional($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                                <td class="text-right">{{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                                <td>
                                    @if ($hija && $urlHija)
                                        <a href="{{ $urlHija }}"
                                           class="text-primary font-weight-bold"
                                           target="_blank" rel="noopener"
                                           title="Abrir SP hija en solapa de consulta (sin menú)">
                                            #{{ $hija->codigo }}
                                        </a>
                                    @else
                                        <span class="text-muted">Pendiente</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($hija)
                                        @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $hija->estado])
                                    @else
                                        <span class="badge badge-light border">Sin generar</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($hija && $urlHija)
                                        <a href="{{ $urlHija }}"
                                           class="btn btn-outline-primary btn-xs btn-sm py-0 px-2"
                                           target="_blank" rel="noopener"
                                           title="Abrir SP hija en solapa de consulta (sin menú)">
                                            <i class="fa fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($modoModal)
                <p class="text-muted small mb-0 mt-2">
                    <i class="fa fa-info-circle"></i>
                    Los enlaces abren en una solapa nueva sin men&uacute;. Cerrala para volver al listado con el mismo filtro.
                </p>
            @endif
        @endif
    </div>
</div>
<style>
    .sp-familia-card {
        background: linear-gradient(180deg, #f4f9fc 0%, #ffffff 55%);
        border: 1px solid #d6eaf8 !important;
        border-radius: .35rem;
    }
</style>
