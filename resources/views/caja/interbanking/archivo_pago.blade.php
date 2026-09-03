@extends("theme.$theme.layout")

@section('titulo')
    Archivo pagos Interbanking
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
                <h3 class="card-title">Archivo ASCII de pagos (Interbanking)</h3>
                <div class="card-tools">
                    <a href="{{ route('interbanking_archivo_pago') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('interbanking') }}" class="btn btn-outline-info btn-sm" title="Volver">
                        <i class="fa fa-reply-all"></i> Lectura IB
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('interbanking_archivo_pago') }}" id="form-ib-archivo-pago" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente a Anita <code>p-pagoxbanco</code>: arma <strong>pagobanco.txt</strong> con
                        transferencias del ERP (órdenes de pago + OPP de Ingresos/Egresos, CBU de
                        <code>proveedor_formapago</code>) y las de Anita (<code>pago</code>/<code>auxpag</code>)
                        que aún no estén en el ERP. Si el CBU en Anita viene vacío, usa el de <code>propago</code>.
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
                            <input type="hidden" name="cbu_origen" id="cbu_origen"
                                   value="{{ $filtros['cbu_origen'] ?? '' }}">
                            <div class="input-group">
                                <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                       value="{{ $cuenta_origen->codigo ?? '' }}"
                                       placeholder="Código" title="Código. F1 = consulta">
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
                        <label for="cbu_origen_mostrar" class="{{ $colLabel }} requerido">CBU origen</label>
                        <div class="{{ $colInput }}">
                            <input type="text" class="form-control text-monospace" id="cbu_origen_mostrar" readonly
                                   value="{{ $filtros['cbu_origen'] ?? '' }}"
                                   placeholder="Se toma de la cuenta de caja">
                            <small class="form-text text-muted">CBU de la cuenta de caja elegida (queda recordada para el usuario).</small>
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
                                   value="{{ $filtros['tipo_op'] ?? 'OPP' }}"
                                   title="OPP / OPA / 0 = todas OP*">
                        </div>
                        <label for="tipo_aplicacion" class="{{ $colLabel }}">Tipo aplicación Anita</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="tipo_aplicacion" id="tipo_aplicacion" class="form-control" maxlength="3"
                                   value="{{ $filtros['tipo_aplicacion'] ?? '' }}"
                                   placeholder="Vacío = todas salvo CHP"
                                   title="axp_tipo_ap (tctes). Vacío incluye todas excepto cheques propios.">
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
                        <label for="fecha_solicitud" class="{{ $colLabel }} requerido">Fecha solicitud</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_solicitud" id="fecha_solicitud" class="form-control" required
                                   value="{{ $filtros['fecha_solicitud'] ?? date('Y-m-d') }}">
                        </div>
                        <label for="secuencia" class="{{ $colLabel }} requerido">Nº secuencia</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="secuencia" id="secuencia" class="form-control" min="1" required
                                   value="{{ (int) ($filtros['secuencia'] ?? 1) }}">
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
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if (!empty($consultado) && ($resultado['cantidad'] ?? 0) > 0)
                        <a href="{{ route('descargar_interbanking_archivo_pago', $filtrosQuery) }}"
                           class="btn btn-success" id="btn-descargar-pagobanco">
                            <i class="fa fa-download"></i> Descargar pagobanco.txt
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
                                <th>N.Pro.</th>
                                <th>Proveedor</th>
                                <th>Tip</th>
                                <th>Nº OP</th>
                                <th>Fecha</th>
                                <th>CBU</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr>
                                    <td>{{ $fila['origen'] ?? '' }}</td>
                                    <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                                    <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
                                    <td>{{ $fila['tipo'] ?? '' }}</td>
                                    <td>
                                        {{ sprintf('%04d-%08d', (int) ($fila['sucursal'] ?? 0), (int) ($fila['numero'] ?? 0)) }}
                                    </td>
                                    <td>
                                        @if (!empty($fila['fecha']))
                                            {{ date('d/m/Y', strtotime($fila['fecha'])) }}
                                        @endif
                                    </td>
                                    <td class="text-monospace">{{ $fila['cbu'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Sin transferencias en el rango.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($filas) > 0)
                            <tfoot>
                                <tr>
                                    <th colspan="7" class="text-right">Total general</th>
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
                        <h3 class="card-title">Omitidas por CBU / monto ({{ count($omitidas) }})</h3>
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
    'overlayId' => 'ib-archivo-pago-overlay',
    'tituloId' => 'ib-archivo-pago-titulo',
    'subtituloId' => 'ib-archivo-pago-subtitulo',
    'titulo' => 'Consultando pagos…',
    'subtitulo' => 'Puede demorar si lee Anita. No cierre la página.',
])
@include('includes.caja.modalconsultacuentacaja')
@endsection

@section('scripts')
<script>
    window.ibArchivoPagoCuentacajaPorCodigoUrl = @json(route('leer_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
</script>
<script src="{{ asset('assets/pages/scripts/caja/interbanking/archivo_pago.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/interbanking/archivo_pago.js')) ?: time() }}" type="text/javascript"></script>
<script>
(function () {
    var form = document.getElementById('form-ib-archivo-pago');
    var overlay = document.getElementById('ib-archivo-pago-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('ib-archivo-pago-titulo');
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
            mostrar('Consultando pagos…');
        });
    }
    var btn = document.getElementById('btn-descargar-pagobanco');
    if (btn) {
        btn.addEventListener('click', function () {
            mostrar('Generando pagobanco.txt…');
            setTimeout(ocultar, 4000);
        });
    }
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
