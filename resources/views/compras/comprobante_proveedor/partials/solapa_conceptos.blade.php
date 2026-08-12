@php
    $conceptosListaOld = old('concepto_ivacompra_ids');
    $montosOld = old('montos', []);
    $mostrarPreviewAsiento = (bool) ($mostrarSolapaAsiento ?? false);
@endphp

<div class="row">
    <div class="{{ $mostrarPreviewAsiento ? 'col-lg-6' : 'col-12' }} mb-3 mb-lg-0">
        <h4 class="mb-2">Conceptos de IVA compra</h4>
        <p class="text-muted small mb-3">
            Agregue uno o más renglones. Código + Enter o <kbd>F1</kbd>/lupa para consultar.
            El modal lista solo conceptos configurados para el <strong>tipo de comprobante</strong> seleccionado.
            En el monto, <kbd>Enter</kbd> valida coherencia y actualiza la vista previa del asiento.
        </p>

        <div id="cp-conceptos-iva-coherencia-banner" class="alert alert-danger py-2 mb-3 d-none" role="alert"></div>
        <div id="cp-conceptos-iva-coherencia-aviso" class="alert alert-info py-2 mb-3 d-none" role="alert"></div>
        <div id="cp-conceptos-tipo-aviso" class="alert alert-warning py-2 mb-3 d-none" role="alert"></div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-2" id="concepto-table">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:48%;">Concepto</th>
                        <th style="width:22%;" class="text-right">Monto</th>
                        <th style="width:8%;" class="text-center" title="Cuenta contable DEBE en el maestro">Cta.</th>
                        <th style="width:8%;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-concepto-table">
                    @if ($conceptosListaOld)
                        @foreach ($conceptosListaOld as $idx => $conceptoId)
                            @php
                                $conceptoId = (int) $conceptoId;
                                $concepto = ($concepto_ivacompra_query ?? collect())->firstWhere('id', $conceptoId);
                                $codigo = $concepto->codigo ?? '';
                                $nombre = $concepto->nombre ?? '';
                                $montoVal = $montosOld[$idx] ?? '';
                            @endphp
                            <tr class="item-concepto">
                                <td>
                                    <input type="hidden" name="concepto_ivacompra_ids[]" class="concepto_ivacompra_id" value="{{ $conceptoId ?: '' }}">
                                    <div class="d-flex flex-wrap align-items-center">
                                        <input type="text" class="form-control form-control-sm codigo_concepto_ivacompra mr-1"
                                            value="{{ $codigo }}" style="width:5.5rem;" autocomplete="off"
                                            title="Código + Enter · F1 consulta" placeholder="Cód.">
                                        <input type="text" class="form-control form-control-sm nombre_concepto_ivacompra mr-1"
                                            value="{{ $nombre }}" readonly style="min-width:8rem;flex:1;" placeholder="Descripción">
                                        <button type="button" class="btn btn-outline-primary btn-sm consultaconcepto_ivacompra tooltipsC flex-shrink-0"
                                            title="Consulta conceptos (F1)">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" inputmode="decimal" name="montos[]"
                                        class="form-control form-control-sm monto js-monto-ar text-right"
                                        value="{{ filled($montoVal) ? number_format((float) $montoVal, 2, ',', '.') : '' }}">
                                </td>
                                <td class="text-center align-middle cp-celda-aviso-concepto">
                                    <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn-accion-tabla eliminar_concepto tooltipsC" title="Eliminar línea">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @elseif (($conceptos ?? collect())->isNotEmpty())
                        @foreach ($conceptos as $renglon)
                            @php
                                $concepto = $renglon->concepto_ivacompras
                                    ?? ($concepto_ivacompra_query ?? collect())->firstWhere('id', $renglon->concepto_ivacompra_id ?? 0);
                            @endphp
                            <tr class="item-concepto">
                                <td>
                                    <input type="hidden" name="concepto_ivacompra_ids[]" class="concepto_ivacompra_id"
                                        value="{{ $renglon->concepto_ivacompra_id ?? '' }}">
                                    <div class="d-flex flex-wrap align-items-center">
                                        <input type="text" class="form-control form-control-sm codigo_concepto_ivacompra mr-1"
                                            value="{{ $concepto->codigo ?? '' }}" style="width:5.5rem;" autocomplete="off"
                                            title="Código + Enter · F1 consulta" placeholder="Cód.">
                                        <input type="text" class="form-control form-control-sm nombre_concepto_ivacompra mr-1"
                                            value="{{ $concepto->nombre ?? '' }}" readonly style="min-width:8rem;flex:1;" placeholder="Descripción">
                                        <button type="button" class="btn btn-outline-primary btn-sm consultaconcepto_ivacompra tooltipsC flex-shrink-0"
                                            title="Consulta conceptos (F1)">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" inputmode="decimal" name="montos[]"
                                        class="form-control form-control-sm monto js-monto-ar text-right"
                                        value="{{ number_format((float) ($renglon->monto ?? 0), 2, ',', '.') }}">
                                </td>
                                <td class="text-center align-middle cp-celda-aviso-concepto">
                                    <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn-accion-tabla eliminar_concepto tooltipsC" title="Eliminar línea">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_concepto" class="btn btn-outline-primary btn-sm pull-right">+ Agrega renglón</button>
            </div>
        </div>
    </div>

    @if ($mostrarPreviewAsiento)
    <div class="col-lg-6">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between" style="gap:6px;">
                <strong class="mb-0"><i class="fa fa-calculator"></i> Preview asiento</strong>
                <div class="d-flex flex-wrap align-items-center" style="gap:4px;">
                    <span id="cp-preview-asiento-status" class="small text-muted mr-1" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cp-refrescar-preview-conceptos" title="Recalcular">
                        <i class="fa fa-refresh"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" id="cp-ir-solapa-asiento" title="Abrir solapa completa">
                        <i class="fa fa-expand"></i> Completo
                    </button>
                </div>
            </div>
            <div class="card-body p-2" style="max-height:70vh;overflow:auto;">
                <p class="small text-muted mb-2">
                    Se actualiza al cambiar conceptos o montos. La edición fina de cuentas queda en la solapa
                    <em>Asiento contable</em> (al contabilizar se graba el asiento definitivo).
                </p>
                <div id="cp-asiento-preview-conceptos" class="cp-asiento-preview-target">
                    @include('compras.comprobante_proveedor.partials.solapa_asiento_contable_body', [
                        'asientoPreview' => $asientoPreview ?? ['activo' => false, 'es_preview' => true],
                        'data' => $data,
                    ])
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
