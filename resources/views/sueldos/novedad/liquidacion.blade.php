@extends("theme.$theme.layout")
@section('titulo')
    Novedades de la corrida
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@php use App\Support\Sueldos\NovedadSueldosCatalogo; @endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Novedades · Corrida N&deg; {{ $liquidacion->numero }}
                    <small class="text-white-50">{{ $liquidacion->descripcion }} · {{ $liquidacion->periodo }}</small>
                </h3>
                <div class="card-tools">
                    @if ($puedeCrear ?? false)
                        <a href="{{ route('crear_novedad_sueldos', ['liquidacion_id' => $liquidacion->id]) }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Nueva novedad
                        </a>
                    @endif
                    <a href="{{ route('editar_liquidacion_sueldos', ['id' => $liquidacion->id]) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a la corrida
                    </a>
                    <a href="{{ route('consultar_novedad_sueldos', ['liquidacion_id' => $liquidacion->id]) }}" class="btn btn-outline-light btn-sm">
                        Listado completo
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Legajo</th>
                            <th>Empleado</th>
                            <th>Concepto</th>
                            <th class="text-right">Valor 1</th>
                            <th class="text-right">Valor 2</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ optional($data->empleado)->legajo }}</td>
                            <td>{{ optional($data->empleado)->nombre }}</td>
                            <td>{{ optional($data->concepto)->codigo }} — {{ optional($data->concepto)->descripcion }}</td>
                            <td class="text-right">{{ number_format((float) $data->valor1, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->valor2, 2, ',', '.') }}</td>
                            <td>{{ NovedadSueldosCatalogo::etiquetaEstado($data->estado) }}</td>
                            <td>{{ NovedadSueldosCatalogo::etiquetaOrigen($data->origen) }}</td>
                            <td>
                                @if (can('editar-novedad-sueldos', false))
                                    <a href="{{ route('editar_novedad_sueldos', ['id' => $data->id]) }}" class="btn-accion-tabla" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay novedades cargadas en esta corrida.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@if(method_exists($datas, 'links'))
    {{ $datas->appends($filtrosQuery ?? [])->links() }}
@endif
@endsection
