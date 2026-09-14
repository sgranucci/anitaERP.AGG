@php
    $alcance = $filtros['alcance_clientes'] ?? \App\Support\Ventas\ClienteCuentacorrienteReporteFiltros::ALCANCE_TODOS;
    $clientesIniciales = $clientes_iniciales ?? [];
    $idsIniciales = collect($clientesIniciales)->pluck('id')->filter()->implode(',');
    $codigoDesde = $filtros['cliente_codigo_desde'] ?? '';
    $codigoHasta = $filtros['cliente_codigo_hasta'] ?? '';
    $esTodos = $alcance === 'todos';
    $esPuntuales = $alcance === 'puntuales';
    $esRango = $alcance === 'rango';
@endphp

<div class="form-group row mb-3" id="bloque-alcance-clientes-cc">
    <label class="col-lg-2 control-label text-right pr-2">Clientes</label>
    <div class="col-lg-9">
        <div class="cc-reporte-alcance border rounded">
            <div class="cc-reporte-alcance-opciones">
                <label class="cc-reporte-alcance-card{{ $esTodos ? ' is-active' : '' }}" for="alcance_todos">
                    <input type="radio" name="alcance_clientes" id="alcance_todos" value="todos"
                        @checked($esTodos) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-users"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Todos</strong>
                        <small class="d-block text-muted">Clientes con deuda o movimientos según empresa y fechas</small>
                    </span>
                </label>

                <label class="cc-reporte-alcance-card{{ $esPuntuales ? ' is-active' : '' }}" for="alcance_puntuales">
                    <input type="radio" name="alcance_clientes" id="alcance_puntuales" value="puntuales"
                        @checked($esPuntuales) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-user-plus"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Elegir clientes</strong>
                        <small class="d-block text-muted">Uno o varios, buscándolos por código o con la lupa</small>
                    </span>
                </label>

                <label class="cc-reporte-alcance-card{{ $esRango ? ' is-active' : '' }}" for="alcance_rango">
                    <input type="radio" name="alcance_clientes" id="alcance_rango" value="rango"
                        @checked($esRango) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-exchange-alt"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Rango de códigos</strong>
                        <small class="d-block text-muted">Desde un código hasta otro (inclusive)</small>
                    </span>
                </label>
            </div>

            <div class="cc-reporte-alcance-panel border-top bg-light px-3 py-3" id="panel-alcance-puntuales"
                @if (! $esPuntuales) style="display: none;" @endif>
                <input type="hidden" name="cliente_ids" id="cliente_ids" value="{{ $idsIniciales }}">
                <p class="small text-muted mb-2 mb-md-3">
                    Ingrese el código y pulse <kbd>Enter</kbd> o <strong>Agregar</strong>.
                    También puede abrir la lupa (<kbd>F1</kbd>) para buscar por nombre.
                </p>
                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm consultacliente-cc-reporte"
                        title="Buscar cliente (F1)" data-destino="seleccion">
                        <i class="fa fa-search"></i>
                    </button>
                    <input type="text"
                        class="form-control form-control-sm codigocliente-cc-reporte"
                        id="codigocliente_cc_reporte"
                        placeholder="Código"
                        autocomplete="off"
                        style="max-width: 110px;">
                    <input type="text"
                        class="form-control form-control-sm nombrecliente-cc-reporte flex-grow-1"
                        id="nombrecliente_cc_reporte"
                        placeholder="Nombre del cliente"
                        readonly>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-cliente-cc-reporte">
                        <i class="fa fa-plus"></i> Agregar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 bg-white" id="tabla-clientes-cc-reporte">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width: 100px;">Código</th>
                                <th>Cliente</th>
                                <th style="width: 70px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-clientes-cc-reporte">
                            @foreach ($clientesIniciales as $cli)
                                <tr data-id="{{ $cli['id'] ?? '' }}">
                                    <td>{{ $cli['codigo'] ?? '' }}</td>
                                    <td>{{ $cli['nombre'] ?? '' }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-cliente-cc-reporte" title="Quitar">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2" id="aviso-clientes-cc-reporte">
                    @if ($clientesIniciales === [])
                        Todavía no agregó clientes. Elija al menos uno para consultar.
                    @else
                        {{ count($clientesIniciales) }} cliente(s) en la lista.
                    @endif
                </p>
            </div>

            <div class="cc-reporte-alcance-panel border-top bg-light px-3 py-3" id="panel-alcance-rango"
                @if (! $esRango) style="display: none;" @endif>
                <p class="small text-muted mb-2 mb-md-3">
                    Defina el primer y el último código. Se incluyen todos los clientes cuyos códigos
                    numéricos quedan entre ambos (no hace falta armar una lista).
                </p>
                <div class="form-row">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <label for="cliente_codigo_desde" class="small text-muted mb-1 d-block">Desde código</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm consultacliente-cc-reporte"
                                title="Elegir código inicial" data-destino="rango_desde">
                                <i class="fa fa-search"></i>
                            </button>
                            <input type="text"
                                name="cliente_codigo_desde"
                                id="cliente_codigo_desde"
                                class="form-control form-control-sm"
                                value="{{ $codigoDesde }}"
                                placeholder="Ej. 100"
                                autocomplete="off"
                                style="max-width: 110px;">
                            <input type="text"
                                class="form-control form-control-sm flex-grow-1"
                                id="nombrecliente_rango_desde"
                                placeholder="Nombre"
                                readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="cliente_codigo_hasta" class="small text-muted mb-1 d-block">Hasta código</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm consultacliente-cc-reporte"
                                title="Elegir código final" data-destino="rango_hasta">
                                <i class="fa fa-search"></i>
                            </button>
                            <input type="text"
                                name="cliente_codigo_hasta"
                                id="cliente_codigo_hasta"
                                class="form-control form-control-sm"
                                value="{{ $codigoHasta }}"
                                placeholder="Ej. 250"
                                autocomplete="off"
                                style="max-width: 110px;">
                            <input type="text"
                                class="form-control form-control-sm flex-grow-1"
                                id="nombrecliente_rango_hasta"
                                placeholder="Nombre"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cc-reporte-alcance-panel border-top px-3 py-2" id="panel-alcance-todos"
                @if (! $esTodos) style="display: none;" @endif>
                <p class="small text-muted mb-0">
                    No hace falta elegir clientes: el reporte arma la lista sola según empresa, fechas y tipo de consulta.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .cc-reporte-alcance { overflow: hidden; background: #fff; }
    .cc-reporte-alcance-opciones {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
    }
    .cc-reporte-alcance-card {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0;
        padding: 12px 14px;
        cursor: pointer;
        border-right: 1px solid #dee2e6;
        background: #fff;
        transition: background .15s ease, box-shadow .15s ease;
    }
    .cc-reporte-alcance-card:last-child { border-right: 0; }
    .cc-reporte-alcance-card:hover { background: #f8fbff; }
    .cc-reporte-alcance-card.is-active {
        background: #eaf4fb;
        box-shadow: inset 0 -3px 0 #3498db;
    }
    .cc-reporte-alcance-radio { margin-top: 4px; flex-shrink: 0; }
    .cc-reporte-alcance-icono {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #eef2f7;
        color: #2c3e50;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .cc-reporte-alcance-card.is-active .cc-reporte-alcance-icono {
        background: #3498db;
        color: #fff;
    }
    .cc-reporte-alcance-texto strong { display: block; color: #1b4f72; font-size: 0.95rem; }
    .cc-reporte-alcance-texto small { line-height: 1.25; }
    @media (max-width: 991.98px) {
        .cc-reporte-alcance-opciones { grid-template-columns: 1fr; }
        .cc-reporte-alcance-card { border-right: 0; border-bottom: 1px solid #dee2e6; }
        .cc-reporte-alcance-card:last-child { border-bottom: 0; }
    }
</style>
