@php
    use App\Support\Stock\FormulaArticuloGastronomia;
    $esEdicion = isset($data) && $data && ($data->id ?? null);
    if (old('articulo_ids') !== null && is_array(old('articulo_ids'))) {
        $filas = max(1, count(old('articulo_ids')));
    } elseif ($esEdicion && $data->formula_articulo_hijos) {
        $filas = max(1, $data->formula_articulo_hijos->count());
    } else {
        $filas = 1;
    }
    $tieneRanura = config('app.empresa') === 'FRASLE' && \Illuminate\Support\Facades\Schema::hasColumn('formula_articulo_hijo', 'ranura');
    $formulaGastronomiaOpcional = FormulaArticuloGastronomia::opcionalesHabilitados();
@endphp
<div id="tab1" class="form1 tab-content">
        <div class="form-group row">
            <label class="col-lg-3 col-form-label" for="formula_cabecera_sku_show">Art&iacute;culo (cabecera)</label>
            <div class="col-lg-9">
                <small class="d-block text-muted mb-1">Opcional. La misma f&oacute;rmula puede reutilizarse en varios art&iacute;culos (v&iacute;nculo desde cada art&iacute;culo).</small>
                <input type="hidden" name="articulo_id" id="formula_cabecera_articulo_id" value="{{ old('articulo_id', $data->articulo_id ?? '') }}" />
                <input type="hidden" id="formula_cabecera_sku" class="codigoarticulo" value="{{ old('formula_cabecera_sku', optional($data->articulos)->sku ?? '') }}" />
                <input type="hidden" id="formula_cabecera_desc" class="descripcionarticulo" value="{{ old('formula_cabecera_desc', optional($data->articulos)->descripcion ?? '') }}" />
                <div class="d-flex flex-wrap align-items-stretch w-100" style="gap: 0.35rem;">
                    <input type="text" readonly class="form-control form-control-sm text-monospace" id="formula_cabecera_sku_show" style="flex: 0 0 5.25rem; max-width: 20%; min-width: 4.25rem;" value="{{ old('formula_cabecera_sku_show', optional($data->articulos)->sku ?? '') }}" placeholder="SKU" title="SKU" />
                    <input type="text" readonly class="form-control form-control-sm" id="formula_cabecera_desc_show" style="flex: 1 1 42%; max-width: 50%; min-width: 0;" value="{{ old('formula_cabecera_desc_show', optional($data->articulos)->descripcion ?? '') }}" placeholder="Descripci&oacute;n" title="Descripci&oacute;n" />
                    <div class="d-flex align-items-stretch flex-shrink-0" style="gap: 0.25rem;">
                        <button type="button" class="btn btn-outline-secondary btn-sm js-consulta-articulo-cabecera" title="Buscar art&iacute;culo"><i class="fa fa-search"></i></button>
                        @if($esEdicion)
                        <button type="button" class="btn btn-outline-info btn-sm js-modal-articulos-formula" title="Art&iacute;culos que usan esta f&oacute;rmula" data-url="{{ route('articulos_asociados_formula_articulo', ['id' => $data->id]) }}"><i class="fa fa-list"></i></button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group row">
            <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo f&oacute;rmula</label>
            <div class="col-lg-9">
                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="50" value="{{ old('codigo', $data->codigo ?? '') }}" placeholder="N&uacute;mero de f&oacute;rmula en Anita (stkcm_formula)" />
                <small class="text-muted">Opcional. Si sincroniza desde Anita, se guarda el valor de <code>stkcm_formula</code>.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="cantidadunidad" class="col-lg-3 col-form-label requerido">Cantidad unidad</label>
            <div class="col-lg-3">
                <input type="number" step="0.01" name="cantidadunidad" id="cantidadunidad" class="form-control" value="{{ old('cantidadunidad', $data->cantidadunidad ?? '1') }}" required />
            </div>
            <label for="estado" class="col-lg-2 col-form-label requerido">Estado</label>
            <div class="col-lg-4">
                <select name="estado" id="estado" class="form-control" required>
                    @foreach($estado_enum as $est)
                        <option value="{{ $est['nombre'] }}" @if(old('estado', $data->estado ?? ($estado_enum[0]['nombre'] ?? '')) == $est['nombre']) selected @endif>{{ $est['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
            <div class="col-lg-9">
                <textarea name="detalle" id="detalle" class="form-control" rows="2">{{ old('detalle', $data->detalle ?? '') }}</textarea>
            </div>
        </div>

        <h5 class="mb-3">&Iacute;tems de la f&oacute;rmula</h5>
        <style>
            /* Misma altura en cada celda: inputs, selects, input-group y botones */
            #tabla-formula-hijos tbody tr.fila-formula-hijo > td { vertical-align: middle; }
            #tabla-formula-hijos tbody tr.fila-formula-hijo .form-control-sm,
            #tabla-formula-hijos tbody tr.fila-formula-hijo .input-group-sm > .form-control {
                box-sizing: border-box;
                min-height: 2.25rem;
                height: 2.25rem;
            }
            #tabla-formula-hijos tbody tr.fila-formula-hijo select.form-control-sm {
                line-height: 1.25;
                padding-top: 0.2rem;
                padding-bottom: 0.2rem;
            }
            #tabla-formula-hijos tbody tr.fila-formula-hijo .input-group-sm > .input-group-append > .btn,
            #tabla-formula-hijos tbody tr.fila-formula-hijo .input-group-sm > .input-group-prepend > .btn,
            #tabla-formula-hijos tbody tr.fila-formula-hijo .btn-sm {
                box-sizing: border-box;
                min-height: 2.25rem;
                height: 2.25rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding-top: 0;
                padding-bottom: 0;
            }
            #tabla-formula-hijos tbody tr.fila-formula-hijo .d-flex.flex-nowrap {
                align-items: center;
            }
            /* SKU + descripción ocupan todo el ancho de la celda */
            #tabla-formula-hijos tbody tr.fila-formula-hijo td:first-child {
                min-width: 300px;
            }
            #tabla-formula-hijos tbody tr.fila-formula-hijo .celda-articulo-formula-linea {
                width: 100%;
                min-width: 0;
            }
            #tabla-formula-hijos tbody tr.fila-formula-hijo .celda-articulo-formula-linea .descripcionarticulo {
                flex: 1 1 0%;
                min-width: 0;
            }
        </style>
        <div class="table-responsive">
            <table class="table table-bordered" id="tabla-formula-hijos" data-gastronomia-opcional="{{ $formulaGastronomiaOpcional ? '1' : '0' }}">
                <thead class="thead-light">
                    <tr>
                        <th style="min-width: 300px;">Art&iacute;culo</th>
                        <th style="width:100px;">Cantidad</th>
                        <th style="width:130px;">Factor costo</th>
                        <th>Subf&oacute;rmula</th>
                        @if ($formulaGastronomiaOpcional)
                        <th style="width:90px;">Opcional</th>
                        <th style="width:100px;" title="Solo si opcional = S&iacute;">Orden opc.</th>
                        @endif
                        <th style="width: 9.5rem; max-width: 10rem;">Dep&oacute;sito</th>
                        @if ($tieneRanura)
                        <th style="width:90px;">Ranura</th>
                        @endif
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < $filas; $i++)
                        @php
                            $h = ($esEdicion && isset($data->formula_articulo_hijos[$i])) ? $data->formula_articulo_hijos[$i] : null;
                            $oid = old("formula_articulo_hijo_ids.$i", $h->id ?? '');
                            $oaid = old("articulo_ids.$i", $h->articulo_id ?? '');
                            $osku = old("articulo_skus.$i", $h->articulos->sku ?? '');
                            $odesc = old("articulo_descs.$i", $h->articulos->descripcion ?? '');
                            $fhid = old("formula_hija_ids.$i", $h->formula_hija_id ?? '');
                            $fhlab = old("formula_hija_labels.$i", $h && $h->formula_hija_id ? (($h->formula_hija->articulos->sku ?? '').' F#'.$h->formula_hija_id) : '');
                        @endphp
                        <tr class="fila-formula-hijo">
                            <td class="p-1 align-middle">
                                <input type="hidden" name="formula_articulo_hijo_ids[]" value="{{ $oid }}" />
                                <input type="hidden" name="articulo_ids[]" class="articulo_id" value="{{ $oaid }}" />
                                <input type="hidden" class="unidadmedida_id" value="" />
                                <input type="hidden" class="categoria_id" value="" />
                                <input type="hidden" class="subcategoria_id" value="" />
                                <input type="hidden" class="unidadmedida" value="" />
                                <div class="d-flex flex-nowrap w-100 celda-articulo-formula-linea" style="gap: 3px;">
                                    <input type="text" readonly class="form-control form-control-sm codigoarticulo flex-shrink-0 text-monospace" style="width: 18ch; min-width: 18ch; flex: 0 0 18ch; box-sizing: border-box;" value="{{ $osku }}" placeholder="SKU" title="SKU" />
                                    <input type="text" readonly class="form-control form-control-sm descripcionarticulo text-truncate" value="{{ $odesc }}" placeholder="Descripci&oacute;n" title="{{ $odesc }}" />
                                    <button type="button" title="Consulta art&iacute;culos" class="btn btn-sm btn-outline-secondary consultaarticulo tooltipsC flex-shrink-0"><i class="fa fa-search text-primary"></i></button>
                                </div>
                            </td>
                            <td class="p-1 align-middle"><input type="number" step="0.01" name="cantidades[]" class="form-control form-control-sm" value="{{ old("cantidades.$i", $h->cantidad ?? '') }}" /></td>
                            <td class="p-1 align-middle"><input type="number" step="0.01" name="factorcostos[]" class="form-control form-control-sm" style="min-width: 5.5rem;" value="{{ old("factorcostos.$i", $h->factorcosto ?? '1') }}" /></td>
                            <td class="p-1 align-middle">
                                <input type="hidden" name="formula_hija_ids[]" class="fh_formula_hija_id" value="{{ $fhid }}" />
                                <div class="input-group input-group-sm">
                                    <input type="text" readonly class="form-control fh_formula_hija_label" value="{{ $fhlab }}" placeholder="Opcional" />
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-consulta-formula-linea" data-exclude="{{ $data->id ?? 0 }}"><i class="fa fa-flask"></i></button>
                                    </div>
                                </div>
                            </td>
                            @if ($formulaGastronomiaOpcional)
                            <td class="p-1 align-middle text-center">
                                <select name="esopcional[]" class="form-control form-control-sm js-esopcional-formula">
                                    <option value="0" @if((string) old("esopcional.$i", ($h && ($h->esopcional ?? false)) ? '1' : '0') !== '1') selected @endif>No</option>
                                    <option value="1" @if((string) old("esopcional.$i", ($h && ($h->esopcional ?? false)) ? '1' : '0') === '1') selected @endif>S&iacute;</option>
                                </select>
                            </td>
                            <td class="p-1 align-middle">
                                @php
                                    $esOpFila = (string) old("esopcional.$i", ($h && ($h->esopcional ?? false)) ? '1' : '0') === '1';
                                    $ooVal = old("ordenopcionales.$i", optional($h)->ordenopcional ?? '');
                                @endphp
                                <input type="number" name="ordenopcionales[]" min="1" max="65535" step="1" class="form-control form-control-sm js-ordenopcional-formula" value="{{ $ooVal !== '' && $ooVal !== null ? $ooVal : '' }}" placeholder="1…n" @if(! $esOpFila) disabled @endif />
                            </td>
                            @endif
                            <td class="p-1 align-middle">
                                <select name="deposito_ids[]" class="form-control form-control-sm text-truncate" style="max-width: 10rem;">
                                    <option value="">--</option>
                                    @foreach($deposito_query as $dep)
                                        <option value="{{ $dep->id }}" @if((int)old("deposito_ids.$i", $h->deposito_id ?? 0) === (int)$dep->id) selected @endif title="{{ $dep->codigo }} {{ $dep->nombre }}">{{ $dep->codigo }} {{ $dep->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            @if ($tieneRanura)
                            <td class="p-1 align-middle"><input type="number" name="ranuras[]" class="form-control form-control-sm" value="{{ old("ranuras.$i", $h->ranura ?? '') }}" /></td>
                            @endif
                            <td class="p-1 align-middle text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-fila-formula" title="Quitar línea">&times;</button>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-secondary mb-3" id="js-agregar-fila-formula"><i class="fa fa-plus"></i> Agregar l&iacute;nea</button>
</div>
