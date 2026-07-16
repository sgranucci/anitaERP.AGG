@extends("theme.$theme.layout")
@section('titulo')
    SICORE — presentación ARCA
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">SICORE (formato versión 8)</h3>
                <div class="card-tools">
                    @if (can('listar-sicore-config', false))
                        <a href="{{ route('sicore_config') }}" class="btn btn-outline-secondary btn-sm" title="Configuración">
                            <i class="fa fa-cogs"></i> Configuración
                        </a>
                    @endif
                    <a href="{{ route('sicore') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('sicore') }}" id="form-sicore" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Generación del archivo RG 738/99 para presentación en ARCA.
                        Ventas: percepciones desde <code>venta_impuesto</code>.
                        Compras: retenciones desde Anita (<code>retmov</code> / <code>retimov</code>) con régimen de tablas ERP.
                        Sueldos: retenciones 4ta categoría desde <code>auxrec</code>.
                    </p>

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
                        <label for="criterio" class="{{ $colLabel }} requerido">Exporta</label>
                        <div class="{{ $colInput }}">
                            <select name="criterio" id="criterio" class="form-control" required>
                                @foreach ($criterios_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['criterio'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
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
                    @if ($consultado && can('exportar-sicore', false))
                        <a href="{{ route('exportar_sicore', $filtrosQuery) }}" class="btn btn-success">
                            <i class="fa fa-download"></i> Descargar archivo SICORE v8
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if ($consultado && $resultado)
            @if (can('exportar-sicore', false))
                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_sicore',
                        'queryparams' => $filtrosQuery,
                    ])
                </div>
            @endif

            @include('contable.sicore.partials.conciliacion', [
                'conciliacion' => $resultado['conciliacion'] ?? [],
                'periodo_texto' => $periodo_texto ?? '',
            ])

            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        SICORE a presentar — {{ $periodo_texto }}
                        <span class="badge badge-info ml-2">{{ $resultado['totales']['registros'] ?? 0 }}</span>
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tabla-sicore">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Reg.</th>
                                <th>Imp.</th>
                                <th>Proveedor</th>
                                <th>Documento</th>
                                <th>Razón social</th>
                                <th>Fecha</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Importe</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultado['registros'] ?? [] as $reg)
                                <tr>
                                    <td>{{ str_pad((string) ($reg['cod_regimen'] ?? ''), 3, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $reg['cod_impuesto'] ?? '' }}</td>
                                    <td class="text-nowrap">{{ $reg['codigo_proveedor'] ?? '' }}</td>
                                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                                    <td>{{ $reg['razon_social'] ?? '' }}</td>
                                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right @if (($reg['importe'] ?? 0) < 0) text-danger @endif">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $reg['referencia'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Sin registros en el período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (($resultado['totales']['registros'] ?? 0) > 0)
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="6">Totales</td>
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
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/sicore/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/sicore/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
