@extends("theme.$theme.layout")
@section('titulo')
    Sesión de impresión
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/pages/css/ventas/programa_impresion/form.css') }}?v={{ @filemtime(public_path('assets/pages/css/ventas/programa_impresion/form.css')) ?: time() }}">
@endsection

@section('scripts')
<script>
window.impresionSesionAuto = @json((bool) ($autoEjecutar ?? false));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/programa_impresion/sesion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/programa_impresion/sesion.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Sesión de impresión de comprobantes</h3>
                <div class="card-tools">
                    <a href="{{ $volverUrl ?? route('factura') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                    <a href="{{ route('configurar_salida', ['programa' => $programaSeteo, 'retorno' => url()->full()]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-cog"></i> Configura salida
                    </a>
                </div>
            </div>
            <div class="card-body">
                @php
                    $origenTipo = $sesion['origen_tipo'] ?? '';
                    $docOrigen = $sesion['documentos'][$origenTipo] ?? null;
                @endphp
                @if ($docOrigen)
                    <p class="mb-1 h5">
                        {{ $docOrigen['codigo'] ?? ('#'.($sesion['origen_id'] ?? '')) }}
                        @if (!empty($docOrigen['nombre']))
                            <span class="text-muted font-weight-normal">— {{ $docOrigen['nombre'] }}</span>
                        @endif
                    </p>
                @endif
                <p class="mb-1">
                    <strong>Programa:</strong>
                    {{ $sesion['programa']['nombre'] ?? 'Sin programa' }}
                    @if (!empty($sesion['programa']['codigo']))
                        ({{ $sesion['programa']['codigo'] }})
                    @endif
                </p>
                <p class="text-muted small mb-3">{{ $sesion['motivo'] ?? '' }} — modo {{ $sesion['modo'] ?? 'OPERATIVO' }}</p>

                @if (!empty($sesion['tiene_venta']) && ($sesion['origen_tipo'] ?? '') !== 'FACTURA')
                    <p>
                        <a href="{{ route('sesion_impresion_factura', ['id' => $sesion['documentos']['FACTURA']['id'] ?? 0, 'auto' => 1]) }}" class="btn btn-outline-primary btn-sm">
                            Pack completo de la factura
                        </a>
                    </p>
                @endif

                @if (!empty($sesion['pack']))
                    @include('ventas.programa_impresion.partials.ruta_sesion', [
                        'sesion' => $sesion,
                        'resultado' => $resultado ?? null,
                    ])
                @else
                    <p class="text-muted">No hay copias para este documento (revise el programa y las reglas).</p>
                @endif

                @if (!empty($resultado['error']))
                    <div class="alert alert-danger">{{ $resultado['error'] }}</div>
                @endif

                <div class="mt-3 d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <form action="{{ route('ejecutar_impresion_sesion') }}" method="POST" class="form-inline mb-0" id="form-ejecutar-sesion" style="gap: 8px;">
                        @csrf
                        <input type="hidden" name="origen_tipo" value="{{ $sesion['origen_tipo'] ?? '' }}">
                        <input type="hidden" name="origen_id" value="{{ (int) ($sesion['origen_id'] ?? 0) }}">
                        <input type="hidden" name="modo" value="{{ $sesion['modo'] ?? 'OPERATIVO' }}">
                        @if (!empty($sesion['solo_formulario']))
                            <input type="hidden" name="solo_formulario" value="{{ $sesion['solo_formulario'] }}">
                        @elseif (($sesion['origen_tipo'] ?? '') !== 'FACTURA')
                            <input type="hidden" name="pack" value="1">
                        @endif
                        <button type="submit" class="btn btn-primary" id="btn-ejecutar-sesion" {{ empty($sesion['pack']) ? 'disabled' : '' }}>
                            <i class="fa fa-print"></i> Ejecutar sesión
                        </button>
                    </form>
                    @if (!empty($resultado['pdf_sesion']))
                        <a href="{{ route('descargar_impresion_sesion', ['t' => basename((string) $resultado['pdf_sesion'])]) }}" class="btn btn-outline-primary">
                            <i class="fa fa-file-pdf"></i> Descargar PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<div id="impresion-sesion-overlay" class="impresion-sesion-overlay" hidden>
    <div class="impresion-sesion-overlay-caja">
        <i class="fa fa-print fa-2x mb-2"></i>
        <div class="font-weight-bold">Generando e imprimiendo la ruta…</div>
        <div class="text-muted small">La pantalla ya está armada; ahora se generan los PDF (factura, remito, pedido).</div>
    </div>
</div>
@endsection
