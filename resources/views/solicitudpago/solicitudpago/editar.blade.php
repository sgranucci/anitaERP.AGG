@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de pago
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/montos_formato.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/concepto_solicitudpago/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/arbolaprobacion/panel_ia.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $soloConsulta = ! empty($soloConsulta);
    $ocultarVolver = ! empty($ocultarVolver);
    $puedeActualizar = ! empty($puedeActualizar);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloConsulta && ! $puedeActualizar)
                        Consultar solicitud de pago #{{ $data->codigo }}
                    @else
                        Editar solicitud de pago #{{ $data->codigo }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if (can('listar-solicitud-pago', false) || can('editar-solicitud-pago', false))
                        <a href="{{ route('imprimir_pdf_solicitudpago', $data->id) }}"
                           class="btn btn-primary btn-sm"
                           title="Emitir comprobante PDF"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fa fa-print"></i> Emitir
                        </a>
                    @endif
                    @if (! $soloConsulta && can('actualizar-solicitud-pago', false))
                        @if ($data->estado !== 'SUSPENDIDA')
                            <form action="{{ route('suspender_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('¿Suspender esta solicitud?');">
                                    <i class="fa fa-pause"></i> Suspender
                                </button>
                            </form>
                        @else
                            <form action="{{ route('levantar_suspension_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('¿Levantar suspensión?');">
                                    <i class="fa fa-play"></i> Levantar suspensi&oacute;n
                                </button>
                            </form>
                        @endif
                        @if (\App\Support\Solicitudpago\SolicitudpagoEstados::puedeReenviarAlArbol($data->estado ?? ''))
                            <form action="{{ route('reenviar_arbol_aprobacion_solicitudpago', $data->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Reenviar esta solicitud al árbol de aprobación? Se eliminan los movimientos previos y se vuelve a notificar desde el primer nivel pendiente.');">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"
                                        title="Elimina movimientos previos del árbol y vuelve a disparar correos/aprobaciones">
                                    <i class="fa fa-fw fa-sitemap"></i> Reenviar al árbol
                                </button>
                            </form>
                            @if (!empty($tienePendientesCorreoArbol))
                                <form action="{{ route('reenviar_correo_arbol_solicitudpago', $data->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Reenviar el correo del nivel pendiente actual? No reinicia el árbol: usa los mismos enlaces de aprobación.');">
                                    @csrf
                                    <button type="submit" class="btn btn-info btn-sm"
                                            title="Reenvía el correo a los firmantes del nivel pendiente (por si no lo recibieron)">
                                        <i class="fa fa-fw fa-envelope"></i> Reenviar correo
                                    </button>
                                </form>
                            @endif
                        @endif
                        @if ($data->estado === 'AUTORIZADA')
                            <a href="{{ route('ir_a_pago_solicitudpago', $data->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-money"></i> Pagar (IE)
                            </a>
                            <form action="{{ route('marcar_pagada_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('¿Marcar como PAGADA sin IE?');">
                                    <i class="fa fa-check"></i> Marcar pagada
                                </button>
                            </form>
                        @endif
                    @endif
                    @if (! $ocultarVolver)
                        @if (! empty($acceso_visualizacion_por_hash))
                            <a href="{{ route('inicio') }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver
                            </a>
                        @else
                            <a href="{{route('consultar_solicitudpago')}}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_solicitudpago', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off"
                  @if ($soloConsulta && ! $puedeActualizar) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="@if ($soloConsulta && ! $puedeActualizar) pe-none @endif"
                     @if ($soloConsulta && ! $puedeActualizar) style="opacity:.92" @endif>
                    @include('solicitudpago.solicitudpago.partials.form_tabs')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if (! $soloConsulta)
                                @include('includes.boton-form-editar')
                            @else
                                @if ($puedeActualizar)
                                    @include('includes.boton-form-editar')
                                @endif
                                @if (! empty($acceso_visualizacion_por_hash))
                                    <a href="{{ route('inicio') }}" class="btn btn-secondary @if ($puedeActualizar) ml-2 @endif">
                                        Volver
                                    </a>
                                @else
                                    <button type="button" class="btn btn-secondary @if ($puedeActualizar) ml-2 @endif" onclick="window.close()">
                                        Cerrar solapa
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@if (session('abrir_pdf_solicitudpago'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.open(@json(session('abrir_pdf_solicitudpago')), '_blank');
});
</script>
@endif
@endsection
