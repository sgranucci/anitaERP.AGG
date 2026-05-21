@extends("theme.$theme.layout")

@section('titulo')
    Transferencia de mercadería
@endsection

@section('styles')
<style>
    .tm-page {
        padding-bottom: 5.5rem;
    }
    .tm-cabecera {
        position: sticky;
        top: 0;
        z-index: 1020;
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem 0;
        margin: 0 -0.5rem 0.75rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    .tm-cabecera label {
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.15rem;
        color: #495057;
    }
    .tm-cabecera .form-control,
    .tm-cabecera .btn {
        font-size: 1rem;
        min-height: 2.75rem;
    }
    .tm-deposito-campo .form-control {
        font-size: 1rem;
        min-height: 2.75rem;
    }
    .tm-deposito-campo .btn {
        min-height: 2.75rem;
        min-width: 2.75rem;
    }
    .tm-filtro {
        position: sticky;
        top: 0;
        z-index: 1015;
        background: #f8f9fa;
        padding: 0.5rem 0;
        margin-bottom: 0.5rem;
    }
    .tm-filtro input {
        font-size: 1.05rem;
        min-height: 2.75rem;
    }
    .tm-item {
        border: 1px solid #dee2e6;
        border-radius: 0.35rem;
        padding: 0.6rem 0.75rem;
        margin-bottom: 0.5rem;
        background: #fff;
    }
    .tm-item.tm-sin-erp {
        border-left: 4px solid #dc3545;
        opacity: 0.85;
    }
    .tm-item .tm-desc {
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.25;
        margin-bottom: 0.25rem;
        word-break: break-word;
    }
    .tm-item .tm-meta {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .tm-item .tm-saldo {
        font-size: 1.1rem;
        font-weight: 700;
        color: #17a2b8;
    }
    .tm-item input.tm-cant {
        font-size: 1.25rem;
        font-weight: 700;
        text-align: center;
        min-height: 2.75rem;
        max-width: 6rem;
    }
    .tm-barra {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1030;
        background: #fff;
        border-top: 1px solid #dee2e6;
        padding: 0.6rem 0.75rem;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
    }
    .tm-barra .btn-transferir {
        font-size: 1.1rem;
        font-weight: 700;
        min-height: 3rem;
        width: 100%;
    }
    .tm-vacio {
        text-align: center;
        color: #6c757d;
        padding: 2rem 1rem;
    }
    @media (min-width: 768px) {
        .tm-lista {
            max-width: 720px;
            margin: 0 auto;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    window.TM_URLS = {
        inventario: @json(route('transferencia_mercaderia_inventario')),
        preferencias: @json(route('transferencia_mercaderia_preferencias')),
        guardar: @json(route('transferencia_mercaderia_guardar')),
        articuloEditar: @json(url('stock/articulo')).replace(/\/$/, '') + '/',
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/transferencia_mercaderia/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="row tm-page">
    <div class="col-12">
        @include('includes.mensaje')

        <div class="card card-outline card-primary mb-2">
            <div class="card-header py-2">
                <h3 class="card-title mb-0" style="font-size: 1.1rem;">
                    Transferencia entre depósitos
                </h3>
            </div>
            <div class="card-body py-2">
                <div class="tm-cabecera">
                    <div class="form-row">
                        @include('stock.partials.campo_consulta_deposito', [
                            'prefix' => 'salida',
                            'label' => 'Depósito salida',
                            'depositoId' => optional($depSalida)->id ?? '',
                            'codigo' => optional($depSalida)->codigo ?? '',
                            'descripcion' => optional($depSalida)->nombre ?? '',
                        ])
                        @include('stock.partials.campo_consulta_deposito', [
                            'prefix' => 'entrada',
                            'label' => 'Depósito entrada',
                            'depositoId' => optional($depEntrada)->id ?? '',
                            'codigo' => optional($depEntrada)->codigo ?? '',
                            'descripcion' => optional($depEntrada)->nombre ?? '',
                        ])
                        <div class="form-group col-12 mb-2">
                            <label for="tipotransaccion_id">Tipo de transacción</label>
                            <select id="tipotransaccion_id" class="form-control" required>
                                <option value="">— Seleccionar —</option>
                                @foreach ($tipotransacciones as $t)
                                    <option value="{{ $t->id }}"
                                        @if ((int) ($defaults['tipotransaccion_id'] ?? 0) === (int) $t->id) selected @endif>
                                        {{ $t->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-12 mb-0">
                            <button type="button" id="tm_btn_cargar" class="btn btn-info btn-block">
                                <i class="fa fa-refresh"></i> Cargar stock (artículos con depósito de entrega = salida)
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tm-filtro" id="tm_panel_filtro" style="display: none;">
                    <input type="search" id="tm_filtro_desc" class="form-control"
                        placeholder="Buscar por descripción o SKU…" autocomplete="off">
                </div>

                <div id="tm_estado" class="text-muted small mb-2"></div>
                <div id="tm_lista" class="tm-lista"></div>
            </div>
        </div>
    </div>
</div>

<div class="tm-barra">
    <button type="button" id="tm_btn_transferir" class="btn btn-success btn-transferir" disabled>
        Transferir (0)
    </button>
</div>

@include('includes.stock.modalconsultadeposito')
@endsection
