@php
    $data = $data ?? null;
    $valorEnum = function (string $campo) use ($data) {
        return old($campo, $data ? ($data->{$campo} ?? '') : '');
    };
    $codigoSeleccionado = (string) old('codigoafip', $data->codigoafip ?? '');
    $codigoNormalizado = $codigoSeleccionado !== ''
        ? \App\Services\Arca\ArcaTiposComprobanteCatalogoService::normalizarCodigoAfip($codigoSeleccionado)
        : '';
    $empresa_query = $empresa_query ?? collect();
    $empresaArcaId = (int) ($empresaArcaId ?? 0);
    $tiposCbteArca = $tiposCbteArca ?? [];
    $codigosArca = array_column($tiposCbteArca, 'codigo');
    $codigoFueraDeArca = $codigoNormalizado !== '' && ! in_array($codigoNormalizado, $codigosArca, true);
    $sincronizadoArcaTexto = $sincronizadoArcaTexto ?? null;
@endphp
<div class="row">
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="abreviatura" class="col-lg-4 control-label text-right pr-2 requerido">Abreviatura</label>
            <div class="col-lg-4">
                <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{ old('abreviatura', $data->abreviatura ?? '') }}" maxlength="5" required>
            </div>
        </div>
        <div class="form-group row" id="tipotransaccion-arca-panel"
             data-url-tipos="{{ route('tipotransaccion_compra_arca_tipos_cbte') }}"
             data-select-id="codigoafip">
            <label for="empresa_arca_id" class="col-lg-4 control-label text-right pr-2">Empresa (ARCA)</label>
            <div class="col-lg-8">
                @if ($empresa_query->isEmpty())
                    <select name="empresa_arca_id" id="empresa_arca_id" class="form-control" disabled>
                        <option value="">Sin empresas con certificado ARCA</option>
                    </select>
                @else
                    @include('includes.form-empresa-asignada-control', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresaArcaId,
                        'id' => 'empresa_arca_id',
                        'name' => 'empresa_arca_id',
                        'required' => false,
                        'mostrar_opcion_vacia' => false,
                        'data_fouc' => true,
                    ])
                @endif
                <small class="form-text text-muted">
                    El listado sale de
                    <code>arca_tipo_comprobante</code>, sincronizado con los web services que tienen puntos de venta activos.
                    @if (!empty($webserviceArcaEtiqueta))
                        Activos:
                        <strong>{{ $webserviceArcaEtiqueta }}</strong>.
                    @endif
                </small>
                @if ($sincronizadoArcaTexto && count($tiposCbteArca) > 0)
                    <small id="tipotransaccion-webservice-arca" class="form-text text-muted">
                        Catálogo local: {{ count($tiposCbteArca) }} tipos (sincronizado {{ $sincronizadoArcaTexto }}).
                    </small>
                @else
                    <small id="tipotransaccion-webservice-arca" class="form-text text-muted">
                        Todavía no hay catálogo local. Use Actualizar desde ARCA.
                    </small>
                @endif
                <div id="tipotransaccion-arca-estado" class="alert alert-info py-2 px-3 mt-2 mb-0 d-none" role="status" aria-live="polite"></div>
                <button type="button" id="btn-actualizar-tipos-arca" class="btn btn-outline-secondary btn-sm mt-2"
                        @if ($empresa_query->isEmpty()) disabled @endif>
                    <i class="fa fa-refresh" id="btn-actualizar-tipos-arca-icono"></i>
                    <i class="fa fa-spinner fa-spin d-none" id="btn-actualizar-tipos-arca-spinner" aria-hidden="true"></i>
                    <span id="btn-actualizar-tipos-arca-texto"> Actualizar desde ARCA</span>
                </button>
            </div>
        </div>
        <div class="form-group row">
            <label for="codigoafip" class="col-lg-4 control-label text-right pr-2 requerido">Tipo comprobante ARCA</label>
            <div class="col-lg-8">
                <select name="codigoafip" id="codigoafip" class="form-control" required data-fouc
                        @if ($empresa_query->isEmpty() && $codigoNormalizado === '') disabled @endif>
                    <option value="">Elija tipo de comprobante ARCA</option>
                    @foreach ($tiposCbteArca as $tipo)
                        @php
                            $tipoCodigo = (string) ($tipo['codigo'] ?? '');
                            $tipoCodigoNorm = $tipoCodigo !== ''
                                ? \App\Services\Arca\ArcaTiposComprobanteCatalogoService::normalizarCodigoAfip($tipoCodigo)
                                : '';
                        @endphp
                        <option value="{{ $tipoCodigo }}" @selected($tipoCodigoNorm !== '' && $tipoCodigoNorm === $codigoNormalizado)>
                            {{ $tipoCodigo }} — {{ $tipo['descripcion'] }}
                        </option>
                    @endforeach
                    @if ($codigoFueraDeArca)
                        <option value="{{ $codigoNormalizado }}" selected>
                            {{ $codigoNormalizado }} — valor actual (no figura en el catálogo)
                        </option>
                    @endif
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="operacion" class="col-lg-4 control-label text-right pr-2 requerido">Operaci&oacute;n</label>
            <div class="col-lg-8">
                <select name="operacion" id="operacion" class="form-control" required>
                    <option value="">Elija operaci&oacute;n</option>
                    @foreach ($operacionEnum as $value => $operacion)
                        <option value="{{ $value }}" @selected($value == $valorEnum('operacion'))>{{ $operacion }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="signo" class="col-lg-4 control-label text-right pr-2 requerido">Signo</label>
            <div class="col-lg-8">
                <select name="signo" id="signo" class="form-control" required>
                    <option value="">Elija signo</option>
                    @foreach ($signoEnum as $value => $signo)
                        <option value="{{ $value }}" @selected($value == $valorEnum('signo'))>{{ $signo }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="estado" class="col-lg-4 control-label text-right pr-2 requerido">Estado</label>
            <div class="col-lg-8">
                <select name="estado" id="estado" class="form-control" required>
                    <option value="">Elija estado</option>
                    @foreach ($estadoEnum as $value => $estado)
                        <option value="{{ $value }}" @selected($value == $valorEnum('estado'))>{{ $estado }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="subdiario" class="col-lg-4 control-label text-right pr-2 requerido">Subdiario IVA</label>
            <div class="col-lg-8">
                <select name="subdiario" id="subdiario" class="form-control" required>
                    <option value="">Elija subdiario</option>
                    @foreach ($subdiarioEnum as $value => $subdiario)
                        <option value="{{ $value }}" @selected($value == $valorEnum('subdiario'))>{{ $subdiario }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="asientocontable" class="col-lg-4 control-label text-right pr-2 requerido">Asiento contable</label>
            <div class="col-lg-8">
                <select name="asientocontable" id="asientocontable" class="form-control" required>
                    <option value="">Elija asiento contable</option>
                    @foreach ($asientocontableEnum as $value => $asientocontable)
                        <option value="{{ $value }}" @selected($value == $valorEnum('asientocontable'))>{{ $asientocontable }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="retieneiva" class="col-lg-4 control-label text-right pr-2 requerido">Retiene IVA</label>
            <div class="col-lg-8">
                <select name="retieneiva" id="retieneiva" class="form-control" required>
                    <option value="">Elija si retiene IVA</option>
                    @foreach ($retieneEnum as $value => $retieneiva)
                        <option value="{{ $value }}" @selected($value == $valorEnum('retieneiva'))>{{ $retieneiva }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="retieneganancia" class="col-lg-4 control-label text-right pr-2 requerido">Retiene ganancias</label>
            <div class="col-lg-8">
                <select name="retieneganancia" id="retieneganancia" class="form-control" required>
                    <option value="">Elija si retiene ganancias</option>
                    @foreach ($retieneEnum as $value => $retieneganancia)
                        <option value="{{ $value }}" @selected($value == $valorEnum('retieneganancia'))>{{ $retieneganancia }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="retieneIIBB" class="col-lg-4 control-label text-right pr-2 requerido">Retiene IIBB</label>
            <div class="col-lg-8">
                <select name="retieneIIBB" id="retieneIIBB" class="form-control" required>
                    <option value="">Elija si retiene IIBB</option>
                    @foreach ($retieneEnum as $value => $retieneIIBB)
                        <option value="{{ $value }}" @selected($value == $valorEnum('retieneIIBB'))>{{ $retieneIIBB }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Centros de costo</h3>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-bordered mb-0" id="centrocosto-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Centro de costo</th>
                    <th style="width: 3rem;"></th>
                </tr>
            </thead>
            <tbody id="tbody-centrocosto-table">
                @foreach ($filasCentrocosto as $fila)
                    @include('compras.tipotransaccion_compra.partials.renglon_centrocosto', [
                        'fila' => $fila,
                        'nro' => $loop->iteration,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="button" id="agrega_renglon_centrocosto" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-plus"></i> Agrega rengl&oacute;n
        </button>
    </div>
</div>

<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Conceptos de IVA compras</h3>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-bordered mb-0" id="concepto_ivacompra-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Concepto de IVA compras</th>
                    <th style="width: 3rem;"></th>
                </tr>
            </thead>
            <tbody id="tbody-concepto-ivacompra-table">
                @foreach ($filasConcepto as $fila)
                    @include('compras.tipotransaccion_compra.partials.renglon_concepto', [
                        'fila' => $fila,
                        'nro' => $loop->iteration,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="button" id="agrega_renglon_concepto_ivacompra" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-plus"></i> Agrega rengl&oacute;n
        </button>
    </div>
</div>

<template id="template-renglon-centrocosto">
    @include('compras.tipotransaccion_compra.partials.renglon_centrocosto', [
        'fila' => ['id' => '', 'codigo' => '', 'nombre' => ''],
        'nro' => 1,
    ])
</template>
<template id="template-renglon-concepto_ivacompra">
    @include('compras.tipotransaccion_compra.partials.renglon_concepto', [
        'fila' => ['id' => '', 'codigo' => '', 'nombre' => ''],
        'nro' => 1,
    ])
</template>
