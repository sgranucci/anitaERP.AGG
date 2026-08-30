{{-- Shell oculto: el JS de facturación del CRUD lee estos mismos IDs/clases. --}}
<div id="pedido-facturar-index-shell" class="d-none" aria-hidden="true">
    <form id="formgeneral" autocomplete="off" onsubmit="return false;">
        <div id="datosfactura"
             data-puntoventa="{{ $puntoventa_query }}"
             data-tipotransaccion="{{ $tipotransaccion_query }}"
             data-incoterm="{{ $incoterm_query }}"
             data-formapago="{{ $formapago_query }}">
            <input type="hidden" id="codigopedido" value="">
            <input type="hidden" id="pedido_id" name="pedido_id" value="">
            <input type="hidden" id="cliente_id" name="cliente_id" value="">
            <input type="hidden" id="nombrecliente" name="nombrecliente" value="">
            <input type="hidden" id="estadopedido" name="estadopedido" value="">
            <input type="hidden" id="descuento" name="descuento" value="0">
            <input type="hidden" id="lugarentrega" name="lugarentrega" value="">
            <input type="hidden" id="cliente_entrega_id" name="cliente_entrega_id" value="">
            <input type="hidden" id="cliente_entrega_id_previa" value="">
            <input type="hidden" id="entrega_nombre" value="">
            <input type="hidden" id="fl_cliente_tiene_entrega" value="0">
            <input type="hidden" id="estadocliente" value="">
        </div>
        <div id="aviso-padron-operacion-pedido" class="d-none"></div>
        <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" id="puntoventadefault_id" value="{{ $puntoventadefault_id ?? '' }}">
        <input type="hidden" id="puntoventaremitodefault_id" value="{{ $puntoventaremitodefault_id ?? '' }}">
        <input type="hidden" id="tipotransacciondefault_id" value="{{ $tipotransacciondefault_id ?? '' }}">
        <input type="hidden" id="totalcajaspedido" value="">
        <input type="hidden" id="totalpiezaspedido" value="">
        <input type="hidden" id="totalkilospesados" value="">
        <table>
            <tbody id="tbody-tabla"></tbody>
        </table>
        @include('ventas.cliente.partials.arca_apoc_operacion_support')
    </form>
</div>
@include('ventas.pedido.modalfacturapedido')
@include('includes.ventas.modalseleccionclienteentrega')
