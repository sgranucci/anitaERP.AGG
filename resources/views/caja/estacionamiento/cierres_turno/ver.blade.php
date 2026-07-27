@extends("theme.$theme.layout")
@php
    $d = $datos;
@endphp

@section('titulo')
    Cierre de turno — op. {{ $turno->id }}
@endsection

@section('styles')
<style>
    .est-grilla-conciliacion-wrap {
        max-height: 520px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .est-grilla-conciliacion-wrap table { margin-bottom: 0; font-size: 0.85rem; white-space: nowrap; }
    .est-grilla-conciliacion-wrap th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
</style>
@endsection

@section('scripts')
<script>
    window.CIERRES_TURNO_ESTACIONAMIENTO = {
        urlApiComprobantes: @json(route('estacionamiento_cierres_turno_api_comprobantes')),
        urlFacturaVerBase: @json(($puede_ver_factura ?? false) ? url('caja/estacionamiento/facturas-dia') : null),
        puedeVerFactura: @json($puede_ver_factura ?? false),
    };
    window.CIERRE_TURNO_VER = {
        tipo: 'cierre',
        id: {{ (int) $turno->id }},
        referencia: @json($referencia ?? ''),
    };
</script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/cierres_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/cierres_turno.js')) }}" type="text/javascript"></script>
<script>
    (function ($) {
        'use strict';
        var datos = @json($datos);
        $(function () {
            var cont = document.getElementById('panel-resumen-cierre-ver');
            if (!cont || typeof window.EstacionamientoTotalesTurnoRender === 'undefined') {
                return;
            }
            function opcionesRenderTotales(totales) {
                var opts = { conciliarMedios: true };
                if (totales && totales.arqueo_medios_cierre) {
                    opts.arqueoMediosCierre = true;
                    opts.arqueoSoloLectura = true;
                    opts.cuentacaja_efectivo_id = datos.cuentacaja_efectivo_id || 1;
                }
                return opts;
            }

            var html = '';
            if (datos.totales_turno) {
                html += window.EstacionamientoTotalesTurnoRender.renderTotalesHtml(
                    datos.totales_turno,
                    'Totales del turno',
                    opcionesRenderTotales(datos.totales_turno)
                );
            }
            if (datos.totales_dia) {
                html += window.EstacionamientoTotalesTurnoRender.renderTotalesHtml(
                    datos.totales_dia,
                    'Totales del día (misma PC y jornada)',
                    { conciliarMedios: true }
                );
            }
            cont.innerHTML = html;
        });
    })(jQuery);
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <strong>{{ $d['titulo'] ?? 'Cierre de turno' }}</strong>
                    <span class="text-muted ml-1">— {{ $d['subtitulo'] ?? '' }}</span>
                </span>
                <div class="btn-group btn-group-sm mt-1 mt-md-0">
                    @if ($puede_ver_comprobante ?? false)
                        <a href="{{ route('estacionamiento_cierre_turno_comprobante_cierre', ['id' => $turno->id, 'inline' => 1]) }}"
                           target="_blank"
                           rel="noopener"
                           class="btn btn-outline-danger">
                            <i class="fa fa-file-pdf-o"></i> PDF resumen
                        </a>
                    @endif
                    @if ($desde_modal ?? false)
                        <button type="button" class="btn btn-outline-secondary" onclick="window.close();">
                            Cerrar ventana
                        </button>
                    @else
                        <a href="{{ route('estacionamiento_cierres_turno') }}" class="btn btn-outline-secondary">
                            Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="cierre-turno-ver-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-ver-resumen-link" data-toggle="tab"
                           href="#tab-ver-resumen" role="tab">Resumen</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-ver-comprobantes-link" data-toggle="tab"
                           href="#tab-ver-comprobantes" role="tab">Comprobantes</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-ver-resumen" role="tabpanel">
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2"><strong>Datos del turno</strong></div>
                            <div class="card-body py-2 small">
                                <div class="row">
                                    <div class="col-md-3">
                                        <span class="text-muted d-block">Empresa</span>
                                        <strong>{{ $d['empresa_nombre'] ?? '—' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted d-block">Terminal (PC)</span>
                                        <strong>{{ $d['identificador_pc'] ?? '—' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted d-block">Turno</span>
                                        <strong>
                                            {{ $d['turno_catalogo'] ?? '—' }}
                                            @if (! empty($d['turno_horario']) && ($d['turno_horario'] ?? '—') !== '—')
                                                ({{ $d['turno_horario'] }})
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted d-block">Fecha jornada</span>
                                        <strong>{{ $d['fecha_jornada'] ?? '—' }}</strong>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Habilitación</span>
                                        <strong>{{ $d['habilitacion_en'] ?? '—' }}</strong>
                                        <span class="text-muted"> — Abierto por {{ $d['usuario_habilita'] ?? $d['usuario_habilitado'] ?? '' }}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Cierre</span>
                                        <strong>{{ $d['cierre_en'] ?? '—' }}</strong>
                                        <span class="text-muted"> — {{ $d['usuario_registro'] ?? '' }}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Monto habilitación</span>
                                        <strong>${{ number_format((float) ($d['monto_habilitacion'] ?? 0), 2, ',', '.') }}</strong>
                                    </div>
                                </div>
                                @if (($d['cantidad_parciales'] ?? 0) > 0)
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <span class="text-muted">Cierres parciales registrados en el turno:</span>
                                        <strong>{{ (int) $d['cantidad_parciales'] }}</strong>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        @include('caja.estacionamiento.cierres_turno.partials.numeracion_fiscal_turno', [
                            'd' => $d,
                            'numeracion' => $d['numeracion_fiscal'] ?? [],
                            'modo_web' => true,
                        ])

                        <div id="panel-resumen-cierre-ver" class="mb-3"></div>

                        @if (! empty($d['medios_contado_cierre']))
                        <div class="card card-outline card-success mb-3">
                            <div class="card-header py-2">
                                <strong>Arqueo por medio de pago (cierre definitivo)</strong>
                            </div>
                            <div class="card-body py-2">
                                @include('caja.estacionamiento.cierres_turno.partials.comprobante_medios_pago_cierre', [
                                    'totalesTurno' => $d['totales_turno'] ?? [],
                                    'hayNc' => ((int) ($d['totales_turno']['cantidad_notas_credito'] ?? 0)) > 0
                                        || abs((float) ($d['totales_turno']['total_notas_credito'] ?? 0)) >= 0.005,
                                    'hayInv' => ((int) ($d['totales_turno']['cantidad_invitaciones'] ?? 0)) > 0
                                        || abs((float) ($d['totales_turno']['total_invitaciones'] ?? 0)) >= 0.005,
                                    'ncTotalGlobal' => (float) ($d['totales_turno']['total_notas_credito'] ?? 0),
                                    'ncCantGlobal' => (int) ($d['totales_turno']['cantidad_notas_credito'] ?? 0),
                                    'invTotalGlobal' => (float) ($d['totales_turno']['total_invitaciones'] ?? 0),
                                    'invCantGlobal' => (int) ($d['totales_turno']['cantidad_invitaciones'] ?? 0),
                                    'tituloMedios' => 'Montos contados por el cajero',
                                    'etiquetaTotal' => 'Total cobrado neto',
                                ])
                            </div>
                        </div>
                        @endif

                        <div class="card card-outline card-warning mb-0">
                            <div class="card-header py-2"><strong>Ajustes al cierre</strong></div>
                            <div class="card-body py-2 small">
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Redondeo turno</span>
                                        <strong>
                                            @if ($d['redondeo_turno'] !== null)
                                                ${{ number_format((float) $d['redondeo_turno'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Redondeo invitaciones</span>
                                        <strong>
                                            @if ($d['redondeo_invitaciones'] !== null)
                                                ${{ number_format((float) $d['redondeo_invitaciones'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Sobrante / faltante</span>
                                        <strong>
                                            @if ($d['sobrante_faltante'] !== null)
                                                ${{ number_format((float) $d['sobrante_faltante'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </strong>
                                    </div>
                                </div>
                                @if (! empty($d['observacion_cierre']))
                                <div class="mt-2">
                                    <span class="text-muted d-block">Observaciones de cierre</span>
                                    <div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $d['observacion_cierre'] }}</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-ver-comprobantes" role="tabpanel">
                        <p class="text-muted small mb-2" id="modal-comprobantes-cierre-subtitulo">{{ $referencia ?? '' }}</p>
                        <div class="d-flex flex-wrap align-items-center mb-2">
                            <label class="mb-0 small mr-3">
                                <input type="checkbox" id="filtro-solo-diferencias-cierre" class="mr-1"/>
                                Solo comprobantes con diferencia de cobranza
                            </label>
                        </div>
                        <div id="grilla-comprobantes-cierre" class="est-grilla-conciliacion-wrap">
                            <p class="text-muted p-3 mb-0">Seleccione la solapa o espere la carga…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
