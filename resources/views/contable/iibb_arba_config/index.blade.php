@extends("theme.$theme.layout")
@section('titulo')
    Configuración ARBA
@endsection

@section('contenido')
    <div class="row">
        <div class="col-lg-12">
            @include('includes.mensaje')
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Configuración ARBA (liquidación y solicitud de pago)</h3>
                    <div class="card-tools">
                        <a href="{{ route('ingresos_brutos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver al proceso
                        </a>
                        @if (can('crear-iibb-arba-config', false))
                            <a href="{{ route('crear_iibb_arba_config') }}" class="btn btn-outline-primary btn-sm">
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
                                <th>Proveedor (ARBA)</th>
                                <th>Concepto</th>
                                <th>Cuenta Debe</th>
                                <th>Cuenta Banco</th>
                                <th class="text-right">Días duplicado</th>
                                <th class="text-right">Días aviso pendiente</th>
                                <th class="text-center">Activo</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($datas as $row)
                                <tr>
                                    <td>{{ $row->empresa->nombre ?? ('#'.$row->empresa_id) }}</td>
                                    <td>{{ $row->proveedor->nombre ?? '' }}</td>
                                    <td>{{ $row->conceptoSolicitudpago->nombre ?? '' }}</td>
                                    <td>{{ $row->cuentacontableDebe->codigo ?? '' }}</td>
                                    <td>{{ $row->cuentacontableBanco->codigo ?? '' }}</td>
                                    <td class="text-right">{{ $row->dias_duplicado }}</td>
                                    <td class="text-right">{{ $row->dias_aviso_pendiente }}</td>
                                    <td class="text-center">
                                        @if ($row->activo)
                                            <span class="badge badge-success">Sí</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        @if (can('editar-iibb-arba-config', false))
                                            <a href="{{ route('editar_iibb_arba_config', $row->id) }}"
                                               class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('eliminar-iibb-arba-config', false))
                                            <form action="{{ route('eliminar_iibb_arba_config', $row->id) }}"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar esta configuración ARBA?');">
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
                                    <td colspan="9" class="text-center text-muted py-4">Sin configuraciones ARBA.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
