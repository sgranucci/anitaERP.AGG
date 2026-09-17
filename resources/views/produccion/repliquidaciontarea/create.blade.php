@extends("theme.$theme.layout")
@section('titulo')
    Liquidaci&oacute;n de tareas de OT
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/produccion/tarea/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/produccion/tarea/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/produccion/empleado/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/produccion/empleado/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/produccion/repliquidaciontarea/reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/produccion/repliquidaciontarea/reporte.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'liquidacion-tarea-overlay',
    'tituloId' => 'liquidacion-tarea-overlay-titulo',
    'subtituloId' => 'liquidacion-tarea-overlay-subtitulo',
    'titulo' => 'Generando reporte…',
    'subtitulo' => 'Puede demorar según el período. Pulse Esc si la descarga ya terminó.',
])
@php
    $puedeImportarTareasL8 = \App\Support\Configuracion\EntornoEmpresaSupport::esFerli()
        && can('importar-pedido-l8', false);
@endphp
@if ($puedeImportarTareasL8)
    @include('includes.proceso_overlay_aviso', [
        'overlayId' => 'overlay-importar-tareas-l8-liquidacion',
        'tituloId' => 'overlay-importar-tareas-l8-liquidacion-titulo',
        'subtituloId' => 'overlay-importar-tareas-l8-liquidacion-subtitulo',
        'titulo' => 'Importando tareas desde L8…',
        'subtitulo' => 'Altas faltantes y actualización de fechas de finalización. No cierre la página.',
    ])
@endif
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-calculator"></i> Liquidaci&oacute;n de tareas de OT</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if ($puedeImportarTareasL8)
                        <form id="form-importar-tareas-l8-liquidacion" method="post"
                              action="{{ route('repliquidaciontarea_importar_tareas_l8') }}"
                              class="d-inline mr-1" autocomplete="off">
                            @csrf
                            <input type="hidden" name="desdefecha" id="import_l8_desdefecha" value="{{ $valores['desdefecha'] ?? date('Y-m-01') }}">
                            <input type="hidden" name="hastafecha" id="import_l8_hastafecha" value="{{ $valores['hastafecha'] ?? date('Y-m-d') }}">
                            <button type="submit" class="btn btn-outline-warning btn-sm"
                                    title="Trae de L8 las tareas faltantes y actualiza fechas de finalización (no duplica)">
                                <i class="fa fa-download"></i> Traer tareas L8 faltantes
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('rep_liquidaciontarea') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            {{-- data-sin-bloqueo-grabacion: el POST descarga attachment y no navega --}}
            <form action="{{ route('crear_repliquidaciontarea') }}" id="form-general"
                class="form-horizontal form--label-right mb-0" method="POST" autocomplete="off"
                data-sin-bloqueo-grabacion="1">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe de liquidaci&oacute;n de tareas por empleado, OT y art&iacute;culo.
                        Los rangos vac&iacute;os (cliente, tarea, empleado, art&iacute;culo) se interpretan como
                        <strong>todos</strong>. Use <kbd>F1</kbd> o la lupa para consultar; <kbd>Enter</kbd> resuelve el c&oacute;digo.
                        @if ($puedeImportarTareasL8)
                            El bot&oacute;n <strong>Traer tareas L8 faltantes</strong> usa el rango de fechas del formulario:
                            inserta ids que a&uacute;n no existen y actualiza <em>hastafecha</em> / <em>desdefecha</em> de las ya cargadas.
                        @endif
                    </p>
                    @include('produccion.repliquidaciontarea.form', ['valores' => $valores ?? [], 'estadoOt_enum' => $estadoOt_enum])
                </div>
                <div class="card-footer">
                    <button type="submit" name="extension" value="Genera Reporte en PDF" class="btn btn-app bg-danger">
                        <i class="fas fa-file-pdf"></i> Pdf
                    </button>
                    <button type="submit" name="extension" value="Genera Reporte en Excel" class="btn btn-app bg-success">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button type="submit" name="extension" value="Genera Reporte en CSV" class="btn btn-app bg-warning">
                        <i class="fas fa-file-csv"></i> Csv
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.ventas.modalconsultacliente')
@include('includes.stock.modalconsultaarticulo')
@include('includes.produccion.modalconsultatarea')
@include('includes.produccion.modalconsultaempleado')
@endsection
