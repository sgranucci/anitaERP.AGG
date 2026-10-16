@php
    $waitryArticuloId = (int) ($producto->id ?? 0);
    $waitryPuedeActualizar = \App\Support\Stock\ArticuloWaitrySupport::puedeOperar();
    $waitryPanel = app(\App\Services\Stock\ArticuloWaitrySyncService::class)->panel($producto ?? null, $waitryPuedeActualizar);
    $waitryPatron = \App\Support\Ventas\GastronomiaSkuCatalogoSupport::patronRegexCatalogo();
@endphp
<div id="articulo-waitry-root"
    data-patron="{{ $waitryPatron }}"
    data-articulo-id="{{ $waitryArticuloId }}"
    data-puede-enviar="{{ $waitryPanel['puede_enviar'] ? '1' : '0' }}"
    data-url-marcas="{{ $waitryArticuloId > 0 ? route('articulo_waitry_marcas', ['id' => $waitryArticuloId]) : '' }}"
    data-url-alta="{{ $waitryArticuloId > 0 ? route('articulo_waitry_alta', ['id' => $waitryArticuloId]) : '' }}"
    data-url-baja="{{ $waitryArticuloId > 0 ? route('articulo_waitry_baja', ['id' => $waitryArticuloId]) : '' }}">
</div>
<div class="modal fade" id="modal-articulo-waitry" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Waitry — {{ $waitryPanel['sku'] !== '' ? $waitryPanel['sku'] : 'artículo' }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    <strong>Preparar</strong> deja el producto en <strong>Sin Menú</strong>.
                    <strong>No preparar</strong> lo manda a la categoría No preparar, que Waitry excluye del filtro de cocina.
                    Un envío con el mismo SKU actualiza el producto. La foto y la categoría pública se cargan después en Waitry.
                    Cómo se ve eso en el KDS todavía no está confirmado.
                </p>
                <p class="text-muted small mb-3" id="articulo-waitry-sku-guardado">
                    Se envía el SKU guardado{{ $waitryPanel['sku'] !== '' ? ': '.$waitryPanel['sku'] : '' }}.
                </p>
                <div class="form-row mb-3">
                    <div class="col-sm-6">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="articulo-waitry-preparar" {{ $waitryPanel['marcas']['preparar'] ? 'checked' : '' }}>
                            <label class="custom-control-label" for="articulo-waitry-preparar">Preparar</label>
                        </div>
                        <small class="text-muted">Sin tilde, el alta va a la categoría No preparar.</small>
                    </div>
                </div>
                <div id="articulo-waitry-aviso" class="alert alert-info py-2 {{ $waitryPanel['puede_enviar'] ? 'd-none' : '' }}">
                    @if ($waitryArticuloId <= 0)
                        Guardá el artículo para poder enviarlo a Waitry.
                    @elseif (! $waitryPuedeActualizar)
                        Consulta: no se puede enviar ni cambiar las marcas.
                    @else
                        El envío usa el SKU ya guardado.
                    @endif
                </div>
                <div id="articulo-waitry-resultado" class="alert py-2 d-none"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Sala</th>
                                <th>placeId</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="articulo-waitry-salas">
                            @foreach ($waitryPanel['salas'] as $sala)
                                <tr data-empresa-id="{{ $sala['empresa_id'] }}">
                                    <td>{{ $sala['nombre'] }}</td>
                                    <td>{{ $sala['place_id'] }}</td>
                                    <td class="articulo-waitry-estado">{{ $sala['estado'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-primary" id="btn-articulo-waitry-marcas" {{ $waitryPanel['puede_enviar'] ? '' : 'disabled' }}>Guardar marcas</button>
                <button type="button" class="btn btn-warning" id="btn-articulo-waitry-baja" {{ $waitryPanel['puede_enviar'] ? '' : 'disabled' }}>Suspender en Waitry</button>
                <button type="button" class="btn btn-primary" id="btn-articulo-waitry-alta" {{ $waitryPanel['puede_enviar'] ? '' : 'disabled' }}>Dar de alta en Waitry</button>
            </div>
        </div>
    </div>
</div>
