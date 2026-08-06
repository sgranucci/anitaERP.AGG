@extends("theme.$theme.layout")

@section('titulo')
    OC {{ $orden->numeroordencompra }}
@endsection

@section('styles')
    @include('compras.portal_proveedor.partials.estilos')
@endsection

@section('contenido')
@php
    $badgeClass = match ($orden->estadoordencompra) {
        'PENDIENTE' => 'portal-estado-oc-pendiente',
        'APROBADA' => 'portal-estado-oc-aprobada',
        'CUMPLIDA' => 'portal-estado-oc-cumplida',
        'SUSPENDIDA' => 'portal-estado-oc-suspendida',
        'CERRADA' => 'portal-estado-oc-cerrada',
        default => 'badge-secondary',
    };
    $facturas = $orden->portal_facturas ?? collect();
    $precargas = $orden->portal_precargas ?? collect();
    $fmtFecha = static function ($v) {
        if (! $v) {
            return '—';
        }
        if ($v instanceof \Carbon\CarbonInterface) {
            return $v->format('d/m/Y');
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $v;
        }
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="mb-3">
            <a href="{{ route('portal_proveedores_ordenes', $filtrosQuery ?? ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left"></i> Volver a órdenes de compra
            </a>
            <a href="{{ route('portal_proveedores', ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-success btn-sm">
                <i class="fa fa-upload"></i> Presentar factura
            </a>
            <a href="{{ route('portal_proveedores_pagos', ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-primary btn-sm">
                <i class="fa fa-money"></i> Ver pagos
            </a>
        </div>

        <div class="card">
            <div class="card-header" style="background:#85C1E9;color:#17202A;">
                <h3 class="card-title">
                    Orden de compra {{ $orden->numeroordencompra }}
                    <span class="badge {{ $badgeClass }} ml-2">{{ $orden->estadoordencompra }}</span>
                </h3>
            </div>
            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'ordenes',
                    'proveedorId' => $proveedorId,
                ])

                <div class="row mb-3">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Proveedor</dt>
                            <dd class="col-sm-8">{{ $proveedor->nombre }} · CUIT {{ $proveedor->nroinscripcion ?: '—' }}</dd>
                            <dt class="col-sm-4">Empresa</dt>
                            <dd class="col-sm-8">{{ $orden->empresas->nombre ?? '—' }}</dd>
                            <dt class="col-sm-4">Fecha OC</dt>
                            <dd class="col-sm-8">{{ $fmtFecha($orden->fecha) }}</dd>
                            <dt class="col-sm-4">Fecha entrega</dt>
                            <dd class="col-sm-8">{{ $fmtFecha($orden->fechaentrega) }}</dd>
                            <dt class="col-sm-4">Condición de pago</dt>
                            <dd class="col-sm-8">{{ $orden->condicionpagos->nombre ?? '—' }}</dd>
                            <dt class="col-sm-4">Detalle</dt>
                            <dd class="col-sm-8">{{ $orden->detalle ?: ($orden->comentario ?: '—') }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Monto OC</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">
                                        {{ number_format((float) ($orden->monto_lineas ?? 0), 2, ',', '.') }}
                                        {{ $orden->monedacabecera_abreviatura ?? '' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Facturas</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">{{ $facturas->count() }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Pagos vinculados</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">{{ (int) ($orden->portal_pagos_count ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mt-2"><i class="fa fa-list"></i> Ítems de la orden</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Importe</th>
                                <th>Moneda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orden->ordencompra_articulos as $lin)
                                <tr>
                                    <td>{{ optional($lin->articulos)->sku ?? '—' }}</td>
                                    <td>{{ optional($lin->articulos)->descripcion ?? ($lin->descripcion ?? '—') }}</td>
                                    <td class="text-right">{{ number_format((float) $lin->cantidad, 3, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $lin->precio, 4, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $lin->cantidad * (float) $lin->precio, 2, ',', '.') }}</td>
                                    <td>{{ optional($lin->monedas)->abreviatura ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted text-center">Sin ítems en la orden.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($precargas->isNotEmpty())
                    <h5><i class="fa fa-hourglass-half"></i> Facturas en precarga (aún no formalizadas)</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Comprobante</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Total</th>
                                    <th>Estado</th>
                                    <th>Origen</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($precargas as $pre)
                                    <tr>
                                        <td>
                                            {{ $pre->letra }} {{ str_pad((string) $pre->sucursal, 4, '0', STR_PAD_LEFT) }}-{{ $pre->numerocomprobante }}
                                        </td>
                                        <td>{{ $fmtFecha($pre->fechafactura) }}</td>
                                        <td class="text-right">{{ number_format((float) $pre->total, 2, ',', '.') }}</td>
                                        <td><span class="badge badge-warning">{{ $pre->estado }}</span></td>
                                        <td>{{ $pre->origen_entrada ?: '—' }}</td>
                                        <td>
                                            <a href="{{ route('portal_proveedores_factura', ['id' => $pre->id, 'proveedor_id' => $proveedorId]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Ver PDF"
                                               target="_blank" rel="noopener">
                                                <i class="fa fa-file-pdf-o text-danger"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h5><i class="fa fa-files-o"></i> Facturas asociadas</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Pagado</th>
                                <th class="text-right">Saldo</th>
                                <th>Pagos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($facturas as $fac)
                                @php
                                    $badgeFac = match ($fac->estado) {
                                        'CONTABILIZADO', 'APROBADO' => 'portal-estado-confirmada',
                                        'ANULADO' => 'portal-estado-baja',
                                        'PENDIENTE_REVISION', 'PENDIENTE_APROBACION', 'PENDIENTE_DIFERENCIA', 'PRECARGA' => 'portal-estado-oc-pendiente',
                                        default => 'badge-secondary',
                                    };
                                    $pagosFac = $fac->portal_pagos ?? collect();
                                @endphp
                                <tr>
                                    <td>{{ $fac->etiqueta_factura ?? \App\Support\Compras\PortalProveedorOrdencompraListadoFiltros::etiquetaFactura($fac) }}</td>
                                    <td>{{ $fmtFecha($fac->fechacomprobante) }}</td>
                                    <td><span class="badge {{ $badgeFac }}">{{ $fac->estado }}</span></td>
                                    <td class="text-right">
                                        {{ number_format((float) $fac->total, 2, ',', '.') }}
                                        {{ optional($fac->monedas)->abreviatura ?? '' }}
                                    </td>
                                    <td class="text-right">{{ number_format((float) ($fac->total_pagado_portal ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($fac->saldo_portal ?? 0), 2, ',', '.') }}</td>
                                    <td>
                                        @forelse ($pagosFac as $pago)
                                            <div class="mb-1">
                                                <a class="text-primary"
                                                   href="{{ route('portal_proveedores_pago', ['id' => $pago->id, 'proveedor_id' => $proveedorId]) }}"
                                                   target="_blank" rel="noopener">
                                                    {{ $pago->etiquetaComprobante() }}
                                                </a>
                                                <small class="text-muted">
                                                    {{ $fmtFecha($pago->fecha) }}
                                                    · {{ number_format((float) ($pago->monto_aplicado_a_factura ?? $pago->monto), 2, ',', '.') }}
                                                    <span class="badge {{ $pago->estado === 'CONFIRMADA' ? 'portal-estado-confirmada' : 'badge-secondary' }}">
                                                        {{ $pago->estado }}
                                                    </span>
                                                </small>
                                                <a href="{{ route('portal_proveedores_pago_pdf', ['id' => $pago->id, 'proveedor_id' => $proveedorId]) }}"
                                                   class="btn-accion-tabla tooltipsC" title="PDF OP"
                                                   target="_blank" rel="noopener">
                                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                                </a>
                                            </div>
                                        @empty
                                            <span class="text-muted">Sin pagos</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted text-center">
                                        Todavía no hay facturas asociadas a esta OC.
                                        Puede presentar una desde el módulo Facturas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
