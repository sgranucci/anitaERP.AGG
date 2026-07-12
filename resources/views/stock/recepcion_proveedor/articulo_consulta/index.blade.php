@extends("theme.$theme.layout")
@section('titulo')
Recepciones — {{ $contexto['articulo']['sku'] ?? '' }}
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('contenido')
@php
    $art = $contexto['articulo'] ?? [];
    $modoConsulta = request()->input('vista') === 'consulta';
    $sufijoUm = \App\Support\Stock\MovimientosArticuloDepositoSupport::sufijoColumnaCantidad($art['unidad_medida'] ?? '');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-truck"></i>
                    Recepciones de proveedor
                </h3>
                <div class="card-tools">
                    @if ($modoConsulta)
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                        <i class="fa fa-fw fa-times"></i> Cerrar solapa
                    </button>
                    @else
                    <a href="{{ $volverUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply"></i> Volver
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body pb-2">
                <dl class="row mb-0 small">
                    <dt class="col-sm-2">Art&iacute;culo</dt>
                    <dd class="col-sm-10">
                        <strong>{{ $art['sku'] ?? '' }}</strong>
                        @if (! empty($art['descripcion']))
                            — {{ $art['descripcion'] }}
                        @endif
                    </dd>
                    <dt class="col-sm-2">Unidad de medida</dt>
                    <dd class="col-sm-10">{{ ! empty($art['unidad_medida']) ? $art['unidad_medida'] : '—' }}</dd>
                    <dt class="col-sm-2">Registros</dt>
                    <dd class="col-sm-10">{{ $filas->total() }} l&iacute;nea(s) en recepciones</dd>
                </dl>
            </div>
            <div class="card-body table-responsive p-0 pt-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_recepcion_proveedor_articulo',
                    'queryparams' => $queryParams ?? [],
                ])
                @include('stock.recepcion_proveedor.articulo_consulta.partials.tabla_datos', [
                    'filas' => $filas,
                    'sufijoUm' => $sufijoUm,
                    'puedeVerRecepcion' => true,
                ])
            </div>
            @if (method_exists($filas, 'links'))
                <div class="card-footer clearfix">
                    {{ $filas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
