@php
    use App\Models\Compras\Suscripcion_Comprobante;
    use App\Services\Compras\SuscripcionComprobanteService;
    $comps = $comprobantes ?? collect();
    $servicioComp = app(SuscripcionComprobanteService::class);
@endphp
<div class="card">
    <div class="card-header py-2">
        <h3 class="card-title">Comprobantes de portal</h3>
        <div class="card-tools">
            <a href="{{ route('comprobantes_pendientes_suscripcion') }}" class="btn btn-outline-secondary btn-xs">Ver pendientes</a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Comercio</th>
                    <th class="text-right">Importe</th>
                    <th>Estado</th>
                    <th>Archivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comps as $c)
                    @php $cargo = $c->suscripcion_cargos; @endphp
                    <tr>
                        <td>{{ $c->periodo }}</td>
                        <td>{{ optional($cargo)->comercio ?: '—' }}</td>
                        <td class="text-right">
                            {{ $cargo ? number_format((float) $cargo->monto, 2, ',', '.') : '—' }}
                        </td>
                        <td>
                            <span class="{{ Suscripcion_Comprobante::clasePillEstado($c->estado) }}">
                                {{ Suscripcion_Comprobante::etiquetaEstado($c->estado) }}
                            </span>
                        </td>
                        <td>
                            @if ($c->tieneArchivo())
                                <a href="{{ route('descargar_comprobante_suscripcion', $c->id) }}" target="_blank">
                                    {{ $c->archivo_nombre }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($servicioComp->puedeGestionar($c) && $c->estado !== Suscripcion_Comprobante::ESTADO_NO_APLICA)
                                <form method="post" action="{{ route('subir_comprobante_suscripcion', $c->id) }}" enctype="multipart/form-data" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="retorno" value="ficha">
                                    <input type="file" name="archivo" accept=".pdf,.png,.jpg,.jpeg" required
                                           class="form-control-file form-control-sm d-inline-block" style="max-width:8rem;"
                                           onchange="this.form.submit()">
                                </form>
                            @endif
                            @if (can('configurar-suscripcion', false) && $c->pendiente())
                                <form method="post" action="{{ route('noaplica_comprobante_suscripcion', $c->id) }}" class="d-inline"
                                      onsubmit="return confirm('¿Marcar como no aplica?');">
                                    @csrf
                                    <input type="hidden" name="retorno" value="ficha">
                                    <button type="submit" class="btn btn-xs btn-outline-secondary">N/A</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">
                            Todavía no hay comprobantes. Aparecen al asociar un cargo del resumen.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
