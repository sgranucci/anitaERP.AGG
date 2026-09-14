@extends("theme.$theme.layout")
@section('titulo')
    COT electr&oacute;nico ARBA (gu&iacute;a)
@endsection

@section('contenido')
@php
    $guia = $guia ?? null;
    $lineasGuia = $guia?->lineas ?? collect();
    $esBorrador = $guia === null || ($guia->esBorrador() ?? true);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        @if (!empty($resultadoProceso))
            <div class="alert alert-{{ ($resultadoProceso['ok'] ?? false) ? 'success' : 'danger' }}">
                {{ $resultadoProceso['mensaje'] ?? '' }}
                @if (!empty($resultadoProceso['sesion_id']))
                    <div class="mt-1">
                        <a href="{{ route('cot_electronico', ['sesion_id' => $resultadoProceso['sesion_id'], 'guia_id' => $guia->id ?? null]) }}#sesion-detalle" class="alert-link">
                            Ver detalle de la sesi&oacute;n #{{ $resultadoProceso['sesion_id'] }}
                        </a>
                    </div>
                    @if ($resultadoProceso['ok'] ?? false)
                        <div class="mt-2">
                            <a href="{{ route('sesion_impresion_cot', ['id' => $resultadoProceso['sesion_id']]) }}"
                                class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> Imprimir COT
                            </a>
                            <a href="{{ route('sesion_impresion_cot', ['id' => $resultadoProceso['sesion_id'], 'pdf' => 1]) }}"
                                class="btn btn-outline-danger btn-sm">
                                <i class="fa fa-file-pdf"></i> PDF constancia
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        @if (!empty($resultadoPruebaConexion))
            @php
                $pruebaOk = (bool) ($resultadoPruebaConexion['ok'] ?? false);
            @endphp
            <div class="alert alert-{{ $pruebaOk ? 'success' : 'danger' }}">
                <strong>Prueba de conexi&oacute;n ARBA:</strong>
                {{ $resultadoPruebaConexion['mensaje'] ?? '' }}
            </div>
        @endif

        <div class="card card-primary" id="card-cot-guia"
            data-urls='@json($urlsGuia)'
            data-csrf="{{ csrf_token() }}"
            data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}">
            <div class="card-header">
                <h3 class="card-title">Gu&iacute;a de remitos / COT</h3>
                <div class="card-tools d-flex align-items-center">
                    <form method="post" action="{{ route('cot_electronico_probar_conexion') }}" class="mr-2 mb-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light" title="Valida URL, usuario y clave CIT">
                            <i class="fa fa-plug"></i> Probar conexi&oacute;n
                        </button>
                    </form>
                    <span class="badge badge-info mr-1">Modo gu&iacute;a</span>
                    <span class="badge badge-{{ $ambiente === 'prod' ? 'danger' : 'warning' }}">
                        {{ strtoupper($ambiente) }}
                    </span>
                </div>
            </div>

            <form id="form-cot-guia-guardar" method="post" action="{{ route('cot_electronico_guia_guardar') }}">
                @csrf
                <input type="hidden" name="guia_id" id="guia_id" value="{{ $guia->id ?? '' }}">
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label text-right pr-2">N&deg; gu&iacute;a</label>
                        <div class="col-lg-2">
                            <div class="input-group input-group-sm">
                                <input type="number" name="numero" id="numero_guia" class="form-control"
                                    value="{{ $guia->numero ?? '' }}"
                                    placeholder="{{ $siguienteNumeroGuia }}"
                                    {{ $guia ? 'readonly' : '' }} min="1">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="btn-consulta-guia" title="F1 consultar gu&iacute;as">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Vac&iacute;o = alta autom&aacute;tica</small>
                        </div>
                        <label for="fecha" class="col-lg-1 col-form-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm"
                                value="{{ $fecha }}" required {{ $esBorrador ? '' : 'readonly' }}>
                        </div>
                        <label class="col-lg-1 col-form-label text-right pr-2">Estado</label>
                        <div class="col-lg-2">
                            <input type="text" class="form-control form-control-sm" readonly
                                value="{{ $guia->estado ?? 'nueva' }}">
                        </div>
                    </div>

                    <div class="form-group row tm-transporte-campo">
                        <label class="col-lg-2 col-form-label text-right pr-2 requerido">Expreso</label>
                        <div class="col-lg-6">
                            <input type="hidden" name="transporte_id" id="transporte_id" class="transporte_id"
                                value="{{ $guia->transporte_id ?? '' }}">
                            <div class="input-group input-group-sm">
                                <input type="text" name="transporte_codigo" id="transporte_codigo"
                                    class="form-control codigotransporte"
                                    value="{{ optional($guia->transportes ?? null)->codigo ?? '' }}"
                                    maxlength="10" {{ $esBorrador ? '' : 'readonly' }}>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary consultatransporte"
                                        {{ $esBorrador ? '' : 'disabled' }} title="F1 / lupa">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                                <input type="text" id="nombretransporte" class="form-control nombretransporte" readonly
                                    value="{{ optional($guia->transportes ?? null)->nombre ?? '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row" id="fila-cuit-chofer-cot">
                        <label for="cuit_chofer" class="col-lg-2 col-form-label text-right pr-2">CUIT chofer</label>
                        <div class="col-lg-3">
                            <div class="input-group input-group-sm">
                                <input type="text" name="cuit_chofer" id="cuit_chofer"
                                    class="form-control input-cuit-chofer-guia"
                                    placeholder="XX-XXXXXXXX-X"
                                    maxlength="13"
                                    autocomplete="off"
                                    value="{{ old('cuit_chofer', \App\Support\Ventas\CuitFormatoValidacionSupport::formatear($guia->cuit_chofer ?? '')) }}"
                                    {{ $esBorrador ? '' : 'readonly' }}>
                                <div class="input-group-append">
                                    <span class="input-group-text cot-cuit-loading d-none" title="Consultando padr&oacute;n">
                                        <i class="fa fa-spinner fa-spin"></i>
                                    </span>
                                </div>
                            </div>
                            <small class="text-danger cot-cuit-error d-none">CUIT inv&aacute;lida</small>
                        </div>
                        <label class="col-lg-2 col-form-label text-right pr-2">Titular CUIT</label>
                        <div class="col-lg-3">
                            <input type="text" id="titular_cuit_chofer" class="form-control form-control-sm input-titular-cuit" readonly
                                value="" title="Se completa al validar la CUIT en padr&oacute;n ARCA">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="dominio" class="col-lg-2 col-form-label text-right pr-2">Dominio</label>
                        <div class="col-lg-2">
                            <input type="text" name="dominio" id="dominio" class="form-control form-control-sm"
                                value="{{ old('dominio', $guia->dominio ?? optional($guia->transportes ?? null)->patentevehiculo ?? '') }}"
                                maxlength="10" {{ $esBorrador ? '' : 'readonly' }}>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h5 class="mb-0">Facturas de la gu&iacute;a</h5>
                        @if ($esBorrador)
                            <div>
                                <button type="button" class="btn btn-outline-info btn-sm" id="btn-pendientes-dia">
                                    <i class="fa fa-calendar-check"></i> Facturas del d&iacute;a pendientes
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-agregar-linea">
                                    <i class="fa fa-plus"></i> Agregar rengl&oacute;n
                                </button>
                            </div>
                        @endif
                    </div>
                    <p class="small text-muted mb-2">
                        Factura: alcanza con el <strong>n&uacute;mero</strong> (ej. 83027) o <strong>PV-n&uacute;mero</strong> (12-83027). Enter completa cliente, bultos y pares.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="tabla-cot-guia-lineas">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:220px;">Factura</th>
                                    <th>Cliente</th>
                                    <th style="width:90px;" class="text-right">Bultos</th>
                                    <th style="width:90px;" class="text-right">Pares</th>
                                    <th style="width:110px;" class="text-right">Valor decl.</th>
                                    <th style="width:90px;">Expreso</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lineasGuia as $linea)
                                    @include('ventas.cot_electronico.partials.guia_linea_row', [
                                        'linea' => $linea,
                                        'editable' => $esBorrador,
                                    ])
                                @empty
                                    @if ($esBorrador)
                                        @include('ventas.cot_electronico.partials.guia_linea_row', [
                                            'linea' => null,
                                            'editable' => true,
                                        ])
                                    @else
                                        <tr><td colspan="7" class="text-center text-muted">Sin l&iacute;neas</td></tr>
                                    @endif
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold" style="background:#eaf6fb;">
                                    <td colspan="2" class="text-right">Totales</td>
                                    <td class="text-right" id="tot-bultos">0</td>
                                    <td class="text-right" id="tot-pares">0</td>
                                    <td class="text-right" id="tot-valor">0,00</td>
                                    <td colspan="2" id="tot-cant">0 facturas</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </form>

            <div class="card-footer">
                @if ($esBorrador)
                    <button type="submit" form="form-cot-guia-guardar" class="btn btn-primary" id="btn-guardar-guia">
                        <i class="fa fa-save"></i> Guardar gu&iacute;a
                    </button>
                    <form id="form-cot-guia-enviar" method="post" action="{{ route('cot_electronico_guia_enviar') }}" class="d-inline">
                        @csrf
                        <div id="enviar-campos-mirror"></div>
                        <input type="hidden" name="imprimir_al_procesar" value="0">
                        <button type="submit" class="btn btn-success" id="btn-enviar-guia">
                            <i class="fa fa-paper-plane"></i> Enviar a ARBA
                        </button>
                    </form>
                    <div class="form-check form-check-inline ml-2 align-middle">
                        <input type="checkbox" id="imprimir_al_procesar" class="form-check-input" value="1"
                            {{ ! empty($imprimirAlProcesar) ? 'checked' : '' }}>
                        <label class="form-check-label" for="imprimir_al_procesar">Imprimir COT al procesar</label>
                    </div>
                @endif
                <a href="{{ route('cot_electronico') }}" class="btn btn-outline-secondary btn-sm ml-2">
                    <i class="fa fa-file"></i> Nueva gu&iacute;a
                </a>
                <span class="small text-muted ml-2">
                    @if (! empty($impresoraUsuario['nombre']))
                        Impresora: <strong>{{ $impresoraUsuario['nombre'] }}</strong>
                    @else
                        Sin impresora asignada
                    @endif
                </span>
                @include('includes.ventas.link_mi_impresora', [
                    'claseBtnMiImpresora' => 'btn btn-outline-secondary btn-sm ml-2',
                ])
            </div>
        </div>

        {{-- Detalle primero: al abrir una sesión queda a la vista (AdminLTE no scrollea por #hash). --}}
        @include('ventas.cot_electronico.partials.sesion_detalle')
        @include('ventas.cot_electronico.partials.sesiones_envio')
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cot-guia-overlay',
    'tituloId' => 'cot-guia-overlay-titulo',
    'subtituloId' => 'cot-guia-overlay-subtitulo',
    'titulo' => 'Procesando COT…',
    'subtitulo' => 'Puede demorar según ARBA. No cierre la página.',
])

{{-- Modal consultar guías --}}
<div class="modal fade" id="consultaGuiaCotModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consultar gu&iacute;as COT</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label class="col-form-label mr-2">Buscar</label>
                    <input type="text" id="consulta-guia-texto" class="form-control col-lg-6" placeholder="N&uacute;mero o dominio">
                </div>
                <table class="table table-sm table-bordered table-hover">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>N&deg;</th>
                            <th>Fecha</th>
                            <th>Expreso</th>
                            <th>L&iacute;neas</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="consulta-guia-body"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal facturas pendientes del día --}}
<div class="modal fade" id="pendientesCotModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Facturas del d&iacute;a pendientes de COT</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Fecha de la gu&iacute;a. Se listan remitos/facturas a&uacute;n no enviados a ARBA.</p>
                <div class="table-responsive" style="max-height:420px;overflow:auto;">
                    <table class="table table-sm table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:40px;"><input type="checkbox" id="check-todos-pendientes" checked></th>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Expreso</th>
                                <th class="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody id="pendientes-cot-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btn-agregar-pendientes">
                    <i class="fa fa-plus"></i> Agregar seleccionadas
                </button>
            </div>
        </div>
    </div>
</div>

<template id="tpl-cot-guia-linea">
    @include('ventas.cot_electronico.partials.guia_linea_row', ['linea' => null, 'editable' => true])
</template>

@include('includes.ventas.modalconsultatransporte')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/transporte/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/transporte/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cot_electronico/guia.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cot_electronico/guia.js')) }}"></script>
@endsection
