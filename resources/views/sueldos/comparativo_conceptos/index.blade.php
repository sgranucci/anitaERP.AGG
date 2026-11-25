@extends("theme.$theme.layout")

@section('titulo')
    Comparativo de conceptos
@endsection

@section('scripts')
<script>
window.empleadoSueldosConsultaUrls = {
    buscar: @json(route('consulta_operativa_empleado_sueldos')),
    resolver: @json(route('resolver_operativo_empleado_sueldos'))
};
</script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/consulta.js')) ?: time() }}"></script>
<script>
(function () {
    function syncForma() {
        var forma = document.getElementById('cc_forma');
        if (!forma) return;
        var esPeriodo = forma.value === 'periodo';
        document.querySelectorAll('[data-cc-periodo]').forEach(function (el) {
            el.style.display = esPeriodo ? '' : 'none';
        });
        document.querySelectorAll('[data-cc-liquidacion]').forEach(function (el) {
            el.style.display = esPeriodo ? 'none' : '';
        });
    }
    function syncOrden() {
        var orden = document.getElementById('cc_orden');
        if (!orden) return;
        var v = orden.value;
        document.querySelectorAll('[data-cc-orden]').forEach(function (el) {
            el.style.display = el.getAttribute('data-cc-orden') === v ? '' : 'none';
        });
    }
    function syncFiltroEmpleados() {
        var f = document.getElementById('cc_filtro_empleados');
        if (!f) return;
        var show = f.value !== 'sin';
        document.querySelectorAll('[data-cc-fecha-filtro]').forEach(function (el) {
            el.style.display = show ? '' : 'none';
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        syncForma();
        syncOrden();
        syncFiltroEmpleados();
        var forma = document.getElementById('cc_forma');
        var orden = document.getElementById('cc_orden');
        var filtro = document.getElementById('cc_filtro_empleados');
        if (forma) forma.addEventListener('change', syncForma);
        if (orden) orden.addEventListener('change', syncOrden);
        if (filtro) filtro.addEventListener('change', syncFiltroEmpleados);
    });
})();
</script>
@endsection

@section('contenido')
@php
    use App\Support\Sueldos\ComparativoConceptosListadoFiltros as F;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comparativo de conceptos liquidados</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Compara cantidades e importes de conceptos entre per&iacute;odos o liquidaciones
                    (Anita <code>l-comparacon</code>). Con dos columnas muestra la diferencia.
                </p>

                <form method="get" action="{{ route('comparativo_conceptos_sueldos') }}" class="mb-3" id="form-comparativo-conceptos">
                    <input type="hidden" name="consultar" value="1">

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? array_filter([(int) ($filtros['empresa_id'] ?? 0)]),
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'id_prefix' => 'cc_rep',
                        'col_label' => 'col-lg-2',
                        'col_body' => 'col-lg-10',
                    ])

                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="cc_orden">Tipo de orden</label>
                            <select name="orden" id="cc_orden" class="form-control form-control-sm">
                                <option value="{{ F::ORDEN_LEGAJO }}" @selected(($filtros['orden'] ?? '') === F::ORDEN_LEGAJO)>x Legajo</option>
                                <option value="{{ F::ORDEN_CCOSTO }}" @selected(($filtros['orden'] ?? '') === F::ORDEN_CCOSTO)>x Centro de costo</option>
                                <option value="{{ F::ORDEN_CATEGORIA }}" @selected(($filtros['orden'] ?? '') === F::ORDEN_CATEGORIA)>x Categor&iacute;a</option>
                                <option value="{{ F::ORDEN_SINDICATO }}" @selected(($filtros['orden'] ?? '') === F::ORDEN_SINDICATO)>x Sindicato</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="cc_forma">Forma de listar</label>
                            <select name="forma" id="cc_forma" class="form-control form-control-sm">
                                <option value="{{ F::FORMA_PERIODO }}" @selected(($filtros['forma'] ?? '') === F::FORMA_PERIODO)>x Per&iacute;odo</option>
                                <option value="{{ F::FORMA_LIQUIDACION }}" @selected(($filtros['forma'] ?? '') === F::FORMA_LIQUIDACION)>x Liquidaci&oacute;n</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="cc_formato">Formato</label>
                            <select name="formato" id="cc_formato" class="form-control form-control-sm">
                                <option value="{{ F::FORMATO_CANTIDAD }}" @selected(($filtros['formato'] ?? '') === F::FORMATO_CANTIDAD)>Cantidades</option>
                                <option value="{{ F::FORMATO_IMPORTE }}" @selected(($filtros['formato'] ?? '') === F::FORMATO_IMPORTE)>Importes</option>
                                <option value="{{ F::FORMATO_AMBOS }}" @selected(($filtros['formato'] ?? '') === F::FORMATO_AMBOS)>Cantidades e importes</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="cc_filtro_empleados">Filtro empleados</label>
                            <select name="filtro_empleados" id="cc_filtro_empleados" class="form-control form-control-sm">
                                <option value="{{ F::FILTRO_SIN }}" @selected(($filtros['filtro_empleados'] ?? '') === F::FILTRO_SIN)>Sin filtro</option>
                                <option value="{{ F::FILTRO_ACTIVOS }}" @selected(($filtros['filtro_empleados'] ?? '') === F::FILTRO_ACTIVOS)>Activos</option>
                                <option value="{{ F::FILTRO_BAJAS }}" @selected(($filtros['filtro_empleados'] ?? '') === F::FILTRO_BAJAS)>Bajas</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2" data-cc-fecha-filtro>
                            <label class="small mb-1" for="cc_fecha_filtro">A fecha</label>
                            <input type="date" name="fecha_filtro" id="cc_fecha_filtro" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_filtro'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-2" data-cc-periodo>
                            <label class="small mb-1">Desde per&iacute;odo</label>
                            <input type="month" name="periodo_desde" class="form-control form-control-sm"
                                   value="{{ F::periodoParaInput((string)($filtros['periodo_desde'] ?? '')) }}">
                        </div>
                        <div class="form-group col-md-2" data-cc-periodo>
                            <label class="small mb-1">Hasta per&iacute;odo</label>
                            <input type="month" name="periodo_hasta" class="form-control form-control-sm"
                                   value="{{ F::periodoParaInput((string)($filtros['periodo_hasta'] ?? '')) }}">
                        </div>
                        <div class="form-group col-md-2" data-cc-liquidacion style="display:none">
                            <label class="small mb-1">Desde liquidaci&oacute;n (n&deg;)</label>
                            <input type="number" name="liquidacion_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['liquidacion_desde'] ?? '' }}" placeholder="N&uacute;mero">
                        </div>
                        <div class="form-group col-md-2" data-cc-liquidacion style="display:none">
                            <label class="small mb-1">Hasta liquidaci&oacute;n (n&deg;)</label>
                            <input type="number" name="liquidacion_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['liquidacion_hasta'] ?? '' }}" placeholder="N&uacute;mero">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Desde concepto</label>
                            <input type="number" name="concepto_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['concepto_desde'] ?? '' }}" placeholder="C&oacute;digo">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Hasta concepto</label>
                            <input type="number" name="concepto_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['concepto_hasta'] ?? '' }}" placeholder="C&oacute;digo">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'cc_legajo_desde',
                                'inputName' => 'legajo_desde',
                                'legajo' => $filtros['legajo_desde'],
                                'label' => 'Empleado desde',
                                'nextFocus' => '#cc_legajo_hasta_legajo',
                            ])
                        </div>
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'cc_legajo_hasta',
                                'inputName' => 'legajo_hasta',
                                'legajo' => $filtros['legajo_hasta'],
                                'label' => 'Empleado hasta',
                                'nextFocus' => '#cc_consultar',
                            ])
                        </div>
                    </div>

                    <div class="form-row" data-cc-orden="ccosto" style="display:none">
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Desde c. costo</label>
                            <select name="centrocosto_id_desde" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($centrocostos as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['centrocosto_id_desde'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Hasta c. costo</label>
                            <select name="centrocosto_id_hasta" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($centrocostos as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['centrocosto_id_hasta'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row" data-cc-orden="categoria" style="display:none">
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Desde categor&iacute;a</label>
                            <select name="categoria_id_desde" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($categorias as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['categoria_id_desde'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Hasta categor&iacute;a</label>
                            <select name="categoria_id_hasta" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($categorias as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['categoria_id_hasta'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row" data-cc-orden="sindicato" style="display:none">
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Desde sindicato</label>
                            <select name="sindicato_id_desde" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($sindicatos as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['sindicato_id_desde'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Hasta sindicato</label>
                            <select name="sindicato_id_hasta" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($sindicatos as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['sindicato_id_hasta'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button class="btn btn-primary btn-sm mr-2" id="cc_consultar" type="submit">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('comparativo_conceptos_sueldos') }}" class="btn btn-outline-secondary btn-sm"
                               title="Limpiar filtros">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
                    @if (!empty($resultado['advertencia']))
                        <div class="alert alert-warning py-2">{{ $resultado['advertencia'] }}</div>
                    @endif

                    <div class="small text-muted mb-2">
                        {{ $resultado['subtitulo'] }} ·
                        {{ $resultado['totales']['empleados'] ?? 0 }} emp. ·
                        {{ $resultado['totales']['conceptos'] ?? 0 }} conceptos ·
                        {{ $resultado['totales']['filas_dato'] ?? 0 }} filas
                        @if (($resultado['formato'] ?? '') !== 'cantidad')
                            · Total $ {{ number_format((float)($resultado['totales']['total_importe'] ?? 0), 2, ',', '.') }}
                        @endif
                    </div>

                    <div class="mb-3">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_comparativo_conceptos_sueldos',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>

                    @include('sueldos.comparativo_conceptos.partials.tabla_datos', [
                        'filas' => $filasPaginadas,
                        'resultado' => $resultado,
                        'esPantalla' => true,
                        'puede_ver_empleado' => can('editar-empleado-sueldos', false) || can('listar-empleado-sueldos', false),
                        'puede_ver_concepto' => can('editar-concepto-sueldos', false) || can('listar-concepto-sueldos', false),
                        'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
                        'puede_ver_categoria' => can('editar-categoria-sueldos', false) || can('listar-categoria-sueldos', false),
                        'puede_ver_sindicato' => can('editar-sindicato-sueldos', false) || can('listar-sindicato-sueldos', false),
                        'puede_ver_liquidacion' => can('editar-liquidacion-sueldos', false) || can('listar-liquidacion-sueldos', false),
                    ])
                    <div class="mt-2">{{ $filasPaginadas->links() }}</div>
                @elseif ($consultado)
                    <div class="alert alert-warning">Seleccione al menos una empresa para consultar.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaempleado_sueldos')
@endsection
