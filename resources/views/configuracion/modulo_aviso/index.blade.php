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
            </div>
            <div class="card-body p-0">
                <p class="px-3 pt-3 mb-2 text-muted small">
                    Definí destinatarios y plantillas de correo por evento de cada módulo.
                    Los tipos inactivos o sin destinatarios no envían mail.
                </p>
                @foreach ($tipos as $modulo => $items)
                <h5 class="px-3 mt-3 text-uppercase text-secondary">{{ $modulo }}</h5>
                <table class="table table-striped table-sm mb-4">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Código</th>
                            <th class="text-center">Activo</th>
                            <th class="text-center">Destinatarios</th>
                            <th class="text-center">PDF</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $tipo)
                        <tr>
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
                            <td class="text-center">{{ (int) $tipo->destinatarios_activos_count }}</td>
                            <td class="text-center">{{ $tipo->adjuntar_pdf ? 'Sí' : 'No' }}</td>
                            <td class="text-right">
                                @can('editar-modulo-aviso')
                                <a href="{{ url('configuracion/modulo-aviso/'.$tipo->id.'/editar') }}" class="btn btn-sm btn-primary">
                                    <i class="fa fa-edit"></i> Configurar
                                </a>
                                @endcan
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
