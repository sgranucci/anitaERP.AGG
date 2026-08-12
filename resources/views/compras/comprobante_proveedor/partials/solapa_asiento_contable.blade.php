<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 8px;">
    <h5 class="mb-0">Asiento contable</h5>
</div>

<div id="cp-asiento-preview-body" class="cp-asiento-preview-target">
    @include('compras.comprobante_proveedor.partials.solapa_asiento_contable_body', [
        'asientoPreview' => $asientoPreview ?? ['activo' => false],
        'data' => $data,
    ])
</div>
