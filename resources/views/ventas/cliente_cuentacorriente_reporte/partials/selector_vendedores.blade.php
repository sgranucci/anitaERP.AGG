@php
    $alcance = $filtros['alcance_vendedores'] ?? \App\Support\Ventas\ClienteCuentacorrienteReporteFiltros::ALCANCE_TODOS;
    $vendedoresIniciales = $vendedores_iniciales ?? [];
    $idsIniciales = collect($vendedoresIniciales)->pluck('id')->filter()->implode(',');
    $codigoDesde = $filtros['vendedor_codigo_desde'] ?? '';
    $codigoHasta = $filtros['vendedor_codigo_hasta'] ?? '';
    $esTodos = $alcance === 'todos';
    $esPuntuales = $alcance === 'puntuales';
    $esRango = $alcance === 'rango';
@endphp

<div class="form-group row mb-3" id="bloque-alcance-vendedores-cc">
    <label class="col-lg-2 control-label text-right pr-2">Vendedores</label>
    <div class="col-lg-9">
        <div class="cc-reporte-alcance border rounded">
            <div class="cc-reporte-alcance-opciones">
                <label class="cc-reporte-alcance-card{{ $esTodos ? ' is-active' : '' }}" for="alcance_vendedor_todos">
                    <input type="radio" name="alcance_vendedores" id="alcance_vendedor_todos" value="todos"
                        @checked($esTodos) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-briefcase"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Todos</strong>
                        <small class="d-block text-muted">Sin filtro por vendedor del cliente</small>
                    </span>
                </label>

                <label class="cc-reporte-alcance-card{{ $esPuntuales ? ' is-active' : '' }}" for="alcance_vendedor_puntuales">
                    <input type="radio" name="alcance_vendedores" id="alcance_vendedor_puntuales" value="puntuales"
                        @checked($esPuntuales) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-id-badge"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Elegir vendedores</strong>
                        <small class="d-block text-muted">Uno o varios, por código o con la lupa</small>
                    </span>
                </label>

                <label class="cc-reporte-alcance-card{{ $esRango ? ' is-active' : '' }}" for="alcance_vendedor_rango">
                    <input type="radio" name="alcance_vendedores" id="alcance_vendedor_rango" value="rango"
                        @checked($esRango) class="cc-reporte-alcance-radio">
                    <span class="cc-reporte-alcance-icono"><i class="fa fa-exchange-alt"></i></span>
                    <span class="cc-reporte-alcance-texto">
                        <strong>Rango de códigos</strong>
                        <small class="d-block text-muted">Desde un código hasta otro (inclusive)</small>
                    </span>
                </label>
            </div>

            <div class="cc-reporte-alcance-panel border-top bg-light px-3 py-3" id="panel-alcance-vendedores-puntuales"
                @if (! $esPuntuales) style="display: none;" @endif>
                <input type="hidden" name="vendedor_ids" id="vendedor_ids" value="{{ $idsIniciales }}">
                <p class="small text-muted mb-2 mb-md-3">
                    Ingrese el código y pulse <kbd>Enter</kbd> o <strong>Agregar</strong>.
                    También puede abrir la lupa (<kbd>F1</kbd>) para buscar por nombre.
                </p>
                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm consultavendedor-cc-reporte"
                        title="Buscar vendedor (F1)" data-destino="seleccion">
                        <i class="fa fa-search"></i>
                    </button>
                    <input type="text"
                        class="form-control form-control-sm codigovendedor-cc-reporte"
                        id="codigovendedor_cc_reporte"
                        placeholder="Código"
                        autocomplete="off"
                        style="max-width: 110px;">
                    <input type="text"
                        class="form-control form-control-sm nombrevendedor-cc-reporte flex-grow-1"
                        id="nombrevendedor_cc_reporte"
                        placeholder="Nombre del vendedor"
                        readonly>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-vendedor-cc-reporte">
                        <i class="fa fa-plus"></i> Agregar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 bg-white" id="tabla-vendedores-cc-reporte">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width: 100px;">Código</th>
                                <th>Vendedor</th>
                                <th style="width: 70px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-vendedores-cc-reporte">
                            @foreach ($vendedoresIniciales as $vend)
                                <tr data-id="{{ $vend['id'] ?? '' }}">
                                    <td>{{ $vend['codigo'] ?? '' }}</td>
                                    <td>{{ $vend['nombre'] ?? '' }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-vendedor-cc-reporte" title="Quitar">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2" id="aviso-vendedores-cc-reporte">
                    @if ($vendedoresIniciales === [])
                        Todavía no agregó vendedores. Elija al menos uno para consultar.
                    @else
                        {{ count($vendedoresIniciales) }} vendedor(es) en la lista.
                    @endif
                </p>
            </div>

            <div class="cc-reporte-alcance-panel border-top bg-light px-3 py-3" id="panel-alcance-vendedores-rango"
                @if (! $esRango) style="display: none;" @endif>
                <p class="small text-muted mb-2 mb-md-3">
                    Defina el primer y el último código. Se incluyen los clientes cuyo vendedor
                    queda entre ambos códigos (no hace falta armar una lista).
                </p>
                <div class="form-row">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <label for="vendedor_codigo_desde" class="small text-muted mb-1 d-block">Desde código</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm consultavendedor-cc-reporte"
                                title="Elegir código inicial" data-destino="rango_desde">
                                <i class="fa fa-search"></i>
                            </button>
                            <input type="text"
                                name="vendedor_codigo_desde"
                                id="vendedor_codigo_desde"
                                class="form-control form-control-sm"
                                value="{{ $codigoDesde }}"
                                placeholder="Ej. 1"
                                autocomplete="off"
                                style="max-width: 110px;">
                            <input type="text"
                                class="form-control form-control-sm flex-grow-1"
                                id="nombrevendedor_rango_desde"
                                placeholder="Nombre"
                                readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="vendedor_codigo_hasta" class="small text-muted mb-1 d-block">Hasta código</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm consultavendedor-cc-reporte"
                                title="Elegir código final" data-destino="rango_hasta">
                                <i class="fa fa-search"></i>
                            </button>
                            <input type="text"
                                name="vendedor_codigo_hasta"
                                id="vendedor_codigo_hasta"
                                class="form-control form-control-sm"
                                value="{{ $codigoHasta }}"
                                placeholder="Ej. 50"
                                autocomplete="off"
                                style="max-width: 110px;">
                            <input type="text"
                                class="form-control form-control-sm flex-grow-1"
                                id="nombrevendedor_rango_hasta"
                                placeholder="Nombre"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cc-reporte-alcance-panel border-top px-3 py-2" id="panel-alcance-vendedores-todos"
                @if (! $esTodos) style="display: none;" @endif>
                <p class="small text-muted mb-0">
                    No hace falta elegir vendedor: se consideran todos los clientes según el resto de filtros.
                </p>
            </div>
        </div>
    </div>
</div>
