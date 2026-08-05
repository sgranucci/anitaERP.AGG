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
            <input type="number" step="0.01" min="0" max="100"
                name="tolerancias[__IDX__][tolerancia_importe_pct]"
                class="form-control form-control-sm text-right"
                value="0">
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla tooltipsC js-quitar-tolerancia-cp" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
