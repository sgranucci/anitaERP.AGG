@extends("theme.$theme.layout")
@section('titulo')
Nueva recepción Surmar
@endsection

@section('scripts')
<script>
window.SURMAR_RECEPCION_CREAR = {
    urls: {
        buscarOc: @json(route('recepcion_proveedor_surmar_buscar_oc_pendientes')),
        precargaOc: @json(route('recepcion_proveedor_surmar_precarga_oc'))
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor_surmar/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor_surmar/crear.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $puedeConsultarOc = can('editar-ordencompra', false) || can('listar-ordencompra', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Nueva recepción Surmar</h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <form action="{{ route('guardar_recepcion_proveedor_surmar') }}" method="POST" id="form-recepcion-surmar" class="form-horizontal" autocomplete="off">
                @csrf
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa_id }}">
                <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ old('ordencompra_id') }}">
                <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ old('proveedor_id') }}">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Seleccione la <strong>orden de compra</strong> (como en Anita / recepción AGG). Luego se abre la carga provisoria:
                        cada ítem se graba con lote/peso y emite etiqueta al aceptar.
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-6">
                            <input type="text" class="form-control" value="Surmar" readonly>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Nº OC</label>
                        <div class="col-lg-6">
                            <div class="d-flex flex-wrap align-items-center" style="gap:4px;">
                                <button type="button" id="btn-consulta-oc-recepcion-modal" class="btn btn-sm btn-outline-primary flex-shrink-0" title="Buscar OC Surmar pendientes">
                                    <i class="fa fa-search"></i>
                                </button>
                                <input type="number"
                                       id="numero_oc_buscar"
                                       name="numero_oc_buscar"
                                       class="form-control flex-grow-1"
                                       placeholder="Número OC"
                                       min="1"
                                       value="{{ old('numero_oc_buscar') }}"
                                       autofocus
                                       title="Enter o Tab para cargar la orden de compra"
                                       style="min-width:6rem;max-width:10rem;">
                                @if ($puedeConsultarOc)
                                    <a href="#"
                                       id="btn-consultar-oc-recepcion-surmar"
                                       class="btn btn-sm btn-info flex-shrink-0 d-none"
                                       target="_blank" rel="noopener"
                                       title="Consultar orden de compra">
                                        <i class="fa fa-file-text-o"></i> Consultar OC
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Proveedor</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="text" id="codigoproveedor" class="form-control" style="max-width:7rem;" readonly placeholder="Cód.">
                                <input type="text" id="proveedor_nombre" class="form-control" readonly placeholder="Se completa al cargar la OC" value="{{ old('proveedor_nombre') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'recepcion_surmar',
                        'layout' => 'form_row',
                        'inputName' => 'deposito_id',
                        'inputId' => 'deposito_id',
                        'depositoId' => old('deposito_id'),
                        'codigo' => old('deposito_codigo', ''),
                        'descripcion' => old('deposito_descripcion', ''),
                        'col_label' => 'col-lg-4 control-label text-right pr-2 requerido',
                        'col_input' => 'col-lg-6',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Observación</label>
                        <div class="col-lg-6">
                            <textarea name="observacion" class="form-control" rows="2">{{ old('observacion') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-arrow-right"></i> Iniciar carga</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaordencompra_recepcion')
@endsection
