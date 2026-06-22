<div
    id="proveedor-arca-validacion-config"
    class="d-none"
    aria-hidden="true"
    data-arca-validar-impuestos="{{ filter_var(config('arca.padron_validacion_proveedor.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-validar-url="{{ ($proveedorId ?? null) ? route('validar_proveedor_arca_padron', ['id' => $proveedorId]) : '' }}"
    data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
    data-condicioniva-ri-id="{{ (int) config('arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id', 1) }}"
    data-condicioniva-monotributo-id="{{ (int) config('arca.padron_validacion_proveedor.condicioniva_monotributo_id', 4) }}"
    data-proveedor-id="{{ (int) ($proveedorId ?? 0) }}"
    data-suspender-en-abm="{{ ($proveedorId ?? null) ? '1' : '0' }}"
></div>
