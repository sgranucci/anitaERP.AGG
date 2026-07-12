<div
    id="cliente-arca-apoc-config"
    class="d-none"
    aria-hidden="true"
    data-arca-apoc-habilitado="{{ filter_var(config('arca_wsapoc.validar_cliente_abm', true), FILTER_VALIDATE_BOOLEAN) && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-apoc-validar-url="{{ ($clienteId ?? null) ? route('validar_cliente_arca_apoc', ['id' => $clienteId]) : '' }}"
    data-cliente-id="{{ (int) ($clienteId ?? 0) }}"
    data-suspender-en-abm="{{ ($clienteId ?? null) ? '1' : '0' }}"
></div>
