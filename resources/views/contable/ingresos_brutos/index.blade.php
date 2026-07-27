@extends("theme.$theme.layout")
@section('titulo')
    Ingresos Brutos — presentación ARBA
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ingresos Brutos (archivo ARBA)</h3>
                <div class="card-tools">
                    @if (can('listar-ingresos-brutos-config', false))
                        <a href="{{ route('ingresos_brutos_config') }}" class="btn btn-outline-secondary btn-sm" title="Configuración">
                            <i class="fa fa-cogs"></i> Configuración
                        </a>
                    @endif
                    <a href="{{ route('ingresos_brutos') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('ingresos_brutos') }}" id="form-ingresos-brutos" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Generación del archivo de retenciones / percepciones IIBB para presentación en ARBA.
                        Retenciones desde Anita (<code>retibrmov</code>) y ERP (<code>pagoproveedor_retencion</code>).
                        Percepciones desde ventas / Anita según configuración.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                        $periodoYm = (string) ($filtros['periodo'] ?? date('Ym'));
                        $periodoMonth = strlen($periodoYm) === 6
                            ? substr($periodoYm, 0, 4).'-'.substr($periodoYm, 4, 2)
                            : date('Y-m');
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
                        <label for="tipo" class="{{ $colLabel }} requerido">Tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo" id="tipo" class="form-control" required>
                                @foreach ($tipos_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['tipo'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }} requerido">Provincia</label>
                        <div class="{{ $colInput }}">
                            <input type="hidden" class="provincia_id" id="provincia_id" name="provincia_id"
                                value="{{ (int) ($filtros['provincia_id'] ?? 0) }}" required>
                            <div class="input-group">
                                <input type="text" class="form-control codigoprovincia" id="codigoprovincia" name="codigoprovincia"
                                    value="{{ $provincia->codigo ?? '' }}" placeholder="Cód." autocomplete="off">
                                <input type="text" class="form-control nombreprovincia" id="nombreprovincia" name="nombreprovincia"
                                    value="{{ $provincia->nombre ?? '' }}" readonly placeholder="Nombre provincia">
                                <div class="input-group-append">
                                    <button type="button" title="Consultar provincias" class="btn btn-outline-secondary consultaprovincia tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <label for="periodo_mes" class="{{ $colLabel }} requerido">Período</label>
                        <div class="{{ $colInput }}">
                            <input type="month" name="periodo_mes" id="periodo_mes" class="form-control"
                                value="{{ $periodoMonth }}" required>
                            <input type="hidden" name="periodo" id="periodo" value="{{ $periodoYm }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="liquidacion" class="{{ $colLabel }} requerido">Liquidación</label>
                        <div class="{{ $colInput }}">
                            <select name="liquidacion" id="liquidacion" class="form-control" required>
                                @foreach ($liquidaciones_enum as $value => $label)
                                    <option value="{{ $value }}" @selected((int) ($filtros['liquidacion'] ?? 1) === (int) $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde / Hasta</label>
                        <div class="{{ $colInput }}">
                            <div class="form-row">
                                <div class="col-6">
                                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" readonly
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" readonly
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }}"></div>
                        <div class="{{ $colInput }}">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="conciliar_contable" id="conciliar_contable" value="1"
                                    @checked(($filtros['conciliar_contable'] ?? true))>
                                <label class="custom-control-label" for="conciliar_contable">
                                    Conciliar contra mayor contable (cuentas configuradas)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && can('exportar-ingresos-brutos', false))
                        <a href="{{ route('exportar_ingresos_brutos', $filtrosQuery) }}" class="btn btn-success">
                            <i class="fa fa-download"></i> Descargar archivo ARBA
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'ingresos-brutos-procesando-overlay',
            'tituloId' => 'ingresos-brutos-procesando-titulo',
            'subtituloId' => 'ingresos-brutos-procesando-subtitulo',
            'titulo' => 'Consultando Ingresos Brutos…',
            'subtitulo' => 'Puede demorar según el período y la conciliación contable. No cierre la página.',
        ])

        @if ($consultado && $resultado)
            @if (! empty($resultado['mensaje_config']))
                <div class="alert alert-warning">
                    {{ $resultado['mensaje_config'] }}
                </div>
            @endif

            @if (can('exportar-ingresos-brutos', false) && (($resultado['totales']['registros'] ?? 0) > 0))
                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_ingresos_brutos',
                        'queryparams' => $filtrosQuery,
                    ])
                </div>
            @endif

            @include('contable.ingresos_brutos.partials.conciliacion', [
                'conciliacion' => $resultado['conciliacion'] ?? [],
                'periodo_texto' => $periodo_texto ?? '',
            ])

            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        Ingresos Brutos a presentar — {{ $periodo_texto }}
                        <span class="badge badge-info ml-2">{{ $resultado['totales']['registros'] ?? 0 }}</span>
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Cert/Nro</th>
                                <th>Documento</th>
                                <th>Razón</th>
                                <th>Fecha</th>
                                <th class="text-right">Alícuota</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Importe</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultado['registros'] ?? [] as $reg)
                                <tr>
                                    <td>{{ $reg['nro_cert'] ?? $reg['nro_comp'] ?? '' }}</td>
                                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                                    <td>{{ $reg['razon_social'] ?? '' }}</td>
                                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($reg['alicuota'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right{{ ((float) ($reg['importe'] ?? 0) < 0) ? ' text-danger' : '' }}">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $reg['referencia'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Sin registros en el período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (($resultado['totales']['registros'] ?? 0) > 0)
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="5">Totales</td>
                                    <td class="text-right">{{ number_format((float) ($resultado['totales']['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($resultado['totales']['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@include('includes.configuracion.modalconsultaprovincia')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/configuracion/provincia/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/provincia/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/ingresos_brutos/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/ingresos_brutos/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
