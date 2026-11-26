{{--
    Guardar ahorro de una línea de OC (sin circuito de aprobación).
    Los campos no llevan name: los lee y envía public/assets/pages/scripts/compras/ordencompra/ahorro.js.
    Tipo de ahorro = tabla: consulta con lista (no select).
--}}
@php
    $ahTiposOc = app(\App\Repositories\Compras\TipoAhorroRepositoryInterface::class)->activos()
        ->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => (string) $t->nombre])
        ->values();
@endphp
<script>
window.ocAhorroConfig = {
    tipos: @json($ahTiposOc),
    origenes: @json(\App\Support\Compras\AhorroLineaSupport::origenes())
};
</script>
<div class="modal fade" id="modalOcAhorroLinea" tabindex="-1" role="dialog" aria-labelledby="modalOcAhorroLineaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalOcAhorroLineaLabel">Ahorro de la l&iacute;nea</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="oc-ah-articulo"></p>
                <p class="small mb-3">
                    Se guarda sobre la l&iacute;nea ya grabada de la orden, con el precio y la cantidad que tiene guardados.
                    No vuelve a aprobaci&oacute;n. Queda registrado en la auditor&iacute;a de la orden.
                </p>

                <div class="form-group row">
                    <label for="oc-ah-precio" class="col-lg-4 col-form-label text-right pr-2">Precio anterior</label>
                    <div class="col-lg-3">
                        <input type="number" step="0.0001" min="0" id="oc-ah-precio" class="form-control">
                    </div>
                    <div class="col-lg-2">
                        <select id="oc-ah-moneda" class="form-control">
                            @foreach ($moneda_query as $moneda)
                                <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 small text-muted align-self-center">Neto de IVA.</div>
                </div>

                <div class="form-group row">
                    <label for="oc-ah-origen" class="col-lg-4 col-form-label text-right pr-2">Origen del precio anterior</label>
                    <div class="col-lg-6">
                        <select id="oc-ah-origen" class="form-control">
                            <option value="">&mdash;</option>
                            @foreach (\App\Support\Compras\AhorroLineaSupport::origenes() as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="oc-ah-referencia" class="col-lg-4 col-form-label text-right pr-2">Referencia</label>
                    <div class="col-lg-8">
                        <input type="text" id="oc-ah-referencia" class="form-control" maxlength="255" placeholder="N.&ordm; de OC, presupuesto o lista">
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 col-form-label text-right pr-2">Tipo de ahorro</label>
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center">
                            <input type="hidden" id="oc-ah-tipo-id">
                            <button type="button" class="btn-accion-tabla tooltipsC flex-shrink-0" id="oc-ah-tipo-lupa" title="Elegir tipo de ahorro">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" id="oc-ah-tipo-nombre" class="form-control ml-1" readonly placeholder="Sin tipo">
                            <button type="button" class="btn btn-link btn-sm text-danger" id="oc-ah-tipo-quitar" title="Quitar el tipo">Quitar</button>
                        </div>
                        <div class="list-group mt-1 d-none" id="oc-ah-tipo-lista"></div>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="oc-ah-observacion" class="col-lg-4 col-form-label text-right pr-2">Observaci&oacute;n</label>
                    <div class="col-lg-8">
                        <textarea id="oc-ah-observacion" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="alert mb-0 py-2" id="oc-ah-resultado" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" id="oc-ah-guardar">Guardar ahorro</button>
            </div>
        </div>
    </div>
</div>
