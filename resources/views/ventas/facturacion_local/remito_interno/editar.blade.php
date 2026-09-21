@extends("theme.$theme.layout")
@section('titulo')
    Remito interno Nº {{ $data->numero }}
@endsection
@section('scripts')
<script>
window.RI_CFG = {
    urls: {
        buscar: @json(route('api_buscar_articulo_remito_interno')),
        variantes: @json(url('ventas/facturacion-local/remitos-internos/api/variantes'))
    },
    csrf: @json(csrf_token()),
    editable: @json((bool) ($editable ?? false))
};
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/remito_interno/form.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    Remito interno Nº {{ $data->numero }}
                    <small class="ml-2 text-white-50">{{ RemitoInternoEstadosSupport::etiqueta($data->estado) }}</small>
                </h3>
                <div>
                    @if ($data->estado !== RemitoInternoEstadosSupport::BORRADOR && can('pdf-remito-interno-facturacion-local', false))
                        <a href="{{ route('pdf_remito_interno', $data->id) }}" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                            <i class="fa fa-file-pdf-o"></i> PDF
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_remitos_internos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            @if ($editable)
            <form action="{{ route('actualizar_remito_interno', $data->id) }}" method="POST" id="form-remito-interno" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.facturacion_local.remito_interno.partials.form', [
                        'data' => $data,
                        'locales' => $locales,
                        'editable' => true,
                    ])
                </div>
            </form>
            @else
            <div class="card-body">
                @include('ventas.facturacion_local.remito_interno.partials.form', [
                    'data' => $data,
                    'locales' => $locales,
                    'editable' => false,
                ])
            </div>
            @endif

            <div class="card-footer d-flex flex-wrap align-items-center">
                @if ($editable)
                    <button type="submit" class="btn btn-primary mr-2" form="form-remito-interno">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                    @if (can('confirmar-remito-interno-facturacion-local', false))
                        <form action="{{ route('confirmar_remito_interno', $data->id) }}" method="POST" class="d-inline mr-2"
                              onsubmit="return confirm('¿Confirmar remito y generar movimiento de stock?');">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Confirmar
                            </button>
                        </form>
                    @endif
                @endif
                @if ($data->estado === RemitoInternoEstadosSupport::CONFIRMADO && can('anular-remito-interno-facturacion-local', false))
                    <form action="{{ route('anular_remito_interno', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Anular remito y revertir el stock?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa fa-times"></i> Anular
                        </button>
                    </form>
                @endif
                @if ($data->movimientostock_id)
                    <span class="ml-auto text-muted small">
                        Mov. stock #{{ $data->movimientostock_id }}
                        @if ($data->movimientoStock)
                            ({{ $data->movimientoStock->codigo }})
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
