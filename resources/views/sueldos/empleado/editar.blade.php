@extends("theme.$theme.layout")
@section('titulo')
    Empleados
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/form.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/domicilio.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/domicilio.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/arca-padron.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/arca-padron.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/bases.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/bases.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/set_conceptos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/set_conceptos.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/formula_debugger.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/formula_debugger.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/liquidacion_preview.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/liquidacion_preview.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/ausencias.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/ausencias.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/indumentaria.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/indumentaria.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/familiares.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/familiares.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/concepto/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/planes_cuota.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/planes_cuota.js')) ?: time() }}"></script>
@if (can('listar-novedad-sueldos', false) || can('editar-empleado-sueldos', false))
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/novedades.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/novedades.js')) ?: time() }}"></script>
@endif
@if (can('editar-empleado-sueldos', false))
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/fallos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/fallos.js')) ?: time() }}"></script>
@endif
@if (can('listar-sancion-empleado-sueldos', false) || can('editar-empleado-sueldos', false))
<script src="{{ asset('assets/pages/scripts/sueldos/tipo_sancion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/tipo_sancion/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/motivo_sancion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/motivo_sancion/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/sanciones.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/sanciones.js')) ?: time() }}"></script>
@endif
@if (can('listar-siradig-sueldos', false))
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/siradig.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/siradig.js')) ?: time() }}"></script>
@endif
@endsection

