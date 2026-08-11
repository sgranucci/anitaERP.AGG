<div
    id="cliente-arca-config"
    class="d-none"
    aria-hidden="true"
    data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
    data-arca-validar-impuestos="{{ filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-condicioniva-ri-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1) }}"
    data-condicioniva-monotributo-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4) }}"
    data-condicioniva-baja-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_baja_impuestos_id', 7) }}"
    data-verificar-documento-url="{{ route('verificar_cliente_documento_alta') }}"
    data-permitir-cuit-duplicado="{{ filter_var(config('cliente.permitir_cuit_duplicado', false), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-tiposuspension-baja-id="{{ (int) (App\Support\Ventas\ArcaPadronTiposuspensionClienteSupport::idBajaImpuestos() ?? 0) }}"
></div>
@if (!empty($clienteId))
<div
    id="cliente-arca-validacion-config"
    class="d-none"
    aria-hidden="true"
    data-arca-validar-impuestos="{{ filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-validar-url="{{ route('validar_cliente_arca_padron', ['id' => $clienteId]) }}"
    data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
    data-condicioniva-ri-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1) }}"
    data-condicioniva-monotributo-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4) }}"
    data-condicioniva-baja-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_baja_impuestos_id', 7) }}"
    data-cliente-id="{{ (int) $clienteId }}"
    data-cuit-field="numerodocumento"
    data-suspender-en-abm="0"
    data-tiposuspension-baja-id="{{ (int) (App\Support\Ventas\ArcaPadronTiposuspensionClienteSupport::idBajaImpuestos() ?? 0) }}"
></div>
@endif
@include('includes.compras.arca_impuestos_validacion_modal')
@include('compras.proveedor.arca-padron-modals')
