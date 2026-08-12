@extends("theme.$theme.layout")
@section('titulo')
    Conceptos del Libro de IVA Compras
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/concepto_ivacompra/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Compras\ConceptoIvacompraListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conceptos del Libro de IVA Compras</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-concepto-ivacompra',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ConceptoIvacompraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('concepto_ivacompra'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-concepto-ivacompra',
                        'toggleId' => 'btn-toggle-filtros-concepto-ivacompra',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_concepto_ivacompra', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-concepto-iva-compra',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('concepto_ivacompra') }}" id="form-filtros-concepto-ivacompra" class="mb-0">
                @include('compras.concepto_ivacompra.partials.filtros_listado', [
                    'limpiarUrl' => route('concepto_ivacompra'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_concepto_ivacompra',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Columna IVA</th>
                            <th>Ret. Gan.</th>
                            <th>Ret. IIBB</th>
                            <th>Empresas / cuentas</th>
                            <th>Código</th>
                            <th style="width:5.5rem;min-width:5.5rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->desc_tipo_concepto }}</td>
                            <td>{{ $data->columna_ivacompras->nombre ?? '' }}</td>
                            <td>{{ $data->desc_retiene_ganancia }}</td>
                            <td>{{ $data->desc_retiene_iibb }}</td>
                            <td>
                                @forelse (($data->concepto_ivacompra_empresas ?? []) as $lineaEmp)
                                    <div class="small mb-1">
                                        <strong>{{ $lineaEmp->empresa->nombre ?? ('#'.$lineaEmp->empresa_id) }}</strong>
                                        <span class="text-muted">—</span>
                                        D: {{ $lineaEmp->cuentacontabledebe->codigo ?? '—' }}
                                        / H: {{ $lineaEmp->cuentacontablehaber->codigo ?? '—' }}
                                    </div>
                                @empty
                                    @if ($data->cuentacontablesdebe || $data->cuentacontableshaber)
                                        <span class="small text-muted">
                                            D: {{ $data->cuentacontablesdebe->nombre ?? '—' }}
                                            / H: {{ $data->cuentacontableshaber->nombre ?? '—' }}
                                        </span>
                                    @else
                                        <span class="text-muted">Sin empresas</span>
                                    @endif
                                @endforelse
                            </td>
                            <td>{{ $data->codigo }}</td>
                            <td class="text-nowrap text-center">
                                @if (can('editar-concepto-iva-compra', false))
                                    <a href="{{ route('editar_concepto_ivacompra', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-concepto-iva-compra', false))
                                <form action="{{ route('eliminar_concepto_ivacompra', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
        {{ $datas->appends($filtrosQuery ?? [])->links() }}
    </div>
</div>
@endsection