@section('contenido')
@php
    use App\Support\Sueldos\EmpleadoEstados;
    $puedeEditar = can('actualizar-empleado-sueldos', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary" id="tab2" data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}">
            <div class="card-header">
                <h3 class="card-title">
                    Empleado #{{ $data->legajo }} — {{ $data->nombre }}
                    <span class="badge badge-{{ EmpleadoEstados::esActivo($data->estado) ? 'success' : (EmpleadoEstados::esProvisorio($data->estado) ? 'warning' : 'secondary') }}">
                        {{ EmpleadoEstados::label($data->estado) }}
                    </span>
                </h3>
                <div class="card-tools">
                    @if (EmpleadoEstados::esProvisorio($data->estado) && ($puedeAutorizar ?? false))
                        <form action="{{ route('autorizar_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Autorizar el alta y dejar al empleado activo?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fa fa-check"></i> Autorizar alta
                            </button>
                        </form>
                    @endif
                    <a href="{{route('consultar_empleado_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" id="tabs-empleado" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab"><i class="fa fa-info-circle"></i> Datos personales</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-laborales" role="tab"><i class="fa fa-briefcase"></i> Laborales</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-bases" role="tab"><i class="fa fa-list-ol"></i> Liquidación <span class="badge badge-info" id="badge-cant-bases">{{ count($basesGrilla ?? []) }}</span></a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-leyendas" role="tab"><i class="fa fa-comment"></i> Leyendas</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-foto" role="tab"><i class="fa fa-camera"></i> Foto</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab"><i class="fa fa-paperclip"></i> Archivos</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-ausencias" role="tab"><i class="fa fa-umbrella-beach"></i> Vacaciones / Ausencias</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-indumentaria" role="tab"><i class="fa fa-tshirt"></i> Indumentaria</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-historia" role="tab"><i class="fa fa-history"></i> Baja / reingreso</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-familiares" role="tab"><i class="fa fa-users"></i> Familiares (Ganancias)</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-planes-cuota" role="tab"><i class="fa fa-hand-holding-usd"></i> Préstamos / Cuotas</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-novedades" role="tab"><i class="fa fa-bolt"></i> Novedades</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-fallos" role="tab"><i class="fa fa-balance-scale"></i> Fallos</a></li>
                        @if (can('listar-sancion-empleado-sueldos', false) || can('editar-empleado-sueldos', false))
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-sanciones" role="tab"><i class="fa fa-gavel"></i> Sanciones</a></li>
                        @endif
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-siradig" role="tab"><i class="fa fa-file-invoice-dollar"></i> SiRADIG (F572)</a></li>
                    </ul>
                </div>

                {{-- Formulario del legajo (fuera del tab-content: los campos de cabecera se asocian con form="form-general") --}}
                <form action="{{ route('actualizar_empleado_sueldos', ['id' => $data->id]) }}"
                      id="form-general"
                      class="d-none"
                      method="POST"
                      enctype="multipart/form-data"
                      autocomplete="off">
                    @csrf
                    @method('put')
                </form>

                {{-- Un solo tab-content: Bootstrap solo oculta bien hermanos del mismo contenedor --}}
                <div class="tab-content pt-3 pb-2" id="empleado-tab-content">
                    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel" data-form-general="1">
                        @include('sueldos.empleado.partials.form_datos')
                    </div>
                    <div class="tab-pane fade" id="tab-laborales" role="tabpanel" data-form-general="1">
                        @include('sueldos.empleado.partials.form_laborales')
                    </div>
                    <div class="tab-pane fade" id="tab-bases" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-leyendas" role="tabpanel" data-form-general="1">
                        @include('sueldos.empleado.partials.form_leyendas')
                    </div>
                    <div class="tab-pane fade" id="tab-foto" role="tabpanel" data-form-general="1">
                        @include('sueldos.empleado.partials.form_foto')
                    </div>
                    <div class="tab-pane fade" id="tab-archivos" role="tabpanel" data-form-general="1">
                        @include('sueldos.empleado.partials.form_archivos')
                    </div>
                    <div class="tab-pane fade" id="tab-ausencias" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-indumentaria" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-historia" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-familiares" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-planes-cuota" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-novedades" role="tabpanel"></div>
                    <div class="tab-pane fade" id="tab-fallos" role="tabpanel"></div>
                    @if (can('listar-sancion-empleado-sueldos', false) || can('editar-empleado-sueldos', false))
                    <div class="tab-pane fade" id="tab-sanciones" role="tabpanel"></div>
                    @endif
                    <div class="tab-pane fade" id="tab-siradig" role="tabpanel"></div>
                </div>

                <div id="host-bases" class="pt-3 d-none">
                    @include('sueldos.empleado.partials.form_bases')
                </div>
                <div id="host-historia" class="pt-3 d-none">
                    @include('sueldos.empleado.partials.form_historia')
                </div>
                <div id="host-ausencias" class="pt-3 d-none"
                     data-url="{{ route('ausencias_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando vacaciones y ausencias…</div>
                </div>
                <div id="host-indumentaria" class="pt-3 d-none"
                     data-url="{{ route('indumentaria_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando indumentaria…</div>
                </div>
                <div id="host-familiares" class="pt-3 d-none"
                     data-url="{{ route('familiares_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando familiares…</div>
                </div>
                <div id="host-planes-cuota" class="pt-3 d-none"
                     data-url="{{ route('planes_cuota_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando préstamos/cuotas…</div>
                </div>
                <div id="host-novedades" class="pt-3 d-none"
                     data-url="{{ route('novedades_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando novedades…</div>
                </div>
                <div id="host-fallos" class="pt-3 d-none"
                     data-url="{{ route('fallos_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando fallos…</div>
                </div>
                @if (can('listar-sancion-empleado-sueldos', false) || can('editar-empleado-sueldos', false))
                <div id="host-sanciones" class="pt-3 d-none"
                     data-url="{{ route('sanciones_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando sanciones…</div>
                </div>
                @endif
                @if (can('listar-siradig-sueldos', false))
                <div id="host-siradig" class="pt-3 d-none"
                     data-url="{{ route('siradig_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando SiRADIG…</div>
                </div>
                @endif
            </div>

            {{-- Barra de acciones fija: Actualizar legajo visible en todas las solapas --}}
            @if ($puedeEditar)
                <div class="card-footer empleado-acciones-legajo">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="text-muted small mb-2 mb-md-0 pr-md-3">
                            <i class="fa fa-info-circle text-info"></i>
                            Guarda datos personales, laborales, leyendas, foto y archivos.
                            Las solapas con alta propia (ausencias, familiares, etc.) se graban con sus botones.
                        </div>
                        <div class="d-flex flex-wrap align-items-center">
                            <a href="{{ route('consultar_empleado_sueldos') }}" class="btn btn-outline-info btn-sm mr-2 mb-1 mb-md-0">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                            <button type="submit" form="form-general" class="btn botonsubmit btn-success mb-1 mb-md-0">
                                <i class="fa fa-save"></i> Actualizar empleado
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .empleado-acciones-legajo {
        position: sticky;
        bottom: 0;
        z-index: 20;
        background: #fff;
        border-top: 1px solid #dee2e6;
        box-shadow: 0 -4px 12px rgba(23, 32, 42, 0.08);
    }
</style>

@include('sueldos.empleado.modal_vigencias_base')
@include('includes.sueldos.modalconsultaconcepto_sueldos')
@include('includes.sueldos.modalconsultatipo_sancion')
@include('includes.sueldos.modalconsultamotivo_sancion')
@include('compras.proveedor.arca-cuit-entry-modal')
@include('compras.proveedor.arca-padron-modals')

<script>
(function () {
    function asociarCamposFormGeneral(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.getAttribute('form')) {
                return;
            }
            // No asociar controles de paneles AJAX/históricos que tengan su propio form
            if (el.closest('form') && el.closest('form').id !== 'form-general') {
                return;
            }
            el.setAttribute('form', 'form-general');
        });
    }

    function moveHosts() {
        var map = [
            ['tab-bases', 'host-bases'],
            ['tab-historia', 'host-historia'],
            ['tab-ausencias', 'host-ausencias'],
            ['tab-indumentaria', 'host-indumentaria'],
            ['tab-familiares', 'host-familiares'],
            ['tab-planes-cuota', 'host-planes-cuota'],
            ['tab-novedades', 'host-novedades'],
            ['tab-fallos', 'host-fallos'],
            ['tab-sanciones', 'host-sanciones'],
            ['tab-siradig', 'host-siradig']
        ];
        map.forEach(function (pair) {
            var pane = document.getElementById(pair[0]);
            var host = document.getElementById(pair[1]);
            if (pane && host) {
                pane.appendChild(host);
                host.classList.remove('d-none');
            }
        });

        document.querySelectorAll('#empleado-tab-content [data-form-general="1"]').forEach(function (pane) {
            asociarCamposFormGeneral(pane);
        });
    }

    moveHosts();
})();
</script>
@endsection
