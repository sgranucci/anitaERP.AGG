@extends("theme.$theme.layout")
@section('titulo')
    Nuevo cumplimiento &mdash; requisici&oacute;n de sala
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div id="cumple-alerta-error" class="alert alert-danger alert-dismissible d-none" role="alert">
            <button type="button" class="close" aria-label="Cerrar" onclick="$('#cumple-alerta-error').addClass('d-none');">&times;</button>
            <h4><i class="icon fa fa-times"></i> Error</h4>
            <ul class="mb-0"></ul>
        </div>
        <div id="cumple-alerta-aviso" class="alert alert-warning alert-dismissible d-none" role="alert">
            <button type="button" class="close" aria-label="Cerrar" onclick="$('#cumple-alerta-aviso').addClass('d-none');">&times;</button>
            <h5 class="mb-2">
                <i class="icon fa fa-exclamation-triangle"></i>
                <span class="cumple-alerta-aviso-titulo">Atenci&oacute;n</span>
            </h5>
            <ul class="mb-0 cumple-alerta-aviso-lista"></ul>
        </div>
        <style>
            #tabla-lineas-cumple tr.fila-saldo-insuficiente {
                background-color: #f8d7da !important;
            }
            #tabla-lineas-cumple tr.fila-saldo-aviso {
                background-color: #fff3cd !important;
            }
            #tabla-lineas-cumple tr.fila-saldo-insuficiente .input-cantidad-entrega,
            #tabla-lineas-cumple tr.fila-saldo-aviso .input-cantidad-entrega {
                border-color: #dc3545;
            }
        </style>
        @if (!empty($pdfToken))
            <div class="alert alert-success">
                Cumplimiento grabado.
                <a href="{{ route('pdf_cumplir_requisicion_sala', ['token' => $pdfToken]) }}" class="btn btn-sm btn-outline-dark ml-2" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf"></i> Imprimir comprobante PDF
                </a>
                <a href="{{ route('cumplir_requisicion_sala') }}" class="btn btn-sm btn-outline-info ml-2">Ir al listado</a>
            </div>
        @endif
        @if (!empty($errorCarga))
            <div class="alert alert-warning">{{ $errorCarga }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Nuevo cumplimiento de requisici&oacute;n de sala</h3>
                <div class="card-tools">
                    <a href="{{ route('cumplir_requisicion_sala') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('grabar_cumplir_requisicion_sala') }}" method="POST" id="form-cumple-requisicion-sala" autocomplete="off">
                @csrf
                <input type="hidden" name="requisicion_sala_id" id="requisicion_sala_id" value="{{ old('requisicion_sala_id', $requisicion->id ?? '') }}">
                <input type="hidden" id="requisicion_empresa_id" value="{{ $requisicion->empresa_id ?? '' }}">
                {{-- Sin #empresa_id: la consulta de depósitos no se acota a la empresa de la req
                     (el lab usa tip. 406 Biyemas aunque la requisición sea Kandiko/Rebisco). --}}

                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="tabs-modo-cumple">
                        <li class="nav-item">
                            <a class="nav-link {{ empty($modoNpu) ? 'active' : '' }}" href="{{ route('crear_cumplir_requisicion_sala') }}">Por n&uacute;mero de requisici&oacute;n</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !empty($modoNpu) ? 'active' : '' }}" href="{{ route('crear_cumplir_requisicion_sala', ['modo' => 'npu']) }}">Carga por NPU</a>
                        </li>
                    </ul>

                    <div id="bloque-modo-numero" class="{{ !empty($modoNpu) ? 'd-none' : '' }}">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Requisici&oacute;n</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="requisicion_display" readonly
                                    value="{{ $requisicion ? ('#'.$requisicion->numerorequisicion.' — id '.$requisicion->id) : '' }}"
                                    placeholder="Use la lupa para buscar requisici&oacute;n aprobada">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="btn-consulta-requisicion-cumple" title="Buscar requisici&oacute;n">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div id="bloque-modo-npu" class="{{ empty($modoNpu) ? 'd-none' : '' }}">
                        <div class="form-group row">
                            <label for="input-npu-cumple" class="col-lg-2 col-form-label requerido">NPU / QR</label>
                            <div class="col-lg-4">
                                <input type="text" class="form-control" id="input-npu-cumple" maxlength="50" placeholder="Escanee o ingrese NPU y Enter" autocomplete="off">
                            </div>
                            <div class="col-lg-6">
                                <p class="form-text text-muted mb-0">Agrega una l&iacute;nea pendiente por cada NPU. Puede mezclar &iacute;tems de distintas requisiciones aprobadas.</p>
                            </div>
                        </div>
                    </div>

                    <div id="bloque-cabecera-requisicion" class="{{ $requisicion ? '' : 'd-none' }}">
                        <div class="row mb-3" id="fila-resumen-cabecera">
                            <div class="col-md-4">
                                <strong>Estado:</strong> <span id="cabecera-estado">{{ $requisicion->estado ?? '' }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Fecha:</strong> <span id="cabecera-fecha">{{ optional($requisicion->fecha ?? null)->format('d/m/Y') }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>F. entrega req.:</strong> <span id="cabecera-fecha-entrega">{{ optional($requisicion->fecha_entrega ?? null)->format('d/m/Y') }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Empresa:</strong> <span id="cabecera-empresa">{{ $requisicion->empresas->nombre ?? '' }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Dep&oacute;sito destino:</strong>
                                <span id="cabecera-deposito">{{ $requisicion ? \App\Models\Stock\Depmae::etiquetaDesdePartes((string) ($requisicion->depositos->codigo ?? ''), (string) ($requisicion->depositos->nombre ?? ''), (int) ($requisicion->depositos->id ?? 0)) : '' }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Centro costo:</strong> <span id="cabecera-centrocosto">{{ $requisicion ? trim(($requisicion->centrocostos->codigo ?? '').' '.($requisicion->centrocostos->nombre ?? '')) : '' }}</span>
                            </div>
                            <div class="col-md-12 mt-2 {{ empty($modoNpu) ? 'd-none' : '' }}" id="cabecera-npu-resumen">
                                <span class="badge badge-info" id="badge-requisiciones-npu">0 l&iacute;neas cargadas</span>
                            </div>
                        </div>

                        @if (\App\Support\Stock\TransferenciaMercaderiaIntercompanySupport::puedeUsar())
                        <div class="form-group row mb-2" id="crs_panel_intercompany">
                            <div class="col-lg-12">
                                <button type="button" id="crs_btn_intercompany" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa fa-building"></i> Ver dep&oacute;sitos de otras empresas
                                </button>
                                <button type="button" id="btn-crs-aplicar-deposito-todas" class="btn btn-outline-primary btn-sm ml-1" title="Copia el dep&oacute;sito origen de la primera l&iacute;nea a todas">
                                    <i class="fa fa-copy"></i> Aplicar dep&oacute;sito origen a todas las l&iacute;neas
                                </button>
                                <input type="hidden" id="crs_modo_intercompany" value="0">
                                <small class="text-muted d-block mt-1">
                                    El origen por defecto suele ser el dep&oacute;sito laboratorio (406 Biyemas).
                                    Si no hay saldo, cambie el dep&oacute;sito por l&iacute;nea o apl&iacute;quelo a todas; la validaci&oacute;n de stock se confirma al grabar.
                                </small>
                            </div>
                        </div>
                        @else
                        <div class="form-group row mb-2">
                            <div class="col-lg-12">
                                <button type="button" id="btn-crs-aplicar-deposito-todas" class="btn btn-outline-primary btn-sm" title="Copia el dep&oacute;sito origen de la primera l&iacute;nea a todas">
                                    <i class="fa fa-copy"></i> Aplicar dep&oacute;sito origen a todas las l&iacute;neas
                                </button>
                                <small class="text-muted d-block mt-1">
                                    Si el dep&oacute;sito origen no tiene saldo, c&aacute;mbielo por l&iacute;nea o apl&iacute;quelo a todas; la validaci&oacute;n de stock se confirma al grabar.
                                </small>
                            </div>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="tabla-lineas-cumple">
                                <thead style="background-color:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Req.</th>
                                        <th>Art&iacute;culo</th>
                                        <th>Descripci&oacute;n</th>
                                        <th class="text-right">Pend.</th>
                                        <th class="text-right" title="Saldo en dep&oacute;sito origen">Saldo</th>
                                        <th title="Dep&oacute;sito de origen (incluye empresa)">Dep. origen</th>
                                        <th>T&eacute;cnico</th>
                                        <th>UID</th>
                                        <th>NPU</th>
                                        <th class="text-right">Cant. cumple</th>
                                        <th>Motivo pend.</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-lineas-cumple">
                                    @if ($requisicion && $lineas->isNotEmpty())
                                        @foreach ($lineas as $idx => $linea)
                                            @php
                                                $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);
                                                $esReparacion = (string) ($linea->destino ?? '') === 'R';
                                                $oldLinea = $oldLineasPorArticuloId[$linea->id] ?? [];
                                                $depOrigenId = $oldLinea['deposito_origen_id'] ?? $depositoLabId;
                                                $depOrigenCodigo = $oldLinea['deposito_origen_codigo'] ?? ($depositoLab->codigo ?? '');
                                                $depOrigenNombre = $oldLinea['deposito_origen_nombre'] ?? ($depositoLabNombreConEmpresa ?? ($depositoLab->nombre ?? ''));
                                                $tecnicoOld = $oldLinea['tecnico_laboratorio_id'] ?? '';
                                                $cantidadOld = $oldLinea['cantidad_entrega'] ?? '';
                                                $npuOld = $oldLinea['numeroparte'] ?? $linea->numeroparte;
                                                $motivoOld = $oldLinea['estadoparcial'] ?? '';
                                                $estadoLineaOld = $oldLinea['estado_linea'] ?? '';
                                                $fechaEntregaOld = $oldLinea['fecha_entrega'] ?? '';
                                                $remitoOld = $oldLinea['numeroremito'] ?? '';
                                                $responsableOld = $oldLinea['nombreresponsable'] ?? '';
                                                $motivoLabel = '';
                                                if ($motivoOld !== '') {
                                                    foreach ($estado_parcial_enum as $motivoEnum) {
                                                        if ((string) ($motivoEnum['valor'] ?? '') === (string) $motivoOld) {
                                                            $motivoLabel = (string) ($motivoEnum['nombre'] ?? $motivoOld);
                                                            break;
                                                        }
                                                    }
                                                    if ($motivoLabel === '') {
                                                        $motivoLabel = (string) $motivoOld;
                                                    }
                                                }
                                            @endphp
                                            <tr class="fila-cumple-linea" data-linea-id="{{ $linea->id }}" data-articulo-id="{{ $linea->articulo_id }}" data-requisicion-id="{{ $requisicion->id }}" data-destino="{{ $linea->destino ?? '' }}">
                                                <td>
                                                    @if (can('editar-requisicion-sala', false))
                                                        <a href="{{ route('editar_requisicion_sala', ['id' => $requisicion->id]) }}" class="text-primary" target="_blank" rel="noopener" title="Editar requisici&oacute;n">#{{ $requisicion->numerorequisicion }}</a>
                                                    @else
                                                        #{{ $requisicion->numerorequisicion }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ((int) ($linea->articulo_id ?? 0) > 0 && \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar())
                                                        <a href="{{ \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar((int) $linea->articulo_id) }}" class="text-primary" target="_blank" rel="noopener" title="Editar art&iacute;culo">{{ $linea->articulos->sku ?? '' }}</a>
                                                    @else
                                                        {{ $linea->articulos->sku ?? '' }}
                                                    @endif
                                                </td>
                                                <td>{{ $linea->descripcionArticulo() }}</td>
                                                <td class="text-right pendiente-cell">{{ number_format($pendiente, 2, '.', '') }}</td>
                                                <td class="align-middle text-right col-saldo-orig">
                                                    <span class="ms-saldo-origen text-monospace small" title="Saldo en dep&oacute;sito origen">&mdash;</span>
                                                </td>
                                                <td class="tm-deposito-campo">
                                                    <input type="hidden" class="deposito_id" name="lineas[{{ $idx }}][deposito_origen_id]" value="{{ $depOrigenId }}">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control form-control-sm codigodeposito" placeholder="C&oacute;d." maxlength="20" value="{{ $depOrigenCodigo }}">
                                                        <input type="text" class="form-control form-control-sm descripciondeposito" readonly placeholder="Dep&oacute;sito" value="{{ $depOrigenNombre }}">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-outline-secondary btn-sm consultadeposito" title="Consultar dep&oacute;sito"><i class="fa fa-search"></i></button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($esReparacion)
                                                        <select name="lineas[{{ $idx }}][tecnico_laboratorio_id]" class="form-control form-control-sm select-tecnico select-tecnico-reparacion">
                                                            <option value="">Seleccione&hellip;</option>
                                                            @foreach ($tecnicos as $tec)
                                                                <option value="{{ $tec->id }}" {{ (string) $tecnicoOld === (string) $tec->id ? 'selected' : '' }}>{{ $tec->nombre }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="text-muted small">No aplica</span>
                                                        <input type="hidden" name="lineas[{{ $idx }}][tecnico_laboratorio_id]" value="">
                                                    @endif
                                                </td>
                                                <td>{{ $linea->uid }}</td>
                                                <td>
                                                    <input type="text" name="lineas[{{ $idx }}][numeroparte]" class="form-control form-control-sm input-npu-linea" maxlength="50" value="{{ $npuOld }}" placeholder="NPU">
                                                </td>
                                                <td>
                                                    <input type="hidden" name="lineas[{{ $idx }}][requisicion_sala_articulo_id]" value="{{ $linea->id }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][estadoparcial]" class="input-estadoparcial" value="{{ $motivoOld }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][estado_linea]" class="input-estado-linea" value="{{ $estadoLineaOld }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][fecha_entrega]" class="input-fecha-entrega" value="{{ $fechaEntregaOld }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][numeroremito]" class="input-numeroremito" value="{{ $remitoOld }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][nombreresponsable]" class="input-nombreresponsable" value="{{ $responsableOld }}">
                                                    <input type="number" step="0.01" min="0" name="lineas[{{ $idx }}][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="{{ number_format($pendiente, 4, '.', '') }}" value="{{ $cantidadOld }}">
                                                </td>
                                                <td class="motivo-parcial-label small text-muted">{{ $motivoLabel }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group row mt-3">
                            <label for="leyenda" class="col-lg-2 col-form-label">Leyenda</label>
                            <div class="col-lg-8">
                                <textarea name="leyenda" id="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{ old('leyenda') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-grabar-cumple" {{ $requisicion ? '' : 'disabled' }}>
                        <i class="fa fa-save"></i> Grabar cumplimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('sala.cumplir_requisicion_sala.partials.modales', [
    'estado_parcial_enum' => $estado_parcial_enum,
    'estado_linea_enum' => $estado_linea_enum,
])
@include('includes.stock.modalconsultadeposito')
{{-- Lab elige depósitos de varias empresas (406 Biyemas vs Kandiko): forzar columna Empresa --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var $flag = document.getElementById('consultadeposito-mostrar-empresa');
        if ($flag) {
            $flag.value = '1';
        }
        var thead = document.querySelector('#tabla-data-deposito thead tr');
        if (thead && !thead.querySelector('.col-empresa-deposito')) {
            var thAcciones = thead.querySelector('th:last-child');
            var th = document.createElement('th');
            th.className = 'col-empresa-deposito';
            th.textContent = 'Empresa';
            if (thAcciones) {
                thead.insertBefore(th, thAcciones);
            } else {
                thead.appendChild(th);
            }
        }
    });
</script>
@endsection

@section('scripts')
@php
    $urlEditarArticuloCumple = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar()
        ? route('editar_articulo', ['id' => '__ID__', 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '';
    $urlEditarRequisicionCumple = can('editar-requisicion-sala', false)
        ? route('editar_requisicion_sala', ['id' => '__ID__'])
        : '';
@endphp
<script>
    window.cumpleRequisicionSalaConfig = {
        urlConsulta: @json(route('consulta_requisicion_sala_cumple')),
        urlConsultaNpu: @json(route('consulta_npu_cumple_requisicion_sala')),
        urlDatos: @json(url('sala/cumplir-requisicion-sala/datos')),
        urlCumplir: @json(route('crear_cumplir_requisicion_sala')),
        urlGrabar: @json(route('grabar_cumplir_requisicion_sala')),
        urlSaldoOrigen: @json(route('cumplir_requisicion_sala_saldo_articulo')),
        urlPdf: @json(url('sala/cumplir-requisicion-sala/pdf')),
        urlEditarArticulo: @json($urlEditarArticuloCumple),
        urlEditarRequisicion: @json($urlEditarRequisicionCumple),
        depositoLabId: {{ (int) $depositoLabId }},
        depositoLabCodigo: @json($depositoLab->codigo ?? ''),
        depositoLabNombre: @json($depositoLabNombreConEmpresa ?? ($depositoLab->nombre ?? '')),
        forzarEmpresaDeposito: true,
        modoNpu: {{ !empty($modoNpu) ? 'true' : 'false' }},
        estadoParcialEnum: @json($estado_parcial_enum),
        oldLineas: @json($oldLineas ?? []),
        tecnicosPorEmpresa: @json($tecnicosPorEmpresa ?? []),
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sala/cumplir_requisicion_sala/form-saldo-origen.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sala/cumplir_requisicion_sala/form-saldo-origen.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sala/cumplir_requisicion_sala/form.v2.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sala/cumplir_requisicion_sala/form.v2.js')) ?: time() }}"></script>
@endsection
