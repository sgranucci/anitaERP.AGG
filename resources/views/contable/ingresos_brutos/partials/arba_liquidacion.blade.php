@php
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
    $periodoTxt = substr((string) $arba['periodo'], 4, 2).'/'.substr((string) $arba['periodo'], 0, 4);
    $quincenaTxt = ((int) $arba['quincena']) === 1 ? '1.ª' : '2.ª';
    $control = $arba['control'] ?? [];
    $sol = $arbaSolicitud ?? ['error' => 'Falta la configuración de la solicitud de pago ARBA.', 'puede_generar' => false];
    $puedeSolicitud = ! empty($sol['puede_generar']);
    $estadoDev = [
        'neteo' => ['Neteo', 'badge-success'],
        'pendiente' => ['Pendiente', 'badge-warning'],
        'confirmar' => ['Confirmar certificado', 'badge-info'],
        'sin_identificar' => ['Sin identificar', 'badge-danger'],
    ];
    $estadoCert = [
        'sin_cambio' => ['Sin cambio', 'text-muted'],
        'modificado' => ['Neteo parcial', 'text-warning'],
        'eliminado' => ['Neteo total (sale del TXT)', 'text-danger'],
    ];
@endphp

<div class="card card-outline card-primary mt-3" id="iibb-arba-panel">
    <div class="card-header d-flex align-items-center flex-wrap">
        <h3 class="card-title mb-0">
            Liquidación ARBA — {{ $quincenaTxt }} quincena {{ $periodoTxt }}
        </h3>
        <span class="text-muted small ml-2">Neteo de devoluciones contra retenciones del mismo proveedor</span>
        <div class="ml-auto">
            @if (! empty($control['habilitado']))
                @if (! empty($control['ok']))
                    <span class="badge badge-success"><i class="fa fa-check"></i> Conciliación OK (tolerancia ${{ $m($control['tolerancia'] ?? 0) }})</span>
                @else
                    <span class="badge badge-danger"><i class="fa fa-times"></i> Conciliación con diferencias</span>
                @endif
            @endif
        </div>
    </div>

    <div class="card-body">
        @if (! empty($arba['bloqueos']))
            <div class="alert alert-warning mb-2">
                <strong><i class="fa fa-exclamation-triangle"></i> No se puede liquidar todavía:</strong>
                <ul class="mb-0">
                    @foreach ($arba['bloqueos'] as $b)
                        <li>{{ $b }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($arba['advertencias']))
            <div class="alert alert-warning mb-2">
                <ul class="mb-0">
                    @foreach ($arba['advertencias'] as $a)
                        <li><i class="fa fa-clock"></i> {{ $a }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($arbaLiquidacion)
            <div class="small text-muted mb-2">
                Última liquidación de esta quincena: <strong>{{ $arbaLiquidacion->nombre_txt }}</strong>
                del {{ optional($arbaLiquidacion->created_at)->format('d/m/Y H:i') }}
                — VEP {{ $m($arbaLiquidacion->vep_esperado) }}.
            </div>
        @endif

        <form method="POST" id="form-arba" action="{{ route('ingresos_brutos_arba_recalcular') }}" data-enter-campos="1">
            @csrf
            @foreach ($filtrosQuery as $k => $v)
                @if (! is_array($v))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach

            {{-- Devoluciones del período --}}
            <h6 class="mt-2">Devoluciones de la quincena (asiento 214010-014, Debe)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="iibb-arba-devoluciones">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Asiento</th>
                            <th>Fecha</th>
                            <th>Detalle</th>
                            <th class="text-right">Importe</th>
                            <th>Proveedor</th>
                            <th class="text-right">Cert. ref.</th>
                            <th class="text-right">Neteado</th>
                            <th class="text-right">Pendiente</th>
                            <th>Estado</th>
                            <th style="min-width:230px;">Corregir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($arba['devoluciones'] as $f)
                            @php
                                [$etq, $cls] = $estadoDev[$f['estado']] ?? [$f['estado'], 'badge-secondary'];
                            @endphp
                            <tr>
                                <td>{{ $f['asiento_numero'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                                <td class="small">{{ $f['detalle'] }}</td>
                                <td class="text-right">{{ $m($f['importe']) }}</td>
                                <td class="small">
                                    {{ $f['proveedor_label'] !== '' ? $f['proveedor_label'] : '—' }}
                                    @if ($f['identificacion'])
                                        <span class="text-muted">({{ $f['identificacion'] }})</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $f['cert_ref'] ?: '—' }}</td>
                                <td class="text-right">{{ $m($f['neteado'] ?? 0) }}</td>
                                <td class="text-right">{{ $m($f['pendiente'] ?? 0) }}</td>
                                <td><span class="badge {{ $cls }}">{{ $etq }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center">
                                        <div class="w-100 mb-1">
                                            @include('compras.partials.campo_consulta_proveedor', [
                                                'prefix' => 'dec-'.$f['asiento_movimiento_id'],
                                                'proveedorId' => '',
                                                'codigo' => '',
                                                'nombre' => '',
                                                'label' => 'Proveedor',
                                                'inputName' => 'dec['.$f['asiento_movimiento_id'].'][proveedor_id]',
                                                'inputId' => 'dec-prov-'.$f['asiento_movimiento_id'],
                                                'required' => false,
                                                'col_label' => 'sr-only',
                                                'col_input' => 'w-100 p-0',
                                            ])
                                        </div>
                                        <input type="text" class="form-control form-control-sm mr-1 mb-1" style="width:90px;"
                                            name="dec[{{ $f['asiento_movimiento_id'] }}][cert]"
                                            value="{{ $f['cert_ref'] ?: '' }}" placeholder="Certificado">
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="dec-pend-{{ $f['asiento_movimiento_id'] }}"
                                            name="dec[{{ $f['asiento_movimiento_id'] }}][pendiente]" value="1"
                                            @checked($f['estado'] === 'pendiente')>
                                        <label class="custom-control-label small" for="dec-pend-{{ $f['asiento_movimiento_id'] }}">No netear (pendiente)</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="dec-conf-{{ $f['asiento_movimiento_id'] }}"
                                            name="dec[{{ $f['asiento_movimiento_id'] }}][confirmo]" value="1"
                                            @checked(in_array($f['estado'], ['neteo', 'pendiente'], true))>
                                        <label class="custom-control-label small" for="dec-conf-{{ $f['asiento_movimiento_id'] }}">Confirmo proveedor / certificado</label>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No hay devoluciones de retenciones en la quincena.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pendientes --}}
            @if (! empty($arba['pendientes']))
                <h6 class="mt-3">Pendientes sin neteo</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="iibb-arba-pendientes">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Origen</th>
                                <th>Asiento</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th class="text-right">Saldo</th>
                                <th class="text-right">Antigüedad (días)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($arba['pendientes'] as $p)
                                <tr class="{{ ! empty($p['aviso']) ? 'table-warning' : '' }}">
                                    <td>{{ ($p['origen'] ?? '') === 'anterior' ? 'Quincena anterior' : 'Esta quincena' }}</td>
                                    <td>{{ $p['asiento_numero'] ?? '' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($p['fecha'])->format('d/m/Y') }}</td>
                                    <td class="small">{{ $p['proveedor_label'] ?? '' }}</td>
                                    <td class="text-right">{{ $m($p['pendiente']) }}</td>
                                    <td class="text-right">
                                        {{ $p['antiguedad_dias'] ?? 0 }}
                                        @if (! empty($p['aviso']))
                                            <span class="badge badge-warning">&gt; {{ $arba['dias_aviso'] ?? 0 }} días</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Neteos contra certificados --}}
            @if (! empty($arba['aplicaciones']))
                <h6 class="mt-3">Neteos aplicados</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="iibb-arba-neteos">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Certificado</th>
                                <th>Devolución</th>
                                <th class="text-right">Importe neteado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($arba['aplicaciones'] as $ap)
                                <tr>
                                    <td>{{ $ap['nro_cert'] }}</td>
                                    <td class="small">{{ $ap['etiqueta'] ?? $ap['devolucion_id'] }}</td>
                                    <td class="text-right">{{ $m($ap['importe']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Certificados con neteo --}}
            @php
                $certsAjustados = array_filter($arba['certificados'] ?? [], static fn ($c) => ($c['estado'] ?? '') !== 'sin_cambio');
            @endphp
            @if (! empty($certsAjustados))
                <h6 class="mt-3">Certificados ajustados por el neteo</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="iibb-arba-certificados">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Certificado</th>
                                <th>CUIT</th>
                                <th>Razón social</th>
                                <th class="text-right">Importe original</th>
                                <th class="text-right">Neteado</th>
                                <th class="text-right">Importe TXT</th>
                                <th class="text-right">Base TXT</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($certsAjustados as $c)
                                @php [$eC, $cC] = $estadoCert[$c['estado']] ?? [$c['estado'], '']; @endphp
                                <tr>
                                    <td>{{ $c['nro_cert'] }}</td>
                                    <td>{{ $c['nro_documento'] }}</td>
                                    <td class="small">{{ $c['razon_social'] }}</td>
                                    <td class="text-right">{{ $m($c['importe_original']) }}</td>
                                    <td class="text-right">{{ $m($c['neteado']) }}</td>
                                    <td class="text-right">{{ $m($c['importe_nuevo']) }}</td>
                                    <td class="text-right">{{ $m($c['base_txt']) }}</td>
                                    <td class="{{ $cC }}">{{ $eC }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Totales y conciliación --}}
            @php $t = $arba['totales'] ?? []; @endphp
            @if (! empty($t))
                <h6 class="mt-3">Conciliación</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="iibb-arba-conciliacion">
                        <tbody>
                            <tr><td>Retenciones ERP (listado)</td><td class="text-right">{{ $m($t['erp']) }}</td></tr>
                            <tr><td>(−) Neteo de la quincena</td><td class="text-right">{{ $m($t['neteo_quincena']) }}</td></tr>
                            <tr><td>(−) Neteo de quincenas anteriores</td><td class="text-right">{{ $m($t['neteo_anteriores']) }}</td></tr>
                            <tr class="font-weight-bold"><td>TXT ARBA</td><td class="text-right">{{ $m($t['txt']) }}</td></tr>
                            <tr><td>Pendientes de la quincena (sin neteo)</td><td class="text-right">{{ $m($t['pendientes_quincena']) }}</td></tr>
                            <tr><td>Pendientes de quincenas anteriores</td><td class="text-right">{{ $m($t['pendientes_anteriores']) }}</td></tr>
                            <tr><td>Movimiento del período en el mayor</td><td class="text-right">{{ $t['movimiento_mayor'] !== null ? $m($t['movimiento_mayor']) : '—' }}</td></tr>
                            <tr><td>Residuo período (mayor − TXT + pendientes − neteos anteriores)</td>
                                <td class="text-right {{ ! empty($control['ok']) ? '' : 'text-danger' }}">{{ $control['residuo_periodo'] !== null ? $m($control['residuo_periodo']) : '—' }}</td></tr>
                            <tr><td>Saldo del mayor (cuenta, al hasta)</td><td class="text-right">{{ $t['saldo_mayor'] !== null ? $m($t['saldo_mayor']) : '—' }}</td></tr>
                            <tr><td>Residuo saldo (saldo mayor − TXT + pendientes)</td>
                                <td class="text-right {{ ! empty($control['ok']) ? '' : 'text-danger' }}">{{ $control['residuo_pendientes'] !== null ? $m($control['residuo_pendientes']) : '—' }}</td></tr>
                            <tr class="font-weight-bold"><td>DJ (suma de líneas truncadas)</td><td class="text-right">{{ $m($t['dj']) }}</td></tr>
                            <tr class="font-weight-bold"><td>VEP (DJ truncada a 10 centavos)</td><td class="text-right">{{ $m($t['vep']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="d-flex flex-wrap align-items-center">
                <button type="submit" class="btn btn-sm btn-outline-primary mr-2 mb-1" name="accion" value="recalcular">
                    <i class="fa fa-sync"></i> Recalcular neteo
                </button>
                <button type="submit" class="btn btn-sm btn-success mr-2 mb-1"
                    formaction="{{ route('ingresos_brutos_arba_liquidar') }}"
                    @disabled(empty($arba['puede_liquidar']) || ! can('exportar-ingresos-brutos', false))
                    title="Persiste devoluciones y neteos, y descarga el ZIP (TXT + PDF)">
                    <i class="fa fa-file-archive"></i> Liquidación ARBA (ZIP)
                </button>
                <button type="button" class="btn btn-sm btn-primary mr-2 mb-1"
                    data-toggle="modal" data-target="#modal-arba-solicitud"
                    @disabled(! $puedeSolicitud || ! can('crear-solicitud-pago', false))
                    title="{{ $puedeSolicitud ? 'Genera la solicitud de pago del VEP' : ($sol['error'] ?? '') }}">
                    <i class="fa fa-file-invoice-dollar"></i> Generar solicitud de pago
                </button>
                @if (! $puedeSolicitud && ! empty($sol['error']))
                    <span class="small text-muted mb-1">{{ $sol['error'] }}</span>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Solicitud de pago: fuera del form de liquidación para no anidar formularios --}}
@if ($puedeSolicitud)
    <div class="modal fade" id="modal-arba-solicitud" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('ingresos_brutos_arba_solicitud_pago') }}" enctype="multipart/form-data" data-enter-campos="1">
                    @csrf
                    @foreach ($filtrosQuery as $k => $v)
                        @if (! is_array($v))
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <div class="modal-header">
                        <h5 class="modal-title">Generar solicitud de pago — VEP ARBA</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @if (! empty($sol['duplicado']))
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i> {{ $sol['duplicado']['mensaje'] }}
                                <a href="{{ $sol['duplicado']['url'] }}" target="_blank" rel="noopener">Ver solicitud</a>
                            </div>
                        @endif

                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr><th style="width:30%">Empresa</th><td>{{ $sol['empresa_label'] }}</td></tr>
                                <tr><th>Proveedor</th><td>{{ $sol['proveedor_label'] }}</td></tr>
                                <tr><th>Concepto / forma</th><td>{{ $sol['concepto_label'] }} — {{ $sol['formapago_label'] }}</td></tr>
                                <tr><th>Sector / moneda</th><td>{{ $sol['sector_label'] }} — {{ $sol['moneda_label'] }}</td></tr>
                                <tr><th>Centro de costo</th><td>{{ $sol['centrocosto_label'] }}</td></tr>
                                <tr><th>DJ / VEP</th><td>DJ {{ $sol['dj_fmt'] }} — <strong>VEP {{ $sol['monto_fmt'] }}</strong></td></tr>
                                <tr><th>Detalle</th><td>{{ $sol['detalle'] }}</td></tr>
                            </tbody>
                        </table>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background-color:#85C1E9;color:#17202A;">
                                    <tr><th>Cuenta</th><th>Centro de costo</th><th>D/H</th><th class="text-right">Debe</th><th class="text-right">Haber</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($sol['asiento'] as $linea)
                                        <tr>
                                            <td class="small">{{ $linea['cuenta'] }}</td>
                                            <td class="small">{{ $linea['centrocosto'] }}</td>
                                            <td>{{ $linea['dh'] }}</td>
                                            <td class="text-right">{{ $linea['debe'] !== null ? $m($linea['debe']) : '' }}</td>
                                            <td class="text-right">{{ $linea['haber'] !== null ? $m($linea['haber']) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="arba-fecha-vto">Vencimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="arba-fecha-vto" name="fecha_vencimiento" required>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="arba-observacion">Observación</label>
                                <input type="text" class="form-control" id="arba-observacion" name="observacion" maxlength="160">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="arba-adjuntos">Adjuntos (VEP y otros)</label>
                            <input type="file" class="form-control-file" id="arba-adjuntos" name="adjuntos[]" multiple>
                            <small class="form-text text-muted">La constancia de la liquidación ({{ $sol['nombre_adjunto_dj'] }}) se adjunta automáticamente.</small>
                        </div>
                        @if (! empty($sol['duplicado']))
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="arba-generar-igual" name="generar_igual" value="1">
                                <label class="custom-control-label" for="arba-generar-igual">Generar igual pese al posible duplicado</label>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i> Generar solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- Consulta de proveedor para las devoluciones sin identificar (fuera de los formularios) --}}
@include('includes.compras.modalconsultaproveedor')
