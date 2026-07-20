@extends("theme.$theme.layout")
@section('titulo')
    Escala Art. 94 Ganancias
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/ganancia_escala/editar.js")}}" type="text/javascript"></script>
@endsection

@php
    $mesesNombre = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    $filasTramos = old('tramos');
    if (! is_array($filasTramos)) {
        $filasTramos = $tramos->map(fn ($t) => [
            'desde' => $t->desde,
            'hasta' => $t->hasta,
            'fijo' => $t->fijo,
            'alicuota' => $t->alicuota,
            'excedente' => $t->excedente,
        ])->values()->all();
    }
    if ($filasTramos === []) {
        $filasTramos = [['desde' => '0', 'hasta' => '', 'fijo' => '0', 'alicuota' => '0', 'excedente' => '0']];
    }
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Escala {{ $anio }} — {{ $mesesNombre[(int) $mes] ?? $mes }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_ganancia_escala_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_ganancia_escala_sueldos', ['anio' => $anio, 'mes' => $mes]) }}"
                  id="form-escala" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body table-responsive p-0">
                    <table class="table table-bordered mb-0" id="tabla-tramos-escala">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:50px">#</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Fijo</th>
                                <th>Al&iacute;cuota %</th>
                                <th>Excedente</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filasTramos as $idx => $tramo)
                            <tr class="fila-tramo">
                                <td class="text-center align-middle nro-tramo">{{ $idx + 1 }}</td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="tramos[{{ $idx }}][desde]" required
                                           value="{{ $tramo['desde'] ?? '0' }}"/>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="tramos[{{ $idx }}][hasta]"
                                           value="{{ $tramo['hasta'] ?? '' }}"
                                           placeholder="En adelante"/>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="tramos[{{ $idx }}][fijo]" required
                                           value="{{ $tramo['fijo'] ?? '0' }}"/>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm"
                                           name="tramos[{{ $idx }}][alicuota]" required
                                           value="{{ $tramo['alicuota'] ?? '0' }}"/>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="tramos[{{ $idx }}][excedente]" required
                                           value="{{ $tramo['excedente'] ?? '0' }}"/>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-tramo" title="Quitar tramo">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-agregar-tramo">
                        <i class="fa fa-plus"></i> Agregar tramo
                    </button>
                </div>
                <div class="card-footer">
                    @if (can('actualizar-ganancia-escala-sueldos', false))
                        <button type="submit" class="btn botonsubmit btn-success">
                            <i class="fa fa-save"></i> Actualizar escala
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
