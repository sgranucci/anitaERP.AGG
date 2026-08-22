@extends("theme.$theme.layout")
@section('titulo')
    Sanciones de empleados
@endsection

@section('contenido')
@php
    use App\Support\Sueldos\EmpleadoSancionSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Sanciones de empleados</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual')
                </div>
            </div>
            <form method="get" action="{{ route('sancion_reporte_sueldos') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas las asignadas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" {{ (int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id ? 'selected' : '' }}>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Estado</label>
                            <select name="estado" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach ($estados as $cod => $label)
                                    <option value="{{ $cod }}" {{ ($filtros['estado'] ?? '') === $cod ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Legajo desde / hasta</label>
                            <div class="d-flex">
                                <input type="number" name="legajo_desde" class="form-control form-control-sm mr-1" value="{{ $filtros['legajo_desde'] ?? '' }}">
                                <input type="number" name="legajo_hasta" class="form-control form-control-sm" value="{{ $filtros['legajo_hasta'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-4 mb-2">
                            @include('sueldos.partials.campo_consulta_tipo_sancion', [
                                'inputName' => 'tipo_sancion_id',
                                'inputId' => 'filtro_tipo_sancion_id',
                                'tipoId' => $filtros['tipo_sancion_id'] ?? '',
                                'required' => false,
                            ])
                        </div>
                        <div class="col-md-4 mb-2">
                            @include('sueldos.partials.campo_consulta_motivo_sancion', [
                                'inputName' => 'motivo_sancion_id',
                                'inputId' => 'filtro_motivo_sancion_id',
                                'motivoId' => $filtros['motivo_sancion_id'] ?? '',
                                'required' => false,
                            ])
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0 d-block">&nbsp;</label>
                            <div class="form-check">
                                <input type="checkbox" name="incluir_comentario" value="1" class="form-check-input" id="incluir_comentario"
                                       {{ !empty($filtros['incluir_comentario']) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="incluir_comentario">Con comentarios</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0 d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                        </div>
                    </div>
                </div>
            </form>
            @if ($consultado)
                <div class="card-body table-responsive p-0">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_sancion_reporte_sueldos',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                    @php
                        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas ?? collect());
                    @endphp
                    @if ($logos)
                        <div class="px-3 pt-2">
                            @foreach ($logos as $logo)
                                <img src="{{ $logo['uri'] }}" alt="" style="max-height:40px;margin-right:8px;">
                            @endforeach
                        </div>
                    @endif
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Legajo</th>
                                <th>Nombre</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Días</th>
                                <th>Estado</th>
                                <th>Importe no cobrado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($datas as $row)
                                <tr>
                                    <td>
                                        @if (can('editar-empleado-sueldos', false) && $row->empleado)
                                            <a class="text-primary" target="_blank" rel="noopener"
                                               href="{{ route('editar_empleado_sueldos', ['id' => $row->empleado_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                                {{ $row->empleado->legajo }}
                                            </a>
                                        @else
                                            {{ optional($row->empleado)->legajo }}
                                        @endif
                                    </td>
                                    <td>{{ optional($row->empleado)->nombre }}</td>
                                    <td>{{ optional($row->fecha_hecho)->format('d/m/Y') }}</td>
                                    <td>{{ optional($row->tipo)->nombre }}</td>
                                    <td>{{ optional($row->motivo)->nombre }}</td>
                                    <td class="text-right">{{ $row->cant_dias }}</td>
                                    <td>{{ EmpleadoSancionSupport::etiquetaEstado($row->estado) }}</td>
                                    <td class="text-right">{{ number_format((float) $row->importe_perdida, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Sin registros para el filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($datas)
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                @endif
            @endif
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultatipo_sancion')
@include('includes.sueldos.modalconsultamotivo_sancion')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/tipo_sancion/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/motivo_sancion/consulta.js') }}"></script>
@endsection
