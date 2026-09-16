<div class="card-body">
    <div class="row">
        <div class="col-lg-10 col-xl-8">
            <div class="form-group row">
                <label for="mventa_id" class="col-lg-3 control-label text-right pr-2 requerido">Marca</label>
                <div class="col-lg-8">
                    <select name="mventa_id" id="mventa_id" class="form-control" required>
                        <option value="">-- Seleccionar marca --</option>
                        @foreach ($mventa_query as $value)
                            <option value="{{ $value->id }}" {{ (int) old('mventa_id') === (int) $value->id ? 'selected' : '' }}>
                                {{ $value->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row tm-articulo-campo" id="ot-articulo-campo">
                <label for="ot-codigoarticulo" class="col-lg-3 control-label text-right pr-2 requerido">Artículo</label>
                <div class="col-lg-8">
                    <input type="hidden" name="articulo_id" id="ot-articulo-id" class="articulo_id" value="{{ old('articulo_id') }}">
                    <div class="d-flex align-items-center flex-nowrap">
                        <button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0 mr-1">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text"
                               id="ot-codigoarticulo"
                               name="codigoarticulo"
                               class="codigoarticulo form-control flex-shrink-0 mr-1"
                               style="width: 140px; max-width: 30%;"
                               value="{{ old('codigoarticulo') }}"
                               autocomplete="off"
                               required
                               title="Código / SKU. F1 abre el modal.">
                        <input type="text"
                               id="ot-descripcionarticulo"
                               name="descripcionarticulo"
                               class="descripcionarticulo form-control"
                               value="{{ old('descripcionarticulo') }}"
                               readonly
                               tabindex="-1"
                               placeholder="Descripción">
                    </div>
                </div>
            </div>

            <div class="form-group row tm-combinacion-campo" id="ot-combinacion-campo">
                <label for="ot-codigocombinacion" class="col-lg-3 control-label text-right pr-2 requerido">Combinación</label>
                <div class="col-lg-8">
                    <input type="hidden" name="combinacion_id" id="ot-combinacion-id" class="combinacion_id" value="{{ old('combinacion_id') }}">
                    <input type="hidden" name="combinacion_todas" id="ot-combinacion-todas" class="ot-combinacion-todas" value="{{ old('combinacion_todas', '0') }}">
                    <div class="d-flex align-items-center flex-nowrap">
                        <button type="button" title="Consulta combinaciones (F1)" class="btn-accion-tabla consultacombinacion tooltipsC flex-shrink-0 mr-1">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text"
                               id="ot-codigocombinacion"
                               name="codigocombinacion"
                               class="codigocombinacion form-control flex-shrink-0 mr-1"
                               style="width: 140px; max-width: 30%;"
                               value="{{ old('codigocombinacion') }}"
                               autocomplete="off"
                               title="Código de combinación. F1 abre el modal. Use «Seleccionar todas» para todas.">
                        <input type="text"
                               id="ot-descripcioncombinacion"
                               name="descripcioncombinacion"
                               class="descripcioncombinacion form-control"
                               value="{{ old('descripcioncombinacion') }}"
                               readonly
                               tabindex="-1"
                               placeholder="Descripción / TODAS">
                    </div>
                    <small class="form-text text-muted">F1 o lupa: buscar. En el modal puede elegir una o «Seleccionar todas».</small>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card-footer">
    <button type="submit" name="extension" id="extension" class="btn btn-info">
        <i class="fa fa-search"></i> Consulta Pedidos
    </button>
</div>
