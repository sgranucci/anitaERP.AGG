@php
    $articuloIdAcc = (int) ($articuloId ?? 0);
    $depositoIdAcc = (int) ($depositoId ?? 0);
    $volverKardex = (string) ($volverUrl ?? url()->current());
    $mostrarAcciones = $articuloIdAcc > 0 && (
        ($puede_ver_articulo ?? false)
        || ($puede_ver_formula ?? false)
        || ($puede_ver_movimientos ?? false)
    );
@endphp
@if ($mostrarAcciones)
    @if ($puede_ver_articulo ?? false)
        <a href="{{ route('editar_articulo', ['id' => $articuloIdAcc, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
            target="_blank"
            rel="noopener"
            class="btn-accion-tabla tooltipsC"
            title="Consultar art&iacute;culo">
            <i class="fa fa-edit"></i>
        </a>
    @endif
    @if ($puede_ver_formula ?? false)
        @include('includes.btn_formula_articulo', ['articuloId' => $articuloIdAcc])
    @endif
    @if ($puede_ver_movimientos ?? false)
        <a href="{{ route('recuento_movimientos_articulo', \App\Support\Stock\MovimientosArticuloDepositoSupport::parametrosUrlKardex($articuloIdAcc, $depositoIdAcc, $volverKardex)) }}"
            target="_blank"
            rel="noopener"
            class="btn-accion-tabla tooltipsC"
            title="Kardex de stock">
            <i class="fa fa-exchange text-primary"></i>
        </a>
    @endif
@endif
