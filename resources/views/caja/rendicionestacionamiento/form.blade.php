@php
    use App\Support\Caja\Estacionamiento\EstacionamientoCuentacajaEfectivo;
    
    $esEdicion = isset($data) && $data->id;
    $movimientosIniciales = [];
    if ($esEdicion) {
        foreach ($data->movimientos as $m) {
            $movimientosIniciales[] = [
                'cuentacaja_id' => (int) $m->cuentacaja_id,
                'codigo' => (string) ($m->cuentacaja?->codigo ?? ''),
                'nombre' => (string) ($m->cuentacaja?->nombre ?? ''),
                'monto' => round((float) $m->monto, 2),
                'cotizacion' => round((float) $m->cotizacion, 2),
            ];
        }
        $totalesTurnoEdicion = is_array($totalesTurno ?? null) ? $totalesTurno : [];
        $ncCantEdicion = (int) ($totalesTurnoEdicion['cantidad_notas_credito'] ?? 0);
        $ncMontoEdicion = array_key_exists('total_notas_credito', $totalesTurnoEdicion)
            ? round((float) $totalesTurnoEdicion['total_notas_credito'], 2)
            : (round((float) $data->totalnotacredito, 2) > 0 ? -round((float) $data->totalnotacredito, 2) : 0.0);
        if ($ncCantEdicion > 0 || abs($ncMontoEdicion) >= 0.005) {
            $movimientosIniciales[] = [
                'cuentacaja_id' => 0,
                'codigo' => '',
                'nombre' => 'Notas de crédito ('.$ncCantEdicion.' comp.)',
                'monto' => $ncMontoEdicion,
                'cotizacion' => 1.0,
                'es_nota_credito' => true,
            ];
        }
    }
    $tipoSel = old('tipo', $esEdicion ? ($data->tipo ?? 'turno') : 'turno');
    $esJornada = $tipoSel === 'jornada';
    $turnoSel = old('turno_operativo_estacionamiento_id', $esEdicion && ! $esJornada ? $data->turno_operativo_estacionamiento_id : '');
    $jornadaSel = old('jornada_estacionamiento_id', $esEdicion && $esJornada ? $data->jornada_estacionamiento_id : '');
    $empresaSel = old('empresa_id', $esEdicion ? $data->empresa_id : ($empresa_default_id ?? ''));
    $cuentacajaEfectivoId = (int) (EstacionamientoCuentacajaEfectivo::idParaEmpresa((int) $empresaSel) ?? 0);

    $pvCaeLabel = '';
    $pvCaeaLabel = '';
    $turnoEtiqueta = '';
    if ($esEdicion) {
        $pvCaeLabel = trim(($data->puntoventaCae?->codigo ?? '').' — '.($data->puntoventaCae?->nombre ?? ''), ' —');
        $pvCaeaLabel = trim(($data->puntoventaCaea?->codigo ?? '').' — '.($data->puntoventaCaea?->nombre ?? ''), ' —');
        $turnoEtiqueta = 'Op. #'.$data->turno_operativo_estacionamiento_id
            .' — '.($data->turnoOperativo?->turno?->nombre ?? '')
            .' — '.($data->turnoOperativo?->identificador_pc ?? '')
            .' — cierre '.($data->turnoOperativo?->cierre_en?->format('d/m/Y H:i') ?? '');
    }

    $codigoInicial = old('codigo', $esEdicion ? $data->codigo : ($codigo_propuesto ?? ''));
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = ! $esEdicion && $empresasDisponibles->count() === 1;
    $apiBase = rtrim((string) config('app.app_carpeta', ''), '/');
    $auditoriaJornada = $auditoriaJornada ?? null;

    $datosInicialesJson = [
        'tipo' => $tipoSel,
        'turno_operativo_estacionamiento_id' => $turnoSel,
        'jornada_estacionamiento_id' => $jornadaSel,
        'turno_etiqueta' => $turnoEtiqueta,
        'codigo' => $codigoInicial,
        'empresa_id' => $empresaSel,
        'puntoventa_cae_id' => old('puntoventa_cae_id', $esEdicion ? $data->puntoventa_cae_id : ''),
        'puntoventa_caea_id' => old('puntoventa_caea_id', $esEdicion ? $data->puntoventa_caea_id : ''),
        'puntoventa_cae_label' => $pvCaeLabel,
        'puntoventa_caea_label' => $pvCaeaLabel,
        'caja_id' => old('caja_id', $esEdicion ? $data->caja_id : ($caja_id ?? 0)),
        'fecharendicion' => old('fecharendicion', $esEdicion ? $data->fecharendicion?->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')),
        'iniciodelfondo' => old('iniciodelfondo', $esEdicion ? $data->iniciodelfondo : 0),
        'totalfactura' => old('totalfactura', $esEdicion ? $data->totalfactura : 0),
        'totalcobrado' => old('totalcobrado', $esEdicion ? $data->totalcobrado : 0),
        'totalinvitacion' => old('totalinvitacion', $esEdicion ? $data->totalinvitacion : 0),
        'totalnotacredito' => old('totalnotacredito', $esEdicion ? $data->totalnotacredito : 0),
        'totalredondeo' => old('totalredondeo', $esEdicion ? $data->totalredondeo : 0),
        'totalredondeoinvitacion' => old('totalredondeoinvitacion', $esEdicion ? $data->totalredondeoinvitacion : 0),
        'sobrantefaltante' => old('sobrantefaltante', $esEdicion ? $data->sobrantefaltante : 0),
        'observacion' => old('observacion', $esEdicion ? $data->observacion : ''),
        'identificador_pc' => $esEdicion ? ($data->turnoOperativo?->identificador_pc ?? '') : '',
        'turno_nombre' => $esEdicion ? ($data->turnoOperativo?->turno?->nombre ?? '') : '',
        'fecha_jornada' => $esEdicion ? ($data->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y') ?? '') : '',
        'habilitacion_en' => $esEdicion ? ($data->turnoOperativo?->habilitacion_en?->format('d/m/Y H:i') ?? '') : '',
        'cierre_en' => $esEdicion ? ($data->turnoOperativo?->cierre_en?->format('d/m/Y H:i') ?? '') : '',
        'usuario_habilita' => $esEdicion ? ($data->turnoOperativo?->usuarioHabilitacion?->nombre ?? '') : '',
        'usuario_habilitado' => $esEdicion ? ($data->turnoOperativo?->usuarioHabilitado?->nombre ?? '') : '',
        'usuario_cierre' => $esEdicion ? ($data->turnoOperativo?->usuarioCierre?->nombre ?? '') : '',
        'monto_habilitacion' => $esEdicion ? round((float) ($data->turnoOperativo?->monto_habilitacion ?? 0), 2) : 0,
        'url_comprobante_cierre' => ($esEdicion && ! $esJornada && can('ver-comprobante-cierre-turno-estacionamiento', false))
            ? route('estacionamiento_cierre_turno_comprobante_cierre', ['id' => $data->turno_operativo_estacionamiento_id, 'inline' => 1])
            : '',
        'movimientos' => $movimientosIniciales,        'numeracion_resumen' => $esEdicion && $esJornada && is_array($data->numeracion_comprobantes_json)
            ? (string) ($data->numeracion_comprobantes_json['resumen_numeracion'] ?? '')
            : '',
        'cuentacaja_efectivo_id' => $cuentacajaEfectivoId,
        'cierre_cargado' => $esEdicion,
        'totales_turno' => $totalesTurno ?? null,
        'movimientos_desde_contado_cierre' => is_array($totalesTurno ?? null)
            && ! empty($totalesTurno['arqueo_medios_cierre']),
    ];

    if ($esEdicion && $esJornada && is_array($auditoriaJornada ?? null)) {
        if (! empty($auditoriaJornada['apertura_en'])) {
            $datosInicialesJson['apertura_en'] = $auditoriaJornada['apertura_en'];
        }
        if (! empty($auditoriaJornada['cierre_en'])) {
            $datosInicialesJson['cierre_en'] = $auditoriaJornada['cierre_en'];
        }
        if (! empty($auditoriaJornada['usuario_apertura'])) {
            $datosInicialesJson['usuario_apertura'] = $auditoriaJornada['usuario_apertura'];
        }
        if (! empty($auditoriaJornada['usuario_cierre'])) {
            $datosInicialesJson['usuario_cierre'] = $auditoriaJornada['usuario_cierre'];
        }
        if (empty($datosInicialesJson['numeracion_por_puntoventa'])
            && is_array($auditoriaJornada['numeracion_comprobantes_json']['por_puntoventa'] ?? null)) {
            $datosInicialesJson['numeracion_por_puntoventa'] = $auditoriaJornada['numeracion_comprobantes_json']['por_puntoventa'];
        }
    } elseif ($esEdicion && $esJornada && is_array($data->numeracion_comprobantes_json['por_puntoventa'] ?? null)) {
        $datosInicialesJson['numeracion_por_puntoventa'] = $data->numeracion_comprobantes_json['por_puntoventa'];
    }
@endphp

<div id="rendicion-estacionamiento-app"
     data-api-turno="{{ route('api_rendicion_estacionamiento_datos_turno') }}"
     data-api-jornada="{{ route('api_rendicion_estacionamiento_datos_jornada') }}"
     data-api-turno-numero="{{ url('caja/rendicionestacionamiento/api/turno') }}"
     data-api-jornada-numero="{{ url('caja/rendicionestacionamiento/api/jornada') }}"
     data-api-proponer-codigo="{{ route('api_rendicion_estacionamiento_proponer_codigo') }}"
     data-empresa-unica="{{ $empresaUnica ? '1' : '0' }}"
     data-modo="{{ $esEdicion ? 'editar' : 'crear' }}"
     data-rendicion-id="{{ $esEdicion ? $data->id : '' }}"
     data-totales-turno='@json($totalesTurno ?? null)'
     data-totales-dia='@json($totalesDia ?? null)'
     data-tolerancia-informe-z="{{ (float) config('estacionamiento.cierre_totem_informe_z_tolerancia', 0.02) }}"
     data-inicial='@json($datosInicialesJson)'>

    @if (($caja_id ?? 0) <= 0 && ! $esEdicion)
    <div class="alert alert-danger">
        No tiene caja asignada para hoy. Ingrese desde <strong>Movimientos de caja</strong> o solicite asignación de cajero antes de registrar la rendición.
    </div>
    @endif

    <div id="alert-errores-rendicion-estacionamiento" class="alert alert-danger d-none" role="alert">
        <button type="button" class="close js-cerrar-error-rendicion" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="alert-heading mb-2"><i class="fa fa-exclamation-triangle"></i> Atención</h4>
        <div class="js-contenido-errores-rendicion mb-0"></div>
    </div>

    <input type="hidden" name="tipo" id="tipo_rendicion" value="{{ $tipoSel }}"/>

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2"><strong>Datos de la rendición</strong></div>
        <div class="card-body py-2">
            @if (! $esEdicion)
            <div class="form-group">
                <label class="d-block requerido">Origen del cierre</label>
                <div class="btn-group btn-group-toggle" data-toggle="buttons" id="grp-tipo-rendicion">
                    <label class="btn btn-outline-primary {{ ! $esJornada ? 'active' : '' }}">
                        <input type="radio" name="tipo_ui" value="turno" autocomplete="off" {{ ! $esJornada ? 'checked' : '' }}> Cierre de turno
                    </label>
                    <label class="btn btn-outline-primary {{ $esJornada ? 'active' : '' }}">
                        <input type="radio" name="tipo_ui" value="jornada" autocomplete="off" {{ $esJornada ? 'checked' : '' }}> Cierre de jornada
                    </label>
                </div>
                <small class="text-muted d-block mt-1">La rendición de jornada registra Numeración/Z por jornada y últimos comprobantes por PV; no replica en Anita.</small>
            </div>
            @else
            <p class="mb-2">
                <span class="text-muted">Tipo:</span>
                <strong>{{ $esJornada ? 'Cierre de jornada (solo ERP)' : 'Cierre de turno' }}</strong>
            </p>
            @if ($esJornada)
            <input type="hidden" name="jornada_estacionamiento_id" value="{{ $jornadaSel }}"/>
            @endif
            @endif
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="empresa_id" class="requerido">Empresa</label>
                    @if ($esEdicion)
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresaSel }}"/>
                        <input type="text" class="form-control" readonly value="{{ $data->empresa?->nombre ?? '—' }}"/>
                    @else
                        @include('includes.form-empresa-asignada-control', [
                            'empresa_query' => $empresasDisponibles,
                            'empresa_id' => $empresaSel,
                            'required' => true,
                            'opcion_vacia' => '— Seleccionar —',
                        ])
                    @endif
                </div>
                <div class="form-group col-md-4">
                    <label for="fecharendicion" class="requerido">Fecha/hora registro en caja</label>
                    <input type="datetime-local" name="fecharendicion" id="fecharendicion" class="form-control" required
                           value="{{ old('fecharendicion', $esEdicion ? $data->fecharendicion?->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}"/>
                    <small class="text-muted">Momento real del registro. La fecha contable es la de jornada del cierre.</small>
                </div>
                <div class="form-group col-md-4">
                    <label for="codigo" id="lbl-codigo">Ticket / código</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" maxlength="80" readonly
                           value="{{ $codigoInicial }}"
                           placeholder="Se asigna al guardar"/>
                    @if (! $esEdicion)
                        <small class="text-muted" id="hint-codigo">Turno: código Anita. Jornada: código interno ERP.</small>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <div class="card card-outline card-info mb-3" id="card-seleccion-cierre">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong id="titulo-seleccion-cierre">{{ $esJornada ? 'Cierre de jornada a rendir' : 'Cierre de turno a rendir' }}</strong>
            <a href="#" id="link-comprobante-cierre" class="btn btn-danger btn-sm d-none" target="_blank" rel="noopener">
                <i class="fa fa-file-pdf-o"></i> Ver comprobante estacionamiento
            </a>
        </div>
        <div class="card-body py-2">
            <div id="bloque-seleccion-turno" class="{{ $esJornada && ! $esEdicion ? 'd-none' : '' }}">
            @if ($esEdicion && ! $esJornada)
                <input type="hidden" name="turno_operativo_estacionamiento_id" id="turno_operativo_estacionamiento_id" value="{{ $turnoSel }}"/>
                <p class="mb-0"><span class="text-muted">Turno operativo:</span> <strong id="lbl-turno-seleccionado">{{ $turnoEtiqueta }}</strong></p>
            @elseif (! $esEdicion)
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-md-0">
                        <label for="turno_operativo_numero" class="requerido">Nº cierre (op.)</label>
                        <input type="number" min="1" step="1" id="turno_operativo_numero" class="form-control"
                               placeholder="Ej. 125" value="{{ old('turno_operativo_numero', $turnoSel) }}"/>
                        <input type="hidden" name="turno_operativo_estacionamiento_id" id="turno_operativo_estacionamiento_id" value="{{ $turnoSel }}"/>
                    </div>
                    <div class="form-group col-md-2 mb-md-0">
                        <button type="button" class="btn btn-warning btn-block consultacierre" title="Buscar cierre">
                            <i class="fa fa-search"></i> Consultar
                        </button>
                    </div>
                    <div class="form-group col-md-7 mb-0">
                        <label class="text-muted small mb-0">Descripción</label>
                        <p class="form-control-plaintext mb-0 font-weight-bold" id="lbl-turno-seleccionado">—</p>
                    </div>
                </div>
                <small class="text-muted">Seleccione la empresa, cargue el cierre y verifique los datos de estacionamiento antes de rendir en caja.</small>
            @endif
            </div>
            <div id="bloque-seleccion-jornada" class="{{ ! $esJornada && ! $esEdicion ? 'd-none' : '' }}">
            @if ($esEdicion && $esJornada)
                <p class="mb-0"><span class="text-muted">Jornada:</span> <strong id="lbl-jornada-seleccionada">#{{ $jornadaSel }} — {{ $data->jornada?->fecha_jornada?->format('d/m/Y') }}</strong></p>
                @if ((int) ($data->numeracion_order_id_hasta ?? 0) > 0)
                <p class="mb-0 small text-info"><i class="fa fa-flag"></i> Último comprobante Numeración incluido: <strong>{{ $data->numeracion_order_id_hasta }}</strong></p>
                @endif
            @elseif (! $esEdicion)
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-md-0">
                        <label for="jornada_estacionamiento_numero" class="requerido">Nº jornada</label>
                        <input type="number" min="1" step="1" id="jornada_estacionamiento_numero" class="form-control"
                               placeholder="Ej. 42" value="{{ old('jornada_estacionamiento_numero', $jornadaSel) }}"/>
                        <input type="hidden" name="jornada_estacionamiento_id" id="jornada_estacionamiento_id" value="{{ $jornadaSel }}"/>
                    </div>
                    <div class="form-group col-md-2 mb-md-0">
                        <button type="button" class="btn btn-warning btn-block consultacierrejornada" title="Buscar jornada">
                            <i class="fa fa-search"></i> Consultar
                        </button>
                    </div>
                    <div class="form-group col-md-7 mb-0">
                        <label class="text-muted small mb-0">Descripción</label>
                        <p class="form-control-plaintext mb-0 font-weight-bold" id="lbl-jornada-seleccionada">—</p>
                    </div>
                </div>
                <small class="text-muted">Jornadas cerradas pendientes de presentación en caja. Al cargar, revise Numeración, Totales Z y totales antes de rendir.</small>
            @endif
            </div>
        </div>
    </div>

    @if (! $esEdicion)
    <div id="aviso-sin-cierre-cargado" class="alert alert-warning mb-3">
        <strong><i class="fa fa-info-circle"></i> Para ver medios de pago, totales, ticket Numeración e Totales Z:</strong>
        seleccione la empresa, elija <em>Cierre de jornada</em> o <em>Cierre de turno</em>, ingrese el número y pulse
        <strong>Consultar</strong> o salga del campo numérico (Tab/Enter). Los datos aparecen debajo de esta advertencia.
    </div>
    @endif

    <div id="panel-datos-turno" class="d-none">
        <div id="panel-verificacion-estacionamiento" class="card card-outline card-primary mb-3">
            <div class="card-header py-2 bg-primary text-white">
                <strong><i class="fa fa-check-square-o"></i> Verificación del cajero — datos de estacionamiento</strong>
            </div>
            <div class="card-body py-2">
                <p class="mb-2 small">
                    Compare con el comprobante o resumen que entrega el operador de estacionamiento:
                    totales facturados y cobrados, medios de pago, redondeos y (en jornada) último Numeración, numeración por PV e Totales Z por jornada.
                </p>
                <ul class="small mb-0 pl-3" id="lista-verificacion-estacionamiento">
                    <li id="verif-item-comprobante" class="text-muted">Abrir el comprobante de cierre estacionamiento (botón arriba).</li>
                    <li id="verif-item-totales" class="text-muted">Revisar facturación y cobranzas del cierre.</li>
                    <li id="verif-item-medios" class="text-muted">Contrastar medios rendidos en caja con lo físico recibido.</li>
                    <li id="verif-item-jornada-numeracion" class="text-muted d-none">Validar último ticket Numeración y numeración de comprobantes.</li>
                    <li id="verif-item-jornada-z" class="text-muted d-none">Validar Totales Z vs totales del sistema por jornada.</li>
                </ul>
            </div>
        </div>

        <div id="panel-auditoria-jornada" class="d-none card card-outline card-warning mb-3">
            <div class="card-header py-2"><strong>Marcadores de auditoría (jornada)</strong></div>
            <div class="card-body py-2 small">
                <p class="mb-2"><span class="text-muted">Últimos comprobantes AnitaERP por punto de venta (todos los CAE y CAEA configurados):</span></p>
                <div id="contenedor-numeracion-pv" class="table-responsive">
                    <p class="text-muted mb-0" id="lbl-numeracion-pv">—</p>
                </div>
            </div>
        </div>

        <div id="panel-informe-z-jornada" class="d-none card card-outline card-secondary mb-3">
            <div class="card-header py-2"><strong>Conciliación Totales Z (jornadas Numeración)</strong></div>
            <div class="card-body py-2 small" id="contenido-informe-z-jornada"></div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2"><strong id="titulo-panel-datos">{{ $esJornada ? 'Datos de la jornada rendida' : 'Datos del turno rendido' }}</strong></div>
            <div class="card-body py-2 small">
                <div class="row">
                    <div class="col-md-3">
                        <span class="text-muted d-block">Terminal (PC)</span>
                        <strong id="lbl-pc">—</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block">Turno</span>
                        <strong id="lbl-turno">—</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block">Fecha jornada</span>
                        <strong id="lbl-jornada">—</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block">Monto habilitación</span>
                        <strong id="lbl-monto-habilitacion">—</strong>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <span class="text-muted d-block">Habilitación</span>
                        <strong id="lbl-habilitacion-en">—</strong>
                        <span class="text-muted" id="lbl-usuarios-habilitacion"></span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Cierre definitivo</span>
                        <strong id="lbl-cierre-en">—</strong>
                        <span class="text-muted" id="lbl-usuario-cierre"></span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Inicio del fondo (rendición)</span>
                        <strong id="lbl-fondo">—</strong>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Punto de venta CAE</span>
                        <strong id="lbl-pv-cae">—</strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Punto de venta CAEA</span>
                        <strong id="lbl-pv-caea">—</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2"><strong id="titulo-panel-facturacion">Facturación y cobranzas del turno</strong></div>
            <div class="card-body py-2">
                <div id="panel-resumen-cierre"></div>
            </div>
        </div>

        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <strong>Medios rendidos en caja</strong>
                <small class="text-muted d-block font-weight-normal mt-1">Indique lo que ingresa físicamente a caja por cada medio. Si el turno tiene arqueo en el cierre, los montos se precargan con lo <strong>contado por el cajero</strong> (eso es lo que se graba en Anita como rendvalor). Si algún monto rendido difiere del <strong>esperado sistema</strong>, el campo <strong>sobrante / faltante</strong> se ajusta automáticamente para mantener la rendición cuadrada.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-movimientos">
                        <thead class="thead-light gastro-totales-tabla">
                            <tr>
                                <th>Medio de pago</th>
                                <th class="gastro-col-esperado text-right">Esperado sistema</th>
                                <th class="gastro-col-monto text-right">Monto rendido</th>
                                <th class="gastro-col-cotiz text-right">Cotiz.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-movimientos">
                            <tr><td colspan="4" class="text-muted text-center p-3">Seleccione un cierre de turno.</td></tr>
                        </tbody>
                        <tfoot class="thead-light">
                            <tr class="font-weight-bold">
                                <td>Total grilla</td>
                                <td></td>
                                <td class="text-right gastro-totales-monto" id="total-grilla">$0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-outline card-warning mb-3">
            <div class="card-header py-2"><strong>Ajustes de rendición</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="totalredondeo">Redondeo rendición</label>
                        <input type="number" step="0.01" name="totalredondeo" id="totalredondeo" class="form-control js-recalcula" value="{{ old('totalredondeo', $esEdicion ? $data->totalredondeo : 0) }}"/>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="totalredondeoinvitacion">Redondeo invitaciones</label>
                        <input type="number" step="0.01" name="totalredondeoinvitacion" id="totalredondeoinvitacion" class="form-control js-recalcula" value="{{ old('totalredondeoinvitacion', $esEdicion ? $data->totalredondeoinvitacion : 0) }}"/>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="sobrantefaltante">Sobrante / faltante</label>
                        <input type="number" step="0.01" name="sobrantefaltante" id="sobrantefaltante" class="form-control js-recalcula" value="{{ old('sobrantefaltante', $esEdicion ? $data->sobrantefaltante : 0) }}"/>
                        <small class="text-muted">Positivo = sobrante, negativo = faltante. Se recalcula al modificar cualquier medio rendido (compensa la diferencia con el sistema).</small>
                    </div>
                </div>
                <div id="alert-diferencias" class="alert mb-0 d-none"></div>
            </div>
        </div>
    </div>

    <input type="hidden" name="caja_id" id="caja_id" value="{{ old('caja_id', $esEdicion ? $data->caja_id : ($caja_id ?? 0)) }}"/>
    <input type="hidden" name="puntoventa_cae_id" id="puntoventa_cae_id" value="{{ old('puntoventa_cae_id', $esEdicion ? $data->puntoventa_cae_id : '') }}"/>
    <input type="hidden" name="puntoventa_caea_id" id="puntoventa_caea_id" value="{{ old('puntoventa_caea_id', $esEdicion ? $data->puntoventa_caea_id : '') }}"/>
    <input type="hidden" name="iniciodelfondo" id="iniciodelfondo" value="{{ old('iniciodelfondo', $esEdicion ? $data->iniciodelfondo : 0) }}"/>
    <input type="hidden" name="totalfactura" id="totalfactura" value="{{ old('totalfactura', $esEdicion ? $data->totalfactura : 0) }}"/>
    <input type="hidden" name="totalcobrado" id="totalcobrado" value="{{ old('totalcobrado', $esEdicion ? $data->totalcobrado : 0) }}"/>
    <input type="hidden" name="totalinvitacion" id="totalinvitacion" value="{{ old('totalinvitacion', $esEdicion ? $data->totalinvitacion : 0) }}"/>
    <input type="hidden" name="totalnotacredito" id="totalnotacredito" value="{{ old('totalnotacredito', $esEdicion ? $data->totalnotacredito : 0) }}"/>

    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
        <div class="col-lg-8">
            <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="65535">{{ old('observacion', $esEdicion ? $data->observacion : '') }}</textarea>
        </div>
    </div>
</div>
