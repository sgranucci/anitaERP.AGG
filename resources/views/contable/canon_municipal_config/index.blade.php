@extends("theme.$theme.layout")
@section('titulo')
    Configuración canon municipal
@endsection

@section('contenido')
    <div class="row">
        <div class="col-lg-12">
            @include('includes.mensaje')
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Configuración canon municipal bingo</h3>
                    <div class="card-tools">
                        <a href="{{ route('canon_municipal') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver al proceso
                        </a>
                        @if (can('crear-canon-municipal-config', false))
                            <a href="{{ route('crear_canon_municipal_config') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-plus"></i> Nuevo
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Empresa</th>
                                <th>Municipio</th>
                                <th>Legajo</th>
                                <th>Periodicidad</th>
                                <th>Plantilla</th>
                                <th class="text-right">Alícuota</th>
                                <th>Firmante</th>
                                <th class="text-center">Activo</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($datas as $row)
                                <tr>
                                    <td>{{ $row->empresa->nombre ?? ('#'.$row->empresa_id) }}</td>
                                    <td>{{ $row->municipio }}</td>
                                    <td>{{ $row->legajo }}</td>
                                    <td>{{ \App\Models\Contable\Canon_Municipal_Config::$enumPeriodicidad[$row->periodicidad] ?? $row->periodicidad }}</td>
                                    <td>{{ \App\Models\Contable\Canon_Municipal_Config::$enumPlantilla[$row->plantilla] ?? $row->plantilla }}</td>
                                    <td class="text-right">{{ number_format((float) $row->alicuota * 100, 2, ',', '.') }}%</td>
                                    <td>{{ $row->firmante_nombre }}</td>
                                    <td class="text-center">
                                        @if ($row->activo)
                                            <span class="badge badge-success">Sí</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        @if (can('editar-canon-municipal-config', false))
                                            <a href="{{ route('editar_canon_municipal_config', $row->id) }}"
                                               class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('eliminar-canon-municipal-config', false))
                                            <form action="{{ route('eliminar_canon_municipal_config', $row->id) }}"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar esta configuración?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Sin configuraciones.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
