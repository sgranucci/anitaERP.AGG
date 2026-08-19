@php
    $avisoAnita = $facturaYaEnAnita ?? session('factura_ya_en_anita');
    $precargaIdAviso = (int) ($avisoAnita['precarga_id'] ?? 0);
@endphp
@if (! empty($avisoAnita))
<div class="alert alert-warning">
    <h5 class="mb-2"><i class="fa fa-info-circle"></i> Factura ya cargada en Anita</h5>
    <p class="mb-2">{{ $avisoAnita['mensaje'] ?? '' }}</p>
    @if ($precargaIdAviso > 0 && empty($avisoAnita['ya_marcada']))
        <p class="mb-2">Podés marcar la precarga #{{ $precargaIdAviso }} como ya cargada para sacarla de Pendientes, sin generar el comprobante en el ERP.</p>
        @include('compras.precarga_comprobante_proveedor.partials.boton_marcar_cargada_anita', [
            'precargaId' => $precargaIdAviso,
            'claseBoton' => 'btn btn-warning btn-sm',
            'etiquetaBoton' => 'Marcar precarga como ya cargada en Anita',
        ])
    @endif
</div>
@endif
