@php
    $avisoAnita = $facturaYaEnAnita ?? session('factura_ya_en_anita');
    $precargaIdAviso = (int) ($avisoAnita['precarga_id'] ?? 0);
@endphp
@if (! empty($avisoAnita))
<div class="alert alert-warning">
    <h5 class="mb-2"><i class="fa fa-ban"></i> Factura ya existente en Anita — no se puede confirmar en el ERP</h5>
    <p class="mb-2">{{ $avisoAnita['mensaje'] ?? '' }}</p>
    @if (! empty($avisoAnita['comprobante_id']))
        <p class="mb-2">
            El comprobante #{{ (int) $avisoAnita['comprobante_id'] }} está en borrador en el ERP.
            No lo contabilices: Anita ya tiene esa identificación fiscal.
            Si sobra el borrador, borralo (solo ERP; no se toca la factura nativa de Anita).
        </p>
    @endif
    @if ($precargaIdAviso > 0 && empty($avisoAnita['ya_marcada']) && empty($avisoAnita['comprobante_id']))
        <p class="mb-2">Podés marcar la precarga #{{ $precargaIdAviso }} como ya cargada para sacarla de Pendientes, sin generar el comprobante en el ERP.</p>
        @include('compras.precarga_comprobante_proveedor.partials.boton_marcar_cargada_anita', [
            'precargaId' => $precargaIdAviso,
            'claseBoton' => 'btn btn-warning btn-sm',
            'etiquetaBoton' => 'Marcar precarga como ya cargada en Anita',
        ])
    @endif
</div>
@endif
