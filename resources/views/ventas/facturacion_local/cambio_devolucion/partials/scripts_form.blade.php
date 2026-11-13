<script>
window.cdmLegajoId = @json($data->exists ? (int) $data->id : 0);
window.cdmEditable = @json((bool) ($editable ?? false));
window.cdmPuedeCorregirReemplazo = @json((bool) ($puedeCorregirReemplazo ?? false));
window.cdmPuedeSumarReemplazo = @json((bool) ($puedeSumarReemplazo ?? false));
window.cdmBuscarVentaUrl = @json(route('api_buscar_venta_cambio_devolucion_marketplace'));
window.cdmArticulosFacturaUrl = @json(route('api_articulos_factura_cambio_devolucion_marketplace', ['ventaId' => '__ID__']));
window.cdmAgregarDevolucionesUrl = @json($data->exists ? route('agregar_devoluciones_cambio_devolucion_marketplace', $data->id) : '');
window.cdmVariantesUrl = @json(route('api_variantes_cambio_devolucion_marketplace', ['articuloId' => '__ID__']));
window.cdmPrecioUrl = @json(route('api_precio_cambio_devolucion_marketplace'));
window.cdmPreviewFac = @json($previewFac ?? null);
window.cdmConfirmarFac = function (form, pregunta) {
    var select = form.querySelector('[name=modo_precio]');
    var modo = select ? select.value : '';
    var etiqueta = select ? select.options[select.selectedIndex].text.trim() : '';
    var preview = window.cdmPreviewFac;
    var fila = preview && preview.modos ? preview.modos[modo] : null;
    if (fila && !fila.ok) {
        alert('No se puede facturar con «' + etiqueta + '»:\n' + fila.error);
        return false;
    }
    var pesos = function (n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var texto = pregunta + '\n\nPrecio: ' + etiqueta;
    if (fila) {
        var facturado = Number(preview.vigente || 0) + Number(fila.total || 0);
        var diferencia = Math.round((facturado - Number(preview.devolver || 0)) * 100) / 100;
        texto += '\nTotal de esta factura: ' + pesos(fila.total);
        if (Number(preview.vigente || 0) > 0) {
            texto += '\nFacturas de reemplazo vigentes: ' + pesos(preview.vigente);
        }
        texto += '\nA devolver' + (preview.devolver_estimado ? ' (estimado con la grilla)' : '') + ': ' + pesos(preview.devolver);
        if (Math.abs(diferencia) < 0.01) {
            texto += '\nDiferencia: sin diferencia';
        } else if (diferencia > 0) {
            texto += '\nDiferencia: el cliente debe ' + pesos(diferencia);
        } else {
            texto += '\nDiferencia: Ferli acredita ' + pesos(-diferencia);
        }
        if (Math.abs(diferencia) >= 0.01) {
            texto += '\n\nRevisá que no haya un par de más ni un precio mal cargado.';
        }
    }
    return confirm(texto);
};
</script>
@if ($lineaInteractiva ?? $editable ?? false)
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/talle/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/talle/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/color/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/color/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js')) ?: time() }}"></script>
