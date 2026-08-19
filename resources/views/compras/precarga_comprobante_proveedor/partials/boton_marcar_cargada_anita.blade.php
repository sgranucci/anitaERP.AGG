@php
    $precargaIdMarcar = (int) ($precargaId ?? 0);
    $claseBoton = $claseBoton ?? 'btn-accion-tabla tooltipsC text-info';
    $etiquetaBoton = $etiquetaBoton ?? '';
    $confirmarTexto = $confirmarTexto
        ?? '¿Marcar la precarga #'.$precargaIdMarcar.' como ya cargada en Anita? Debe existir la factura en Anita. La precarga saldrá de Pendientes y no se generará comprobante en el ERP.';
@endphp
@if ($precargaIdMarcar > 0 && can('editar-precarga-proveedores', false))
<form action="{{ route('marcar_precarga_comprobante_proveedor_cargada_anita', ['id' => $precargaIdMarcar]) }}"
      method="POST"
      class="d-inline"
      onsubmit="return confirm(@json($confirmarTexto));">
    @csrf
    <button type="submit" class="{{ $claseBoton }}" title="Marcar como ya cargada en Anita">
        <i class="fa fa-check-circle"></i>@if ($etiquetaBoton !== '') {{ $etiquetaBoton }}@endif
    </button>
</form>
@endif
