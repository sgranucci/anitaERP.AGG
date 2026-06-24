@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;

    $destinoImpresion = ProveedorCuentacorrienteGrillaSupport::destinoImpresion($data);
    $urlImpresion = ProveedorCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ProveedorCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       target="_blank"
       rel="noopener"
       class="btn-accion-tabla tooltipsC"
       title="{{ $destinoImpresion['titulo'] ?? 'Ver PDF de la factura' }}">
        <i class="fa fa-print"></i>
    </a>
@endif
<a href="{{ route('editar_cuentacorriente_proveedor', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
    <i class="fa fa-edit"></i>
</a>
<a href="#" class="btn-accion-tabla tooltipsC veraplicaciones" title="Ver aplicaciones">
    <i class="fa fa-clone"></i>
</a>
