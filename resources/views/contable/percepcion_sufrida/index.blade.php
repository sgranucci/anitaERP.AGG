@extends("theme.$theme.layout")
@section('titulo')
    {{ $titulo }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $titulo }}</h3>
                <div class="card-tools">
                    <a href="{{ route($ruta_index) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route($ruta_index) }}" id="form-percepcion-sufrida" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">{{ $ayuda }}</p>
                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                    @endphp
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
                        <label class="{{ $colLabel }}">Cuenta</label>
                        <div class="{{ $colInput }}">
                            <span class="form-control-plaintext">{{ $cuenta_texto }}</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-01') }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label class="{{ $colLabel }}">Anita hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="text" class="form-control" value="{{ $fecha_limite }}" readonly>
                        </div>
                        <div class="col-lg-6">
                            <p class="form-control-plaintext text-muted small mb-0">
                                Hasta esa fecha el mayor se lee de Anita. Desde el día siguiente, solo del ERP.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && can($permiso_exportar, false))
                        @if (($es_iibb ?? '0') === '1')
                            <a href="{{ route($ruta_exportar, array_merge($filtrosQuery, ['jurisdiccion' => 901])) }}" class="btn btn-success js-percepcion-descarga">
                                <i class="fa fa-download"></i> Descargar 901
                            </a>
                            <a href="{{ route($ruta_exportar, array_merge($filtrosQuery, ['jurisdiccion' => 902])) }}" class="btn btn-success js-percepcion-descarga">
                                <i class="fa fa-download"></i> Descargar 902
                            </a>
                            <a href="{{ route('exportar_sifere_reporte', array_merge($filtrosQuery, ['jurisdiccion' => 901])) }}" class="btn btn-outline-success js-percepcion-descarga">
                                <i class="fa fa-file-excel-o"></i> Excel 901
                            </a>
                            <a href="{{ route('exportar_sifere_reporte', array_merge($filtrosQuery, ['jurisdiccion' => 902])) }}" class="btn btn-outline-success js-percepcion-descarga">
                                <i class="fa fa-file-excel-o"></i> Excel 902
                            </a>
                        @else
                            <a href="{{ route($ruta_exportar, $filtrosQuery) }}" class="btn btn-success js-percepcion-descarga">
                                <i class="fa fa-download"></i> {{ $archivo_boton }}
                            </a>
                        @endif
                    @endif
                </div>
            </form>
        </div>

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'percepcion-sufrida-overlay',
            'tituloId' => 'percepcion-sufrida-titulo',
            'subtituloId' => 'percepcion-sufrida-subtitulo',
            'titulo' => 'Consultando…',
            'subtitulo' => 'Puede demorar. No cierre la página.',
        ])

        @if ($error !== '')
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

            @if ($consultado && $resultado)
            @php
                $tot = $resultado['totales'] ?? [];
                $diferencias = $resultado['diferencias'] ?? [];
                $hayDiferencias = count($diferencias) > 0;
                $desvioSaldo = round((float) ($tot['desvio_saldo'] ?? 0), 2);
                $desvioCierre = round((float) ($tot['desvio_cierre'] ?? 0), 2);
            @endphp
            @if (($es_iibb ?? '0') === '1' && (abs($desvioSaldo) > 0.05 || abs($desvioCierre) > 0.05))
                <div class="alert alert-danger">
                    El cruce no cierra.
                    @if (abs($desvioSaldo) > 0.05)
                        El mayor no coincide con el saldo del período ({{ number_format($desvioSaldo, 2, ',', '.') }}).
                    @endif
                    @if (abs($desvioCierre) > 0.05)
                        La suma de 901, 902 y las diferencias no explica el mayor ({{ number_format($desvioCierre, 2, ',', '.') }}).
                    @endif
                </div>
            @endif
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Totales — {{ $periodo_texto }}</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th style="width:28%;">Total mayor</th>
                                <td class="text-right">{{ number_format((float) ($tot['mayor'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-muted small">{{ (int) ($tot['lineas_mayor'] ?? 0) }} líneas</td>
                            </tr>
                            @if (($es_iibb ?? '0') === '1')
                                <tr>
                                    <th>Cruzado 901 (CABA)</th>
                                    <td class="text-right">{{ number_format((float) ($tot['cruzado_901'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">Importe a liquidar</td>
                                </tr>
                                <tr>
                                    <th>Cruzado 902 (Buenos Aires)</th>
                                    <td class="text-right">{{ number_format((float) ($tot['cruzado_902'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">Importe a liquidar</td>
                                </tr>
                            @else
                                <tr>
                                    <th>Total cruzado</th>
                                    <td class="text-right">{{ number_format((float) ($tot['cruzado'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">{{ (int) ($tot['lineas_cruzadas'] ?? 0) }} líneas en el archivo</td>
                                </tr>
                            @endif
                            <tr>
                                <th>Diferencias</th>
                                <td class="text-right">{{ number_format((float) ($tot['diferencias'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-muted small">{{ (int) ($tot['lineas_diferencia'] ?? 0) }} líneas</td>
                            </tr>
                            @if (($es_iibb ?? '0') === '1')
                                <tr>
                                    <th>Saldo del período</th>
                                    <td class="text-right">{{ number_format((float) ($tot['saldo_periodo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">Cuenta en pesos, como Sumas y saldos</td>
                                </tr>
                                <tr>
                                    <th>Mayor − saldo</th>
                                    <td class="text-right">{{ number_format((float) ($tot['desvio_saldo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">Tiene que dar cero</td>
                                </tr>
                                <tr>
                                    <th>Mayor − 901 − 902 − diferencias</th>
                                    <td class="text-right">{{ number_format((float) ($tot['desvio_cierre'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-muted small">Tiene que dar cero</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($hayDiferencias)
                @if (can($permiso_exportar, false))
                    <div class="mb-2 mt-3">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => $ruta_listar,
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>
                @endif
                <div class="card card-outline card-secondary mt-3">
                    <div class="card-header">
                        <h3 class="card-title">
                            Diferencias
                            <span class="badge badge-info ml-2">{{ count($diferencias) }}</span>
                        </h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-striped mb-0" id="tabla-percepcion-sufrida">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Comprobante</th>
                                    <th>Emisor</th>
                                    <th>CUIT</th>
                                    <th>Descripción</th>
                                    <th class="text-right">Importe mayor</th>
                                    <th class="text-right">Importe reporte</th>
                                    <th class="text-right">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($diferencias as $fila)
                                    <tr>
                                        <td class="text-nowrap">{{ $fila['fecha'] !== '' ? date('d/m/Y', strtotime($fila['fecha'])) : '' }}</td>
                                        <td class="text-nowrap">{{ trim(($fila['tipo'] ?? '').' '.($fila['comprobante'] ?? '')) }}</td>
                                        <td>{{ ($fila['emisor_nombre'] ?? '') !== '' ? $fila['emisor_nombre'] : ($fila['emisor'] ?? '') }}</td>
                                        <td class="text-nowrap">{{ \App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport::cuitConGuiones((string) ($fila['cuit'] ?? '')) }}</td>
                                        <td>{{ $fila['descripcion'] ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila['importe_mayor'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila['importe_reporte'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila['diferencia'] ?? 0), 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="alert alert-success mt-3 mb-0">
                    Todo cruzó. No hay diferencias para listar.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/percepcion_sufrida/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/percepcion_sufrida/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
