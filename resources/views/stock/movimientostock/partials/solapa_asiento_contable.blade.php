<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 8px;">
    <h5 class="mb-0">Asiento contable</h5>
</div>

<div id="ms-asiento-preview-body">
    @include('stock.movimientostock.partials.solapa_asiento_contable_body', [
        'asientoPreview' => $asientoPreview ?? ['activo' => false],
    ])
</div>
