@extends("theme.$theme.layout")
@section('titulo')
    Unificar clientes UIF
@endsection

@section('styles')
<style>
.uif-unificar-workbench .uif-ficha-meta dt {
    font-weight: 600;
    color: #5d6d7e;
    width: 38%;
}
.uif-unificar-workbench .uif-ficha-meta dd {
    margin-bottom: 0.35rem;
}
.uif-unificar-workbench .origen-chip {
    display: inline-block;
    padding: 0.15rem 0.55rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    background: #d6eaf8;
    color: #1b4f72;
}
.uif-unificar-workbench .stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #f4f6f7;
    font-size: 0.8rem;
    margin-right: 0.35rem;
    margin-bottom: 0.25rem;
}
.uif-unificar-workbench .preview-empty {
    border: 1px dashed #bdc3c7;
    border-radius: 0.35rem;
    padding: 1.5rem;
    text-align: center;
    color: #7f8c8d;
}
.uif-unificar-workbench .table thead th {
    background: #85C1E9;
    color: #17202A;
}
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/uif/cliente_uif/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/uif/cliente_uif/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/uif/unificar_clientes/unificar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/uif/unificar_clientes/unificar.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row uif-unificar-workbench">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        <div class="card card-info mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-object-ungroup"></i> Unificar clientes UIF
                </h3>
                <div class="card-tools">
                    @include('includes.uif.boton-manual')
                    <a href="{{ route('consulta_cliente_uif') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body pb-2">
                <p class="text-muted mb-0">
                    Elija el cliente a <strong>conservar</strong> (ficha que queda) y el de <strong>absorber</strong>
                    (ficha a eliminar). Pueden ser de <strong>distintas salas</strong> (BSA / KSA / RSA):
                    los premios se mueven conservando la sala donde se ganaron.
                    La exportación XML / informe UIF sigue filtrando por sala del premio.
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card card-outline card-success h-100">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa fa-check-circle text-success"></i> Conservar
                        </h3>
                        <span class="badge badge-success float-right">Queda</span>
                    </div>
                    <div class="card-body">
                        @include('uif.partials.campo_consulta_cliente_uif', [
                            'prefix' => 'conservar',
                            'label' => 'Cliente correcto',
                            'inputName' => 'conservar_id',
                            'inputId' => 'cliente_uif_conservar_id',
                            'clienteId' => '',
                            'codigo' => '',
                            'descripcion' => '',
                            'col_label' => 'col-lg-4',
                            'col_input' => 'col-lg-8',
                        ])
                        <div id="ficha-conservar" class="uif-ficha-detalle mt-2 d-none"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card card-outline card-warning h-100">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa fa-exclamation-triangle text-warning"></i> Absorber
                        </h3>
                        <span class="badge badge-warning float-right">Se elimina</span>
                    </div>
                    <div class="card-body">
                        @include('uif.partials.campo_consulta_cliente_uif', [
                            'prefix' => 'absorber',
                            'label' => 'Cliente duplicado',
                            'inputName' => 'absorber_id',
                            'inputId' => 'cliente_uif_absorber_id',
                            'clienteId' => '',
                            'codigo' => '',
                            'descripcion' => '',
                            'col_label' => 'col-lg-4',
                            'col_input' => 'col-lg-8',
                        ])
                        <div id="ficha-absorber" class="uif-ficha-detalle mt-2 d-none"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="banner-validacion" class="alert d-none" role="alert"></div>

        <div class="card card-outline card-primary mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-exchange-alt"></i> Preview de unificación
                </h3>
            </div>
            <div class="card-body" id="preview-body">
                <div class="preview-empty" id="preview-vacio">
                    Seleccione ambos clientes para ver qué se moverá y qué se descartará por conflicto.
                </div>
                <div id="preview-contenido" class="d-none">
                    <div class="mb-3" id="preview-resumen-pills"></div>

                    <h5 class="mb-2">Premios que se mueven</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Sala</th>
                                    <th>Juego</th>
                                    <th>Anita premio</th>
                                </tr>
                            </thead>
                            <tbody id="preview-premios"></tbody>
                        </table>
                    </div>

                    <div id="bloque-premios-conflicto" class="d-none mb-3">
                        <h5 class="text-danger mb-2">Premios en conflicto (no se mueven; se eliminan del absorbido)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Sala</th>
                                        <th>Anita premio</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-premios-conflicto"></tbody>
                            </table>
                        </div>
                    </div>

                    <h5 class="mb-2">Archivos que se mueven</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Archivo</th>
                                </tr>
                            </thead>
                            <tbody id="preview-archivos"></tbody>
                        </table>
                    </div>

                    <h5 class="mb-2">Riesgos que se mueven</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Período</th>
                                    <th>Riesgo</th>
                                </tr>
                            </thead>
                            <tbody id="preview-riesgos"></tbody>
                        </table>
                    </div>

                    <div id="bloque-riesgos-conflicto" class="d-none mb-2">
                        <h5 class="text-danger mb-2">Riesgos con período ya existente (se descartan del absorbido)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Período</th>
                                        <th>Riesgo</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-riesgos-conflicto"></tbody>
                            </table>
                        </div>
                    </div>

                    <p class="small text-muted mb-0" id="preview-nota-inro"></p>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-danger" id="btn-abrir-confirmar" disabled>
                    <i class="fa fa-object-ungroup"></i> Unificar y eliminar ficha absorbida
                </button>
            </div>
        </div>
    </div>
</div>

@include('includes.uif.modalconsultacliente_uif')

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'uif-unificar-overlay',
    'tituloId' => 'uif-unificar-titulo',
    'subtituloId' => 'uif-unificar-subtitulo',
    'titulo' => 'Unificando clientes…',
    'subtitulo' => 'No cierre la página.',
])

<div class="modal fade" id="modal-confirmar-unificar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fa fa-exclamation-triangle"></i> Confirmar unificación
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Esta acción es <strong>irreversible</strong>. La ficha absorbida se borrará físicamente.</p>
                <ul class="mb-3" id="confirmar-resumen-lista"></ul>
                <div class="form-group mb-0">
                    <label for="confirmacion-unificar" class="requerido">Escriba <code>UNIFICAR</code> para continuar</label>
                    <input type="text" id="confirmacion-unificar" class="form-control" autocomplete="off" placeholder="UNIFICAR">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-ejecutar-unificar" disabled>
                    Confirmar unificación
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" name="_token" value="{{ csrf_token() }}">
<script>
window.uifUnificarUrls = {
    preview: @json(route('preview_unificar_clientes_uif')),
    ejecutar: @json(route('ejecutar_unificar_clientes_uif'))
};
</script>
@endsection
