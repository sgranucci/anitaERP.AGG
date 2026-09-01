@extends("theme.$theme.layout")
@section('titulo')
Salida de bienes
@endsection

@section("scripts")
<script src="{{asset('assets/pages/scripts/admin/index.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="row mb-3">
            <div class="col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $kpis['pendiente_aprobacion'] ?? 0 }}</h3>
                        <p>Pend. aprobación</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $kpis['en_custodia'] ?? 0 }}</h3>
                        <p>En custodia</p>
                    </div>
                    <div class="icon"><i class="fa fa-handshake-o"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $kpis['vencidos'] ?? 0 }}</h3>
                        <p>Vencidos (no retorno)</p>
                    </div>
                    <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ $kpis['alta_prioridad'] ?? 0 }}</h3>
                        <p>Prioridad alta abiertos</p>
                    </div>
                    <div class="icon"><i class="fa fa-bolt"></i></div>
                </div>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Salida de bienes</h3>
                <div class="card-tools">
                    @if (can('crear-salida-bienes', false))
                        <a href="{{ route('crear_salida_bienes') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nueva salida
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Fecha</th>
                            <th>Devuelve</th>
                            <th>Origen</th>
                            <th>Destinatario</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Ítems</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prestamos as $p)
                            <tr>
                                <td><strong>{{ $p->codigo }}</strong></td>
                                <td>{{ $p->etiquetaTipo() }}</td>
                                <td>{{ optional($p->fecha_prestamo)->format('d/m/Y') }}</td>
                                <td>
                                    @php $vencido = $p->estaVencido(); @endphp
                                    <span @if ($vencido) class="text-danger" title="Vencido" @endif>
                                        {{ optional($p->fecha_devolucion_prometida)->format('d/m/Y') ?: '—' }}
                                        @if ($vencido)
                                            <i class="fa fa-exclamation-circle"></i>
                                        @endif
                                    </span>
                                </td>
                                <td>{{ optional($p->depositoOrigen)->nombre }}</td>
                                <td>{{ $p->etiquetaDestinatario() }}</td>
                                <td>
                                    @if (($p->prioridad ?? 'NORMAL') === 'ALTA')
                                        <span class="badge badge-danger">Alta</span>
                                    @elseif (($p->prioridad ?? '') === 'BAJA')
                                        <span class="badge badge-secondary">Baja</span>
                                    @else
                                        <span class="badge badge-light">Normal</span>
                                    @endif
                                </td>
                                <td>
                                    @include('stock.salida_bienes.partials.estado_badge', ['estado' => $p->estado])
                                </td>
                                <td class="text-right">{{ $p->items->count() }}</td>
                                <td>
                                    <a href="{{ route('ver_salida_bienes', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('pdf_salida_bienes', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Remito PDF" target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                    @if ($p->estado === 'BORRADOR' && can('editar-salida-bienes', false))
                                        <a href="{{ route('editar_salida_bienes', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($p->estado === 'BORRADOR' && can('borrar-salida-bienes', false))
                                        <form action="{{ route('eliminar_salida_bienes', ['id' => $p->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
