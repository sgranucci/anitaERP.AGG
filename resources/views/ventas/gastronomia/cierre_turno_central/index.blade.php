@extends("theme.$theme.layout")
@section('titulo')
    Cierre centralizado de turnos — gastronomía
@endsection

@section("scripts")
<style>
    .gastro-totales-panel { font-size: 1rem; }
    .gastro-totales-monto { font-size: 1.2rem; line-height: 1.3; }
    .gastro-totales-tabla { font-size: 0.95rem; }
    .gastro-totales-tabla th,
    .gastro-totales-tabla td { padding: 0.45rem 0.6rem; vertical-align: middle; }
    .gastro-grilla-conciliacion-wrap {
        max-height: 320px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .gastro-campo-auto-actualizado {
        background-color: #fff3cd !important;
        transition: background-color 0.3s ease;
    }
    #tabla-turnos-central thead th { background: #85C1E9; color: #17202A; }
</style>
<script>
    window.CIERRE_TURNO_CENTRAL_GASTRONOMIA = {
        csrf: @json(csrf_token()),
        puedeCerrar: @json($puede_cerrar ?? false),
        puedeVerFactura: @json($puede_ver_factura ?? false),
        urlFacturaVerBase: @json($url_factura_ver_base ?? ''),
        urlSaneamientoTurno: @json($url_saneamiento_turno ?? ''),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/saneamiento_huecos_arca.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/saneamiento_huecos_arca.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/cierre_turno_central.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia/cierre_turno_central.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="cierre-turno-central-app"
     data-api-turnos="{{ route('gastronomia_cierre_turno_central_api_turnos') }}"
     data-api-estado="{{ route('gastronomia_cierre_turno_central_api_estado_turno') }}"
     data-api-cerrar="{{ route('gastronomia_cierre_turno_central_api_cerrar') }}"
     data-api-diagnosticar-huecos-arca="{{ route('gastronomia_habilitacion_turno_api_diagnosticar_huecos_arca') }}"
     data-api-ejecutar-saneamiento-huecos-arca="{{ route('gastronomia_habilitacion_turno_api_ejecutar_saneamiento_huecos_arca') }}"
     data-api-conciliacion="{{ route('gastronomia_cierre_turno_central_api_conciliacion_turno') }}"
     data-api-conciliacion-medio="{{ route('gastronomia_cierre_turno_central_api_conciliacion_medio') }}"
     data-api-conciliacion-nc="{{ route('gastronomia_cierre_turno_central_api_conciliacion_notas_credito') }}"
     data-api-conciliacion-invitaciones="{{ route('gastronomia_cierre_turno_central_api_conciliacion_invitaciones') }}"
     data-puede-cerrar="{{ ($puede_cerrar ?? false) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Cierre centralizado de turnos</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2">
                    <strong>Para administración, gerencia y encargados de gastronomía.</strong>
                    Permite cerrar turnos habilitados en cualquier terminal con la misma pantalla de cierre
                    (totales, arqueo por medio, conciliación) que en la PC del punto de venta.
                    Los totales se calculan siempre sobre la IP/terminal del turno, no sobre su navegador.
                </div>

                <form method="get" action="{{ route('gastronomia_cierre_turno_central') }}" class="form-inline mb-3" id="form-filtro-cierre-central">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'select_class' => 'mr-3',
                        'required' => true,
                        'permite_todas' => false,
                    ])
                    <button type="submit" class="btn btn-secondary btn-sm">Actualizar</button>
                </form>

                <div id="panel-jornada-central" class="mb-2"></div>
                <div id="panel-turnos-central"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-cierre-turno-central" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modal-cierre-turno-central-titulo">Cierre definitivo del turno</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modal-cierre-turno-resumen" class="alert alert-light border mb-3"></div>
                <div id="alertas-control-central" class="mb-3"></div>
                <div id="totales-cierre-central" class="mb-3"></div>

                <div class="card card-outline card-primary mb-3">
                    <div class="card-header py-2">
                        <i class="fa fa-balance-scale"></i> Comprobantes del turno
                    </div>
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="btn-grilla-central">
                                <i class="fa fa-table"></i> Ver comprobantes (paginado)
                            </button>
                            <label class="mb-0 small">
                                <input type="checkbox" id="filtro-solo-diferencias-central"/>
                                Solo comprobantes con diferencia
                            </label>
                        </div>
                        <div id="grilla-conciliacion-central" class="gastro-grilla-conciliacion-wrap">
                            <p class="text-muted p-3 mb-0 small">Pulse «Ver comprobantes» para cargar el detalle.</p>
                        </div>
                    </div>
                </div>

                @if ($puede_cerrar ?? false)
                    <div class="card card-outline card-danger">
                        <div class="card-header bg-danger text-white py-2">
                            <i class="fa fa-lock"></i> Confirmar cierre
                        </div>
                        <div class="card-body">
                            <form id="form-cierre-turno-central" autocomplete="off">
                                <input type="hidden" id="central_turno_operativo_id" name="turno_operativo_id" value=""/>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="central_redondeo_invitaciones">Redondeo invitaciones ($0,01)</label>
                                        <input type="number" step="0.01" name="redondeo_invitaciones" id="central_redondeo_invitaciones" class="form-control"/>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="central_redondeo_turno">Redondeo turno</label>
                                        <input type="number" step="0.01" name="redondeo_turno" id="central_redondeo_turno" class="form-control" value="0"/>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="central_sobrante_faltante">Sobrante / faltante</label>
                                        <input type="number" step="0.01" name="sobrante_faltante" id="central_sobrante_faltante" class="form-control" value="0"/>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="central_observacion_cierre">Observaciones</label>
                                    <textarea name="observacion_cierre" id="central_observacion_cierre" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                                <div id="errores-cierre-central" class="alert alert-warning d-none"></div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-secondary mb-0">
                        Su usuario puede consultar totales pero no ejecutar el cierre.
                        Solicite el permiso <em>Cerrar turno centralizado gastronomía</em>.
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                @if ($puede_cerrar ?? false)
                    <button type="button" class="btn btn-danger" id="btn-submit-cierre-central">
                        <i class="fa fa-lock"></i> Confirmar cierre definitivo
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@include('ventas.gastronomia.cierre_turno_central.partials.modal_conciliacion')
@include('ventas.gastronomia.partials.modal_saneamiento_huecos_arca')
@endsection
