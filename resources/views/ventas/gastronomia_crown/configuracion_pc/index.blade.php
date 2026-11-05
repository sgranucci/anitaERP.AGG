@extends("theme.$theme.layout")
@section('titulo')
    PC gastronomía Crown
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Configuración de PC de gastronomía</h3>
                <div class="card-tools">
                    @if (can('crear-configuracion-puntoventa-gastronomia', false))
                    <a href="{{ route('crear_configuracion_pc_gastronomia_crown') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nueva PC
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Puestos del POS Crown: modo, sala, comandas y facturación de cada terminal.</p>
                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Identificador PC</th>
                                <th>Descripción</th>
                                <th>Modo</th>
                                <th>Sala</th>
                                <th>Empresa</th>
                                <th>PV electrónico</th>
                                <th>PV CAEA</th>
                                <th>Lista</th>
                                <th>Tipo factura</th>
                                <th>Tipo NC</th>
                                <th>Salida comandas</th>
                                <th class="width80"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->identificador_pc }}</td>
                                <td>{{ $data->descripcion }}</td>
                                <td>
                                    {{ \App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos::modos()[$data->modo_pos ?? 'resto'] ?? ($data->modo_pos ?? 'resto') }}
                                    @if (! empty($data->solo_marcha))
                                        <span class="badge badge-warning">marcha</span>
                                    @endif
                                    @if (! empty($data->habilita_hotel))
                                        <span class="badge badge-secondary">hotel</span>
                                    @endif
                                </td>
                                <td>{{ optional($data->sala)->codigo }} {{ optional($data->sala)->nombre }}</td>
                                <td>{{ optional($data->empresa)->nombre }}</td>
                                <td>{{ optional($data->puntoventaCae)->codigo }} — {{ optional($data->puntoventaCae)->nombre }}</td>
                                <td>{{ optional($data->puntoventaCaea)->codigo }} — {{ optional($data->puntoventaCaea)->nombre }}</td>
                                <td>{{ optional($data->listaprecio)->nombre ?? '—' }}</td>
                                <td>{{ optional($data->tipotransaccion)->abreviatura }} — {{ optional($data->tipotransaccion)->nombre }}</td>
                                <td>{{ optional($data->tipotransaccionNotaCredito)->abreviatura }} — {{ optional($data->tipotransaccionNotaCredito)->nombre }}</td>
                                <td>{{ optional($data->salidaComanda)->nombre }}</td>
                                <td>
                                    @if (can('editar-configuracion-puntoventa-gastronomia', false))
                                        <a href="{{ route('editar_configuracion_pc_gastronomia_crown', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-configuracion-puntoventa-gastronomia', false))
                                    <form action="{{ route('eliminar_configuracion_pc_gastronomia_crown', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
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
</div>
@endsection
