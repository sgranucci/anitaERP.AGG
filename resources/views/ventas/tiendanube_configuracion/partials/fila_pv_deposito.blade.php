@php
    $idx = $idx ?? 0;
    $par = $par ?? (object) [];
    $pv = $par->puntoventa ?? null;
    $dep = $par->deposito ?? null;
    $pvId = $par->puntoventa_id ?? ($pv->id ?? '');
    $depId = $par->deposito_id ?? ($dep->id ?? '');
    $esDefault = ! empty($par->es_default);
@endphp
<tr class="tn-pv-dep-row">
    <td class="text-center align-middle">
        <input type="radio" name="par_default" class="tn-pv-dep-default" value="{{ $idx }}"
            @if ($esDefault) checked @endif title="Default">
    </td>
    <td>
        @include('ventas.partials.campo_consulta_puntoventa', [
            'prefix' => 'tn_pv_'.$idx,
            'layout' => 'inline',
            'inputName' => 'par_puntoventa_id[]',
            'inputId' => 'par_puntoventa_id_'.$idx,
            'puntoventaId' => $pvId,
            'codigo' => $pv->codigo ?? '',
            'nombre' => $pv->nombre ?? '',
            'required' => false,
            'mostrar_editar' => true,
        ])
    </td>
    <td>
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'tn_dep_'.$idx,
            'layout' => 'inline',
            'inputName' => 'par_deposito_id[]',
            'inputId' => 'par_deposito_id_'.$idx,
            'depositoId' => $depId,
            'codigo' => $dep->codigo ?? '',
            'descripcion' => $dep->nombre ?? '',
            'required' => false,
            'label' => '',
        ])
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn-accion-tabla tn-pv-dep-quitar" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
