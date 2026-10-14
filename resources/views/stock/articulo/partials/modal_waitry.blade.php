@php
    $waitryArticuloId = (int) ($producto->id ?? 0);
    $waitryPuedeActualizar = ! empty($puedeActualizarArticulo);
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
                    El alta crea el producto en la categoría <strong>Sin Menú</strong> de cada sala.
                    El kiosco no lo ofrece a la venta. Un pedido posterior que use el SKU integra igual.
                    La foto y la categoría pública se cargan después en Waitry.
                </p>
                <p class="text-muted small mb-3" id="articulo-waitry-sku-guardado">
                    Se envía el SKU guardado{{ $waitryPanel['sku'] !== '' ? ': '.$waitryPanel['sku'] : '' }}.
                </p>
                <div class="form-row mb-3">
                    <div class="col-sm-6">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="articulo-waitry-dispara-comanda" {{ $waitryPanel['marcas']['dispara_comanda'] ? 'checked' : '' }}>
                            <label class="custom-control-label" for="articulo-waitry-dispara-comanda">Dispara comanda</label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="articulo-waitry-va-cocina" {{ $waitryPanel['marcas']['va_cocina'] ? 'checked' : '' }}>
                            <label class="custom-control-label" for="articulo-waitry-va-cocina">Va a cocina</label>
                        </div>
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
                <button type="button" class="btn btn-warning" id="btn-articulo-waitry-baja" {{ $waitryPanel['puede_enviar'] ? '' : 'disabled' }}>Inactivar en Waitry</button>
                <button type="button" class="btn btn-primary" id="btn-articulo-waitry-alta" {{ $waitryPanel['puede_enviar'] ? '' : 'disabled' }}>Dar de alta en Waitry</button>
            </div>
        </div>
    </div>
</div>
