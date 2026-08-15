@extends("theme.$theme.layout")

@section('titulo')
    Descuentos por fallos
@endsection

@section('scripts')
<script>
window.empleadoSueldosConsultaUrls = {
    buscar: @json(route('consulta_operativa_empleado_sueldos')),
    resolver: @json(route('resolver_operativo_empleado_sueldos'))
};
</script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/consulta.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Proceso de descuentos por fallos</h3>
                <div class="card-tools">
                    @if (can('listar-fallo-reporte-sueldos', false))
                        <a href="{{ route('fallo_reporte_sueldos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-list"></i> Cta. cte. fallos
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Replica <code>p-dtofallo.c</code>: suma faltantes del per&iacute;odo, aplica la tabla de fallos del agrupamiento
                    y genera cuotas ({{ $mesesPlan }} meses). Concepto novedad:
                    @if ($concepto)
                        <strong>{{ $concepto->codigo }} — {{ $concepto->descripcion }}</strong>
                    @else
                        <span class="text-danger">no configurado (SUELDOS_CONCEPTO_DTO_FALLO)</span>
                    @endif
                </p>

                @if (can('crear-dtofallo-sueldos', false))
                <form method="post" action="{{ route('generar_dtofallo_sueldos') }}" class="mb-4">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Empresa</label>
                            <select name="empresa_id" id="dtofallo_empresa_id" class="form-control form-control-sm" required>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)old('empresa_id', $defaults['empresa_id']) === (int)$emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Per&iacute;odo dto. (YYYYMM)</label>
                            <input type="number" name="periodo_descuento" class="form-control form-control-sm"
                                   value="{{ old('periodo_descuento', $defaults['periodo_descuento']) }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Fallo desde</label>
                            <input type="date" name="fecha_fallo_desde" class="form-control form-control-sm"
                                   value="{{ old('fecha_fallo_desde', $defaults['fecha_fallo_desde']) }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Fallo hasta</label>
                            <input type="date" name="fecha_fallo_hasta" class="form-control form-control-sm"
                                   value="{{ old('fecha_fallo_hasta', $defaults['fecha_fallo_hasta']) }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'dtofallo_legajo_desde',
                                'inputName' => 'legajo_desde',
                                'legajo' => old('legajo_desde', $defaults['legajo_desde']),
                                'label' => 'Empleado desde',
                                'nextFocus' => '#dtofallo_legajo_hasta_legajo',
                            ])
                        </div>
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'dtofallo_legajo_hasta',
                                'inputName' => 'legajo_hasta',
                                'legajo' => old('legajo_hasta', $defaults['legajo_hasta']),
                                'label' => 'Empleado hasta',
                                'nextFocus' => '#generar_novedades',
                            ])
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <div class="custom-control custom-checkbox mr-3">
                                <input type="checkbox" class="custom-control-input" id="generar_novedades"
                                       name="generar_novedades" value="1"
                                       @checked(old('generar_novedades', $defaults['generar_novedades']))>
                                <label class="custom-control-label" for="generar_novedades">Generar novedades</label>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm mr-2"
                                    onclick="return confirm('¿Generar descuentos por fallos con estos filtros?');">
                                <i class="fa fa-cogs"></i> Generar
                            </button>
                            <a href="{{ route('consultar_dtofallo_sueldos') }}" class="btn btn-outline-secondary btn-sm"
                               title="Limpiar filtros">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
                @endif

                <h5 class="mb-2">Cierres generados</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Nro</th>
                                <th>Empresa</th>
                                <th>Per&iacute;odo dto.</th>
                                <th>Fallo desde/hasta</th>
                                <th class="text-right">Empl.</th>
                                <th class="text-right">Mov.</th>
                                <th class="text-right">Nov.</th>
                                <th class="text-right">Descuento</th>
                                <th class="text-right">Sanción</th>
                                <th>Estado</th>
                                <th style="width:100px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cierres as $c)
                                <tr>
                                    <td>{{ $c->nro_cierre }}</td>
                                    <td>{{ optional($c->empresa)->nombre }}</td>
                                    <td>{{ $c->periodo_descuento }}</td>
                                    <td>
                                        {{ optional($c->fecha_fallo_desde)->format('d/m/Y') }}
                                        —
                                        {{ optional($c->fecha_fallo_hasta)->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">{{ $c->empleados_procesados }}</td>
                                    <td class="text-right">{{ $c->movimientos_generados }}</td>
                                    <td class="text-right">{{ $c->novedades_generadas }}</td>
                                    <td class="text-right">{{ number_format((float)$c->total_descuento, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float)$c->total_sancion, 2, ',', '.') }}</td>
                                    <td>
                                        @if ($c->estado === 'anulado')
                                            <span class="badge badge-secondary">Anulado</span>
                                        @else
                                            <span class="badge badge-success">Generado</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('ver_dtofallo_sueldos', $c->id) }}" class="btn-accion-tabla" title="Ver">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @if ($c->estado !== 'anulado' && can('borrar-dtofallo-sueldos', false))
                                            <form action="{{ route('anular_dtofallo_sueldos', $c->id) }}" method="post" class="d-inline"
                                                  onsubmit="return confirm('¿Anular este cierre y sus novedades?');">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn-accion-tabla text-danger" title="Anular">
                                                    <i class="fa fa-times-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted py-3">Sin cierres aún.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $cierres->links() }}
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaempleado_sueldos')
@endsection
