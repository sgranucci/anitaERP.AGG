@php
    $fila = $fila ?? [];
    $marketplaceId = (int) ($fila['marketplace_id'] ?? 0);
    $urlMarketplace = ($marketplaceId > 0 && (can('editar-marketplace-facturacion-local', false) || can('listar-marketplace-facturacion-local', false)))
        ? route('editar_marketplace', ['id' => $marketplaceId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '';
@endphp
<tr class="item-articulo-marketplace">
    <td>
        <input type="hidden" name="am_id[]" value="{{ $fila['id'] ?? '' }}">
        <div class="tm-marketplace-campo d-flex align-items-center flex-nowrap">
            <input type="hidden" class="marketplace_id" name="am_marketplace_id[]" value="{{ $fila['marketplace_id'] ?? '' }}">
            <button type="button" class="btn-accion-tabla consultamarketplace tooltipsC flex-shrink-0 mr-1" title="Consulta marketplaces (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text"
                   name="am_marketplace_codigo[]"
                   class="codigomarketplace form-control form-control-sm flex-shrink-0 mr-1"
                   style="width: 5.5rem;"
                   value="{{ $fila['codigo'] ?? '' }}"
                   autocomplete="off"
                   inputmode="numeric"
                   title="Código. F1 abre el modal. Enter resuelve.">
            <input type="text"
                   name="am_marketplace_nombre[]"
                   class="descripcionmarketplace form-control form-control-sm"
                   value="{{ $fila['nombre'] ?? '' }}"
                   readonly
                   tabindex="-1"
                   placeholder="Nombre">
            <a class="btn-accion-tabla btn-link-editar-marketplace tooltipsC ml-1 {{ $urlMarketplace !== '' ? '' : 'd-none' }}"
               title="Consultar marketplace"
               target="_blank"
               rel="noopener"
               href="{{ $urlMarketplace !== '' ? $urlMarketplace : '#' }}">
                <i class="fa fa-edit"></i>
            </a>
        </div>
    </td>
    <td>
        <input type="number" name="am_orden[]" class="form-control form-control-sm am-orden" min="0" max="9999" value="{{ $fila['orden'] ?? 0 }}">
    </td>
    <td>
        <div class="tm-combinacion-campo d-flex align-items-center flex-nowrap">
            <input type="hidden" class="combinacion_id" name="am_combinacion_id[]" value="{{ $fila['combinacion_id'] ?? '' }}">
            <input type="hidden" class="ot-combinacion-todas" value="0">
            <button type="button" class="btn-accion-tabla consultacombinacion tooltipsC flex-shrink-0 mr-1" title="Consulta combinaciones (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text"
                   name="am_combinacion_codigo[]"
                   class="codigocombinacion form-control form-control-sm flex-shrink-0 mr-1"
                   style="width: 6rem;"
                   value="{{ $fila['combinacion_codigo'] ?? '' }}"
                   autocomplete="off"
                   title="Código de combinación del artículo. F1 abre el modal.">
            <input type="text"
                   name="am_combinacion_nombre[]"
                   class="descripcioncombinacion form-control form-control-sm"
                   value="{{ $fila['combinacion_nombre'] ?? '' }}"
                   readonly
                   tabindex="-1"
                   placeholder="Combinación">
        </div>
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla tooltipsC btn-quitar-marketplace-articulo" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
