@php
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
    $turnoSel = old('turno_operativo_gastronomia_id', $esEdicion ? $data->turno_operativo_gastronomia_id : '');
    $empresaSel = old('empresa_id', $esEdicion ? $data->empresa_id : ($empresa_default_id ?? ''));

    $pvCaeLabel = '';
    $pvCaeaLabel = '';
    $turnoEtiqueta = '';
    if ($esEdicion) {
        $pvCaeLabel = trim(($data->puntoventaCae?->codigo ?? '').' — '.($data->puntoventaCae?->nombre ?? ''), ' —');
        $pvCaeaLabel = trim(($data->puntoventaCaea?->codigo ?? '').' — '.($data->puntoventaCaea?->nombre ?? ''), ' —');
        $turnoEtiqueta = 'Op. #'.$data->turno_operativo_gastronomia_id
            .' — '.($data->turnoOperativo?->turno?->nombre ?? '')
            .' — '.($data->turnoOperativo?->identificador_pc ?? '')
            .' — cierre '.($data->turnoOperativo?->cierre_en?->format('d/m/Y H:i') ?? '');
    }

    $codigoInicial = old('codigo', $esEdicion ? $data->codigo : ($codigo_propuesto ?? ''));
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = ! $esEdicion && $empresasDisponibles->count() === 1;
    $apiBase = rtrim((string) config('app.app_carpeta', ''), '/');

    $datosInicialesJson = [
        'turno_operativo_gastronomia_id' => $turnoSel,
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
        'url_comprobante_cierre' => $esEdicion
            ? route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $data->turno_operativo_gastronomia_id, 'inline' => 1])
            : '',
        'movimientos' => $movimientosIniciales,
    ];
@endphp

<div id="rendicion-gastronomia-app"
     data-api-turno="{{ $apiBase }}/caja/rendiciongastronomia/api/datos-turno"
     data-api-turno-numero="{{ $apiBase }}/caja/rendiciongastronomia/api/turno"
     data-api-proponer-codigo="{{ $apiBase }}/caja/rendiciongastronomia/api/proponer-codigo"
     data-empresa-unica="{{ $empresaUnica ? '1' : '0' }}"
     data-modo="{{ $esEdicion ? 'editar' : 'crear' }}"
     data-rendicion-id="{{ $esEdicion ? $data->id : '' }}"
     data-totales-turno='@json($totalesTurno ?? null)'
     data-inicial='@json($datosInicialesJson)'>

    @if (($caja_id ?? 0) <= 0 && ! $esEdicion)
    <div class="alert alert-danger">
        No tiene caja asignada para hoy. Ingrese desde <strong>Movimientos de caja</strong> o solicite asignación de cajero antes de registrar la rendición.
    </div>
    @endif

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2"><strong>Datos de la rendición</strong></div>
        <div class="card-body py-2">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="empresa_id" class="requerido">Empresa</label>
                    @if ($esEdicion)
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresaSel }}"/>
                        <input type="text" class="form-control" readonly value="{{ $data->empresa?->nombre ?? '—' }}"/>
                    @elseif ($empresaUnica)
                        @php $empUnica = $empresasDisponibles->first(); @endphp
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empUnica->id }}"/>
                        <input type="text" class="form-control" readonly value="{{ $empUnica->nombre }}"/>
                    @else
                        <select name="empresa_id" id="empresa_id" class="form-control" required>
                            <option value="">— Seleccionar —</option>
                            @foreach ($empresasDisponibles as $emp)
                                <option value="{{ $emp->id }}" @selected((string) $empresaSel === (string) $emp->id)>{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="form-group col-md-4">
                    <label for="fecharendicion" class="requerido">Fecha rendición</label>
                    <input type="datetime-local" name="fecharendicion" id="fecharendicion" class="form-control" required
                           value="{{ old('fecharendicion', $esEdicion ? $data->fecharendicion?->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}"/>
                </div>
                <div class="form-group col-md-4">
                    <label for="codigo">Ticket / código Anita</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" maxlength="50" readonly
                           value="{{ $codigoInicial }}"
                           placeholder="Se asigna al guardar"/>
                    @if (! $esEdicion)
                        <small class="text-muted">Se genera automáticamente al registrar (siguiente disponible).</small>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong>Cierre de turno a rendir</strong>
            <a href="#" id="link-comprobante-cierre" class="btn btn-outline-danger btn-sm d-none" target="_blank">
                <i class="fa fa-file-pdf-o"></i> Comprobante cierre
            </a>
        </div>
        <div class="card-body py-2">
            @if ($esEdicion)
                <input type="hidden" name="turno_operativo_gastronomia_id" id="turno_operativo_gastronomia_id" value="{{ $turnoSel }}"/>
                <p class="mb-0"><span class="text-muted">Turno operativo:</span> <strong id="lbl-turno-seleccionado">{{ $turnoEtiqueta }}</strong></p>
            @else
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-md-0">
                        <label for="turno_operativo_numero" class="requerido">Nº cierre (op.)</label>
                        <input type="number" min="1" step="1" id="turno_operativo_numero" class="form-control"
                               placeholder="Ej. 125" value="{{ old('turno_operativo_numero', $turnoSel) }}"/>
                        <input type="hidden" name="turno_operativo_gastronomia_id" id="turno_operativo_gastronomia_id" value="{{ $turnoSel }}"/>
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
                <small class="text-muted">Seleccione la empresa y busque el cierre por número o desde la consulta.</small>
            @endif
        </div>
    </div>

    <div id="panel-datos-turno" class="d-none">
        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2"><strong>Datos del turno rendido</strong></div>
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
            <div class="card-header py-2"><strong>Facturación y cobranzas del turno</strong></div>
            <div class="card-body py-2">
                <div id="panel-resumen-cierre"></div>
            </div>
        </div>

        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2"><strong>Medios rendidos en caja</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-movimientos">
                        <thead class="thead-light gastro-totales-tabla">
                            <tr>
                                <th>Medio de pago</th>
                                <th class="text-right" style="width:160px">Monto rendido</th>
                                <th class="text-right" style="width:100px">Cotiz.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-movimientos">
                            <tr><td colspan="3" class="text-muted text-center p-3">Seleccione un cierre de turno.</td></tr>
                        </tbody>
                        <tfoot class="thead-light">
                            <tr class="font-weight-bold">
                                <td>Total grilla</td>
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
                        <small class="text-muted">Positivo = sobrante, negativo = faltante</small>
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
