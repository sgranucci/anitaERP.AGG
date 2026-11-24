{{-- Acciones fijas de la bandeja (fuera del DnD de columnas) --}}
<td class="lw-col-acciones"
    style="width:{{ $anchoAcciones ?? 88 }}px;min-width:{{ $anchoAcciones ?? 88 }}px;max-width:{{ $anchoAcciones ?? 88 }}px;">
<div class="bandeja-acciones">
@if (! $esSectorCxp)
                                    <a href="{{ $row['url_oc'] }}" class="btn btn-xs btn-info" title="Ver orden de compra" target="_blank" rel="noopener">
                                        <i class="fa fa-file-text-o"></i>
                                    </a>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['url_asignar_factura']))
                                        <button type="button" class="btn btn-xs btn-outline-danger js-oc-asignar-factura"
                                                data-url="{{ $row['url_asignar_factura'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-proveedor="{{ $row['proveedor'] }}"
                                                data-moneda-id="{{ (int) ($row['moneda_sugerida_id'] ?? 1) }}"
                                                title="Asignar PDF de factura al legajo">
                                            <i class="fa fa-cloud-upload"></i>
                                        </button>
                                    @endif
                                    @if ($puedeConsultarFacturas)
                                        <button type="button" class="btn btn-xs btn-outline-danger js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_factura'] ?? '' }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="facturas"
                                                title="Consultar facturas del legajo">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin PDF de factura">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @endif
                                    @if (! $esSectorCxp)
                                    @if (!empty($row['tiene_com']))
                                        <button type="button" class="btn btn-xs btn-outline-dark js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="coms"
                                                title="Consultar COM del legajo">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin COM">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @endif
                                    @endif
                                    <button type="button" class="btn btn-xs btn-outline-info js-bandeja-historia"
                                            data-url="{{ $row['url_historia'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            title="Historia de asignación del legajo">
                                        <i class="fa fa-history"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-xs js-bandeja-nota {{ !empty($row['tiene_nota']) ? 'btn-warning' : 'btn-outline-secondary' }}"
                                            data-url="{{ $row['url_nota'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            data-nota="{{ $row['nota_legajo'] ?? '' }}"
                                            title="{{ !empty($row['tiene_nota']) ? ('Nota: '.$row['nota_legajo']) : 'Agregar nota al legajo' }}">
                                        <i class="fa fa-sticky-note{{ !empty($row['tiene_nota']) ? '' : '-o' }}"></i>
                                    </button>
                                    @if (! $esSectorCxp && !empty($puede_asignar_com) && !empty($row['tiene_factura']) && (!empty($row['tiene_com']) || !empty($row['tiene_pendiente_entrega'])))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-asignar-com"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="{{ !empty($row['tiene_pendiente_entrega']) && empty($row['tiene_com']) ? 'Gestionar facturas pendientes de entrega' : 'Asignar COM a la factura' }}">
                                            <i class="fa fa-link"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_cargar_cxp) && !empty($row['url_cargar_cxp']))
                                        <a href="{{ $row['url_cargar_cxp'] }}" class="btn btn-xs btn-primary"
                                           title="Cargar {{ $row['siguiente_pendiente'] ?? 'comprobante' }} en Cuentas a pagar">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    @endif
                                    @if (! $esSectorCxp && !empty($puede_ver_comprobante) && !empty($row['url_comprobante']))
                                        <a href="{{ $row['url_comprobante'] }}" class="btn btn-xs btn-outline-info" title="Ver comprobante cargado" target="_blank" rel="noopener">
                                            <i class="fa fa-check-square-o"></i>
                                        </a>
                                    @endif
                                    @if (!empty($puede_ver_pago) && !empty($row['url_pago']))
                                        @if (!empty($row['url_paquete']))
                                            <button type="button" class="btn btn-xs btn-outline-success js-bandeja-ver-legajo"
                                                    data-url-paquete="{{ $row['url_paquete'] }}"
                                                    data-numero="{{ $row['numero'] }}"
                                                    data-tab="pagos"
                                                    title="Ver pagos del legajo ({{ $row['etiqueta_pago'] ?? 'OP' }})">
                                                <i class="fa fa-money"></i>
                                            </button>
                                        @else
                                            <a href="{{ $row['url_pago'] }}" class="btn btn-xs btn-outline-success" title="Orden de pago {{ $row['etiqueta_pago'] ?? '' }}" target="_blank" rel="noopener">
                                                <i class="fa fa-money"></i>
                                            </a>
                                        @endif
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-gastro"
                                                data-url="{{ $row['url_enviar'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="Enviar a Gastronomía">
                                            <i class="fa fa-cutlery"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-cxp"
                                                data-url="{{ $row['url_enviar_cxp'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="{{ ((int) ($row['pendientes_carga'] ?? 0) > 0 && (int) ($row['sector_id'] ?? 0) > 0) ? 'Enviar FC/NC pendientes a Cuentas a pagar' : 'Enviar a Cuentas a pagar' }}">
                                            <i class="fa fa-share"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_enviar_pagos) && !empty($row['puede_enviar_pagos']))
                                        <button type="button" class="btn btn-xs btn-outline-success js-bandeja-enviar-pagos"
                                                data-url="{{ $row['url_enviar_pagos'] }}"
                                                title="Enviar a Pagos">
                                            <i class="fa fa-share-square-o"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_devolver_cxp) && !empty($row['puede_devolver_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-legajo"
                                                data-url-cxp="{{ $row['url_devolver_cxp'] }}"
                                                data-url-compras="{{ (!empty($puede_devolver_compras) && !empty($row['puede_devolver_compras'])) ? $row['url_devolver_compras'] : '' }}"
                                                title="{{ (!empty($puede_devolver_compras) && !empty($row['puede_devolver_compras'])) ? 'Devolver legajo (Cuentas a pagar o Compras)' : 'Devolver a Cuentas a pagar' }}">
                                            <i class="fa fa-undo"></i>
                                        </button>
                                    @elseif (!empty($puede_devolver_compras) && !empty($row['puede_devolver_compras']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-legajo"
                                                data-url-cxp=""
                                                data-url-compras="{{ $row['url_devolver_compras'] }}"
                                                title="Devolver a Compras">
                                            <i class="fa fa-reply"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_archivar) && !empty($row['puede_finalizar']))
                                        <form method="POST" action="{{ $row['url_finalizar'] }}" class="d-inline"
                                              onsubmit="return confirm('¿Archivar el legajo OC {{ $row['numero'] }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-success" title="Archivar legajo">
                                                <i class="fa fa-archive"></i>
                                            </button>
                                        </form>
                                    @endif
</div>
</td>
