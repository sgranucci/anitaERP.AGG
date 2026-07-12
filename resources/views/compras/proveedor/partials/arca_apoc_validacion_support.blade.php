<div
    id="proveedor-arca-apoc-config"
    class="d-none"
    aria-hidden="true"
    data-arca-apoc-habilitado="{{ filter_var(config('arca_wsapoc.validar_proveedor_abm', true), FILTER_VALIDATE_BOOLEAN) && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-apoc-validar-url="{{ ($proveedorId ?? null) ? route('validar_proveedor_arca_apoc', ['id' => $proveedorId]) : '' }}"
    data-proveedor-id="{{ (int) ($proveedorId ?? 0) }}"
    data-suspender-en-abm="{{ ($proveedorId ?? null) ? '1' : '0' }}"
></div>
