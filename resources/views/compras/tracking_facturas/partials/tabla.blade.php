@php
    use App\Support\Compras\Tracking\TrackingComprobanteFamilia;
    use App\Support\Compras\Tracking\TrackingFacturaFila;

    $puedeVerPdf = can('ver-pdf-tracking-facturas', false);
    $puedeAbrirComprobante = can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false);
    $puedeVerOrdencompra = can('editar-ordencompra', false) || can('listar-ordencompra', false);
    $puedeVerPago = can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false);
@endphp
<table class="table table-hover tf-grilla mb-0" id="tabla-paginada">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>N&uacute;mero</th>
            <th>F. comprobante</th>
            <th>F. carga</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th class="text-right">Importe</th>
            <th class="text-right">Saldo</th>
            <th>Estado</th>
            <th>F. contab.</th>
            <th>OC / Legajo</th>
            <th>Pago</th>
            <th>PDF</th>
            <th class="text-nowrap" style="width:110px"></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($datas as $data)
            @php
                $fila = TrackingFacturaFila::de($data);
                $estado = $fila->estadoContable();
                $pago = $fila->estadoPago();
            @endphp
            <tr data-tf-id="{{ $fila->id() }}">
                <td>
                    <span class="tf-tag {{ $fila->familia() === TrackingComprobanteFamilia::FACTURA ? '' : 'tf-tag-neutro' }}"
                          title="{{ $fila->tipoNombre() }}">
                        {{ $fila->familia() }}
                    </span>
                    <small class="text-muted d-block">{{ $fila->tipoAbreviatura() }}</small>
                </td>
                <td class="tf-comprobante">{{ $fila->numero() }}</td>
                <td>{{ $fila->fechaComprobante() ?: '—' }}</td>
                <td>
                    @if ($fila->fechaCarga() !== '')
                        {{ $fila->fechaCarga() }}
                        <small class="text-muted d-block">{{ $fila->fechaCargaOrigen() }}</small>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>{{ $fila->empresa() }}</td>
                <td class="tf-proveedor">
                    {{ $fila->proveedor() }}
                    @if ($fila->cuit() !== '')
                        <small class="text-muted d-block">{{ $fila->cuit() }}</small>
                    @endif
                </td>
                <td class="tf-num">$ {{ number_format($fila->total(), 2, ',', '.') }}</td>
                <td class="tf-num {{ $fila->saldo() == 0 ? 'tf-apagado' : '' }}">
                    {{ $fila->saldo() == 0 ? '—' : '$ '.number_format($fila->saldo(), 2, ',', '.') }}
                </td>
                <td><span class="tf-pill {{ $estado['clase'] }}">{{ $estado['etiqueta'] }}</span></td>
                <td>
                    @if ($fila->fechaContabilizacion() !== '')
                        {{ $fila->fechaContabilizacion() }}
                        @if ($fila->numeroAsiento() > 0)
                            <small class="text-muted d-block">As. {{ $fila->numeroAsiento() }}</small>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    @if ($fila->numeroOrdencompra() !== '')
                        @if ($puedeVerOrdencompra)
                            <a href="{{ route('editar_ordencompra', ['id' => $fila->ordencompraId()]) }}"
                               class="text-primary" target="_blank" rel="noopener noreferrer"
                               title="Abrir la orden de compra">
                                OC {{ $fila->numeroOrdencompra() }}
                            </a>
                        @else
                            OC {{ $fila->numeroOrdencompra() }}
                        @endif
                    @else
                        <span class="text-muted">Sin OC</span>
                    @endif
                </td>
                <td>
                    <span class="tf-pill {{ $pago['clase'] }}">{{ $pago['etiqueta'] }}</span>
                    @if ($fila->fechaPago() !== '')
                        <small class="text-muted d-block">{{ $fila->fechaPago() }}</small>
                    @endif
                    @php $antiguedad = $fila->antiguedadDeuda(); @endphp
                    @if ($antiguedad !== null)
                        <small class="d-block">
                            <span class="tf-pill {{ $antiguedad['clase'] }}"
                                  title="{{ $antiguedad['dias'] < 0
                                      ? abs($antiguedad['dias']).' días para vencer'
                                      : $antiguedad['dias'].' días de atraso' }}
                                      ({{ $antiguedad['origen'] === 'vencimiento' ? 'por vencimiento' : 'por fecha del comprobante' }})">
                                {{ $antiguedad['etiqueta'] }}
                            </span>
                        </small>
                    @endif
                    @if ($fila->ordenPago() !== '')
                        <small class="d-block">
                            @if ($puedeVerPago && $fila->ordenPagoId() > 0)
                                <a href="{{ route('editar_pagoproveedor', ['id' => $fila->ordenPagoId()]) }}"
                                   class="text-primary js-erp-workspace"
                                   data-ws-modo="edit"
                                   data-ws-id="{{ $fila->id() }}"
                                   data-ws-titulo="Pago {{ $fila->ordenPago() }}"
                                   data-ws-meta="Aplicado a {{ $fila->familia() }} {{ $fila->numero() }}"
                                   data-ws-edit="{{ route('editar_pagoproveedor', ['id' => $fila->ordenPagoId()]) }}"
                                   @if ($puedeVerPdf && $fila->puedeVerPdf())
                                       data-ws-pdf="{{ route('tracking_facturas_pdf', ['id' => $fila->id()]) }}"
                                   @endif
                                   title="Ver la orden de pago en solapa (sin menú)">{{ $fila->ordenPago() }}</a>
                            @else
                                <span class="text-muted"
                                      title="OP del Anita (referencia). No hay registro editable en el ERP; buscala en Pagos a proveedores si fue reimportada.">
                                    {{ $fila->ordenPago() }}
                                </span>
                            @endif
                            @if ($fila->ordenesPagoExtra() > 0)
                                <span class="text-muted"
                                      title="El comprobante se cancel&oacute; con {{ $fila->ordenesPagoExtra() + 1 }} &oacute;rdenes de pago">
                                    +{{ $fila->ordenesPagoExtra() }}
                                </span>
                            @endif
                        </small>
                    @elseif ($fila->estadoPago()['etiqueta'] !== 'Sin resolver' && $fila->estadoPago()['etiqueta'] !== 'Sin datos')
                        <small class="text-muted d-block" title="Estado de pago del índice; sin OP vinculada en el ERP">
                            Sin OP enlazada
                        </small>
                    @endif
                </td>
                <td>
                    @if ($fila->puedeVerPdf())
                        <span class="tf-pill tf-neutro" title="Origen del PDF">{{ $fila->pdfOrigen() }}</span>
                    @elseif ($fila->indexado())
                        <span class="tf-pill tf-alerta" title="No se encontró el comprobante escaneado">Falta</span>
                    @else
                        <span class="tf-pill tf-neutro" title="Todavía no se resolvió el PDF de este comprobante">
                            Sin resolver
                        </span>
                    @endif
                </td>
                <td class="text-nowrap">
                    @if ($puedeVerPdf && $fila->puedeVerPdf())
                        <a href="{{ route('tracking_facturas_pdf', ['id' => $fila->id()]) }}"
                           class="tf-icon-btn js-tf-visor"
                           data-tf-id="{{ $fila->id() }}"
                           data-pdf-url="{{ route('tracking_facturas_pdf', ['id' => $fila->id()]) }}"
                           data-pdf-titulo="{{ $fila->familia() }} {{ $fila->numero() }} — {{ $fila->proveedor() }}"
                           data-pdf-origen="{{ $fila->pdfOrigen() }}"
                           @if ($puedeAbrirComprobante)
                               data-edit-url="{{ route('editar_comprobante_proveedor', ['id' => $fila->id()]) }}"
                           @endif
                           title="Vista previa del PDF (listado a la izquierda)">
                            <i class="fa fa-file-pdf-o"></i>
                        </a>
                    @else
                        <span class="tf-icon-btn tf-deshabilitado"
                              title="{{ $puedeVerPdf ? 'Sin PDF disponible' : 'No tiene permiso para ver el PDF' }}">
                            <i class="fa fa-file-o"></i>
                        </span>
                    @endif
                    @if ($puedeAbrirComprobante)
                        <a href="{{ route('editar_comprobante_proveedor', ['id' => $fila->id()]) }}"
                           class="tf-icon-btn js-erp-workspace"
                           data-ws-modo="edit"
                           data-ws-id="{{ $fila->id() }}"
                           data-ws-titulo="Editar {{ $fila->familia() }} {{ $fila->numero() }}"
                           data-ws-meta="{{ $fila->proveedor() }}"
                           data-ws-edit="{{ route('editar_comprobante_proveedor', ['id' => $fila->id()]) }}"
                           @if ($puedeVerPdf && $fila->puedeVerPdf())
                               data-ws-pdf="{{ route('tracking_facturas_pdf', ['id' => $fila->id()]) }}"
                           @endif
                           title="Editar en solapa (sin menú)">
                            <i class="fa fa-pencil"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="14">
                    <div class="tf-vacio">
                        <i class="fa fa-search"></i>
                        <div class="tf-vacio-titulo">No hay comprobantes con estos criterios</div>
                        <div>Probá ampliar el rango de fechas o cambiar la búsqueda.</div>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
