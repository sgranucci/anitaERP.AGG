@extends("theme.$theme.layout")
@section('titulo')
    Ticket de ingreso #{{ $data->id }}
@endsection

@section('contenido')
@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
    $esVisitante = IngresoProveedorVisitanteSupport::esVisitante($data);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ticket de ingreso #{{ $data->id }}</h3>
            </div>
            <div class="card-body">
                @if ($data->estado === IngresoProveedorEstados::RECHAZADO)
                    <div class="alert alert-danger">
                        Ticket rechazado
                        @if ($data->usuarioAutorizo)
                            por {{ $data->usuarioAutorizo->nombre }}
                        @endif
                        @if ($data->comentario)
                            <div class="mt-2 mb-0"><strong>Motivo:</strong> {{ $data->comentario }}</div>
                        @endif
                    </div>
                @endif

                <table class="table table-sm table-bordered mb-3">
                    <tbody>
                        <tr>
                            <th style="width:22%">Estado</th>
                            <td>{{ IngresoProveedorEstados::etiqueta((string) $data->estado) }}</td>
                            <th style="width:22%">Fecha de carga</th>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <th>Empresa</th>
                            <td>{{ $data->empresas->nombre ?? '—' }}</td>
                            <th>Generó</th>
                            <td>{{ $data->usuarios->nombre ?? $data->usuarios->usuario ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Proveedor / visitante</th>
                            <td colspan="3">
                                {{ IngresoProveedorVisitanteSupport::etiquetaOrigen($data) }}
                                @if ($esVisitante)
                                    <span class="badge badge-secondary">Visitante</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Contrato / Abono</th>
                            <td>{{ $data->ordencompras->numeroordencompra ?? '—' }}</td>
                            <th>Fecha prevista</th>
                            <td>{{ optional($data->fecha_prevista)->format('d/m/Y') ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>Motivo</th>
                            <td>
                                {{ $data->motivos->nombre ?? '—' }}
                                @if ($data->motivo_otro)
                                    — {{ $data->motivo_otro }}
                                @endif
                            </td>
                            <th>Patente</th>
                            <td>{{ $data->patente ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>Sala / Punto</th>
                            <td>{{ $data->puntos->nombre ?? '—' }}</td>
                            <th>Sector</th>
                            <td>{{ $data->sectores->nombre ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>&Aacute;rea</th>
                            <td>{{ $data->areas->nombre ?? '—' }}</td>
                            <th>T&iacute;tulo</th>
                            <td>{{ $data->titulo ?: '—' }}</td>
                        </tr>
                        @if ($data->comentario && $data->estado !== IngresoProveedorEstados::RECHAZADO)
                            <tr>
                                <th>Comentario</th>
                                <td colspan="3">{{ $data->comentario }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <h5>Persona(s) que visita(n)</h5>
                <table class="table table-sm table-bordered mb-3">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nombre</th>
                            <th>Documento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data->personas as $persona)
                            <tr>
                                <td>{{ $persona->nombre }}</td>
                                <td>{{ $persona->documento ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted">Sin personas cargadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <h5>Archivos adjuntos</h5>
                @include('seguridad.ingreso_proveedor.partials.archivos_adjuntos', [
                    'data' => $data,
                    'ocultarInputsConservar' => true,
                    'hashPublico' => $hashPublico ?? $data->hashvisualizar,
                ])

                @include('seguridad.ingreso_proveedor.partials.movimiento_planta', ['data' => $data])
            </div>
        </div>
    </div>
</div>
@endsection
