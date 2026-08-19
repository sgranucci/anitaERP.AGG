@php
    use App\Support\Contable\CuentacontableArbolSupport;
@endphp
<div class="card-body table-responsive p-0">
    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th class="width20">ID</th>
                <th>Empresa</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Nivel</th>
                <th>Tipo</th>
                <th>Rubro</th>
                <th>C.Costo</th>
                <th>Concepto</th>
                <th class="width80" data-orderable="false"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cuentacontables as $data)
                @php $tipo = (string) $data->tipocuenta; @endphp
                <tr class="{{ CuentacontableArbolSupport::esTotalizadora($tipo) ? 'text-muted' : '' }}">
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->empresas->nombre ?? '' }}</td>
                    <td>{{ CuentacontableArbolSupport::formatearCodigo((string) $data->codigo) }}</td>
                    <td>{{ $data->nombre }}</td>
                    <td>{{ $data->nivel }}</td>
                    <td>{{ CuentacontableArbolSupport::etiquetaTipo($tipo) }}</td>
                    <td>{{ $data->rubrocontables->nombre ?? '' }}</td>
                    <td>{{ $data->manejaccosto == 'S' ? 'Sí' : 'No' }}</td>
                    <td>{{ $data->conceptogastos->nombre ?? '' }}</td>
                    <td>
                        @if (can('editar-cuentas-contables', false))
                            <a href="{{ route('editar_cuentacontable', ['id' => $data->id] + $retornoListadoQuery) }}"
                               class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if (can('borrar-cuentas-contables', false))
                            <a href="{{ route('eliminar_cuentacontable', ['id' => $data->id]) }}"
                               class="eliminar-cuentacontable tooltipsC" title="Eliminar este registro">
                                <i class="fa fa-times-circle text-danger"></i>
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">Sin cuentas para estos filtros.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
