<div id="tab1" class="form1 tab-content">
    <div class="row">
        <div class="col-md-6">
            <input type="hidden" name="listaprecio_proveedor_id" id="listaprecio_proveedor_id" value="{{ (isset($data) && $data) ? $data->id : '' }}">

            <div class="form-group row">
                <label for="proveedor_id" class="col-lg-3 control-label requerido">Proveedor</label>
                <div class="col-lg-8">
                    <select name="proveedor_id" id="proveedor_id" class="form-control" required {{ isset($visualizar) ? 'disabled' : '' }}>
                        <option value="">Seleccione...</option>
                        @foreach ($proveedor_query as $p)
                            <option value="{{ $p->id }}" {{ (int) old('proveedor_id', (isset($data) && $data) ? ($data->proveedor_id ?? 0) : 0) === (int) $p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label requerido">Fecha lista</label>
                <div class="col-lg-4">
                    <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="nombre" class="col-lg-3 control-label requerido">Nombre</label>
                <div class="col-lg-8">
                    <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="255" value="{{ old('nombre', (isset($data) && $data) ? $data->nombre : '') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="observaciones" class="col-lg-3 control-label">Observaciones</label>
                <div class="col-lg-8">
                    <textarea name="observaciones" id="observaciones" class="form-control" rows="2" {{ isset($visualizar) ? 'readonly' : '' }}>{{ old('observaciones', (isset($data) && $data) ? ($data->observaciones ?? '') : '') }}</textarea>
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
        <div class="col-md-6">
            <div class="form-group row">
                <label for="condicionpago_id" class="col-lg-4 control-label">Condición pago</label>
                <div class="col-lg-8">
                    <select name="condicionpago_id" id="condicionpago_id" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicionpago_query as $c)
                            <option value="{{ $c->id }}" {{ (int) old('condicionpago_id', (isset($data) && $data) ? ($data->condicionpago_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="condicionentrega_id" class="col-lg-4 control-label">Condición entrega</label>
                <div class="col-lg-8">
                    <select name="condicionentrega_id" id="condicionentrega_id" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicionentrega_query as $c)
                            <option value="{{ $c->id }}" {{ (int) old('condicionentrega_id', (isset($data) && $data) ? ($data->condicionentrega_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="condicioncompra_id" class="col-lg-4 control-label">Condición compra</label>
                <div class="col-lg-8">
                    <select name="condicioncompra_id" id="condicioncompra_id" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicioncompra_query as $c)
                            <option value="{{ $c->id }}" {{ (int) old('condicioncompra_id', (isset($data) && $data) ? ($data->condicioncompra_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="moneda_id" class="col-lg-4 control-label">Moneda</label>
                <div class="col-lg-8">
                    <select name="moneda_id" id="moneda_id" class="form-control" {{ isset($visualizar) ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($moneda_query as $m)
                            <option value="{{ $m->id }}" {{ (int) old('moneda_id', (isset($data) && $data) ? ($data->moneda_id ?? 0) : 0) === (int) $m->id ? 'selected' : '' }}>
                                {{ $m->nombre }} ({{ $m->abreviatura ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <hr>
    <h5>Precios por artículo</h5>
    <p class="text-muted small">Puede repetir el mismo artículo con distinta fecha de vigencia (histórico de precios). La importación Excel también agrega renglones con la vigencia indicada.</p>
    <table class="table" id="tabla-articulos-listaprecio">
        <thead>
            <tr>
                <th style="width: 12%;">Artículo</th>
                <th style="width: 18%;">Descripción</th>
                <th style="width: 10%;">Precio</th>
                <th style="width: 8%;">% Desc.</th>
                <th style="width: 12%;">Cód. art. proveedor</th>
                <th style="width: 12%;">Fecha vigencia</th>
                @if(!isset($visualizar))
                <th style="width: 4%;"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $lineas = (isset($data) && $data && $data->listaprecio_proveedor_articulos && $data->listaprecio_proveedor_articulos->count())
                    ? $data->listaprecio_proveedor_articulos
                    : collect([new \App\Models\Compras\Listaprecio_Proveedor_Articulo()]);
            @endphp
            @foreach ($lineas as $idx => $linea)
            <tr class="item-listaprecio-articulo">
                <td>
                    <input type="hidden" class="linea_id" name="linea_ids[]" value="{{ old('linea_ids.'.$idx, $linea->id ?? '') }}">
                    <div class="form-group row celda-articulo-listaprecio">
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
                    <input type="number" step="0.000001" name="precios[]" class="form-control" value="{{ old('precios.'.$idx, $linea->precio ?? '') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </td>
                <td>
                    <input type="number" step="0.01" name="descuentos[]" class="form-control" value="{{ old('descuentos.'.$idx, isset($linea->descuento) ? $linea->descuento : '0') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </td>
                <td>
                    <input type="text" name="codigos_articulo_proveedor[]" class="form-control" maxlength="100" value="{{ old('codigos_articulo_proveedor.'.$idx, $linea->codigo_articulo_proveedor ?? '') }}" {{ isset($visualizar) ? 'readonly' : '' }}>
                </td>
                <td>
                    <input type="date" name="fechavigencias[]" class="form-control" value="{{ old('fechavigencias.'.$idx, (isset($linea->fechavigencia) && $linea->fechavigencia) ? substr($linea->fechavigencia, 0, 10) : date('Y-m-d')) }}" {{ isset($visualizar) ? 'readonly' : '' }} required>
                </td>
                @if(!isset($visualizar))
                <td class="text-center">
                    <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_listaprecio_articulo tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!isset($visualizar))
    <button type="button" class="pull-right btn btn-danger" id="agrega_renglon_listaprecio_articulo">+ Agregar renglón</button>
    @endif
</div>
@include('includes.stock.modalconsultaarticulo')
