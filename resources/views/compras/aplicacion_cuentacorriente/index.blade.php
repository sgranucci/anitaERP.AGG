@extends("theme.$theme.layout")

@section('titulo')
    Aplicar cuenta corriente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras-aplicacion-cc.css') }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script>
    window.APLICACION_CC_INICIAL = @json($aplicacionCcInicial);
</script>
<script src="{{ asset('assets/pages/scripts/compras/aplicacion_cuentacorriente/workbench.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $k = $kpis;
    $nombreProveedor = $proveedor->nombre ?? '';
    $codigoProveedor = $proveedor->codigo ?? '';
@endphp
<div id="acc-workbench"
     class="acc-page"
     data-url-pendientes="{{ route('api_pendientes_aplicacion_cuentacorriente_proveedor') }}"
     data-url-sugerir="{{ route('api_sugerir_aplicacion_cuentacorriente_proveedor') }}"
     data-url-aplicar="{{ route('aplicar_cuentacorriente_proveedor') }}"
     data-url-desaplicar="{{ url('compras/aplicacion-cuentacorriente/__ID__/desaplicar') }}"
     data-url-cc="{{ url('compras/listarcuentacorrienteproveedor/__ID__') }}">

    <div class="acc-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h1>Aplicar comprobantes de cuenta corriente</h1>
                <p class="acc-sub">El matching FIFO se arma solo. Cambiá montos, destildá o fijá un crédito: lo que edites queda y el resto se vuelve a sugerir.</p>
            </div>
            <div class="acc-hero-tools">
                <a href="{{ route('tesoreria_cockpit') }}" class="btn btn-sm btn-outline-light">Tesorería</a>
                <a href="#" id="acc-link-cc" class="btn btn-sm btn-outline-light {{ $proveedor_id ? '' : 'd-none' }}">Cuenta corriente</a>
            </div>
        </div>
    </div>

    @include('includes.form-error')
    @include('includes.mensaje')

    <div class="acc-toolbar">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1">Empresa</label>
                <select name="empresa_id" id="empresa_id" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach ($empresa_query as $e)
                        <option value="{{ $e->id }}" @selected((int) $empresa_id === (int) $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1">Proveedor</label>
                <div class="input-group input-group-sm">
                    <input type="hidden" name="proveedor_id" id="proveedor_id" class="proveedor_id" value="{{ $proveedor_id ?: '' }}">
                    <input type="text" class="form-control codigoproveedor" id="codigoproveedor" placeholder="Código" value="{{ $codigoProveedor }}">
                    <input type="text" class="form-control nombreproveedor" id="nombreproveedor" readonly placeholder="Nombre" value="{{ $nombreProveedor }}">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info consultaproveedor" title="Consultar"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1">Fecha aplicación</label>
                <input type="date" id="acc-fecha" class="form-control form-control-sm" value="{{ $fecha }}">
            </div>
            <div class="form-group col-md-4 mb-2 acc-toolbar-actions">
                <label class="acc-switch mb-0 mr-2" title="Arma FIFO con el saldo que no fijaste">
                    <input type="checkbox" id="acc-auto" checked>
                    <span>Sugerir al instante</span>
                </label>
                <button type="button" id="btn-acc-fifo" class="btn btn-sm btn-outline-primary">Rehacer FIFO</button>
                <button type="button" id="btn-acc-parear" class="btn btn-sm btn-outline-primary">Parear iguales</button>
                <button type="button" id="btn-acc-limpiar" class="btn btn-sm btn-outline-secondary">Limpiar</button>
            </div>
        </div>
    </div>

    <div class="acc-kpis">
        <div class="acc-kpi is-credito">
            <span class="acc-kpi-label">Créditos</span>
            <span class="acc-kpi-value" id="acc-kpi-creditos">{{ number_format((float) $k['creditos'], 2, ',', '.') }}</span>
            <div class="acc-kpi-hint" id="acc-kpi-creditos-hint">Disponible</div>
        </div>
        <div class="acc-kpi is-deuda">
            <span class="acc-kpi-label">Deuda</span>
            <span class="acc-kpi-value" id="acc-kpi-deudas">{{ number_format((float) $k['deudas'], 2, ',', '.') }}</span>
            <div class="acc-kpi-hint" id="acc-kpi-deudas-hint">Facturas abiertas</div>
        </div>
        <div class="acc-kpi is-ok">
            <span class="acc-kpi-label">Matching ahora</span>
            <span class="acc-kpi-value" id="acc-kpi-match">0,00</span>
            <div class="acc-kpi-hint" id="acc-kpi-match-hint">Sugerido + fijado</div>
        </div>
        <div class="acc-kpi is-warn">
            <span class="acc-kpi-label">Sin asignar</span>
            <span class="acc-kpi-value" id="acc-kpi-libre">{{ number_format((float) $k['creditos'], 2, ',', '.') }}</span>
            <div class="acc-kpi-hint" id="acc-kpi-libre-hint">Crédito que aún no pega</div>
        </div>
    </div>

    <div class="acc-panes">
        <section class="acc-pane">
            <div class="acc-pane-head">
                <h2>Haber · NC y pagos a cuenta</h2>
                <span class="acc-count"><span id="acc-count-creditos">{{ count($creditos) }}</span></span>
            </div>
            <div class="acc-pane-tools">
                <input type="search" id="acc-buscar-credito" class="form-control form-control-sm" placeholder="Buscar crédito…">
            </div>
            <div class="acc-pane-body" id="acc-creditos-body"></div>
        </section>
        <section class="acc-pane">
            <div class="acc-pane-head">
                <h2>Debe · facturas adeudadas</h2>
                <span class="acc-count"><span id="acc-count-deudas">{{ count($deudas) }}</span></span>
            </div>
            <div class="acc-pane-tools acc-pane-tools-deuda">
                <input type="search" id="acc-buscar-deuda" class="form-control form-control-sm" placeholder="Buscar factura…">
                <select id="acc-filtro-deuda" class="form-control form-control-sm">
                    <option value="todas">Todas</option>
                    <option value="compatibles">Compatibles con el crédito</option>
                    <option value="sugeridas">Con matching</option>
                    <option value="vencidas">Vencidas</option>
                    <option value="excluidas">Excluidas</option>
                </select>
            </div>
            <div class="acc-pane-body" id="acc-deudas-body"></div>
        </section>
    </div>

    <div class="acc-board">
        <div class="acc-board-head">
            <h3>Matching en curso</h3>
            <span class="acc-count" id="acc-board-resumen">Sin líneas</span>
        </div>
        <div class="acc-board-legend">
            <span class="acc-badge auto">Sugerida</span> se recálcula sola.
            <span class="acc-badge manual">Fijada</span> la cambiaste vos y no se toca.
            Destildá una factura para sacarla del matching.
            Si crédito y deuda tienen distinta cotización se muestra la diferencia de cambio y se asienta al confirmar.
        </div>
        <div id="acc-board-body" class="acc-board-body"></div>
    </div>

    <div class="acc-recientes">
        <h3>Ya aplicadas (se pueden deshacer)</h3>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Crédito</th>
                        <th>Deuda</th>
                        <th class="text-right">Monto</th>
                        <th class="text-right">DC</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="acc-recientes-body">
                    <tr><td colspan="6" class="text-muted text-center">Seleccione un proveedor</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="acc-dock">
        <div class="acc-dock-stat">Se va a aplicar<strong id="acc-dock-aplicar">0,00</strong></div>
        <div class="acc-dock-stat">Libre del crédito<strong id="acc-dock-resto">—</strong></div>
        <div class="acc-dock-stat">Dif. de cambio<strong id="acc-dock-dc">—</strong></div>
        <div class="acc-dock-stat">Pares<strong id="acc-dock-lineas">0</strong></div>
        <div class="acc-bar" id="acc-dock-bar"><span></span></div>
        <span class="acc-toast-error" id="acc-dock-error"></span>
        @if (can('aplicar-cuentacorriente-proveedor', false))
            <button type="button" id="btn-acc-aplicar" class="btn btn-success btn-aplicar" disabled>Confirmar matching</button>
        @endif
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
