@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', 'Legajo OC '.($ordencompra->numeroordencompra ?? ''))
@section('portal_nav_subtitulo', 'Legajo Gastronomía')

@push('styles')
<style>
    .portal-wrap { max-width: 1180px; }
    .legajo-hub-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2a4f7a 100%);
        color: #fff;
        border-radius: .35rem .35rem 0 0;
        padding: .95rem 1.15rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    .legajo-hub-header h1 {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: .01em;
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
        padding: .9rem 1.1rem;
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
    .legajo-section-title {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .06em;
        color: #6c757d;
        text-transform: uppercase;
        margin: 0 0 .65rem;
    }
    .legajo-nota {
        background: #fff8e6;
        border: 1px solid #f0d78c;
        border-left: 4px solid #c9921a;
        border-radius: .35rem;
        padding: .75rem 1rem;
        margin-bottom: 1rem;
    }
    .legajo-nota .nota-label {
        display: block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .05em;
        color: #8a6a12;
        margin-bottom: .3rem;
    }
    .legajo-nota .nota-body {
        font-size: .92rem;
        color: #3d3208;
        white-space: pre-wrap;
        word-break: break-word;
        margin: 0;
        line-height: 1.45;
    }
    .legajo-doc-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .45rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px rgba(0,0,0,.05);
        overflow: hidden;
    }
    .legajo-doc-card.is-nc { border-color: #b8d4c0; }
    .legajo-doc-card.is-nd { border-color: #e0c4a8; }
    .legajo-doc-card.is-fc { border-color: #c5daf0; }
    .legajo-doc-card .doc-head {
        padding: .75rem 1rem;
        font-weight: 700;
        font-size: .88rem;
        display: flex;
        align-items: center;
        gap: .45rem;
        border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .legajo-doc-card.is-fc .doc-head { background: #eef5fc; color: #1e3a5f; }
    .legajo-doc-card.is-nc .doc-head { background: #eef7f0; color: #1f5c38; }
    .legajo-doc-card.is-nd .doc-head { background: #faf0e6; color: #8a4b12; }
    .legajo-doc-card.is-oc .doc-head,
    .legajo-doc-card.is-com .doc-head { background: #f4f6f8; color: #1e3a5f; }
    .legajo-tipo-pill {
        margin-left: auto;
        font-size: .65rem;
        font-weight: 800;
        letter-spacing: .04em;
        padding: .15rem .45rem;
        border-radius: 999px;
        background: rgba(255,255,255,.75);
    }
    .legajo-doc-card .doc-body {
        padding: .75rem 1rem 1rem;
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
    .legajo-doc-card.is-nc .btn-legajo-pdf {
        background: #e8f5ec;
        color: #1f5c38;
        border-color: #b8d4c0;
    }
    .legajo-doc-card.is-nc .btn-legajo-pdf:hover { background: #d5ecdc; color: #16462b; }
    .btn-legajo-pdf:hover { background: #d6e8f8; color: #142849; }
    .btn-legajo-link {
        display: inline-block;
        color: #1e4f8a;
        font-weight: 600;
        font-size: .88rem;
    }
    .legajo-empty { color: #868e96; font-size: .88rem; padding: .5rem 0; }
    .legajo-hint {
        font-size: .78rem;
        color: #6c757d;
        margin: -.25rem 0 .85rem;
    }
</style>
@endpush

@section('content')
@php
    $oc = $ordencompra;
    $paquete = $paquete_legajo ?? [];
    $cab = $paquete['cabecera'] ?? [];
    $comprobantes = $paquete['comprobantes'] ?? [];
    if ($comprobantes === [] && ! empty($paquete['factura'])) {
        $comprobantes = [$paquete['factura']];
    }
    $ocCard = $paquete['ordencompra'] ?? [];
    $coms = $paquete['recepciones'] ?? [];
    $notaLegajo = trim((string) ($paquete['nota_legajo'] ?? ''));
    $fmt = static function ($n) {
        if ($n === null) {
            return '—';
        }

        return '$ '.number_format((float) $n, 2, ',', '.');
    };
    $claseTipo = static function (?string $tipo): string {
        $t = strtoupper(trim((string) $tipo));
        if ($t === 'NC') {
            return 'is-nc';
        }
        if ($t === 'ND') {
            return 'is-nd';
        }

        return 'is-fc';
    };
    $colComp = count($comprobantes) >= 3 ? 'col-md-4' : (count($comprobantes) === 2 ? 'col-md-6' : 'col-md-6 col-lg-5');
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
                <span class="meta-label">{{ $cab['importe_total_label'] ?? 'IMPORTE TOTAL (CON IVA)' }}</span>
                <span class="meta-value">{{ $fmt($cab['importe_total_con_iva'] ?? null) }}</span>
            </div>
        </div>
    </div>
    <div class="card-body bg-light">
        @if ($notaLegajo !== '')
            <div class="legajo-nota">
                <span class="nota-label">NOTA DEL LEGAJO (COMPRAS)</span>
                <p class="nota-body">{{ $notaLegajo }}</p>
            </div>
        @endif

        <h2 class="legajo-section-title">Comprobantes del proveedor</h2>
        @if (count($comprobantes) > 1)
            <p class="legajo-hint">Este legajo tiene {{ count($comprobantes) }} comprobantes (factura, NC, ND). Revisá cada PDF antes de autorizar.</p>
        @endif
        <div class="row">
            @forelse ($comprobantes as $comp)
                @php
                    $tipo = (string) ($comp['tipo'] ?? 'FC');
                    $titulo = (string) ($comp['tipo_titulo'] ?? 'FACTURA (FC)');
                    $btnPdf = match (strtoupper($tipo)) {
                        'NC' => 'Ver nota de crédito (PDF)',
                        'ND' => 'Ver nota de débito (PDF)',
                        default => 'Ver factura (PDF)',
                    };
                @endphp
                <div class="{{ $colComp }} mb-3">
                    <div class="legajo-doc-card {{ $claseTipo($tipo) }}">
                        <div class="doc-head">
                            <i class="fa fa-file-text-o"></i>
                            <span>{{ $titulo }}</span>
                            <span class="legajo-tipo-pill">{{ $comp['tipo_abrev'] ?? $tipo }}</span>
                        </div>
                        <div class="doc-body">
                            <div class="font-weight-bold mb-2" style="color:#1e3a5f;">{{ $comp['numero'] ?? '—' }}</div>
                            <div class="doc-row"><span class="doc-k">Fecha emisión</span><span class="doc-v">{{ $comp['fecha'] ?? '—' }}</span></div>
                            <div class="doc-row"><span class="doc-k">CUIT proveedor</span><span class="doc-v">{{ $comp['cuit'] ?? '—' }}</span></div>
                            @if (!empty($comp['importes_desde_recepcion']))
                                <p class="mb-2" style="font-size:.78rem;color:#856404;background:#fff3cd;border:1px solid #ffeeba;border-radius:.25rem;padding:.35rem .5rem;">
                                    Importes tomados de la recepción (factura aún no cargada).
                                </p>
                            @endif
                            <div class="doc-row"><span class="doc-k">Neto gravado</span><span class="doc-v">{{ $fmt($comp['neto'] ?? null) }}</span></div>
                            <div class="doc-row"><span class="doc-k">{{ $comp['iva_label'] ?? 'IVA' }}</span><span class="doc-v">{{ $fmt($comp['iva'] ?? null) }}</span></div>
                            <div class="doc-total">
                                <span>
                                    @if (!empty($comp['importes_desde_recepcion']))
                                        Total (provisión COM)
                                    @else
                                        {{ $comp['total_label'] ?? 'Total' }}
                                    @endif
                                </span>
                                <span>{{ $fmt($comp['total'] ?? null) }}</span>
                            </div>
                            @if (empty($comp['exige_com']) && in_array(strtoupper($tipo), ['NC', 'ND'], true))
                                <p class="mb-0 mt-2" style="font-size:.75rem;color:#1f5c38;">
                                    No requiere recepción COM.
                                </p>
                            @endif
                        </div>
                        <div class="doc-foot">
                            @if (!empty($comp['url_pdf']))
                                <a href="{{ $comp['url_pdf'] }}" class="btn-legajo-pdf" target="_blank" rel="noopener noreferrer">
                                    {{ $btnPdf }} <i class="fa fa-external-link"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 mb-3">
                    <div class="legajo-doc-card is-fc">
                        <div class="doc-head">
                            <i class="fa fa-file-text-o"></i>
                            <span>COMPROBANTES</span>
                        </div>
                        <div class="doc-body">
                            <p class="legajo-empty mb-0">No hay comprobantes PDF asociados al legajo.</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <h2 class="legajo-section-title mt-2">Orden de compra y recepción</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="legajo-doc-card is-oc">
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

            <div class="col-md-6 mb-3">
                <div class="legajo-doc-card is-com">
                    <div class="doc-head">
                        <i class="fa fa-truck"></i>
                        <span>RECEPCIÓN (COM)</span>
                        @if (count($coms) > 1)
                            <span class="legajo-tipo-pill">{{ count($coms) }}</span>
                        @endif
                    </div>
                    <div class="doc-body">
                        @php $com = $coms[0] ?? null; @endphp
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
                                <span class="doc-k">Importe provisión</span>
                                <span class="doc-v">{{ $fmt($com['importe_provision'] ?? null) }}</span>
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
