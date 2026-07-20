@extends("theme.$theme.layout")
@section('titulo')
    Flash &mdash; reporte hist&oacute;rico
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Consolidated Income (Flash Report)</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_caja') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('flash_caja_reporte_historico') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 col-sm-6">
                            <label for="empresa_id">Empresa <span class="text-danger">*</span></label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">-- Seleccione --</option>
                                @foreach ($empresa_query as $empresa)
                                    <option value="{{ $empresa->id }}" {{ (int) ($filtros['empresa_id'] ?? 0) === (int) $empresa->id ? 'selected' : '' }}>
                                        {{ $empresa->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 col-sm-6">
                            <label for="fecha_desde">Desde <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <small class="form-text text-muted">El listado arranca el d&iacute;a 1 del mes (como l-flash).</small>
                        </div>
                        <div class="form-group col-md-2 col-sm-6">
                            <label for="fecha_hasta">Hasta (through day) <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2 col-sm-6">
                            <label for="con_season">Season index</label>
                            <select name="con_season" id="con_season" class="form-control">
                                <option value="1" {{ (int) ($filtros['con_season'] ?? 1) === 1 ? 'selected' : '' }}>Con season index</option>
                                <option value="0" {{ (int) ($filtros['con_season'] ?? 1) === 0 ? 'selected' : '' }}>Sin season index</option>
                            </select>
                        </div>
                        <div class="form-group col-md-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if($consultado ?? false)
                <div class="card-body border-top pt-3">
                    @if($reporte !== null)
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_flash_caja_reporte_historico',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                        <p class="text-muted small mb-3">
                            {!! $subtitulo ?? '' !!}
                            &mdash; {{ $reporte['cantidad_dias'] ?? 0 }} d&iacute;a(s) registrados
                            @if(!empty($reporte['through_day']))
                                &mdash; Through day: {{ $reporte['through_day'] }}
                            @endif
                        </p>

                        @if(!empty($reporte['filas_diarias']))
                            @include('caja.flash.partials.tabla_consolidated_income', [
                                'reporte' => $reporte,
                                'mostrar_acciones' => true,
                            ])
                        @else
                            <div class="alert alert-warning">No hay registros flash en el per&iacute;odo seleccionado.</div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
