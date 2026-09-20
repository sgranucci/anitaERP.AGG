<template id="template-tolerancia-cp">
    <tr class="item-tolerancia-cp">
        <td>
            <select name="tolerancias[__IDX__][centrocosto_id]" class="form-control form-control-sm" required>
                <option value="">Seleccione…</option>
                @foreach ($centrocosto_query as $cc)
                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="hidden" name="tolerancias[__IDX__][tolerancia_importe_pct]" value="{{ \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::PCT_DEFAULT }}">
            <input type="number" step="0.01" min="0" max="100"
                name="tolerancias[__IDX__][tolerancia_exceso_pct]"
                class="form-control form-control-sm text-right"
                placeholder="no verificar"
                value="{{ \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::PCT_DEFAULT }}">
        </td>
        <td>
            <input type="number" step="0.01" min="0"
                name="tolerancias[__IDX__][tolerancia_exceso_abs]"
                class="form-control form-control-sm text-right"
                placeholder="no verificar">
        </td>
        <td>
            <input type="number" step="0.01" min="0" max="100"
                name="tolerancias[__IDX__][tolerancia_defecto_pct]"
                class="form-control form-control-sm text-right"
                placeholder="no verificar"
                value="{{ \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::PCT_DEFAULT }}">
        </td>
        <td>
            <input type="number" step="0.01" min="0"
                name="tolerancias[__IDX__][tolerancia_defecto_abs]"
                class="form-control form-control-sm text-right"
                placeholder="no verificar">
        </td>
        <td>
            <select name="tolerancias[__IDX__][accion_fuera_tolerancia]" class="form-control form-control-sm">
                <option value="DEVOLVER_COMPRAS" selected>No dejar cargar y devolver a Compras</option>
                <option value="BLOQUEAR_PAGO">Cargar y bloquear para pago</option>
            </select>
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla tooltipsC js-quitar-tolerancia-cp" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
