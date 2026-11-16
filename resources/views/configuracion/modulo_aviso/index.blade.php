@extends("theme.$theme.layout")
@section('titulo')
Avisos por módulo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-envelope-o"></i> Tipos de aviso configurables</h3>
                <div class="card-tools">
                    @if (! empty($solo_programables))
                        <a href="{{ route('consultar_modulo_aviso') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-list"></i> Todos los avisos
                        </a>
                        <a href="{{ route('historial_modulo_aviso_programado') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-history"></i> Historial
                        </a>
                    @else
                        <a href="{{ route('consultar_modulo_aviso', ['solo_programables' => 1]) }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-clock"></i> Programador de avisos
                        </a>
                    @endif
                    <a href="{{ route('consultar_reemplazo_destinatario_aviso') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-user-clock"></i> Reemplazo por vacaciones
                    </a>
                    <a href="{{ route('consultar_modulo_aviso_evento') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-bolt"></i> Eventos
                    </a>
                    <a href="{{ route('consultar_aviso_regla') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-project-diagram"></i> Reglas
                    </a>
                    <a href="{{ route('editar_user_task_sla') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-hourglass-half"></i> SLA
                    </a>
                    <a href="{{ route('consultar_whatsapp_envio_log') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-list-alt"></i> Audit WA
                    </a>
                    <a href="{{ route('manual_modulo_aviso') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-book"></i> Manual
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                @if (! empty($checklist_operativo))
                    <div class="px-3 pt-3">
                        <div class="border rounded p-2 mb-2" style="background:#f8fbfe;">
                            <strong class="d-block mb-2">Estado operativo del pack</strong>
                            <div class="row">
                                @foreach ($checklist_operativo as $item)
                                    <div class="col-md-4 col-lg-2 mb-2">
                                        <div class="small">
                                            @if ($item['ok'])
                                                <span class="badge badge-success">OK</span>
                                            @else
                                                <span class="badge badge-secondary">Pendiente</span>
                                            @endif
                                            <strong class="d-block mt-1">{{ $item['etiqueta'] }}</strong>
                                            <span class="text-muted">{{ $item['detalle'] }}</span>
                                            @if (!empty($item['url']) && \Illuminate\Support\Facades\Route::has($item['url']))
                                                <br><a href="{{ route($item['url']) }}">Abrir</a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                @if (! empty($solo_programables))
                    @include('configuracion.modulo_aviso.partials.cockpit', ['cockpit' => $cockpit ?? null])
                @endif
                <p class="px-3 pt-3 mb-2 text-muted small">
                    @if (! empty($solo_programables))
                        Solo avisos con cron (día/hora/frecuencia/día del mes). Cockpit arriba: próximas, fallos e historial.
                    @else
                        Definí destinatarios, plantillas y, en los avisos que lo permiten, el día y la hora de envío.
                        Los tipos inactivos o sin destinatarios no envían mail.
                    @endif
                </p>
                @foreach ($tipos as $modulo => $items)
                <h5 class="px-3 mt-3 text-uppercase text-secondary">{{ $modulo }}</h5>
                <table class="table table-striped table-sm mb-4">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Evento</th>
                            <th>Código</th>
                            <th class="text-center">Activo</th>
                            <th>Programación</th>
                            <th>Disparo</th>
                            <th class="text-center">Destinatarios</th>
                            <th class="text-center">PDF</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $tipo)
                        @php
                            $esProg = in_array($tipo->modulo.'.'.$tipo->codigo, $claves_programables ?? [], true);
                        @endphp
                        <tr @if (! empty($tipo->programacion_pausada)) class="table-warning" @endif>
                            <td>
                                <strong>{{ $tipo->nombre }}</strong>
                                @if($tipo->descripcion)
                                    <br><span class="text-muted small">{{ $tipo->descripcion }}</span>
                                @endif
                            </td>
                            <td><code>{{ $tipo->codigo }}</code></td>
                            <td class="text-center">
                                @if($tipo->activo)
                                    <span class="badge badge-success">Sí</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($tipo->programacion_habilitada || ! empty($tipo->programacion_pausada))
                                    {{ \App\Support\Configuracion\ModuloAvisoProgramacionSupport::etiquetaDesdeTipo($tipo) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">
                                @if ($esProg)
                                    <span class="badge badge-info">Cron</span>
                                @else
                                    <span class="badge badge-secondary">Evento</span>
                                @endif
                            </td>
                            <td class="text-center">{{ (int) $tipo->destinatarios_activos_count }}</td>
                            <td class="text-center">{{ $tipo->adjuntar_pdf ? 'Sí' : 'No' }}</td>
                            <td class="text-right text-nowrap">
                                @if (can('editar-modulo-aviso', false))
                                <a href="{{ url('configuracion/modulo-aviso/'.$tipo->id.'/editar') }}" class="btn btn-sm btn-primary">
                                    <i class="fa fa-edit"></i> Configurar
                                </a>
                                @endif
                                @if ($esProg)
                                    <a href="{{ route('impacto_modulo_aviso_programado', $tipo->id) }}" class="btn btn-sm btn-outline-info" title="Impacto">
                                        <i class="fa fa-search"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
