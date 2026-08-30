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
window.impresionSesionFaltaImpresora = @json(! empty($sesion['faltante_impresora_papel']));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/programa_impresion/sesion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/programa_impresion/sesion.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    @if (($sesion['origen_tipo'] ?? '') === 'REPARTO')
                        Sesión de impresión del reparto
                    @elseif (($sesion['origen_tipo'] ?? '') === 'COT')
                        Sesión de impresión del COT
                    @else
                        Sesión de impresión de comprobantes
                    @endif
                </h3>
                <div class="card-tools">
                    @if (! empty($sesion['pack']))
                        <a href="{{ route('descargar_impresion_sesion', ['t' => 'papel']) }}" id="link-descargar-pdf-sesion" class="btn btn-outline-primary btn-sm link-descargar-pdf-sesion" title="Solo las copias de papel marcadas. El NAS no está en este archivo.">
                            <i class="fa fa-file-pdf"></i> Descargar PDF
                        </a>
                    @endif
                    <a href="{{ $volverUrl ?? route('factura') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
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
                @if (! empty($sesion['lote_venta_ids']))
                    @php
                        $loteSoloCopias = ! empty($sesion['solo_copias']);
                        $lotePackCompleto = ! empty($sesion['lote_pack_completo']);
                        $loteEjemploCopia = $loteSoloCopias
                            ? 'Duplicado o Triplicado'
                            : 'Triplicado';
                    @endphp
                    <div class="alert alert-info py-2 mb-3">
                        Se van a usar
                        <strong>{{ (int) ($sesion['lote_cantidad'] ?? count($sesion['lote_venta_ids'])) }}</strong>
                        comprobantes del filtro actual{{ $loteSoloCopias ? ' (solo copias, sin original)' : '' }}.
                        @if ($lotePackCompleto)
                            Se imprimen todas las copias del programa (como al facturar pedido por pedido).
                        @else
                            Marcá la copia (por ejemplo {{ $loteEjemploCopia }}) y ejecutá: sale esa copia en todas las facturas del reparto.
                        @endif
                    </div>
                @endif

                @include('ventas.programa_impresion.partials.mi_impresora', [
                    'sesion' => $sesion,
                    'programaSeteo' => $programaSeteo ?? null,
                    'salidasUsuario' => $salidasUsuario ?? [],
                    'enviarImpresora' => $enviarImpresora ?? true,
                ])

                <form action="{{ route('ejecutar_impresion_sesion') }}" method="POST" class="d-none" id="form-ejecutar-sesion">
                    @csrf
                    <input type="hidden" name="origen_tipo" value="{{ $sesion['origen_tipo'] ?? '' }}">
                    <input type="hidden" name="origen_id" value="{{ (int) ($sesion['origen_id'] ?? 0) }}">
                    <input type="hidden" name="modo" value="{{ $sesion['modo'] ?? 'OPERATIVO' }}">
                    <input type="hidden" name="solo_copia" id="input-solo-copia" value="0">
                    <input type="hidden" name="enviar_impresora" id="input-enviar-impresora" value="{{ ! empty($enviarImpresora) ? '1' : '0' }}">
                    @if (($sesion['origen_tipo'] ?? '') === 'COT' && ! empty($sesion['pack'][0]['remito_envio_id']))
                        <input type="hidden" name="remito_envio_id" value="{{ (int) $sesion['pack'][0]['remito_envio_id'] }}">
                    @endif
                    @if (! empty($sesion['retorno']))
                        <input type="hidden" name="retorno" value="{{ $sesion['retorno'] }}">
                    @endif
                    @if (!empty($sesion['solo_formulario']))
                        <input type="hidden" name="solo_formulario" value="{{ $sesion['solo_formulario'] }}">
                    @elseif (! in_array($sesion['origen_tipo'] ?? '', ['FACTURA', 'REPARTO'], true))
                        <input type="hidden" name="pack" value="1">
                    @endif
                </form>

                @include('ventas.programa_impresion.partials.acciones_sesion', [
                    'sesion' => $sesion,
                    'resultado' => $resultado ?? null,
                    'botonEjecutarId' => 'btn-ejecutar-sesion',
                    'claseContenedor' => 'mb-3',
                ])

                @if (!empty($sesion['tiene_venta']) && ($sesion['origen_tipo'] ?? '') !== 'FACTURA')
                    <p>
                        <a href="{{ route('sesion_impresion_factura', ['id' => $sesion['documentos']['FACTURA']['id'] ?? 0, 'auto' => 1] + (! empty($sesion['retorno']) ? ['retorno' => $sesion['retorno']] : [])) }}" class="btn btn-outline-primary btn-sm">
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

                @include('ventas.programa_impresion.partials.acciones_sesion', [
                    'sesion' => $sesion,
                    'resultado' => $resultado ?? null,
                    'claseContenedor' => 'mt-3',
                ])
            </div>
        </div>
    </div>
</div>
<div id="impresion-sesion-overlay" class="impresion-sesion-overlay" hidden>
    <div class="impresion-sesion-overlay-caja">
        <i class="fa fa-print fa-2x mb-2"></i>
        <div class="font-weight-bold">Enviando la impresión…</div>
        <div class="text-muted small">La pantalla se libera al toque. El PDF de papel y el NAS siguen en segundo plano.</div>
    </div>
</div>
@endsection
