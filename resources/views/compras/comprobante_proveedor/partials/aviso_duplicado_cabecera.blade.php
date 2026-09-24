@php
    $fuente = $fuente ?? null;
    $mensaje = $mensaje ?? '';
    $comprobanteId = (int) ($comprobanteId ?? 0);
    $precargaId = (int) ($precargaId ?? 0);
    $urlEditar = $urlEditar ?? null;
@endphp
@if ($mensaje !== '')
<div class="alert alert-danger" id="cp-aviso-duplicado-cabecera">
    <h5 class="mb-2"><i class="fa fa-exclamation-triangle"></i> Factura ya cargada</h5>
    <p class="mb-2">{{ $mensaje }}</p>
    @if ($fuente === 'erp' && $comprobanteId > 0 && filled($urlEditar))
        <p class="mb-0">
            <a href="{{ $urlEditar }}" class="btn btn-warning btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-edit"></i> Abrir comprobante #{{ $comprobanteId }}
            </a>
        </p>
    @elseif ($fuente === 'precarga' && $precargaId > 0)
        <p class="mb-0">
            <a href="{{ route('editar_precarga_comprobante_proveedor', ['id' => $precargaId]) }}"
               class="btn btn-outline-warning btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-inbox"></i> Abrir precarga #{{ $precargaId }}
            </a>
        </p>
    @endif
</div>
@endif
