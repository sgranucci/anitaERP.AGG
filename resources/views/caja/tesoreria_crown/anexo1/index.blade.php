@extends("theme.$theme.layout")

@section('titulo')
    Anexos
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ filemtime(public_path('assets/css/tabla-ancha-reporte.css')) }}">
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/anexo_orden_columnas.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/anexo_orden_columnas.js')) }}"></script>
    @if (in_array($panel ?? '', ['rescajero', 'anexo3'], true))
        <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/auditoria_importe.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/auditoria_importe.js')) }}"></script>
    @endif
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
@php
    $anexosBaseUrl = route('tesoreria_crown_anexo1');
    $anexosBaseQuery = array_filter([
        'sala_id' => $sala->id ?? null,
        'jornada_id' => $jornada->id ?? null,
    ]);
    $exportAnexoRuta = null;
    $exportAnexoParams = [];
    if ($jornada) {
        $exportAnexoRuta = match ($panel ?? '') {
            'anexo2' => 'listar_anexo2_tesoreria_crown_jornada',
            'rescajero' => 'listar_rescajero_tesoreria_crown_jornada',
            'anexo3' => 'listar_anexo3_tesoreria_crown_jornada',
            'anexo7' => 'listar_anexo7_tesoreria_crown_jornada',
            'anexo8' => 'listar_anexo8_tesoreria_crown_jornada',
            default => 'listar_anexo1_tesoreria_crown_jornada',
        };
        $exportAnexoParams = ($panel ?? '') === 'anexo'
            ? ['id' => $jornada->id, 'formato_anexo' => $anexoVariante ?? 'clasico']
            : (
                ($panel ?? '') === 'anexo2'
                    ? ['id' => $jornada->id, 'grupo_anexo2' => $anexo2['grupo'] ?? 'todo']
                    : ['id' => $jornada->id]
            );
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Anexos
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel={{ urlencode($panel) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @elseif ($sala && can('listar-tesoreria-crown-jornada', false))
                        <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-calendar"></i> Jornadas
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_anexo1',
                'exportRuta' => $exportAnexoRuta,
                'exportQueryparams' => $exportAnexoParams,
            ])
            @include('caja.tesoreria_crown.partials.jornada_selector', [
                'ruta' => 'tesoreria_crown_anexo1',
                'queryExtra' => array_filter([
                    'panel' => $panel ?? 'anexo',
                    'formato_anexo' => ($panel ?? '') === 'anexo' ? ($anexoVariante ?? null) : null,
                    'grupo_anexo2' => ($panel ?? '') === 'anexo2' ? request('grupo_anexo2') : null,
                ]),
            ])
            <div class="card-body">
                @if (! $sala)
                    <p class="mb-0">Elegí una sala.</p>
                @elseif (! $jornada)
                    <p class="mb-2">
                        @if (trim((string) ($fechaPedida ?? '')) !== '')
                            No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }} en esta sala.
                        @else
                            Esta sala no tiene jornada. Abrila para ver el anexo.
                        @endif
                    </p>
                    @if (can('crear-tesoreria-crown-jornada', false))
                        <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus-circle"></i> Ir a abrir jornada
                        </a>
                    @endif
                @elseif ($panel === 'anexo')
                    @include('caja.tesoreria_crown.jornada.partials.anexo1', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                        'anexoIndexUrl' => $anexosBaseUrl,
                        'anexoIndexQuery' => $anexosBaseQuery,
                    ])
                @elseif ($panel === 'anexo2')
                    @include('caja.tesoreria_crown.jornada.partials.anexo2', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                    ])
                @elseif ($panel === 'rescajero')
                    @include('caja.tesoreria_crown.jornada.partials.rescajero', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                    ])
                @elseif ($panel === 'anexo3')
                    @include('caja.tesoreria_crown.jornada.partials.anexo3', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                    ])
                @elseif ($panel === 'anexo7')
                    @include('caja.tesoreria_crown.jornada.partials.anexo7', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                    ])
                @elseif ($panel === 'anexo8')
                    @include('caja.tesoreria_crown.jornada.partials.anexo8', [
                        'anexosBaseUrl' => $anexosBaseUrl,
                        'anexosBaseQuery' => $anexosBaseQuery,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
