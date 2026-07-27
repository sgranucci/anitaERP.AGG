@extends("theme.$theme.layout")

@section('titulo')
    Pago {{ $pago->etiquetaComprobante() }}
@endsection

@section('styles')
    @include('compras.portal_proveedor.partials.estilos')
@endsection

@section('contenido')
@php
    $badgeClass = match ($pago->estado) {
        'CONFIRMADA' => 'portal-estado-confirmada',
        'REVERTIDA' => 'portal-estado-revertida',
        'BAJA' => 'portal-estado-baja',
        default => 'badge-secondary',
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="mb-3">
            <a href="{{ route('portal_proveedores_pagos', $filtrosQuery ?? ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left"></i> Volver a pagos
            </a>
            <a href="{{ route('portal_proveedores_pago_pdf', ['id' => $pago->id, 'proveedor_id' => $proveedorId]) }}"
               class="btn btn-danger btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-file-pdf-o"></i> Descargar PDF de la OP
            </a>
        </div>

        <div class="card">
            <div class="card-header" style="background:#85C1E9;color:#17202A;">
                <h3 class="card-title">
                    Orden de pago {{ $pago->etiquetaComprobante() }}
                    <span class="badge {{ $badgeClass }} ml-2">{{ $pago->estado }}</span>
                </h3>
            </div>
            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'pagos',
                    'proveedorId' => $proveedorId,
                ])

                <div class="row mb-3">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Proveedor</dt>
                            <dd class="col-sm-8">{{ $proveedor->nombre }} · CUIT {{ $proveedor->nroinscripcion ?: '—' }}</dd>
                            <dt class="col-sm-4">Empresa</dt>
                            <dd class="col-sm-8">{{ $pago->empresas->nombre ?? '—' }}</dd>
                            <dt class="col-sm-4">Fecha de pago</dt>
                            <dd class="col-sm-8">{{ optional($pago->fecha)->format('d/m/Y') }}</dd>
                            <dt class="col-sm-4">Detalle</dt>
                            <dd class="col-sm-8">{{ $pago->detalle ?: '—' }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Monto OP</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">
                                        {{ number_format((float) $pago->monto, 2, ',', '.') }}
                                        {{ $pago->monedas->abreviatura ?? '' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Retenciones</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">
                                        {{ number_format((float) $totalRetenciones, 2, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-2">
                                <div class="portal-kpi">
                                    <div class="kpi-label">Neto</div>
                                    <div class="kpi-valor" style="font-size:1.05rem;">
                                        {{ number_format((float) $montoNeto, 2, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mt-2"><i class="fa fa-files-o"></i> Comprobantes cancelados</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th class="text-right">Aplicado</th>
                                <th>Moneda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pago->pagoproveedor_comprobantes as $apl)
                                @php
                                    $cc = $apl->proveedor_cuentacorrientes;
                                    $comp = $cc?->comprobante_proveedores;
                                    $etiqueta = '—';
                                    if ($comp) {
                                        $abrev = optional($comp->tipotransaccion_compras)->abreviatura ?: 'FC';
                                        $etiqueta = sprintf(
                                            '%s %s %04d-%s',
                                            $abrev,
                                            $comp->letra,
                                            (int) $comp->sucursal,
                                            $comp->numerocomprobante
                                        );
                                    } elseif ($cc) {
                                        $etiqueta = 'CC #'.$cc->id;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $etiqueta }}</td>
                                    <td>{{ optional(optional($comp)->fechacomprobante)->format('d/m/Y') ?: '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $apl->montoaplicado, 2, ',', '.') }}</td>
                                    <td>{{ $apl->monedas->abreviatura ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted text-center">Sin comprobantes aplicados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5><i class="fa fa-credit-card"></i> Medios de pago</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>Detalle</th>
                                <th class="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hayMedios = false; @endphp
                            @foreach ($pago->cheques as $ch)
                                @php $hayMedios = true; @endphp
                                <tr>
                                    <td>Cheque</td>
                                    <td>
                                        N° {{ $ch->numerocheque ?: '—' }}
                                        · {{ optional($ch->bancos)->nombre }}
                                        @if (!empty($ch->fechapago))
                                            · Pago {{ \Illuminate\Support\Carbon::parse($ch->fechapago)->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        {{ number_format((float) ($ch->monto ?? 0), 2, ',', '.') }}
                                        {{ optional($ch->monedas)->abreviatura }}
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($pago->caja_movimientos as $mov)
                                @foreach ($mov->caja_movimiento_cuentacajas as $linea)
                                    @php $hayMedios = true; @endphp
                                    <tr>
                                        <td>{{ optional($linea->cuentacajas)->nombre ?: 'Transferencia / caja' }}</td>
                                        <td>{{ optional($linea->cuentacajas)->codigo }}</td>
                                        <td class="text-right">{{ number_format((float) ($linea->monto ?? 0), 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                            @unless ($hayMedios)
                                <tr>
                                    <td colspan="3" class="text-muted text-center">Sin detalle de medios cargado.</td>
                                </tr>
                            @endunless
                        </tbody>
                    </table>
                </div>

                <h5><i class="fa fa-percent"></i> Retenciones</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>Certificado</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Alícuota</th>
                                <th class="text-right">Importe</th>
                                <th>Régimen / provincia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pago->pagoproveedor_retenciones as $ret)
                                <tr>
                                    <td>{{ $ret->etiquetaTipo() }}</td>
                                    <td>{{ $ret->nro_certificado ?: '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $ret->alicuota, 4, ',', '.') }}%</td>
                                    <td class="text-right">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                                    <td>
                                        {{ $ret->codigo_regimen ?: $ret->motivo }}
                                        @if ($ret->provincias)
                                            · {{ $ret->provincias->nombre }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('portal_proveedores_retencion_pdf', [
                                                'id' => $pago->id,
                                                'retencionId' => $ret->id,
                                                'proveedor_id' => $proveedorId,
                                            ]) }}"
                                           class="btn-accion-tabla tooltipsC"
                                           title="Descargar certificado PDF"
                                           target="_blank" rel="noopener">
                                            <i class="fa fa-file-pdf-o text-danger"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted text-center">Este pago no tiene retenciones.</td>
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
