@extends("theme.$theme.layout")
@section('titulo')
    Cobertura LSD
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cobertura de conceptos LSD</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual-lsd')
                    <a href="{{ route('consultar_lsd_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p>
                    Activos: {{ $resumen['activos'] }}
                    · Exportables (sin contribuciones/informativos): {{ $resumen['exportables'] }}
                    · Mapeados: <strong>{{ $resumen['mapeados'] }}</strong>
                    ({{ number_format($resumen['porcentaje'], 1, ',', '') }}%)
                    · Sin mapeo: {{ $resumen['sin_mapeo'] }}
                    · Con bases registro 04: {{ $resumen['con_bases_04'] ?? 0 }}
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sinMapeo as $c)
                                <tr>
                                    <td>{{ $c->codigo }}</td>
                                    <td>{{ $c->descripcion }}</td>
                                    <td>{{ $c->tipo }}</td>
                                    <td>
                                        @if ($puedeEditarConcepto)
                                            <a class="btn-accion-tabla tooltipsC text-primary" title="Editar concepto"
                                               href="{{ route('editar_concepto_sueldos', $c->id) }}" target="_blank" rel="noopener">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Todos los conceptos exportables tienen código AFIP.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
