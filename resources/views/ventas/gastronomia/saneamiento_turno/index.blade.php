@extends("theme.$theme.layout")
@section('titulo')
    Saneamiento turnos gastronomía
@endsection

@section("scripts")
<script>
    window.SANEAMIENTO_TURNO_GASTRONOMIA = {
        csrf: @json(csrf_token()),
        puedeEjecutar: @json($puede_ejecutar ?? false),
        urlFacturasDia: @json($url_facturas_dia ?? ''),
        turnos: @json($turnos->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre])->values()),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/saneamiento_turno.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia/saneamiento_turno.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="saneamiento-turno-app"
     data-api-diagnostico="{{ route('gastronomia_saneamiento_turno_api_diagnostico') }}"
     data-api-extender="{{ route('gastronomia_saneamiento_turno_api_extender_cierre') }}"
     data-api-retroactivo="{{ route('gastronomia_saneamiento_turno_api_crear_retroactivo') }}"
     data-api-recalcular="{{ route('gastronomia_saneamiento_turno_api_recalcular_totales') }}"
     data-api-cerrar-cuentas="{{ route('gastronomia_saneamiento_turno_api_cerrar_cuentas') }}"
     data-api-cerrar-turno-remoto="{{ route('gastronomia_saneamiento_turno_api_cerrar_turno_remoto') }}"
     data-url-informe-pdf="{{ route('gastronomia_saneamiento_turno_informe_pdf') }}"
     data-puede-ejecutar="{{ ($puede_ejecutar ?? false) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">Saneamiento de turnos y facturas huérfanas</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Uso administrativo.</strong>
                    Corrige inconsistencias entre facturas del día y ventanas de turnos cerrados.
                    No modifica comprobantes fiscales; ajusta horas de cierre, crea turnos retroactivos,
                    cierra cuentas abiertas (con confirmación) y permite exportar informe PDF.
                    Las cuentas <strong>cerradas sin facturar</strong> no se ven en el facturador (solo mesas abiertas);
                    el detalle aquí incluye fecha de apertura e ítems cargados.
                    @if (! ($puede_ejecutar ?? false))
                        <br>Su usuario solo puede <strong>consultar</strong> el diagnóstico.
                        Para ejecutar acciones solicite el permiso
                        <em>Ejecutar saneamiento turno gastronomía</em>.
                    @endif
                </div>

                <form method="get" action="{{ route('gastronomia_saneamiento_turno') }}" class="form-inline mb-4" id="form-filtro-saneamiento">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'select_class' => 'mr-3',
                        'required' => true,
                        'permite_todas' => false,
                    ])
                    <label class="mr-2" for="identificador_pc">Terminal (opcional)</label>
                    <input type="text" name="identificador_pc" id="identificador_pc" class="form-control mr-3"
                           value="{{ $identificador_pc }}" placeholder="Todas las PV configuradas"/>
                    <button type="submit" class="btn btn-secondary btn-sm">Diagnosticar</button>
                    @if ($puede_ejecutar ?? false)
                        <button type="button" class="btn btn-outline-danger btn-sm ml-2" id="btn-exportar-informe-pdf">
                            <i class="fa fa-file-pdf-o"></i> Exportar informe PDF
                        </button>
                    @endif
                </form>

                <div id="panel-diagnostico"></div>
            </div>
        </div>
    </div>
</div>
@endsection
