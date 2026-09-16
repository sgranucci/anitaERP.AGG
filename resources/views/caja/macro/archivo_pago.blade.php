@extends("theme.$theme.layout")

@section('titulo')
    Archivo pagos Macro
@endsection

@section('contenido')
@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-3';
    $empresasDisponibles = collect($empresa_query ?? []);
    $filas = $resultado['filas'] ?? [];
    $omitidas = $resultado['omitidas'] ?? [];
    $errores = $resultado['errores'] ?? [];
@endphp

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (session('mensaje_error'))
            <div class="alert alert-danger">{{ session('mensaje_error') }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Exportación pagos Banco Macro (archivo)</h3>
                <div class="card-tools">
                    <a href="{{ route('macro_archivo_pago') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('interbanking_archivo_pago') }}" class="btn btn-outline-info btn-sm" title="Interbanking">
                        <i class="fa fa-exchange-alt"></i> Archivo IB
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('macro_archivo_pago') }}" id="form-macro-archivo-pago" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente a Anita <code>p-enviamacro</code>: genera <strong>BNF.TXT</strong>,
                        <strong>OPG.TXT</strong> y <strong>RTN.TXT</strong> (ZIP).
                        Filtra OP con <code>auxpag.axp_banco</code> = cuenta elegida (TMR/TMK/TMB o CHP/CPC)
                        y las del ERP debitadas en esa cuenta de caja.
                        Canal actual: <code>{{ $canal ?? 'archivo' }}</code>
                        (webservice premium se habilita con <code>MACRO_PAGO_CANAL</code>).
                    </p>

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                        <label class="{{ $colLabel }} requerido">Cuenta de caja</label>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="cuentacaja_id" id="cuentacaja_id"
                                   value="{{ (int) ($filtros['cuentacaja_id'] ?? 0) ?: '' }}">
                            <input type="hidden" name="cuenta_anita" id="cuenta_anita"
                                   value="{{ $filtros['cuenta_anita'] ?? '' }}">
                            <div class="input-group">
                                <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                       value="{{ $cuenta_origen->codigo ?? '' }}"
                                       placeholder="Código" title="Código Anita / ERP. F1 = consulta">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultacuentacaja"
                                            title="Consultar cuentas de caja (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="nombre_cuentacaja" class="{{ $colLabel }}">Nombre cuenta</label>
                        <div class="{{ $colInput }}">
                            <input type="text" class="form-control" id="nombre_cuentacaja" readonly
                                   value="{{ $cuenta_origen->nombre ?? '' }}">
                        </div>
                        <label for="cuenta_debito" class="{{ $colLabel }}">Cuenta débito Macro</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="cuenta_debito" id="cuenta_debito" class="form-control text-monospace"
                                   value="{{ $filtros['cuenta_debito'] ?? '' }}"
                                   maxlength="20" readonly
                                   title="Se calcula sola desde el CBU de la cuenta + sucursal (como p-enviamacro)">
                            <small class="form-text text-muted">Automática desde CBU de la cuenta (no se carga a mano).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="tipo_op" class="{{ $colLabel }}">Tipo de OP</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="tipo_op" id="tipo_op" class="form-control" maxlength="3"
                                   value="{{ $filtros['tipo_op'] ?? '0' }}"
                                   title="0 = todas (OP* + IEV). OPP también incluye IEV (reemplazos de cheques).">
                            <small class="form-text text-muted">0 = todas · OPP incluye IEV (reemplazos)</small>
                        </div>
                        <label for="tipo_aplicacion" class="{{ $colLabel }}">Tipo aplicación Anita</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="tipo_aplicacion" id="tipo_aplicacion" class="form-control" maxlength="3"
                                   value="{{ $filtros['tipo_aplicacion'] ?? '' }}"
                                   placeholder="Vacío = TMR/TMK/TMB + CHP"
                                   title="axp_tipo_ap. Vacío = transferencias y cheques Macro.">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="op_desde" class="{{ $colLabel }}">Desde OP</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="op_desde" id="op_desde" class="form-control" min="0"
                                   value="{{ (int) ($filtros['op_desde'] ?? 0) }}">
                        </div>
                        <label for="op_hasta" class="{{ $colLabel }}">Hasta OP</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="op_hasta" id="op_hasta" class="form-control" min="0"
                                   value="{{ (int) ($filtros['op_hasta'] ?? 99999999) }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="sucursal_banco" class="{{ $colLabel }}">Sucursal banco</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="sucursal_banco" id="sucursal_banco" class="form-control" min="0" max="999"
                                   value="{{ (int) ($filtros['sucursal_banco'] ?? 651) }}">
                            <small class="form-text text-muted">Gerli = 651</small>
                        </div>
                        <label for="usuario_retencion" class="{{ $colLabel }}">Usuario retenciones</label>
                        <div class="{{ $colInput }}">
                            @php
                                $usrMap = $usuarios_retencion ?? [];
                                $usrActual = (string) ($filtros['usuario_retencion'] ?? '');
                            @endphp
                            <select name="usuario_retencion" id="usuario_retencion" class="form-control">
                                @foreach ($usrMap as $empCod => $usr)
                                    <option value="{{ $usr }}"
                                        data-empresa="{{ (int) $empCod }}"
                                        @selected($usrActual === $usr)>
                                        {{ $usr }}
                                        @if ((int) $empCod === 1) (Biyemas)
                                        @elseif ((int) $empCod === 2) (Kandiko)
                                        @elseif ((int) $empCod === 3) (Rebisco)
                                        @endif
                                    </option>
                                @endforeach
                                @if ($usrActual !== '' && ! in_array($usrActual, array_values($usrMap), true))
                                    <option value="{{ $usrActual }}" selected>{{ $usrActual }}</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Orígenes</label>
                        <div class="col-lg-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="incluir_erp" id="incluir_erp" value="1"
                                    @checked(!empty($filtros['incluir_erp']))>
                                <label class="form-check-label" for="incluir_erp">ERP (OP + IE)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="incluir_anita" id="incluir_anita" value="1"
                                    @checked(!empty($filtros['incluir_anita']))>
                                <label class="form-check-label" for="incluir_anita">Anita (pago/auxpag)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="incluir_transferencias" id="incluir_transferencias" value="1"
                                    @checked(!empty($filtros['incluir_transferencias']))>
                                <label class="form-check-label" for="incluir_transferencias">Transferencias</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="incluir_cheques" id="incluir_cheques" value="1"
                                    @checked(!empty($filtros['incluir_cheques']))>
                                <label class="form-check-label" for="incluir_cheques">Cheques</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if (!empty($consultado) && ($resultado['cantidad'] ?? 0) > 0)
                        <a href="{{ route('descargar_macro_archivo_pago', $filtrosQuery) }}"
                           class="btn btn-success" id="btn-descargar-macro">
                            <i class="fa fa-download"></i> Descargar ZIP Macro
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if (!empty($consultado) && $resultado)
            <div class="card card-outline card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $resultado['mensaje'] ?? '' }}
                        @if (($resultado['cantidad'] ?? 0) > 0)
                            — Total $ {{ number_format((float) ($resultado['total_importe'] ?? 0), 2, ',', '.') }}
                        @endif
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Origen</th>
                                <th>Medio</th>
                                <th>N.Pro.</th>
                                <th>Proveedor</th>
                                <th>Tip</th>
                                <th>Nº OP</th>
                                <th>Fecha</th>
                                <th>CBU / Cheque</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr>
                                    <td>{{ $fila['origen'] ?? '' }}</td>
                                    <td>{{ $fila['medio'] ?? '' }}</td>
                                    <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                                    <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
                                    <td>{{ $fila['tipo'] ?? '' }}</td>
                                    <td>{{ $fila['orden_pago'] ?? '' }}</td>
                                    <td>
                                        @if (!empty($fila['fecha']))
                                            {{ date('d/m/Y', strtotime($fila['fecha'])) }}
                                        @endif
                                    </td>
                                    <td class="text-monospace">{{ $fila['referencia_cbu_o_cheque'] ?? ($fila['cbu'] ?? '') }}</td>
                                    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Sin pagos Macro en el rango.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($filas) > 0)
                            <tfoot>
                                <tr>
                                    <th colspan="8" class="text-right">Total general</th>
                                    <th class="text-right">{{ number_format((float) ($resultado['total_importe'] ?? 0), 2, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            @if (count($omitidas) > 0)
                <div class="card card-outline card-warning mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Omitidas ({{ count($omitidas) }})</h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Origen</th>
                                    <th>Tip</th>
                                    <th>Nº OP</th>
                                    <th>Proveedor</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($omitidas as $om)
                                    <tr>
                                        <td>{{ $om['origen'] ?? '' }}</td>
                                        <td>{{ $om['tipo'] ?? '' }}</td>
                                        <td>{{ $om['numero'] ?? '' }}</td>
                                        <td>{{ $om['proveedor'] ?? '' }}</td>
                                        <td>{{ $om['motivo'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (count($errores) > 0)
                <div class="alert alert-warning mt-3">
                    <strong>Avisos bridge Anita:</strong>
                    <ul class="mb-0">
                        @foreach ($errores as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'macro-archivo-pago-overlay',
    'tituloId' => 'macro-archivo-pago-titulo',
    'subtituloId' => 'macro-archivo-pago-subtitulo',
    'titulo' => 'Consultando pagos Macro…',
    'subtitulo' => 'Puede demorar si lee Anita. No cierre la página.',
])
@include('includes.caja.modalconsultacuentacaja')
@endsection

@section('scripts')
<script>
    window.macroArchivoPagoCuentacajaPorCodigoUrl = @json(route('leer_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
    window.macroUsuariosRetencion = @json($usuarios_retencion ?? []);
    window.macroCuentasDebitoEmpresa = @json(config('macro.cuentas_debito', []));
</script>
<script src="{{ asset('assets/pages/scripts/caja/macro/archivo_pago.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/macro/archivo_pago.js')) ?: time() }}" type="text/javascript"></script>
<script>
(function () {
    var form = document.getElementById('form-macro-archivo-pago');
    var overlay = document.getElementById('macro-archivo-pago-overlay');
    var cbuActual = String(@json((string) ($cuenta_origen->cbu ?? ''))).replace(/\D+/g, '');

    function mostrar(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('macro-archivo-pago-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            if (!form.checkValidity()) return;
            mostrar('Consultando pagos Macro…');
        });
    }
    var btn = document.getElementById('btn-descargar-macro');
    if (btn) {
        btn.addEventListener('click', function () {
            mostrar('Generando ZIP Macro…');
            setTimeout(ocultar, 4000);
        });
    }

    function empresaId() {
        var el = document.getElementById('empresa_id');
        return el ? parseInt(el.value || '0', 10) || 0 : 0;
    }

    function syncUsuarioRetencion() {
        var map = window.macroUsuariosRetencion || {};
        var usr = map[empresaId()] || map[String(empresaId())] || '';
        var sel = document.getElementById('usuario_retencion');
        if (!sel || !usr) return;
        sel.value = usr;
    }

    function syncCuentaDebito() {
        var suc = parseInt(document.getElementById('sucursal_banco')?.value || '651', 10) || 651;
        var out = document.getElementById('cuenta_debito');
        if (!out) return;
        var cbu = cbuActual;
        var debito = '';
        if (cbu.length >= 21) {
            var suc3 = ('000' + String(suc)).slice(-3);
            debito = '3' + suc3 + cbu.substr(10, 11);
        }
        if (!debito) {
            var map = window.macroCuentasDebitoEmpresa || {};
            debito = map[empresaId()] || map[String(empresaId())] || '';
        }
        out.value = debito;
    }

    function syncCuentaAnita() {
        var codigo = String(document.getElementById('codigo_cuentacaja')?.value || '').replace(/\D+/g, '');
        var el = document.getElementById('cuenta_anita');
        if (!el) return;
        if (codigo === '') {
            el.value = '';
            return;
        }
        el.value = codigo.slice(-8).padStart(8, '0');
    }

    document.getElementById('codigo_cuentacaja')?.addEventListener('change', function () {
        syncCuentaAnita();
        syncCuentaDebito();
    });
    document.getElementById('sucursal_banco')?.addEventListener('change', syncCuentaDebito);
    document.getElementById('empresa_id')?.addEventListener('change', function () {
        syncUsuarioRetencion();
        syncCuentaDebito();
    });

    $(document).on('macro:cuenta-cambiada', function (_e, cbu) {
        cbuActual = String(cbu || '').replace(/\D+/g, '');
        syncCuentaDebito();
    });

    syncUsuarioRetencion();
    syncCuentaDebito();
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
