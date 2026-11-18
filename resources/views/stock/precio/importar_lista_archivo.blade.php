@extends("theme.$theme.layout")
@section('titulo')
    Importar lista PDF/DOCX
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/listaprecio/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/moneda/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/moneda/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script>
    window.precioArchivoPreviewUrl = @json(route('precio_importar_lista_archivo_preview'));
</script>
<script src="{{ asset('assets/pages/scripts/stock/precio/importar_lista_archivo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/precio/importar_lista_archivo.js')) ?: time() }}" type="text/javascript"></script>
@if (session('precio_archivo_import_resultado'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('banner-resultado-import-archivo');
        if (banner) {
            banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endif
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'lista-archivo-import-overlay',
    'tituloId' => 'lista-archivo-import-titulo',
    'subtituloId' => 'lista-archivo-import-subtitulo',
    'titulo' => 'Importando lista…',
    'subtitulo' => 'Puede demorar según el archivo. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar lista de precios (PDF / DOCX)</h3>
                <div class="card-tools">
                    <a href="{{ route('precio') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('precio_importar_lista_archivo_ejecutar') }}"
                  id="form-import-lista-archivo"
                  class="form-horizontal form--label-right"
                  method="POST"
                  autocomplete="off"
                  enctype="multipart/form-data"
                  data-enter-campos="1">
                @csrf
                <div class="card-body">
                    @include('stock.precio.partials.flash_resultado_importacion_archivo')

                    <div class="alert alert-info small mb-3">
                        <strong>Formato estándar Interforming</strong> (Bagnara, Agro, listas de cliente):
                        columnas <code>Artículo</code> · <code>Detalle</code> · <code>Precio Lista</code>
                        · <code>Precio Min ML</code> (opcional, se ignora).
                        Un SKU por fila; rubros como títulos de sección; pie opcional
                        <code>Vigencia dd/mm/aaaa</code>. Preferí <strong>PDF</strong> si el Word junta dos SKU en una celda.
                    </div>

                    <div class="form-group row">
                        <label for="file" class="col-lg-3 control-label text-right pr-2 requerido">Archivo</label>
                        <div class="col-lg-8">
                            <input type="file" name="file" id="file" class="form-control"
                                   accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   required>
                            <small class="form-text text-muted">PDF o DOCX. Al elegir el archivo se genera la vista previa.</small>
                        </div>
                    </div>

                    @include('stock.partials.campo_consulta_listaprecio', [
                        'prefix' => 'lista_archivo',
                        'label' => 'Lista destino',
                        'listaprecioId' => $listaprecioId,
                        'codigo' => $listaprecioCodigo,
                        'nombre' => $listaprecioNombre,
                        'required' => true,
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])

                    @include('configuracion.partials.campo_consulta_moneda', [
                        'prefix' => 'lista_archivo',
                        'label' => 'Moneda',
                        'monedaId' => $monedaId,
                        'codigo' => $monedaCodigo,
                        'nombre' => $monedaNombre,
                        'required' => true,
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])

                    <div class="form-group row">
                        <label for="fechavigencia" class="col-lg-3 control-label text-right pr-2 requerido">Vigencia</label>
                        <div class="col-lg-8">
                            <input type="date" name="fechavigencia" id="fechavigencia" class="form-control"
                                   value="{{ $fechavigencia }}" required style="max-width: 12rem;">
                            <small class="form-text text-muted" id="ayuda-vigencia-archivo">
                                Si el archivo declara vigencia, se sugiere al previsualizar.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8">
                            <button type="button" id="btn-preview-lista-archivo" class="btn btn-outline-primary btn-sm" disabled>
                                <i class="fa fa-eye"></i> Vista previa
                            </button>
                        </div>
                    </div>

                    <div id="panel-preview-lista-archivo" class="card card-outline card-info d-none mt-3">
                        <div class="card-header py-2">
                            <h3 class="card-title mb-0">
                                Vista previa
                                <span id="badge-preview-lista-archivo" class="badge badge-secondary ml-1">Sin datos</span>
                            </h3>
                        </div>
                        <div class="card-body" id="contenido-preview-lista-archivo">
                            <p class="text-muted mb-0 small">Elija un archivo para analizar la lista.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" id="btn-importar-lista-archivo" class="btn btn-primary btn-sm" disabled>
                        <i class="fa fa-upload"></i> Importar precios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultalistaprecio')
@include('includes.configuracion.modalconsultamoneda')
@endsection
