@php
    $oficinaCompraId = old('oficinacompra_id', (isset($data) && $data) ? ($data->oficinacompra_id ?? null) : null);
    $oficinaCompraNombre = '';
    if (!empty($oficinaCompraId) && isset($oficinacompra_query)) {
        $oficinaCompraNombre = optional($oficinacompra_query->firstWhere('id', (int) $oficinaCompraId))->nombre ?? '';
    }
    // Solo consulta cuando $visualizar es truthy (no bastaba isset(): en editar viene false y ocultaba edición).
    $soloLectura = isset($visualizar) && $visualizar;
@endphp
<div id="tab1" class="form1 tab-content">
    <div class="row">
        <div class="col-sm-6">
            <input type="hidden" name="requisicion_id" id="requisicion_id" value="{{ (isset($data) && $data) ? $data->id : '' }}">
            <input type="hidden" name="oficinacompra_id" id="oficinacompra_id" value="{{ old('oficinacompra_id', (isset($data) && $data) ? ($data->oficinacompra_id ?? '') : '') }}">
            @if(isset($oficinacompra_query))
                <script>
                    window.oficinacompraMap = window.oficinacompraMap || {};
                    @foreach($oficinacompra_query as $oc)
                        window.oficinacompraMap["{{ $oc->id }}"] = @json($oc->nombre);
                    @endforeach
                </script>
            @endif

            <div class="form-group row">
                <label for="empresa_id" class="col-lg-3 control-label requerido">Empresa</label>
                <div class="col-lg-5">
                    <select name="empresa_id" id="empresa_id" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">Seleccione...</option>
                        @foreach ($empresa_query as $empresa)
                            <option value="{{ $empresa->id }}" {{ (int) old('empresa_id', (isset($data) && $data) ? $data->empresa_id : '') === (int) $empresa->id ? 'selected' : '' }}>
                                {{ $empresa->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="oficinacompra_id_show" class="col-lg-3 control-label">Oficina compra</label>
                <div class="col-lg-5">
                    <input type="text" id="oficinacompra_id_show" class="form-control" value="{{ $oficinaCompraNombre }}" readonly>
                </div>
            </div>

            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label requerido">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}" required {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="fechaentrega" class="col-lg-3 control-label requerido">Fecha entrega</label>
                <div class="col-lg-3">
                    <input type="date" name="fechaentrega" id="fechaentrega" class="form-control" value="{{ old('fechaentrega', (isset($data) && $data && $data->fechaentrega) ? substr($data->fechaentrega, 0, 10) : date('Y-m-d')) }}" required {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-3 control-label requerido">Centro costo</label>
                <div class="col-lg-4">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        @php $centrocostoUsuario_id = (isset($data) && $data) ? $data->centrocosto_id : (auth()->user()->centrocosto_id ?? 1); @endphp
                        @foreach ($centrocosto_query as $cc)
                            @if ($cc->id > 0)
                                @if ($cc->id == $centrocostoUsuario_id)
                                    <option value="{{ $cc->id }}" selected>
                                        {{ $cc->codigo }} - {{ $cc->nombre }}
                                    </option>
                                @endif
                            @else
                                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', (isset($data) && $data) ? $data->centrocosto_id : '') === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }} - {{ $cc->nombre }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            @php
                $reqProveedor = (isset($data) && $data) ? $data->proveedores : null;
                $condicionPagoProveedorNombre = optional(optional($reqProveedor)->condicionpagos)->nombre ?? '';
            @endphp
            <div class="form-group row align-items-center" id="div-proveedor">
                <label for="codigoproveedor" class="col-lg-3 control-label">Proveedor sugerido</label>
                <div class="col-lg-9">
                    <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', (isset($data) && $data) ? ($data->proveedor_id ?? '') : '') }}">
                    <div class="d-flex flex-wrap align-items-center">
                        <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor" value="{{ old('codigoproveedor', optional($reqProveedor)->codigo ?? '') }}" style="width: 6.5rem; max-width: 30%; flex-shrink: 0;" {{ $soloLectura ? 'readonly' : '' }}>
                        <input type="text" class="form-control mr-2" id="nombreproveedor" name="nombreproveedor" value="{{ old('nombreproveedor', optional($reqProveedor)->nombre ?? '') }}" readonly style="min-width: 8rem; flex: 1 1 8rem;">
                        @if(!$soloLectura)
                        <button type="button" title="Consulta proveedores" class="btn-accion-tabla consultaproveedor tooltipsC mr-2 mr-md-3">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @endif
                        <div class="d-flex align-items-center flex-grow-1" style="min-width: 12rem;">
                            <label for="condicionpago_proveedor_show" class="control-label mb-0 mr-2 text-nowrap">C.pago</label>
                            <input type="text" class="form-control" id="condicionpago_proveedor_show" readonly tabindex="-1" value="{{ $condicionPagoProveedorNombre }}" placeholder="—">
                        </div>
                    </div>
                    <span id="nombretiposuspension" class="col-form-label text-danger small mb-0 d-block"></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="formapago_id" class="col-lg-3 control-label">Forma de pago</label>
                <div class="col-lg-4">
                    <select name="formapago_id" id="formapago_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($formapago_query as $fp)
                            <option value="{{ $fp->id }}" {{ (int) old('formapago_id', (isset($data) && $data) ? $data->formapago_id : '') === (int) $fp->id ? 'selected' : '' }}>
                                {{ $fp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <label for="numerorequisicion" class="col-lg-2 control-label">Requisición</label>
                <div class="col-lg-2">
                    <input type="text" name="numerorequisicion" id="numerorequisicion" class="form-control" value="{{ old('numerorequisicion', (isset($data) && $data) ? $data->numerorequisicion : '') }}" readonly>
                </div>
            </div>

            <div class="form-group row">
                <label for="tratamiento" class="col-lg-3 control-label requerido">Tratamiento</label>
                <div class="col-lg-4">
                    <select name="tratamiento" id="tratamiento" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($tratamiento_enum as $t)
                            <option value="{{ $t['nombre'] }}" {{ old('tratamiento', (isset($data) && $data) ? $data->tratamiento : 'Normal') == $t['nombre'] ? 'selected' : '' }}>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="motivotratamiento" class="col-lg-3 control-label">Motivo tratamiento</label>
                <div class="col-lg-8">
                    <input type="text" name="motivotratamiento" id="motivotratamiento" class="form-control" value="{{ old('motivotratamiento', (isset($data) && $data) ? $data->motivotratamiento : '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="contrataciondirecta" class="col-lg-3 control-label requerido">Contratación directa</label>
                <div class="col-lg-4">
                    <select name="contrataciondirecta" id="contrataciondirecta" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($contratacionDirecta_enum as $t)
                            <option value="{{ $t['nombre'] }}" {{ old('contrataciondirecta', (isset($data) && $data) ? $data->contrataciondirecta : 'Normal') == $t['nombre'] ? 'selected' : '' }}>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>            

            <div class="form-group row">
                <label for="comentario" class="col-lg-3 control-label">Comentario</label>
                <div class="col-lg-8">
                    <input type="text" name="comentario" id="comentario" class="form-control" value="{{ old('comentario', (isset($data) && $data) ? $data->comentario : '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            @if(isset($data))
            <div class="form-group row">
                <label for="estado" class="col-lg-3 control-label">Estado</label>
                <div class="col-lg-5">
                    <select name="estado" id="estado" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($estado_enum as $e)
                            <option value="{{ $e['nombre'] }}" {{ old('estado', $data->estado ?? '') == $e['nombre'] ? 'selected' : '' }}>
                                {{ $e['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group row">
            <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
            <div class="col-lg-6">
                <textarea name="detalle" id="detalle" rows="3" class="form-control">{{ old('detalle', (isset($data) && $data) ? $data->detalle : '') }}</textarea>
            </div>
        </div>
    </div>
    <hr>
    <h5>Artículos</h5>
    @php
        $centrocostoDefaultDestino = (int) (
            (isset($data) && $data && $data->centrocosto_id)
                ? $data->centrocosto_id
                : (auth()->user()->centrocosto_id ?? 1)
        );
    @endphp
    <table class="table" id="tabla-articulos-requisicion" data-requisicion-cc-destino-default="{{ $centrocostoDefaultDestino }}">
        <thead>
            <tr>
                <th style="width: 13%;">Artículo</th>
                <th style="width: 16%;">Descripción</th>
                <th style="width: 8%;">Cantidad</th>
                <th style="width: 9%;">Precio unit.</th>
                <th style="width: 5%;">Moneda</th>
                <th style="width: 10%;">CC destino</th>
                <th style="width: 22%;">Partida presupuesto</th>
                <th style="width: 22%;">Capex</th>
                @if(!$soloLectura)
                <th style="width: 4%;"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $lineas = (isset($data) && $data && $data->requisicion_articulos && $data->requisicion_articulos->count())
                    ? $data->requisicion_articulos
                    : collect([new \App\Models\Compras\Requisicion_Articulo()]);
            @endphp
            @foreach ($lineas as $idx => $linea)
            <tr class="item-requisicion-articulo">
                <td>
                    <div class="form-group row celda-articulo-requisicion mb-0 d-flex align-items-center flex-nowrap">
                        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}" >
                        <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <button type="button" title="Consultar listas de precios de compra (si no hay artículo, muestra las últimas listas vigentes del proveedor)" style="padding:1;" class="btn-accion-tabla consultalistasprecio tooltipsC flex-shrink-0">
                                <i class="fa fa-tags text-info"></i>
                        </button>
                        <input type="text" class="codigoarticulo codigoarticulolocal form-control flex-shrink-0" style="width: 140px; max-width: 15vw; height: 38px;" name="codigoarticulos[]" value="{{ optional($linea->articulos)->sku ?? '' }}" {{ $soloLectura ? 'readonly' : '' }} >
                    </div>
                </td>
                <td>
                    <input type="text" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{ old('descripcionarticulos.'.$idx, optional($linea->articulos)->descripcion ?? '') }}" readonly>
                </td>
                <td>
                    <input type="number" step="0.0001" name="cantidades[]" class="form-control cantidad-linea" value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </td>
                <td>
                    <input type="number" step="0.0001" name="precios[]" class="form-control precio-linea" value="{{ old('precios.'.$idx, $linea->precio ?? '0') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </td>
                <td>
                    <select name="moneda_linea_ids[]" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($moneda_query as $moneda)
                            <option value="{{ $moneda->id }}" {{ (int) old('moneda_linea_ids.'.$idx, $linea->moneda_id ?? 1) === (int) $moneda->id ? 'selected' : '' }}>
                                {{ $moneda->abreviatura }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="centrocostodestino_ids[]" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($centrocosto_query as $cc)
                            <option value="{{ $cc->id }}" {{ (int) old('centrocostodestino_ids.'.$idx, $linea->centrocostodestino_id ?? $centrocostoDefaultDestino) === (int) $cc->id ? 'selected' : '' }}>
                                {{ $cc->codigo }}-{{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <div class="form-group row celda-partidagasto">
                        <input type="hidden" class="partidagasto_id" name="partidagasto_ids[]" value="{{ old('partidagasto_ids.'.$idx, $linea->partidagasto_id ?? '') }}" >
                        <button type="button" title="Consulta partidas (último presupuesto)" style="padding:1;" class="btn-accion-tabla consultapartidagasto tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigopartidagasto col-lg-3 form-control" name="codigopartidagastos[]" value="{{ optional($linea->partidagastos)->codigo ?? '' }}" {{ $soloLectura ? 'readonly' : '' }} >
                        <input type="text" class="descripcionpartidagasto col-lg-8 form-control" name="descripcionpartidagastos[]" value="{{ old('descripcionpartidagastos.'.$idx, optional($linea->partidagastos?->articulos)->detalle ?? '') }}" readonly>
                    </div>
                </td>
                <td>
                    <div class="form-group row celda-capex">
                        <input type="hidden" class="capex_id" name="capex_ids[]" value="{{ old('capex_ids.'.$idx, $linea->capex_id ?? '') }}">
                        <button type="button" title="Consulta CAPEX (último presupuesto)" style="padding:1;" class="btn-accion-tabla consultacapex tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigocapex col-lg-3 form-control" name="codigocapexs[]" value="{{ optional($linea->capexs)->codigo ?? '' }}" {{ $soloLectura ? 'readonly' : '' }} >
                        <input type="text" class="descripcioncapex col-lg-8 form-control" name="descripcioncapexs[]" value="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}" readonly>
                    </div>
                </td>
                @if(!$soloLectura)
                <td class="text-center">
                    <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_requisicion_articulo tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!$soloLectura)
    <div class="d-flex flex-wrap align-items-center justify-content-start mt-2">
        <button type="button" class="btn btn-danger" id="agrega_renglon_requisicion_articulo">+ Agrega rengl&oacute;n</button>
    </div>
    @endif
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.presupuesto.modalconsultapartidagasto')
@include('includes.presupuesto.modalconsultacapex')
@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.modalconsultalistasprecio')