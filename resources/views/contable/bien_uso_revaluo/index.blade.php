@extends("theme.$theme.layout")
@section('titulo')
    Revalúo contable de bienes de uso
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/bien_uso_rubro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso_rubro/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso_indice/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso_indice/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script>
(function () {
    var form = document.getElementById('form-bien-uso-revaluo');
    var overlay = document.getElementById('bien-uso-revaluo-overlay');
    if (form && overlay) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
        });
    }
    window.addEventListener('pageshow', function () {
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.style.display = '';
        }
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'bien-uso-revaluo-overlay',
    'tituloId' => 'bien-uso-revaluo-titulo',
    'subtituloId' => 'bien-uso-revaluo-subtitulo',
    'titulo' => 'Calculando revalúo…',
    'subtitulo' => 'Puede demorar según la cantidad de bienes. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Revalúo contable de bienes de uso</h3>
            </div>
            <form method="get" action="{{ route('bien_uso_revaluo') }}" id="form-bien-uso-revaluo" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Misma lógica que Anita <code>l-biemae.c</code>: valor residual, coeficiente de ajuste,
                        amortizaciones del ejercicio y valores actualizados según criterios contables del país.
                    </p>
                    @php $colLabel = 'col-lg-2 control-label text-right pr-2'; $colInput = 'col-lg-4'; @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids' => $filtros['empresa_ids'] ?? [],
                        'id_prefix' => 'bur',
                        'mostrar_consolidar' => false,
                    ])

                    <div class="form-group row">
                        <label for="fecha_revaluo" class="{{ $colLabel }} requerido">Fecha revalúo</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_revaluo" id="fecha_revaluo" class="form-control" required
                                   value="{{ $filtros['fecha_revaluo'] ?? date('Y-m-d') }}">
                        </div>
                        <label for="periodo_base" class="{{ $colLabel }}">Período base</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="periodo_base" id="periodo_base" class="form-control"
                                   placeholder="YYYYMM" maxlength="6"
                                   value="{{ $filtros['periodo_base'] ?? date('Ym') }}">
                        </div>
                    </div>

                    @include('contable.partials.campo_consulta_bien_uso_indice', [
                        'prefix' => 'revaluo',
                        'indiceId' => $filtros['indice_id'] ?? '',
                        'codigo' => '',
                        'nombre' => '',
                        'inputName' => 'indice_id',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'ayuda' => 'Vacío = sin ajuste por índice (coeficiente 1).',
                    ])

                    <div class="form-group row">
                        <label for="codigo_bien_desde" class="{{ $colLabel }}">Bien desde</label>
                        <div class="col-lg-2">
                            <input type="number" name="codigo_bien_desde" id="codigo_bien_desde" class="form-control"
                                   value="{{ $filtros['codigo_bien_desde'] ?? '' }}">
                        </div>
                        <label for="codigo_bien_hasta" class="col-lg-2 control-label text-right pr-2">hasta</label>
                        <div class="col-lg-2">
                            <input type="number" name="codigo_bien_hasta" id="codigo_bien_hasta" class="form-control"
                                   value="{{ $filtros['codigo_bien_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <div class="custom-control custom-checkbox custom-control-inline">
                                <input type="checkbox" class="custom-control-input" id="filtrar_bajas" name="filtrar_bajas" value="1"
                                       @checked(!empty($filtros['filtrar_bajas']))>
                                <label class="custom-control-label" for="filtrar_bajas">Excluir bienes con baja</label>
                            </div>
                            <div class="custom-control custom-checkbox custom-control-inline">
                                <input type="checkbox" class="custom-control-input" id="persistir" name="persistir" value="1"
                                       @checked(!empty($filtros['persistir']))>
                                <label class="custom-control-label" for="persistir">Grabar revalúo / amortización del ejercicio</label>
                            </div>
                            <div class="custom-control custom-checkbox custom-control-inline">
                                <input type="checkbox" class="custom-control-input" id="fl_mon_origen" name="fl_mon_origen" value="1"
                                       @checked(!empty($filtros['fl_mon_origen']))>
                                <label class="custom-control-label" for="fl_mon_origen">Moneda de origen</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if (!empty($consultado) && $filas)
                <div class="card-body border-top">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_bien_uso_revaluo',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                    <p class="small text-muted mb-2">
                        {{ (int) ($resultado['meta']['cantidad'] ?? 0) }} bienes
                        @if (!empty($resultado['meta']['indice_nombre']))
                            · Índice: {{ $resultado['meta']['indice_nombre'] }}
                        @endif
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>V.U.</th>
                                    <th>Trans.</th>
                                    <th class="text-right">Valor origen</th>
                                    <th class="text-right">Amort. acum.</th>
                                    <th class="text-right">Amort. ej.</th>
                                    <th class="text-right">Amort. mens.</th>
                                    <th class="text-right">V. residual</th>
                                    <th class="text-right">Coef.</th>
                                    <th class="text-right">V. actual</th>
                                    <th class="text-right">Incr. revalúo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $f)
                                    <tr>
                                        <td>
                                            <a class="text-primary" target="_blank" rel="noopener"
                                               href="{{ route('editar_bien_uso', ['id' => $f['bien_uso_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                                {{ $f['codigo_bien'] }}
                                            </a>
                                        </td>
                                        <td>{{ $f['descripcion'] }}</td>
                                        <td>{{ $f['vida_util'] }}</td>
                                        <td>{{ $f['vida_util_transcurrida'] }}</td>
                                        <td class="text-right">{{ number_format((float) $f['valor_origen_cierre'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['amort_acum_inicio'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['amort_ejercicio'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['amort_mensual'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['valor_residual_contable'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['coef_act'], 3, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['valor_actual'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $f['incr_revaluo'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if (!empty($resultado['totales']))
                                <tfoot>
                                    <tr style="font-weight:bold;background:#D6EAF8;">
                                        <td colspan="4">Totales</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['valor_origen_cierre'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['amort_acum_inicio'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['amort_ejercicio'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['amort_mensual'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['valor_residual_contable'] ?? 0), 2, ',', '.') }}</td>
                                        <td></td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['valor_actual'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($resultado['totales']['incr_revaluo'] ?? 0), 2, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    {{ $filas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @elseif (!empty($consultado))
                <div class="card-body border-top text-muted">No hay bienes que coincidan con los filtros.</div>
            @endif
        </div>
    </div>
</div>
@include('includes.contable.modalconsultabienusoindice')
@include('includes.contable.modalconsultabienusorubro')
@include('includes.contable.modalconsultacentrocosto')
@endsection
