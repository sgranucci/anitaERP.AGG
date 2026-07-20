@extends("theme.$theme.layout")
@section('titulo')
    Deducciones Art. 30 Ganancias
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cat&aacute;logo deducciones Art. 30</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th class="text-center" style="width:70px">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deducciones as $ded)
                        <tr>
                            <td>{{ $ded->codigo }}</td>
                            <td>{{ $ded->descripcion }}</td>
                            <td class="text-center">{{ $ded->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-ganancia-deduccion-sueldos', false))
                                    <a href="{{ route('editar_ganancia_deduccion_sueldos', ['codigo' => $ded->codigo]) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar valores mensuales">
                                        <i class="fa fa-edit"></i>
                                    </a>
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
