@extends("theme.$theme.layout")
@section('titulo')
    Empleados
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/form.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/domicilio.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/domicilio.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/arca-padron.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/arca-padron.js')) ?: time() }}"></script>
@if (! ($usaTabla ?? true))
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/bases.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/bases.js')) ?: time() }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/ausencias.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/ausencias.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/indumentaria.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/indumentaria.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/familiares.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/familiares.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/planes_cuota.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/planes_cuota.js')) ?: time() }}"></script>
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
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-siradig" role="tab"><i class="fa fa-file-invoice-dollar"></i> SiRADIG (F572)</a></li>
                    </ul>
                </div>

                <form action="{{route('actualizar_empleado_sueldos', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                    @csrf @method("put")
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('sueldos.empleado.partials.form_datos')
                            @if ($puedeEditar)
                                <div class="row mt-3"><div class="col-lg-3"></div><div class="col-lg-6">@include('includes.boton-form-editar')</div></div>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-laborales" role="tabpanel">
                            @include('sueldos.empleado.partials.form_laborales')
                            @if ($puedeEditar)
                                <div class="row mt-3"><div class="col-lg-3"></div><div class="col-lg-6"><button type="submit" class="btn botonsubmit btn-success">Actualizar</button></div></div>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-bases" role="tabpanel">
                            {{-- bases AJAX; botón Actualizar cabecera --}}
                        </div>
                        <div class="tab-pane fade" id="tab-leyendas" role="tabpanel">
                            @include('sueldos.empleado.partials.form_leyendas')
                            @if ($puedeEditar)
                                <div class="row mt-3"><div class="col-lg-3"></div><div class="col-lg-6"><button type="submit" class="btn botonsubmit btn-success">Actualizar</button></div></div>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-foto" role="tabpanel">
                            @include('sueldos.empleado.partials.form_foto')
                            @if ($puedeEditar)
                                <div class="row mt-3"><div class="col-lg-3"></div><div class="col-lg-6"><button type="submit" class="btn botonsubmit btn-success">Actualizar</button></div></div>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                            @include('sueldos.empleado.partials.form_archivos')
                            @if ($puedeEditar)
                                <div class="row mt-3"><div class="col-lg-3"></div><div class="col-lg-6"><button type="submit" class="btn botonsubmit btn-success">Actualizar</button></div></div>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-ausencias" role="tabpanel">
                            {{-- vacaciones/ausencias fuera del form: se inyecta abajo vía AJAX --}}
                        </div>
                        <div class="tab-pane fade" id="tab-indumentaria" role="tabpanel">
                            {{-- indumentaria fuera del form: se inyecta abajo vía AJAX --}}
                        </div>
                        <div class="tab-pane fade" id="tab-historia" role="tabpanel">
                            {{-- historia fuera del form: se inyecta abajo --}}
                        </div>
                        <div class="tab-pane fade" id="tab-familiares" role="tabpanel">
                            {{-- familiares Ganancias fuera del form: se inyecta abajo vía AJAX --}}
                        </div>
                        <div class="tab-pane fade" id="tab-planes-cuota" role="tabpanel">
                            {{-- préstamos/cuotas fuera del form: se inyecta abajo vía AJAX --}}
                        </div>
                        <div class="tab-pane fade" id="tab-siradig" role="tabpanel">
                            {{-- SiRADIG fuera del form: se inyecta abajo vía AJAX --}}
                        </div>
                    </div>
                </form>

                <div class="tab-content">
                    <div class="tab-pane fade" id="tab-bases-content-host" style="display:none"></div>
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
                @if (can('listar-siradig-sueldos', false))
                <div id="host-siradig" class="pt-3 d-none"
                     data-url="{{ route('siradig_empleado_sueldos', ['empleado' => $data->id]) }}">
                    <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando SiRADIG…</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if (! ($usaTabla ?? true))
    @include('sueldos.empleado.modal_vigencias_base')
@endif
@include('compras.proveedor.arca-cuit-entry-modal')
@include('compras.proveedor.arca-padron-modals')

<script>
(function () {
    function moveHosts() {
        var basesPane = document.getElementById('tab-bases');
        var histPane = document.getElementById('tab-historia');
        var hostBases = document.getElementById('host-bases');
        var hostHist = document.getElementById('host-historia');
        if (basesPane && hostBases) {
            basesPane.appendChild(hostBases);
            hostBases.classList.remove('d-none');
        }
        if (histPane && hostHist) {
            histPane.appendChild(hostHist);
            hostHist.classList.remove('d-none');
        }
        var ausPane = document.getElementById('tab-ausencias');
        var hostAus = document.getElementById('host-ausencias');
        if (ausPane && hostAus) {
            ausPane.appendChild(hostAus);
            hostAus.classList.remove('d-none');
        }
        var indPane = document.getElementById('tab-indumentaria');
        var hostInd = document.getElementById('host-indumentaria');
        if (indPane && hostInd) {
            indPane.appendChild(hostInd);
            hostInd.classList.remove('d-none');
        }
        var famPane = document.getElementById('tab-familiares');
        var hostFam = document.getElementById('host-familiares');
        if (famPane && hostFam) {
            famPane.appendChild(hostFam);
            hostFam.classList.remove('d-none');
        }
        var pcPane = document.getElementById('tab-planes-cuota');
        var hostPc = document.getElementById('host-planes-cuota');
        if (pcPane && hostPc) {
            pcPane.appendChild(hostPc);
            hostPc.classList.remove('d-none');
        }
        var sirPane = document.getElementById('tab-siradig');
        var hostSir = document.getElementById('host-siradig');
        if (sirPane && hostSir) {
            sirPane.appendChild(hostSir);
            hostSir.classList.remove('d-none');
        }
    }
    moveHosts();
})();
</script>
@endsection
