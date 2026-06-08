@extends("theme.$theme.layout")
@section('titulo')
Movimientos de stock — {{ $contexto['articulo']['sku'] ?? '' }}
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $art = $contexto['articulo'] ?? [];
    $dep = $contexto['deposito'] ?? [];
    $modoTodosDepositos = $modoTodosDepositos ?? ($contexto['modo_todos_depositos'] ?? false);
    $modoConsulta = request()->input('vista') === 'consulta';
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-exchange"></i>
                    Movimientos de stock
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
                <dl class="row mb-2 small">
                    <dt class="col-sm-2">Artículo</dt>
                    <dd class="col-sm-10">
                        <strong>{{ $art['sku'] ?? '' }}</strong>
                        @if (! empty($art['descripcion']))
                            — {{ $art['descripcion'] }}
                        @endif
                    </dd>
                    <dt class="col-sm-2">
                        @if ($modoTodosDepositos)
                            Saldo total
                        @else
                            Saldo actual
                        @endif
                    </dt>
                    <dd class="col-sm-10 text-monospace">{{ $contexto['saldo_fmt'] ?? '0' }}</dd>
                </dl>
                <div id="filtro-deposito-movimientos-articulo" class="border-top pt-3">
                    <div class="row align-items-start">
                        <div class="col-lg-8">
                            @include('stock.partials.campo_consulta_deposito', [
                                'prefix' => 'mov_filtro',
                                'layout' => 'form_row',
                                'label' => 'Depósito',
                                'inputId' => 'mov_filtro_deposito_id',
                                'depositoId' => $modoTodosDepositos ? '' : ($dep['id'] ?? ''),
                                'codigo' => $modoTodosDepositos ? '' : ($dep['codigo'] ?? ''),
                                'descripcion' => $modoTodosDepositos ? '' : ($dep['nombre'] ?? ''),
                                'required' => false,
                                'solo_lectura' => false,
                                'col_label' => 'col-sm-3 control-label text-right pr-2',
                                'col_input' => 'col-sm-8',
                            ])
                        </div>
                        <div class="col-lg-4 pt-2">
                            <button type="button"
                                id="btn-movimientos-todos-depositos"
                                class="btn btn-outline-primary btn-sm @if ($modoTodosDepositos) active font-weight-bold @endif"
                                title="Ver saldo y movimientos de todos los depósitos autorizados">
                                <i class="fa fa-warehouse"></i> Todos los depósitos
                            </button>
                            @if ($modoTodosDepositos)
                                <span class="d-block small text-muted mt-1">Mostrando movimientos de todos los depósitos autorizados.</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0 pt-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_recuento_movimientos_articulo',
                    'queryparams' => $queryParams ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:8%">Fecha</th>
                            @if ($modoTodosDepositos)
                            <th style="width:12%">Depósito</th>
                            @endif
                            <th style="width:7%">Tipo</th>
                            <th class="text-right" style="width:8%">Entrada</th>
                            <th class="text-right" style="width:8%">Salida</th>
                            <th style="width:22%">Concepto</th>
                            <th style="width:9%">Mov. stock</th>
                            <th style="width:26%">Leyenda mov.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $m)
                            <tr>
                                <td>{{ $m->fecha ? \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') : '—' }}</td>
                                @if ($modoTodosDepositos)
                                <td class="small">{{ $m->deposito_etiqueta ?? '—' }}</td>
                                @endif
                                <td title="{{ $m->tipo_nombre ?? '' }}">{{ $m->tipo ?? '—' }}</td>
                                <td class="text-right text-monospace text-success">{{ $m->entrada_fmt ?: '—' }}</td>
                                <td class="text-right text-monospace text-danger">{{ $m->salida_fmt ?: '—' }}</td>
                                <td>{{ $m->concepto_display ?? $m->concepto }}</td>
                                <td class="text-monospace">{{ $m->movimiento_codigo ?: ($m->movimientostock_id ? '#'.$m->movimientostock_id : '—') }}</td>
                                <td class="small">{{ $m->movimiento_leyenda }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $modoTodosDepositos ? 8 : 7 }}" class="text-muted text-center">
                                    @if ($modoTodosDepositos)
                                        Sin movimientos registrados en los depósitos autorizados.
                                    @else
                                        Sin movimientos registrados en este depósito.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($movimientos, 'links'))
                <div class="card-footer clearfix">
                    {{ $movimientos->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
<input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
<input type="hidden" id="movimientos-articulo-id" value="{{ $art['id'] ?? '' }}">
@include('includes.stock.modalconsultadeposito')
@endsection
