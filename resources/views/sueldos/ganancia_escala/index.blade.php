@extends("theme.$theme.layout")
@section('titulo')
    Escala Art. 94 Ganancias
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@php
    $mesesNombre = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Escala Art. 94 — per&iacute;odos disponibles</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:100px">A&ntilde;o</th>
                            <th style="width:120px">Mes</th>
                            <th class="text-center" style="width:100px">Tramos</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periodos as $p)
                        <tr>
                            <td>{{ $p->anio }}</td>
                            <td>{{ $mesesNombre[(int) $p->mes] ?? $p->mes }}</td>
                            <td class="text-center">{{ $p->tramos }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-ganancia-escala-sueldos', false))
                                    <a href="{{ route('editar_ganancia_escala_sueldos', ['anio' => $p->anio, 'mes' => $p->mes]) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar tramos">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No hay escalas cargadas.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
