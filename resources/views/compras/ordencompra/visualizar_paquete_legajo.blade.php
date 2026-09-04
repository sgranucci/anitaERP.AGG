@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Legajo OC '.($ordencompra->numeroordencompra ?? ''))
@section('portal_nav_subtitulo', 'Legajo Gastronomía')

@push('styles')
<style>
    .portal-wrap { max-width: 1100px; }
    .legajo-hub-header {
        background: #1e3a5f;
        color: #fff;
        border-radius: .35rem .35rem 0 0;
        padding: .85rem 1.1rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    .legajo-hub-header h1 {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0;
    }
    .legajo-badge {
        background: #fff;
        color: #1e3a5f;
        border-radius: 999px;
        padding: .35rem .85rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .02em;
        white-space: nowrap;
    }
    .legajo-meta {
        background: #f1f3f5;
        border-bottom: 1px solid #dee2e6;
        padding: .85rem 1rem;
    }
    .legajo-meta .meta-label {
        display: block;
        font-size: .68rem;
        font-weight: 700;
        color: #6c757d;
        letter-spacing: .04em;
        margin-bottom: .15rem;
    }
    .legajo-meta .meta-value {
        font-size: .95rem;
        font-weight: 600;
        color: #212529;
    }
    .legajo-cc-pill {
        display: inline-block;
        background: #ffe8cc;
        color: #9a5b00;
        border-radius: .25rem;
        padding: .15rem .45rem;
        font-weight: 700;
        font-size: .85rem;
    }
    .legajo-doc-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .4rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .legajo-doc-card .doc-head {
        padding: .85rem 1rem .4rem;
        font-weight: 700;
        color: #1e3a5f;
        font-size: .95rem;
        display: flex;
        align-items: center;
        gap: .45rem;
    }
    .legajo-doc-card .doc-body {
        padding: .25rem 1rem 1rem;
        flex: 1 1 auto;
        font-size: .88rem;
    }
    .legajo-doc-card .doc-row {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        padding: .28rem 0;
        border-bottom: 1px solid #f1f3f5;
    }
    .legajo-doc-card .doc-row:last-child { border-bottom: 0; }
    .legajo-doc-card .doc-k { color: #6c757d; }
    .legajo-doc-card .doc-v { font-weight: 600; text-align: right; color: #212529; }
    .legajo-doc-card .doc-total {
        margin-top: .65rem;
        padding-top: .55rem;
        border-top: 1px solid #dee2e6;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        gap: .5rem;
    }
    .legajo-doc-card .doc-foot {
        padding: 0 1rem 1rem;
    }
    .btn-legajo-pdf {
        display: block;
        width: 100%;
        text-align: center;
        background: #e8f1fb;
        color: #1e3a5f;
        border: 1px solid #c5daf0;
        border-radius: .3rem;
        padding: .55rem .75rem;
        font-weight: 600;
        font-size: .88rem;
        text-decoration: none !important;
    }
    .btn-legajo-pdf:hover { background: #d6e8f8; color: #142849; }
    .btn-legajo-link {
        display: inline-block;
        color: #1e4f8a;
        font-weight: 600;
        font-size: .88rem;
    }
    .legajo-empty { color: #868e96; font-size: .88rem; padding: .5rem 0; }
</style>
@endpush

@section('content')
@php
    $oc = $ordencompra;
    $paquete = $paquete_legajo ?? [];
    $cab = $paquete['cabecera'] ?? [];
    $factura = $paquete['factura'] ?? null;
    $ocCard = $paquete['ordencompra'] ?? [];
    $coms = $paquete['recepciones'] ?? [];
    $com = $coms[0] ?? null;
    $fmt = static function ($n) {
        if ($n === null) {
            return '—';
        }

        return '$ '.number_format((float) $n, 2, ',', '.');
    };
@endphp
<div class="card portal-card mb-3 border-0 shadow-sm overflow-hidden">
    <div class="legajo-hub-header">
        <h1>Legajo — OC {{ $cab['numero_oc'] ?? ($oc->numeroordencompra ?? '—') }}</h1>
        <span class="legajo-badge">{{ $cab['estado_badge'] ?? 'GASTRONOMÍA — PENDIENTE DE AUTORIZAR' }}</span>
    </div>
    <div class="legajo-meta">
        <div class="row">
            <div class="col-6 col-md-3 mb-2 mb-md-0">
                <span class="meta-label">PROVEEDOR</span>
                <span class="meta-value">{{ $cab['proveedor'] ?? '—' }}</span>
            </div>
            <div class="col-6 col-md-3 mb-2 mb-md-0">
                <span class="meta-label">EMPRESA</span>
                <span class="meta-value">{{ $cab['empresa'] ?? '—' }}</span>
            </div>
            <div class="col-6 col-md-3">
                <span class="meta-label">CENTRO DE COSTO</span>
                <span class="legajo-cc-pill">{{ $cab['centrocosto'] ?? '—' }}</span>
            </div>
            <div class="col-6 col-md-3">
                <span class="meta-label">IMPORTE TOTAL (CON IVA)</span>
                <span class="meta-value">{{ $fmt($cab['importe_total_con_iva'] ?? null) }}</span>
            </div>
        </div>
    </div>
    <div class="card-body bg-light">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="legajo-doc-card">
                    <div class="doc-head">
                        <i class="fa fa-file-text-o"></i>
                        <span>FACTURA (FC)</span>
                    </div>
                    <div class="doc-body">
                        @if ($factura)
                            <div class="font-weight-bold mb-2" style="color:#1e3a5f;">{{ $factura['numero'] }}</div>
                            <div class="doc-row"><span class="doc-k">Fecha emisión</span><span class="doc-v">{{ $factura['fecha'] ?? '—' }}</span></div>
                            <div class="doc-row"><span class="doc-k">CUIT proveedor</span><span class="doc-v">{{ $factura['cuit'] ?? '—' }}</span></div>
                            <div class="doc-row"><span class="doc-k">Neto gravado</span><span class="doc-v">{{ $fmt($factura['neto'] ?? null) }}</span></div>
                            <div class="doc-row"><span class="doc-k">{{ $factura['iva_label'] ?? 'IVA' }}</span><span class="doc-v">{{ $fmt($factura['iva'] ?? null) }}</span></div>
                            <div class="doc-total">
                                <span>Total factura</span>
                                <span>{{ $fmt($factura['total'] ?? null) }}</span>
                            </div>
                        @else
                            <p class="legajo-empty mb-0">No hay factura PDF asociada al legajo.</p>
                        @endif
                    </div>
                    <div class="doc-foot">
                        @if (!empty($factura['url_pdf']))
                            <a href="{{ $factura['url_pdf'] }}" class="btn-legajo-pdf" target="_blank" rel="noopener noreferrer">
                                Ver factura (PDF) <i class="fa fa-external-link"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="legajo-doc-card">
                    <div class="doc-head">
                        <i class="fa fa-file-text-o"></i>
                        <span>ORDEN DE COMPRA (OC)</span>
                    </div>
                    <div class="doc-body">
                        <div class="font-weight-bold mb-2" style="color:#1e3a5f;">{{ $ocCard['numero'] ?? ('OC '.($oc->numeroordencompra ?? '')) }}</div>
                        <div class="doc-row"><span class="doc-k">Fecha OC</span><span class="doc-v">{{ $ocCard['fecha'] ?? '—' }}</span></div>
                        <div class="doc-row"><span class="doc-k">Solicitante</span><span class="doc-v">{{ $ocCard['solicitante'] ?? '—' }}</span></div>
                        <div class="doc-row"><span class="doc-k">Requisición</span><span class="doc-v">{{ $ocCard['requisicion'] ?? '—' }}</span></div>
                        <div class="doc-row"><span class="doc-k">Detalle</span><span class="doc-v">{{ $ocCard['detalle'] ?? '—' }}</span></div>
                        @if (!empty($ocCard['item_resumen']))
                            <div class="doc-row"><span class="doc-k">Ítem</span><span class="doc-v">{{ $ocCard['item_resumen'] }}</span></div>
                        @endif
                        <div class="doc-total">
                            <span>Subtotal (sin IVA)</span>
                            <span>{{ $fmt($ocCard['subtotal'] ?? null) }}</span>
                        </div>
                    </div>
                    <div class="doc-foot">
                        @if (!empty($ocCard['url_pdf']))
                            <a href="{{ $ocCard['url_pdf'] }}" class="btn-legajo-pdf mb-2" target="_blank" rel="noopener noreferrer">
                                Ver OC (PDF) <i class="fa fa-external-link"></i>
                            </a>
                        @endif
                        @if (!empty($url_formulario))
                            <a href="{{ $url_formulario }}" class="btn-legajo-link" target="_blank" rel="noopener noreferrer">
                                Ver OC completa <i class="fa fa-angle-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="legajo-doc-card">
                    <div class="doc-head">
                        <i class="fa fa-truck"></i>
                        <span>RECEPCIÓN (COM)</span>
                    </div>
                    <div class="doc-body">
                        @if ($com)
                            <div class="font-weight-bold mb-2" style="color:#1e3a5f;">{{ $com['numero'] }}</div>
                            <div class="doc-row"><span class="doc-k">Fecha recepción</span><span class="doc-v">{{ $com['fecha'] ?? '—' }}</span></div>
                            <div class="doc-row"><span class="doc-k">Estado</span><span class="doc-v">{{ $com['estado'] ?? '—' }}</span></div>
                            <div class="doc-row"><span class="doc-k">Usuario</span><span class="doc-v">{{ $com['usuario'] ?? '—' }}</span></div>
                            <div class="doc-row">
                                <span class="doc-k">Cant. OC / recibida</span>
                                <span class="doc-v">
                                    {{ number_format((float) ($com['cantidad_oc'] ?? 0), 2, ',', '.') }}
                                    /
                                    {{ number_format((float) ($com['cantidad_recibida'] ?? 0), 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="doc-row">
                                <span class="doc-k">Diferencias</span>
                                <span class="doc-v">
                                    @if (!empty($com['sin_diferencias']))
                                        Sin diferencias
                                    @elseif (!empty($com['diferencias']))
                                        {{ implode(', ', $com['diferencias']) }}
                                    @else
                                        {{ $com['resumen_diferencias'] ?? '—' }}
                                    @endif
                                </span>
                            </div>
                        @else
                            <p class="legajo-empty mb-0">No hay recepción COM en este legajo.</p>
                        @endif
                    </div>
                    <div class="doc-foot">
                        @if (!empty($com['url_pdf']))
                            <a href="{{ $com['url_pdf'] }}" class="btn-legajo-pdf" target="_blank" rel="noopener noreferrer">
                                Ver comprobante <i class="fa fa-external-link"></i>
                            </a>
                        @endif
                        @if (count($coms) > 1)
                            <div class="mt-2 small text-muted">Otras COM:</div>
                            @foreach ($coms as $idx => $otra)
                                @if ($idx === 0)
                                    @continue
                                @endif
                                @if (!empty($otra['url_pdf']))
                                    <a href="{{ $otra['url_pdf'] }}" class="btn-legajo-link d-block mt-1" target="_blank" rel="noopener noreferrer">
                                        {{ $otra['numero'] }} <i class="fa fa-angle-right"></i>
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
