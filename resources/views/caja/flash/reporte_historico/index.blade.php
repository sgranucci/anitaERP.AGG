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
                <h3 class="card-title">Reporte hist&oacute;rico Flash</h3>
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
                        </div>
                        <div class="form-group col-md-2 col-sm-6">
                            <label for="fecha_hasta">Hasta <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
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
                        </p>

                        @if(!empty($reporte['filas_diarias']))
                            <h5 class="mb-2">Detalle por d&iacute;a</h5>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Fecha</th>
                                            <th class="text-right">Att</th>
                                            <th class="text-right">Slot win</th>
                                            <th class="text-right">Rul win</th>
                                            <th class="text-right">Bingo res.</th>
                                            <th class="text-right">AyB</th>
                                            <th class="text-right">Estac.</th>
                                            <th class="text-right">Gaming</th>
                                            <th class="text-right">Revenues</th>
                                            <th class="width60"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reporte['filas_diarias'] as $dia)
                                        <tr>
                                            <td>{{ $dia['fecha'] }}</td>
                                            <td class="text-right">{{ $dia['attendance'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format($dia['slot_win'], 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($dia['rul_win'], 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($dia['bingo_win'], 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format((float) $dia['flash']->ayb, 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format((float) $dia['flash']->estac, 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($dia['total_gaming'], 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($dia['total_revenues'], 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                @if(can('exportar-reporte-flash-caja', false) && !empty($dia['id']))
                                                    <a href="{{ route('flash_caja_reporte', ['id' => $dia['id'], 'formato' => 'PDF']) }}" class="text-danger" target="_blank" rel="noopener" title="PDF d&iacute;a"><i class="fa fa-file-pdf-o"></i></a>
                                                    <a href="{{ route('flash_caja_reporte', ['id' => $dia['id'], 'formato' => 'EXCEL']) }}" class="text-success ml-1" title="Excel d&iacute;a"><i class="fa fa-file-excel-o"></i></a>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-warning">No hay registros flash en el per&iacute;odo seleccionado.</div>
                        @endif

                        @if(($reporte['cantidad_dias'] ?? 0) > 0)
                            <h5 class="mb-2">Totales consolidados del per&iacute;odo</h5>
                            <div class="border p-3 bg-light">
                                @include('caja.flash.partials.contenido_reporte', $reporte)
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
