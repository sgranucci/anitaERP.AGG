@if (filled($precargaId) && filled($rutaalmacenamiento) && puedeVerPrecargaFacturaPdf())
    <a href="{{ urlAppCarpeta('compras/precarga_comprobante_proveedor/'.$precargaId.'/factura-pdf?inline=1') }}"
       class="btn btn-sm btn-outline-danger {{ $claseExtra ?? '' }}"
       target="_blank"
       rel="noopener noreferrer"
       title="Ver PDF escaneado">
        <i class="fa fa-file-pdf-o"></i> Ver PDF
    </a>
@endif
