@php
    $idx = $idx ?? 0;
    $dep = $dep ?? null;
    $depId = $depositoId ?? ($dep->id ?? '');
    $codigo = $codigo ?? ($dep->codigo ?? '');
    $nombre = $nombre ?? ($dep->nombre ?? '');
@endphp
<tr class="tn-stock-dep-row">
    <td>
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'tn_stock_dep_'.$idx,
            'layout' => 'inline',
            'inputName' => 'stock_deposito_id[]',
            'inputId' => 'stock_deposito_id_'.$idx,
            'depositoId' => $depId,
            'codigo' => $codigo,
            'descripcion' => $nombre,
            'required' => false,
            'label' => '',
            'mostrar_editar' => true,
        ])
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn-accion-tabla tn-stock-dep-quitar" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
