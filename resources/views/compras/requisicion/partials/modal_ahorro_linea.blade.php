{{--
    KPI de ahorro: editor del ahorro de una línea de requisición.
    Los campos del modal NO llevan name: se copian a los hidden de la fila (ver requisicion/ahorro.js).
    El tipo de ahorro sale de una tabla: se elige con consulta (id oculto + nombre + lupa), no con select.
--}}
@php
    // Carga posterior (aprobada, con OC, cumplida): el modal es editable y graba solo el ahorro de la línea.
    $ahGuardable = ! empty($ahorroGuardable) && ! empty($requisicionId);
    $ahBloq = $lineasSoloLectura && ! $ahGuardable;
    $ahTipos = app(\App\Repositories\Compras\TipoAhorroRepositoryInterface::class)->activos()
        ->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => (string) $t->nombre])
        ->values();
@endphp
<script>
window.requisicionAhorroConfig = {
    urlCalcular: @json(route('requisicion_ahorro_calcular')),
    urlPrecioAnterior: @json(route('requisicion_ahorro_precio_anterior')),
    editable: @json(! $ahBloq),
    puedeGuardar: @json($ahGuardable),
    urlGuardar: @json($ahGuardable ? route('requisicion_guardar_ahorro', ['id' => $requisicionId]) : null),
    tipos: @json($ahTipos),
    origenes: @json(\App\Support\Compras\AhorroLineaSupport::origenes())
};
</script>
<div class="modal fade" id="modalReqAhorroLinea" tabindex="-1" role="dialog" aria-labelledby="modalReqAhorroLineaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalReqAhorroLineaLabel">Ahorro de la l&iacute;nea</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="req-ah-articulo"></p>

                <div class="form-group row">
                    <label class="col-lg-4 col-form-label text-right pr-2">&Uacute;ltima compra (ERP)</label>
                    <div class="col-lg-8 d-flex align-items-center flex-wrap">
                        <span class="form-control-plaintext w-auto mr-2" id="req-ah-ultima-oc">&mdash;</span>
                        @if (! $ahBloq)
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="req-ah-usar-ultima">Usar como precio anterior</button>
                        @endif
                    </div>
                </div>

                <div class="form-group row">
                    <label for="req-ah-precio" class="col-lg-4 col-form-label text-right pr-2">Precio anterior</label>
                    <div class="col-lg-3">
                        <input type="number" step="0.0001" min="0" id="req-ah-precio" class="form-control" {{ $ahBloq ? 'readonly' : '' }}>
                    </div>
                    <div class="col-lg-2">
                        <select id="req-ah-moneda" class="form-control" {{ $ahBloq ? 'disabled' : '' }}>
                            @foreach ($moneda_query as $moneda)
                                <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 small text-muted align-self-center">Neto de IVA.</div>
                </div>

                <div class="form-group row">
                    <label for="req-ah-origen" class="col-lg-4 col-form-label text-right pr-2">Origen del precio anterior</label>
                    <div class="col-lg-6">
                        <select id="req-ah-origen" class="form-control" {{ $ahBloq ? 'disabled' : '' }}>
                            <option value="">&mdash;</option>
                            @foreach (\App\Support\Compras\AhorroLineaSupport::origenes() as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="req-ah-referencia" class="col-lg-4 col-form-label text-right pr-2">Referencia</label>
                    <div class="col-lg-8">
                        <input type="text" id="req-ah-referencia" class="form-control" maxlength="255" placeholder="N.&ordm; de OC, presupuesto o lista" {{ $ahBloq ? 'readonly' : '' }}>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 col-form-label text-right pr-2">Tipo de ahorro</label>
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center">
                            <input type="hidden" id="req-ah-tipo-id">
                            <button type="button" class="btn-accion-tabla tooltipsC flex-shrink-0" id="req-ah-tipo-lupa" title="Elegir tipo de ahorro" {{ $ahBloq ? 'disabled' : '' }}>
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" id="req-ah-tipo-nombre" class="form-control ml-1" readonly placeholder="Sin tipo">
                            @if (! $ahBloq)
                                <button type="button" class="btn btn-link btn-sm text-danger" id="req-ah-tipo-quitar" title="Quitar el tipo">Quitar</button>
                            @endif
                        </div>
                        <div class="list-group mt-1 d-none" id="req-ah-tipo-lista"></div>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="req-ah-observacion" class="col-lg-4 col-form-label text-right pr-2">Observaci&oacute;n</label>
                    <div class="col-lg-8">
                        <textarea id="req-ah-observacion" class="form-control" rows="2" {{ $ahBloq ? 'readonly' : '' }}></textarea>
                    </div>
                </div>

                <div class="alert alert-light border mb-0 py-2" id="req-ah-resultado" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                @if ($ahGuardable)
                    <button type="button" class="btn btn-primary btn-sm" id="req-ah-guardar">Guardar ahorro</button>
                @elseif (! $ahBloq)
                    <button type="button" class="btn btn-primary btn-sm" id="req-ah-aplicar">Aplicar</button>
                @endif
            </div>
        </div>
    </div>
</div>
