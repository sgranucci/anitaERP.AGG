@extends("theme.$theme.layout")

@section('titulo')
    Bóveda
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
@if (session('emitir_boveda') && can('imprimir-tesoreria-crown-boveda', false))
    <div class="alert alert-info">
        <a href="{{ route('pdf_tesoreria_crown_boveda', (int) session('emitir_boveda')) }}" target="_blank" rel="noopener" id="emitir-boveda-link">Emitir comprobante</a>
    </div>
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Bóveda
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                    @if ($lugar)
                        — {{ $lugar->codigo }} {{ $lugar->nombre }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada && $lugar && can('listar-tesoreria-crown-boveda-arqueo', false))
                        <a href="{{ route('tesoreria_crown_boveda_arqueo', ['sala_id' => $sala->id ?? null, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-sm tc-tool tc-tool-arqueo">
                            <i class="fa fa-balance-scale"></i> Arqueo de turno
                        </a>
                    @endif
                    @if ($jornada && $lugar && $jornada->estaAbierta() && can('crear-tesoreria-crown-boveda', false))
                        <a href="{{ route('nueva_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-primary btn-sm tc-tool tc-tool-nuevo">
                            <i class="fa fa-plus-circle"></i> Nuevo movimiento
                        </a>
                    @endif
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=boveda{{ $lugar ? '&deposito_id='.$lugar->id : '' }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_boveda',
                'exportRuta' => ($jornada && $lugar) ? 'listar_tesoreria_crown_boveda' : null,
                'exportQueryparams' => array_merge($filtrosQuery ?? [], [
                    'columnas' => implode(',', $columnasVisibles ?? []),
                ]),
            ])
            @include('caja.tesoreria_crown.partials.jornada_selector', [
                'ruta' => 'tesoreria_crown_boveda',
                'queryExtra' => array_filter([
                    'deposito_id' => $lugar->id ?? null,
                ]),
            ])
            <div class="card-body">
                @if (! $jornada)
                    <p>
                        @if (trim((string) ($fechaPedida ?? '')) !== '')
                            No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }} en esta sala.
                        @else
                            Esta sala no tiene jornada. Abrila para cargar movimientos.
                        @endif
                    </p>
                @else
                    @include('caja.tesoreria_crown.boveda.partials.panel', [
                        'enEscritorio' => false,
                        'rutaListado' => 'tesoreria_crown_boveda',
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @if (session('emitir_boveda') && can('imprimir-tesoreria-crown-boveda', false))
        <script>
            window.open(@json(route('pdf_tesoreria_crown_boveda', (int) session('emitir_boveda'))), 'bovedaEmitir');
        </script>
    @endif
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}"></script>
    @php $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js'); @endphp<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/boveda_panel.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/boveda_panel.js')) }}"></script>
@endsection
