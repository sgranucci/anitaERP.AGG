@extends("theme.$theme.layout")
@section('titulo')
    Actualizar precios por categoría
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/precio/actualizar-categoria.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $listaIdValor = $listaprecioId !== null ? (int) $listaprecioId : 0;
    $listaCodigo = '';
    $listaNombre = '';
    if ($listaIdValor > 0) {
        $listaSel = collect($listasPrecio ?? [])->first(function ($lista) use ($listaIdValor) {
            return (int) $lista->id === $listaIdValor;
        });
        if ($listaSel) {
            $listaCodigo = (string) ($listaSel->codigo ?? '');
            $listaNombre = (string) ($listaSel->nombre ?? '');
        }
    }
    $puedeAbrirAbmLista = can('editar-listaprecio', false) || can('listar-listaprecio', false);
@endphp
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
                        <label for="categoria_id" class="col-lg-3 control-label text-right pr-2 requerido">Categoría</label>
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
                    <div class="form-group row tm-listaprecio-campo">
                        <label for="codigolistaprecio" class="col-lg-3 control-label text-right pr-2">Lista de precios</label>
                        <div class="col-lg-8">
                            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                                <input type="hidden" name="listaprecio_id" id="listaprecio_id" class="listaprecio_id"
                                    value="{{ $listaIdValor > 0 ? $listaIdValor : '' }}">
                                <button type="button" title="Consulta listas de precios (F1)" class="btn-accion-tabla consultalistaprecio tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                @if ($puedeAbrirAbmLista)
                                    <a href="{{ $listaIdValor > 0 ? route('editar_listaprecio', ['id' => $listaIdValor, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                                        target="_blank" rel="noopener"
                                        class="btn-accion-tabla btn-link-editar-listaprecio tooltipsC flex-shrink-0 {{ $listaIdValor > 0 ? '' : 'd-none' }}"
                                        title="Consultar lista de precios en ABM">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                <input type="text" class="form-control codigolistaprecio flex-shrink-0" id="codigolistaprecio"
                                    value="{{ $listaCodigo }}" placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;"
                                    title="C&oacute;digo de lista (Enter / F1). Vac&iacute;o = todas">
                                <input type="text" class="form-control nombrelistaprecio" id="nombrelistaprecio"
                                    value="{{ $listaNombre }}" placeholder="Todas las listas" readonly
                                    style="min-width: 0; flex: 1 1 auto;">
                            </div>
                            <small class="form-text text-muted">Opcional. Vacío / sin código = todas las listas con precio vigente.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="porcentaje" class="col-lg-3 control-label text-right pr-2 requerido">Porcentaje de ajuste</label>
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
                        <label for="fecha_referencia" class="col-lg-3 control-label text-right pr-2 requerido">Precios vigentes al</label>
                        <div class="col-lg-8">
                            <input type="date" name="fecha_referencia" id="fecha_referencia" class="form-control"
                                value="{{ old('fecha_referencia', $fechaReferencia) }}" required>
                            <small class="form-text text-muted">Fecha desde la cual se toma el precio actual de cada artículo/lista.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nueva_fechavigencia" class="col-lg-3 control-label text-right pr-2 requerido">Nueva vigencia</label>
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
                                <thead style="background:#85C1E9;color:#17202A;">
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
@include('includes.stock.modalconsultalistaprecio')
@endsection
