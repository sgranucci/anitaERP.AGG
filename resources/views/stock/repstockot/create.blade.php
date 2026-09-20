@extends("theme.$theme.layout")
@section('titulo')
    Reporte stock por OT
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/repstockot/reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/repstockot/reporte.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'repstockot-overlay',
    'tituloId' => 'repstockot-overlay-titulo',
    'subtituloId' => 'repstockot-overlay-subtitulo',
    'titulo' => 'Generando Excel…',
    'subtitulo' => 'Puede demorar según los filtros. Pulse Esc si la descarga ya terminó.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Datos Reporte Stock por OT</h3>
            </div>
            {{-- data-sin-bloqueo-grabacion: el POST descarga attachment y no navega --}}
            <form action="{{route('crear_repstockot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                data-sin-bloqueo-grabacion="1">
                @csrf @method("post")
                <div class="card-body">
                    @include('stock.repstockot.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repstockot'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
