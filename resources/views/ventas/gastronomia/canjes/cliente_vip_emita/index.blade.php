@extends("theme.$theme.layout")
@section('titulo')
    {{ $titulo }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/canjes/cliente_vip_emita/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia/canjes/cliente_vip_emita/consulta.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($error))
            <div class="alert alert-danger">{{ $error }}</div>
        @endif
        @if (! empty($aviso))
            <div class="alert alert-warning">{{ $aviso }}</div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $titulo }}</h3>
            </div>
            <form method="get" action="{{ route('consultar_cliente_vip_emita') }}" id="form-consulta-vip-emita" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Un solo texto busca en el nombre y en el alias de Emita. Entran las cuentas Wigos con nombre, alias y última visita. Los apodos sin cuenta de Wigos aparecen al final: se pueden ver, pero no elegir para un canje.
                        La consulta de clientes VIP del ERP sigue en el menú Clientes VIP.
                    </p>
                    <div class="form-group row mb-0">
                        <label for="texto" class="col-lg-3 control-label text-right pr-2">{{ $etiquetaCampo }}</label>
                        <div class="col-lg-6">
                            <input type="text" name="texto" id="texto" class="form-control" value="{{ $texto }}" maxlength="80" autocomplete="off" placeholder="Al menos 3 caracteres">
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            @if ($filas !== null)
                <div class="card-body border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                        <div>
                            @foreach ($logosCabecera as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 42px; max-width: 140px; margin-right: 8px;">
                            @endforeach
                        </div>
                        <div class="text-muted">
                            @if ($total > 0)
                                {{ $total }} {{ $total === 1 ? 'resultado' : 'resultados' }}
                            @else
                                Sin resultados para «{{ $texto }}».
                            @endif
                        </div>
                    </div>
                    @if ($total > 0)
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_cliente_vip_emita',
                            'queryparams' => $filtrosQuery,
                        ])
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    <th>Sala</th>
                                    <th>Origen</th>
                                    <th>Cuenta Wigos</th>
                                    <th>Nombre y apellido</th>
                                    <th>Alias</th>
                                    <th>Nivel tarjeta</th>
                                    <th>VIP</th>
                                    <th>Última visita</th>
                                </tr>
                            </thead>
                            <tbody>
                                @include('ventas.gastronomia.canjes.cliente_vip_emita.partials.tabla_filas', ['filas' => $filas])
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    {{ $filas->appends($filtrosQuery)->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'vip-emita-overlay',
    'tituloId' => 'vip-emita-titulo',
    'subtituloId' => 'vip-emita-subtitulo',
    'titulo' => 'Consultando Emita…',
    'subtitulo' => 'Puede demorar unos segundos. No cierre la página.',
])
@endsection
