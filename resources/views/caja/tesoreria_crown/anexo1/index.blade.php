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
    $modoRango = (bool) ($modoRango ?? false);
    $anexosBaseUrl = route('tesoreria_crown_anexo1');
    $anexosBaseQuery = array_filter([
        'sala_id' => $sala->id ?? null,
        'jornada_id' => (! $modoRango && ($jornada->id ?? null)) ? $jornada->id : null,
        'fecha_desde' => $modoRango ? ($fechaDesde ?? null) : null,
        'fecha_hasta' => $modoRango ? ($fechaHasta ?? null) : null,
        'consultar' => $modoRango && ($consultar ?? false) ? 1 : null,
    ]);
    $exportAnexoRuta = null;
    $exportAnexoParams = [];
    $hayDatosExport = $modoRango
        ? (($jornadasRango ?? collect())->isNotEmpty() && ($consultar ?? false))
        : (bool) $jornada;
    if ($hayDatosExport) {
        $exportAnexoRuta = match ($panel ?? '') {
            'anexo2' => 'listar_anexo2_tesoreria_crown_jornada',
            'rescajero' => 'listar_rescajero_tesoreria_crown_jornada',
            'anexo3' => 'listar_anexo3_tesoreria_crown_jornada',
            'anexo7' => 'listar_anexo7_tesoreria_crown_jornada',
            'anexo8' => 'listar_anexo8_tesoreria_crown_jornada',
            default => 'listar_anexo1_tesoreria_crown_jornada',
        };
        if ($modoRango && in_array($panel ?? '', ['anexo', 'anexo8'], true)) {
            $exportAnexoParams = array_filter([
                'sala_id' => $sala->id ?? null,
                'fecha_desde' => $fechaDesde ?? null,
                'fecha_hasta' => $fechaHasta ?? null,
                'formato_anexo' => ($panel ?? '') === 'anexo' ? ($anexoVariante ?? 'clasico') : null,
                'origen' => 'menu',
            ]);
        } else {
            $exportAnexoParams = ($panel ?? '') === 'anexo'
                ? ['id' => $jornada->id, 'formato_anexo' => $anexoVariante ?? 'clasico']
                : (
                    ($panel ?? '') === 'anexo2'
                        ? ['id' => $jornada->id, 'grupo_anexo2' => $anexo2['grupo'] ?? 'todo']
                        : ['id' => $jornada->id]
                );
        }
    }
    $tituloRango = '';
    if ($modoRango && ($fechaDesde ?? null) && ($fechaHasta ?? null)) {
        $tituloRango = $fechaDesde === $fechaHasta
            ? \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y')
            : \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y')
                .' al '.\Illuminate\Support\Carbon::parse($fechaHasta)->format('d/m/Y');
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Anexos
                    @if ($tituloRango !== '')
                        · {{ $tituloRango }}
                    @elseif ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada && (! $modoRango || ($jornadasRango ?? collect())->count() === 1))
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
                'queryExtra' => array_filter([
                    'panel' => $panel ?? 'anexo',
                    'fecha_desde' => $modoRango ? ($fechaDesde ?? null) : null,
                    'fecha_hasta' => $modoRango ? ($fechaHasta ?? null) : null,
                    'consultar' => $modoRango && ($consultar ?? false) ? 1 : null,
                    'formato_anexo' => ($panel ?? '') === 'anexo' ? ($anexoVariante ?? null) : null,
                ]),
                'exportRuta' => $exportAnexoRuta,
                'exportQueryparams' => $exportAnexoParams,
            ])
            @if ($modoRango)
                @include('caja.tesoreria_crown.partials.jornada_selector_rango', [
                    'ruta' => 'tesoreria_crown_anexo1',
                    'fechaDesde' => $fechaDesde ?? null,
                    'fechaHasta' => $fechaHasta ?? null,
                    'jornadasRango' => $jornadasRango ?? collect(),
                    'queryExtra' => array_filter([
                        'panel' => $panel ?? 'anexo',
                        'formato_anexo' => ($panel ?? '') === 'anexo' ? ($anexoVariante ?? null) : null,
                    ]),
                ])
            @else
                @include('caja.tesoreria_crown.partials.jornada_selector', [
                    'ruta' => 'tesoreria_crown_anexo1',
                    'queryExtra' => array_filter([
                        'panel' => $panel ?? 'anexo',
                        'formato_anexo' => ($panel ?? '') === 'anexo' ? ($anexoVariante ?? null) : null,
                        'grupo_anexo2' => ($panel ?? '') === 'anexo2' ? request('grupo_anexo2') : null,
                    ]),
                ])
            @endif
            <div class="card-body">
                @if (! $sala)
                    <p class="mb-0">Elegí una sala.</p>
                @elseif ($modoRango && ! ($consultar ?? false))
                    <p class="mb-0 text-muted">Indicá el rango de fechas y pulsá Consultar.</p>
                @elseif ($modoRango && ($jornadasRango ?? collect())->isEmpty())
                    <p class="mb-2">
                        No hay jornadas
                        @if (($fechaDesde ?? null) && ($fechaHasta ?? null))
                            del {{ \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y') }}
                            al {{ \Illuminate\Support\Carbon::parse($fechaHasta)->format('d/m/Y') }}
                        @endif
                        en esta sala.
                    </p>
                    @if (can('crear-tesoreria-crown-jornada', false))
                        <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus-circle"></i> Ir a abrir jornada
                        </a>
                    @endif
                @elseif (! $modoRango && ! $jornada)
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
                    @if ($modoRango && count($anexosRango ?? []) > 1)
                        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs', [
                            'anexosBaseUrl' => $anexosBaseUrl,
                            'anexosBaseQuery' => $anexosBaseQuery,
                            'jornada' => $jornada,
                        ])
                        @foreach ($anexosRango as $bloque)
                            @php
                                $jornada = $bloque['jornada'];
                                $anexo = $bloque['anexo'];
                                $anexoError = $bloque['error'];
                            @endphp
                            <h5 class="mt-3 mb-2 border-bottom pb-1">
                                {{ $jornada->fecha->format('d/m/Y') }}
                                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo"
                                   class="btn btn-outline-info btn-sm ml-2" target="_blank" rel="noopener">
                                    <i class="fa fa-desktop"></i>
                                </a>
                            </h5>
                            @include('caja.tesoreria_crown.jornada.partials.anexo1', [
                                'anexosBaseUrl' => $anexosBaseUrl,
                                'anexosBaseQuery' => $anexosBaseQuery,
                                'anexoIndexUrl' => $anexosBaseUrl,
                                'anexoIndexQuery' => $anexosBaseQuery,
                                'ocultarTabsAnexo' => true,
                            ])
                        @endforeach
                    @else
                        @include('caja.tesoreria_crown.jornada.partials.anexo1', [
                            'anexosBaseUrl' => $anexosBaseUrl,
                            'anexosBaseQuery' => $anexosBaseQuery,
                            'anexoIndexUrl' => $anexosBaseUrl,
                            'anexoIndexQuery' => $anexosBaseQuery,
                        ])
                    @endif
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
                    @if ($modoRango && count($anexos8Rango ?? []) > 1)
                        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs', [
                            'anexosBaseUrl' => $anexosBaseUrl,
                            'anexosBaseQuery' => $anexosBaseQuery,
                            'jornada' => $jornada,
                        ])
                        @foreach ($anexos8Rango as $bloque)
                            @php
                                $jornada = $bloque['jornada'];
                                $anexo8 = $bloque['anexo8'];
                                $anexo8Error = $bloque['error'];
                            @endphp
                            <h5 class="mt-3 mb-2 border-bottom pb-1">
                                {{ $jornada->fecha->format('d/m/Y') }}
                                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo8"
                                   class="btn btn-outline-info btn-sm ml-2" target="_blank" rel="noopener">
                                    <i class="fa fa-desktop"></i>
                                </a>
                            </h5>
                            @include('caja.tesoreria_crown.jornada.partials.anexo8', [
                                'anexosBaseUrl' => $anexosBaseUrl,
                                'anexosBaseQuery' => $anexosBaseQuery,
                                'ocultarTabsAnexo' => true,
                            ])
                        @endforeach
                    @else
                        @include('caja.tesoreria_crown.jornada.partials.anexo8', [
                            'anexosBaseUrl' => $anexosBaseUrl,
                            'anexosBaseQuery' => $anexosBaseQuery,
                        ])
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
