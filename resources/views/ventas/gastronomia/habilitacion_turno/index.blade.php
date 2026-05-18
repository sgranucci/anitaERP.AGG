@extends("theme.$theme.layout")
@section('titulo')
    Habilitación de turno gastronomía
@endsection

@section("scripts")
<script>
    window.HABILITACION_TURNO_GASTRONOMIA = {
        csrf: @json(csrf_token()),
        modoCajaDirecto: @json($modo_caja_directo ?? false),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/habilitacion_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/habilitacion_turno.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="habilitacion-turno-app"
     data-api-estado="{{ url('ventas/gastronomia/habilitacion-turno/api/estado') }}"
     data-api-habilitar="{{ url('ventas/gastronomia/habilitacion-turno/api/habilitar') }}"
     data-api-cerrar="{{ url('ventas/gastronomia/habilitacion-turno/api/cerrar') }}"
     data-csrf="{{ csrf_token() }}"
     data-puede-habilitar="{{ ($puede_habilitar ?? false) ? '1' : '0' }}"
     data-puede-cerrar="{{ ($puede_cerrar ?? false) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if ($modo_caja_directo ?? false)
            <div class="alert alert-info">
                El sistema está en modo <strong>caja directo</strong>
                (<code>GASTRONOMIA_REQUIERE_HABILITACION_TURNO=false</code>).
                No se utiliza habilitación ni cierre de turno por terminal.
            </div>
        @elseif (! $cfg)
            <div class="alert alert-warning">
                No hay configuración de punto de venta para el identificador PC
                <code>{{ $identificador_pc }}</code>.
            </div>
        @else
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Habilitación de turno por terminal</h3>
                    <div class="card-tools">
                        <a href="{{ route('gastronomia_cierres_turno') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-file-text-o"></i> Cierres de turno
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Terminal: <strong>{{ $identificador_pc }}</strong>
                        · Empresa: <strong>{{ $cfg->empresa->nombre ?? $cfg->empresa_id }}</strong>
                    </p>

                    @if (empty($jornada['jornada_abierta']))
                        <div class="alert alert-danger">
                            Debe abrir la <a href="{{ route('gastronomia_jornada', ['empresa_id' => $cfg->empresa_id]) }}">jornada</a> antes de habilitar un turno.
                        </div>
                    @endif

                    <div id="panel-estado-turno" class="mb-3"></div>

                    @if ($puede_habilitar ?? false)
                        <div class="card card-outline card-success mb-3" id="card-habilitar">
                            <div class="card-header">Habilitar turno</div>
                            <div class="card-body">
                                <form id="form-habilitar-turno" autocomplete="off">
                                    <div class="form-group">
                                        <label for="turno_gastronomia_id" class="requerido">Turno</label>
                                        <select name="turno_gastronomia_id" id="turno_gastronomia_id" class="form-control" required>
                                            <option value="">Seleccione…</option>
                                            @foreach ($turnos as $t)
                                                <option value="{{ $t->id }}">{{ $t->nombre }} @if($t->etiquetaHorario() !== '—') ({{ $t->etiquetaHorario() }}) @endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="monto_habilitacion" class="requerido">Monto habilitación</label>
                                        <input type="number" step="0.01" min="0" name="monto_habilitacion" id="monto_habilitacion" class="form-control" required value="0"/>
                                    </div>
                                    <div class="form-group">
                                        <label class="requerido">Usuario habilitado</label>
                                        <div class="gastro-campo-consulta d-flex">
                                            <input type="hidden" name="usuario_habilitado_id" id="usuario_habilitado_id" value=""/>
                                            <input type="text" class="form-control gastro-campo-nombre" id="nombre_usuario_habilitado" readonly placeholder="Buscar usuario…"/>
                                            <button type="button" title="Consulta usuarios" class="btn-accion-tabla consultausuario tooltipsC"
                                                data-ptrusuario_id="#usuario_habilitado_id" data-ptrnombre="#nombre_usuario_habilitado">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="observacion_habilitacion">Observaciones</label>
                                        <textarea name="observacion" id="observacion_habilitacion" class="form-control" rows="2" maxlength="2000"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success" id="btn-submit-habilitar">Habilitar turno</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($puede_cerrar ?? false)
                        <div class="card card-outline card-danger d-none" id="card-cerrar">
                            <div class="card-header">Cierre de turno</div>
                            <div class="card-body">
                                <div id="totales-cierre-preview" class="mb-3 small"></div>
                                <form id="form-cerrar-turno" autocomplete="off">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label for="redondeo_invitaciones">Redondeo invitaciones ($0,01)</label>
                                            <input type="number" step="0.01" name="redondeo_invitaciones" id="redondeo_invitaciones" class="form-control"/>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="redondeo_turno">Redondeo turno</label>
                                            <input type="number" step="0.01" name="redondeo_turno" id="redondeo_turno" class="form-control" value="0"/>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="sobrante_faltante">Sobrante / faltante</label>
                                            <input type="number" step="0.01" name="sobrante_faltante" id="sobrante_faltante" class="form-control" value="0"/>
                                            <small class="text-muted">Positivo = sobrante, negativo = faltante</small>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="observacion_cierre">Observaciones cierre</label>
                                        <textarea name="observacion_cierre" id="observacion_cierre" class="form-control" rows="2" maxlength="2000"></textarea>
                                    </div>
                                    <div id="errores-cierre-turno" class="alert alert-warning d-none"></div>
                                    <button type="submit" class="btn btn-danger" id="btn-submit-cerrar">Cerrar turno</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@endsection
