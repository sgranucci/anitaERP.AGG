<div
    id="cliente-arca-apoc-operacion-config"
    class="d-none"
    aria-hidden="true"
    data-arca-apoc-habilitado="{{ filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN) && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-apoc-validar-url="{{ route('validar_cliente_apoc_operacion', ['id' => '__ID__']) }}"
></div>
