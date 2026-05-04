@php
    $oficinaCompraId = old('oficinacompra_id', (isset($data) && $data) ? ($data->oficinacompra_id ?? null) : null);
    $oficinaCompraNombre = '';
    if (!empty($oficinaCompraId) && isset($oficinacompra_query)) {
        $oficinaCompraNombre = optional($oficinacompra_query->firstWhere('id', (int) $oficinaCompraId))->nombre ?? '';
    }
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
                    <select name="empresa_id" id="empresa_id" class="form-control" required {{ isset($visualizar) ? 'disabled' : '' }}>
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
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}" required {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="fechaentrega" class="col-lg-3 control-label requerido">Fecha entrega</label>
                <div class="col-lg-3">
                    <input type="date" name="fechaentrega" id="fechaentrega" class="form-control" value="{{ old('fechaentrega', (isset($data) && $data && $data->fechaentrega) ? substr($data->fechaentrega, 0, 10) : date('Y-m-d')) }}" required {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-3 control-label requerido">Centro costo</label>
                <div class="col-lg-4">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ isset($visualizar) ? 'disabled' : '' }}>
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
                    <div class="d-flex flex-wrap align-items-center">
                        <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', (isset($data) && $data) ? ($data->proveedor_id ?? '') : '') }}">
                        <input type="text" class="form-control codigoproveedor col-lg-2" id="codigoproveedor" name="codigoproveedor" value="{{ old('codigoproveedor', optional($reqProveedor)->codigo ?? '') }}" style="width: 6.5rem; max-width: 30%; flex-shrink: 0;" {{ isset($visualizar) ? 'readonly' : '' }}>
                        <input type="text" class="form-control col-lg-5" id="nombreproveedor" name="nombreproveedor" value="{{ old('nombreproveedor', optional($reqProveedor)->nombre ?? '') }}" readonly style="min-width: 8rem;">
                        @if(empty($visualizar))
                        <button type="button" title="Consulta proveedores" class="btn-accion-tabla consultaproveedor tooltipsC mb-1 mr-2">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @endif
                        <div class="d-flex align-items-center flex-grow-1 mb-1" style="min-width: 12rem;">
                            <label class="col-lg-3 control-label">C.pago</label>
                            <input type="text" class="form-control col-lg-6" id="condicionpago_proveedor_show" readonly tabindex="-1" value="{{ $condicionPagoProveedorNombre }}" placeholder="—">
                        </div>
                        <span id="nombretiposuspension" class="col-form-label text-danger small mb-0"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="formapago_id" class="col-lg-3 control-label">Forma de pago</label>
                <div class="col-lg-4">
                    <select name="formapago_id" id="formapago_id" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
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
                    <select name="tratamiento" id="tratamiento" class="form-control" required {{ isset($visualizar) ? 'disabled' : '' }}>
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
                    <input type="text" name="motivotratamiento" id="motivotratamiento" class="form-control" value="{{ old('motivotratamiento', (isset($data) && $data) ? $data->motivotratamiento : '') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="contrataciondirecta" class="col-lg-3 control-label requerido">Contratación directa</label>
                <div class="col-lg-4">
                    <select name="contrataciondirecta" id="contrataciondirecta" class="form-control" required {{ isset($visualizar) ? 'disabled' : '' }}>
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
                    <input type="text" name="comentario" id="comentario" class="form-control" value="{{ old('comentario', (isset($data) && $data) ? $data->comentario : '') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            @if(isset($data))
            <div class="form-group row">
                <label for="estado" class="col-lg-3 control-label">Estado</label>
                <div class="col-lg-5">
                    <select name="estado" id="estado" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
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
                <th style="width: 12%;">Artículo</th>
                <th style="width: 18%;">Descripción</th>
                <th style="width: 8%;">Cantidad</th>
                <th style="width: 9%;">Precio unit.</th>
                <th style="width: 5%;">Moneda</th>
                <th style="width: 10%;">CC destino</th>
                <th style="width: 22%;">Partida presupuesto</th>
                <th style="width: 22%;">Capex</th>
                @if(!isset($visualizar))
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
                    <div class="form-group row celda-articulo-requisicion">
                        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}" >
                        <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigoarticulo codigoarticulolocal col-lg-10 form-control" name="codigoarticulos[]" value="{{ optional($linea->articulos)->sku ?? '' }}" {{ isset($visualizar) ? 'readonly' : '' }} >
                    </div>
                </td>
                <td>
                    <input type="text" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{ old('descripcionarticulos.'.$idx, optional($linea->articulos)->descripcion ?? '') }}" readonly>
                </td>
                <td>
                    <input type="number" step="0.0001" name="cantidades[]" class="form-control cantidad-linea" value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </td>
                <td>
                    <input type="number" step="0.0001" name="precios[]" class="form-control precio-linea" value="{{ old('precios.'.$idx, $linea->precio ?? '0') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </td>
                <td>
                    <select name="moneda_linea_ids[]" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
                        @foreach ($moneda_query as $moneda)
                            <option value="{{ $moneda->id }}" {{ (int) old('moneda_linea_ids.'.$idx, $linea->moneda_id ?? 1) === (int) $moneda->id ? 'selected' : '' }}>
                                {{ $moneda->abreviatura }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="centrocostodestino_ids[]" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
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
                        <input type="text" class="codigopartidagasto col-lg-3 form-control" name="codigopartidagastos[]" value="{{ optional($linea->partidagastos)->codigo ?? '' }}" {{ isset($visualizar) ? 'readonly' : '' }} >
                        <input type="text" class="descripcionpartidagasto col-lg-8 form-control" name="descripcionpartidagastos[]" value="{{ old('descripcionpartidagastos.'.$idx, optional($linea->partidagastos?->articulos)->detalle ?? '') }}" readonly>
                    </div>
                </td>
                <td>
                    <div class="form-group row celda-capex">
                        <input type="hidden" class="capex_id" name="capex_ids[]" value="{{ old('capex_ids.'.$idx, $linea->capex_id ?? '') }}">
                        <button type="button" title="Consulta CAPEX (último presupuesto)" style="padding:1;" class="btn-accion-tabla consultacapex tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigocapex col-lg-3 form-control" name="codigocapexs[]" value="{{ optional($linea->capexs)->codigo ?? '' }}" {{ isset($visualizar) ? 'readonly' : '' }} >
                        <input type="text" class="descripcioncapex col-lg-8 form-control" name="descripcioncapexs[]" value="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}" readonly>
                    </div>
                </td>
                @if(!isset($visualizar))
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
    @if(!isset($visualizar))
    <button type="button" class="pull-right btn btn-danger" id="agrega_renglon_requisicion_articulo">+ Agrega rengl&oacute;n</button>
    @endif
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.presupuesto.modalconsultapartidagasto')
@include('includes.presupuesto.modalconsultacapex')
@include('includes.compras.modalconsultaproveedor')