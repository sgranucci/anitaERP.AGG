@php
    $articulosListaOld = old('articulo_skus');
    $articulosColeccion = $articulos ?? collect();
@endphp

<h4 class="mb-2">Artículos de la factura</h4>
<p class="text-muted small mb-3">
    Ítems de mercadería (SKU / código proveedor). Pueden venir del OCR+IA al generar desde precarga.
    El control de match vs COM/OC solo corre si está activo en Configuración (por defecto off).
    Código + Enter o <kbd>F1</kbd>/lupa para consultar el catálogo.
</p>

<div class="table-responsive">
    <table class="table table-bordered table-sm mb-2" id="cp-articulo-table">
        <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:18%;">SKU</th>
                <th style="width:14%;">Cód. proveedor</th>
                <th style="width:28%;">Descripción</th>
                <th style="width:12%;" class="text-right">Cantidad</th>
                <th style="width:14%;" class="text-right">Precio unit.</th>
                <th style="width:6%;"></th>
            </tr>
        </thead>
        <tbody id="tbody-cp-articulo-table">
            @if (is_array($articulosListaOld))
                @foreach ($articulosListaOld as $idx => $skuOld)
                    @php
                        $idOld = old('articulo_ids.'.$idx, '');
                        $codProvOld = old('articulo_codigos_proveedor.'.$idx, '');
                        $descOld = old('articulo_descripciones.'.$idx, '');
                        $cantOld = old('articulo_cantidades.'.$idx, '');
                        $precioOld = old('articulo_precios.'.$idx, '');
                    @endphp
                    <tr class="item-cp-articulo">
                        <td>
                            <input type="hidden" name="articulo_ids[]" class="articulo_id" value="{{ $idOld }}">
                            <div class="d-flex flex-wrap align-items-center">
                                <input type="text" name="articulo_skus[]" class="form-control form-control-sm codigoarticulo mr-1"
                                    value="{{ $skuOld }}" style="width:6.5rem;" autocomplete="off"
                                    title="SKU + Enter · F1 consulta" placeholder="SKU">
                                <button type="button" class="btn btn-outline-primary btn-sm consultaarticulo tooltipsC flex-shrink-0"
                                    title="Consulta artículos (F1)">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="articulo_codigos_proveedor[]" class="form-control form-control-sm"
                                value="{{ $codProvOld }}" maxlength="80" autocomplete="off">
                        </td>
                        <td>
                            <input type="text" name="articulo_descripciones[]" class="form-control form-control-sm descripcionarticulo"
                                value="{{ $descOld }}" maxlength="255">
                        </td>
                        <td>
                            <input type="text" inputmode="decimal" name="articulo_cantidades[]"
                                class="form-control form-control-sm js-monto-ar text-right"
                                value="{{ filled($cantOld) ? number_format((float) $cantOld, 2, ',', '.') : '' }}">
                        </td>
                        <td>
                            <input type="text" inputmode="decimal" name="articulo_precios[]"
                                class="form-control form-control-sm js-monto-ar text-right"
                                value="{{ filled($precioOld) ? number_format((float) $precioOld, 2, ',', '.') : '' }}">
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="btn-accion-tabla eliminar_cp_articulo tooltipsC" title="Eliminar línea">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @elseif ($articulosColeccion->isNotEmpty())
                @foreach ($articulosColeccion as $renglon)
                    @php
                        $sku = $renglon->sku ?? optional($renglon->articulos)->sku ?? '';
                        $desc = $renglon->descripcion ?? optional($renglon->articulos)->descripcion ?? '';
                    @endphp
                    <tr class="item-cp-articulo">
                        <td>
                            <input type="hidden" name="articulo_ids[]" class="articulo_id" value="{{ $renglon->articulo_id ?? '' }}">
                            <div class="d-flex flex-wrap align-items-center">
                                <input type="text" name="articulo_skus[]" class="form-control form-control-sm codigoarticulo mr-1"
                                    value="{{ $sku }}" style="width:6.5rem;" autocomplete="off"
                                    title="SKU + Enter · F1 consulta" placeholder="SKU">
                                <button type="button" class="btn btn-outline-primary btn-sm consultaarticulo tooltipsC flex-shrink-0"
                                    title="Consulta artículos (F1)">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="articulo_codigos_proveedor[]" class="form-control form-control-sm"
                                value="{{ $renglon->codigo_proveedor ?? '' }}" maxlength="80" autocomplete="off">
                        </td>
                        <td>
                            <input type="text" name="articulo_descripciones[]" class="form-control form-control-sm descripcionarticulo"
                                value="{{ $desc }}" maxlength="255">
                        </td>
                        <td>
                            <input type="text" inputmode="decimal" name="articulo_cantidades[]"
                                class="form-control form-control-sm js-monto-ar text-right"
                                value="{{ number_format((float) ($renglon->cantidad ?? 0), 2, ',', '.') }}">
                        </td>
                        <td>
                            <input type="text" inputmode="decimal" name="articulo_precios[]"
                                class="form-control form-control-sm js-monto-ar text-right"
                                value="{{ number_format((float) ($renglon->precio_unitario ?? 0), 2, ',', '.') }}">
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="btn-accion-tabla eliminar_cp_articulo tooltipsC" title="Eliminar línea">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
{{-- Siempre enviar keys para que sincronizarArticulos no ignore el vacío al limpiar --}}
<input type="hidden" name="articulo_skus_marker" value="1">
<div class="row">
    <div class="col-md-12">
        <button type="button" id="agrega_renglon_cp_articulo" class="btn btn-outline-primary btn-sm pull-right">+ Agrega renglón</button>
    </div>
</div>
