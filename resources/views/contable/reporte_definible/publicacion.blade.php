@extends("theme.$theme.layout")
@section('titulo')
    Resultado publicado
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}">
<style>
.rd-exec-row-rubro td { border-top: 1px solid #d5d8dc; }
.rd-exec-row-rubro.negrita td { font-weight: 700; color: #1B4F72; }
.rd-exec-row-cuenta td { color: #566573; font-size: 12.5px; }
.rd-exec-indent { display: inline-block; }
.rd-exec-importe, .rd-exec-col-importe { font-variant-numeric: tabular-nums; white-space: nowrap; min-width: 7.5rem; }
.rd-exec-row-rubro .col-fija-1,
.rd-exec-row-rubro .col-fija-2,
.rd-exec-row-rubro .col-fija-der-1,
.rd-exec-row-rubro .col-fija-der-2 { background: #fff; }
.rd-exec-row-cuenta .col-fija-1,
.rd-exec-row-cuenta .col-fija-2,
.rd-exec-row-cuenta .col-fija-der-1,
.rd-exec-row-cuenta .col-fija-der-2 { background: #fff; }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-md-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $publicacion->nombre }}</h3>
                <div class="card-tools">
                    <a href="{{ route('ver_publicacion_reporte_definible', ['id' => $reporte->id, 'publicacionId' => $publicacion->id, 'formato' => 'PDF']) }}"
                       class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('publicaciones_reporte_definible', ['id' => $reporte->id]) }}"
                       class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong>Documento congelado.</strong>
                    Publicado el {{ $publicacion->created_at?->format('d/m/Y H:i') }}
                    por {{ $publicacion->usuario->nombre ?? '—' }}
                    · Período {{ $publicacion->periodo_texto }}
                    · Empresas {{ is_array($publicacion->filtros['empresa_ids'] ?? null) ? implode(', ', $publicacion->filtros['empresa_ids']) : '' }}
                    · Definición v{{ $publicacion->definicion_version }}
                    · Huella <code>{{ substr((string) $publicacion->hash, 0, 16) }}</code>
                    <div class="small text-muted mt-1">
                        Estos números no se recalculan: son los que se presentaron.
                    </div>
                </div>

                <h4 class="mb-0">{{ $reporte->titulo1 ?: $reporte->nombre }}</h4>
                @if ($reporte->titulo2)
                    <div class="text-muted mb-2">{{ $reporte->titulo2 }}</div>
                @endif

                @include('contable.reporte_definible.partials.tabla_resultado', [
                    'resultado' => $resultado,
                    'puede_drill' => false,
                    'drill_url' => '',
                ])

                @include('contable.reporte_definible.partials.notas_pie', [
                    'notas' => $resultado['notas'] ?? [],
                    'notas_url_admin' => null,
                ])
            </div>
        </div>
    </div>
</div>
@endsection
