@extends("theme.$theme.layout")
@section('titulo')
    Actualizar precios por categoría
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/precio/actualizar-categoria.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">Actualizar precios por categoría</h3>
                <div class="card-tools">
                    <a href="{{ route('precio') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form id="form-actualizar-precios-categoria" class="form-horizontal form--label-right" autocomplete="off">
                @csrf
                <div class="card-body">
                    <div class="alert alert-info">
                        Se calculan nuevos precios sobre los <strong>vigentes al</strong> fecha de referencia,
                        aplicando el porcentaje indicado. Los registros anteriores se conservan en el historial.
                        Solo participan <strong>artículos facturables</strong> de la categoría elegida.
                    </div>
                    <div class="form-group row">
                        <label for="categoria_id" class="col-lg-3 col-form-label requerido">Categoría</label>
                        <div class="col-lg-8">
                            <select name="categoria_id" id="categoria_id" class="form-control" required>
                                <option value="">-- Elija categoría --</option>
                                @foreach ($categoria_query as $categoria)
                                    <option value="{{ $categoria->id }}"
                                        @if ((int) old('categoria_id', $categoriaId) === (int) $categoria->id) selected @endif>
                                        {{ $categoria->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="listaprecio_id" class="col-lg-3 col-form-label">Lista de precios</label>
                        <div class="col-lg-8">
                            <select name="listaprecio_id" id="listaprecio_id" class="form-control">
                                <option value="">Todas las listas</option>
                                @foreach ($listasPrecio as $lista)
                                    <option value="{{ $lista->id }}"
                                        @if ($listaprecioId !== null && (int) $listaprecioId === (int) $lista->id) selected @endif>
                                        {{ $lista->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Opcional. Si no elige lista, se actualizan todas las listas donde el artículo tiene precio vigente.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="porcentaje" class="col-lg-3 col-form-label requerido">Porcentaje de ajuste</label>
                        <div class="col-lg-8">
                            <div class="input-group">
                                <input type="number" name="porcentaje" id="porcentaje" class="form-control"
                                    value="{{ old('porcentaje', $porcentaje) }}" step="0.01" required
                                    placeholder="Ej.: 10 para +10%, -5 para -5%">
                                <span class="input-group-append"><span class="input-group-text">%</span></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="fecha_referencia" class="col-lg-3 col-form-label requerido">Precios vigentes al</label>
                        <div class="col-lg-8">
                            <input type="date" name="fecha_referencia" id="fecha_referencia" class="form-control"
                                value="{{ old('fecha_referencia', $fechaReferencia) }}" required>
                            <small class="form-text text-muted">Fecha desde la cual se toma el precio actual de cada artículo/lista.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nueva_fechavigencia" class="col-lg-3 col-form-label requerido">Nueva vigencia</label>
                        <div class="col-lg-8">
                            <input type="date" name="nueva_fechavigencia" id="nueva_fechavigencia" class="form-control"
                                value="{{ old('nueva_fechavigencia', $nuevaFechavigencia) }}" required>
                            <small class="form-text text-muted">Fecha de vigencia de los nuevos precios calculados (debe ser igual o posterior a la fecha de referencia).</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8">
                            <button type="button" class="btn btn-outline-primary" id="btn-preview-precios-categoria">
                                <i class="fa fa-search"></i> Previsualizar
                            </button>
                        </div>
                    </div>
                    <div id="preview-precios-categoria" class="d-none">
                        <div class="alert alert-secondary" id="preview-precios-categoria-resumen"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>SKU</th>
                                        <th>Artículo</th>
                                        <th>Lista</th>
                                        <th class="text-right">Precio actual</th>
                                        <th class="text-right">Precio nuevo</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-precios-categoria-body"></tbody>
                            </table>
                            <p class="small text-muted" id="preview-precios-categoria-nota"></p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8">
                            <button type="submit" class="btn btn-warning" id="btn-aplicar-precios-categoria" disabled>
                                <i class="fa fa-calculator"></i> Aplicar actualización
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
