@extends("theme.$theme.layout")

@section('titulo')
    Alta masiva de stock
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/alta_masiva.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/alta_masiva.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Alta masiva desde Excel</h3>
                <div class="card-tools">
                    <a href="{{ route('movimientostock') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form id="form-alta-masiva-stock"
                method="POST"
                action="{{ route('alta_masiva_movimientostock_grabar') }}"
                enctype="multipart/form-data"
                data-enter-campos="1"
                data-preview-url="{{ route('alta_masiva_movimientostock_preview') }}">
                @csrf
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        El Excel trae la curva: SKU, combinación, color y una columna por talle.
                        Puede haber más de un bloque (por ejemplo femenino y masculino) y filas de total, que se ignoran.
                        Empresa, tipo de transacción, depósito, fecha, lote y leyenda no vienen en el archivo.
                    </p>
                    @if ($esFerli)
                        <div class="alert alert-info py-2">
                            En Ferli el depósito tiene que ser un <strong>local</strong>.
                            Los artículos y las combinaciones se resuelven en el catálogo de locales.
                        </div>
                    @else
                        <div class="alert alert-info py-2">
                            Los artículos se resuelven en el catálogo estándar del movimiento de stock.
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            @include('stock.partials.campo_consulta_tipotransaccion_stock', [
                                'prefix' => 'altamasiva',
                                'tipoId' => (int) old('tipotransaccion_stock_id', $tipoId),
                                'abreviatura' => $tipoAbreviatura,
                                'nombre' => $tipoNombre,
                                'pide_precio' => $tipoPidePrecio,
                                'col_label' => 'col-lg-4 col-form-label text-right pr-2',
                                'col_input' => 'col-lg-8',
                            ])
                            <div class="form-group row mb-2">
                                <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                                <div class="col-lg-8">
                                    <input type="date" name="fecha" id="fecha" class="form-control" required
                                        value="{{ old('fecha', date('Y-m-d')) }}">
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label for="lote" class="col-lg-4 control-label text-right pr-2">Lote</label>
                                <div class="col-lg-8">
                                    <input type="text" name="lote" id="lote" class="form-control" maxlength="40"
                                        value="{{ old('lote', 'LOTE DE ALTA') }}">
                                    <small class="text-muted">Vacío o «LOTE DE ALTA» asigna un número nuevo.</small>
                                </div>
                            </div>
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => old('empresa_id', $empresa_id),
                                'col_label' => 'col-lg-4 text-right pr-2',
                                'col_input' => 'col-lg-8',
                            ])
                        </div>
                        <div class="col-md-6">
                            @include('stock.partials.campo_consulta_deposito', [
                                'prefix' => 'altamasiva',
                                'layout' => 'form_row',
                                'label' => 'Depósito',
                                'inputName' => 'deposito_id',
                                'inputId' => 'deposito_id',
                                'depositoId' => old('deposito_id', $depositoId),
                                'codigo' => $depositoCodigo,
                                'descripcion' => $depositoNombre,
                                'canal' => $depositoCanal,
                                'col_label' => 'col-lg-4 col-form-label text-right pr-2',
                                'col_input' => 'col-lg-8',
                                'ayuda' => $esFerli ? 'Tiene que ser un local.' : '',
                            ])
                            <div class="form-group row mb-2">
                                <label for="leyenda" class="col-lg-4 control-label text-right pr-2">Leyenda</label>
                                <div class="col-lg-8">
                                    <input type="text" name="leyenda" id="leyenda" class="form-control" maxlength="200"
                                        value="{{ old('leyenda') }}" placeholder="Ej. Alta pedido BOA julio">
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label for="precio" class="col-lg-4 control-label text-right pr-2">Precio</label>
                                <div class="col-lg-8">
                                    <input type="number" name="precio" id="precio" class="form-control" min="0" step="0.01"
                                        value="{{ old('precio') }}" placeholder="0">
                                    <small class="text-muted">Obligatorio solo si el tipo de transacción pide precio. Si queda vacío, el movimiento usa el precio del artículo.</small>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label for="archivo" class="col-lg-4 control-label text-right pr-2 requerido">Excel</label>
                                <div class="col-lg-8">
                                    <input type="file" name="archivo" id="archivo" class="form-control" required
                                        accept=".xlsx,.xls,.csv">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center mb-3" style="gap: 6px;">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-preview-alta-masiva">
                            <i class="fa fa-search"></i> Vista previa
                        </button>
                        <button type="submit" class="btn btn-secondary btn-sm" id="btn-grabar-alta-masiva" title="Primero genere la vista previa sin errores.">
                            <i class="fa fa-save"></i> Grabar comprobante
                        </button>
                        <span class="text-muted small" id="alta-masiva-resumen"></span>
                    </div>
                    <div id="alta-masiva-alertas"></div>
                    <div class="table-responsive d-none" id="alta-masiva-tabla-wrap">
                        <table class="table table-sm table-bordered mb-0" id="alta-masiva-tabla">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Fila</th>
                                    <th>SKU</th>
                                    <th>Artículo</th>
                                    <th>Comb.</th>
                                    <th>Color Excel</th>
                                    <th>Color maestro</th>
                                    <th>Talles</th>
                                    <th class="text-right">Pares</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'alta-masiva-overlay',
    'tituloId' => 'alta-masiva-overlay-titulo',
    'subtituloId' => 'alta-masiva-overlay-subtitulo',
    'titulo' => 'Leyendo el Excel…',
    'subtitulo' => 'Puede demorar un momento. No cierre la página.',
])
@include('includes.stock.modalconsultatipotransaccionstock')
@include('includes.stock.modalconsultadeposito')
@endsection
