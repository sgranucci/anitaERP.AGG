{{-- Grilla de bóveda: escritorio (panel=boveda) o menú externo. --}}
@php
    $enEscritorio = ! empty($enEscritorio);
    $lugares = $lugares ?? collect();
    $lugar = $lugar ?? null;
    $movimientos = $movimientos ?? collect();
    $saldo = $saldo ?? [];
    $totalesMonedas = $totalesMonedas ?? ['ARS' => 0.0, 'USD' => 0.0, 'EUR' => 0.0];
    $columnasVisibles = $columnasVisibles ?? [];
    $tieneCriterios = $tieneCriterios ?? false;
    $rutaListado = $rutaListado ?? 'tesoreria_crown_boveda';
    $baseParams = $baseParams ?? array_filter([
        'sala_id' => $sala->id ?? null,
        'jornada_id' => $jornada->id ?? null,
        'deposito_id' => $lugar->id ?? null,
        'id' => $enEscritorio ? ($jornada->id ?? null) : null,
        'panel' => $enEscritorio ? 'boveda' : null,
    ]);
    $urlLugar = function ($depositoId) use ($rutaListado, $baseParams, $enEscritorio, $jornada): string {
        $params = $baseParams;
        $params['deposito_id'] = $depositoId;
        if ($enEscritorio) {
            return route('ver_tesoreria_crown_jornada', $jornada->id).'?'.http_build_query(array_filter([
                'panel' => 'boveda',
                'deposito_id' => $depositoId,
            ]));
        }

        return route($rutaListado, $params);
    };
@endphp

@if (($lugares ?? collect())->isEmpty())
    <div class="alert alert-warning mb-0">
        Esta sala no tiene un depósito de bóveda, pre-depósito o caja chica.
        <a class="text-primary" href="{{ route('tesoreria_crown_deposito', ['sala_id' => $sala->id]) }}">Cargalo en Depósitos</a>
        con el tipo Bóveda.
    </div>
@else
    @if ($lugares->count() > 1)
        <div class="mb-3">
            @foreach ($lugares as $opcion)
                <a class="btn btn-sm {{ $lugar && (int) $lugar->id === (int) $opcion->id ? 'btn-info' : 'btn-outline-info' }} mr-1 mb-1"
                   href="{{ $urlLugar($opcion->id) }}">
                    {{ $opcion->tipoDeposito->nombre ?? '' }} {{ $opcion->codigo }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($enEscritorio && $lugar)
        <div class="d-flex flex-wrap align-items-center mb-3" style="gap:.4rem;">
            @if (can('listar-tesoreria-crown-boveda-arqueo', false))
                <a href="{{ route('tesoreria_crown_boveda_arqueo', ['sala_id' => $sala->id ?? null, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-sm tc-tool tc-tool-arqueo">
                    <i class="fa fa-balance-scale"></i> Arqueo de turno
                </a>
            @endif
            @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-boveda', false))
                <a href="{{ route('nueva_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-primary btn-sm tc-tool tc-tool-nuevo">
                    <i class="fa fa-plus-circle"></i> Nuevo movimiento
                </a>
            @endif
            <span class="text-muted small ml-auto">{{ $lugar->codigo }} — {{ $lugar->nombre }}</span>
        </div>
    @endif

    @if ($lugar)
        <div class="d-flex flex-wrap align-items-center mb-2">
            <button type="button" class="btn btn-sm lw-cortes-toggle collapsed mr-1 mb-1" data-toggle="collapse" data-target="#tc-herramienta-arqueo" aria-expanded="false" aria-controls="tc-herramienta-arqueo">
                <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                Arqueo
            </button>
        </div>
        <div class="collapse mb-3" id="tc-herramienta-arqueo" data-url="{{ route('composicion_billete_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id], false) }}">
            <p class="text-muted small mb-2">Saldo confirmado de {{ $lugar->nombre }} hasta esta jornada (trae el cierre de jornadas anteriores). Dólares y euros quedan en su moneda.</p>
            @include('caja.tesoreria_crown.boveda.partials.saldo_grupos', [
                'grupos' => $saldo,
                'vacio' => 'Todavía no hay cantidades confirmadas en este lugar.',
                'detalleBillete' => true,
            ])
        </div>
        <div class="lw-workbench mb-2" data-form="#form-filtros-tesoreria-crown-boveda">
            <form method="get"
                  action="{{ $enEscritorio ? route('ver_tesoreria_crown_jornada', $jornada->id) : route('tesoreria_crown_boveda') }}"
                  id="form-filtros-tesoreria-crown-boveda"
                  class="mb-0">
                @if ($enEscritorio)
                    <input type="hidden" name="panel" value="boveda">
                @else
                    <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                    <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                @endif
                <input type="hidden" name="deposito_id" value="{{ $lugar->id }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', [
                    'rutaListado' => $rutaListado,
                    'baseUrl' => $enEscritorio
                        ? route('ver_tesoreria_crown_jornada', $jornada->id).'?panel=boveda&deposito_id='.$lugar->id
                        : route('tesoreria_crown_boveda', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]),
                    'limpiarParams' => $enEscritorio
                        ? ['id' => $jornada->id, 'panel' => 'boveda', 'deposito_id' => $lugar->id, 'vista_estandar' => 1]
                        : ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id, 'vista_estandar' => 1],
                ])
                @include('caja.tesoreria_crown.partials.workbench_qbe')
            </form>
        </div>
        @php
            $puedeRevertirGrilla = $jornada->estaAbierta() && can('editar-tesoreria-crown-boveda', false);
            $puedeImprimirGrilla = can('imprimir-tesoreria-crown-boveda', false);
        @endphp
        <div class="tabla-ancha-grilla" data-tabla-ancha>
            <div class="tabla-ancha-scroll-top" hidden>
                <div class="tabla-ancha-scroll-top-inner"></div>
            </div>
            <div class="tabla-ancha-wrap table-responsive">
                <table class="tabla-ancha table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => $rutaListado])
                            @endforeach
                            <th class="text-left" style="width:120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $mov)
                            <tr class="{{ $mov->estado === 'N' ? 'text-muted' : '' }}">
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td>
                                        @include('caja.tesoreria_crown.boveda.partials.workbench_celda', ['key' => $keyColumna])
                                    </td>
                                @endforeach
                                <td class="text-left text-nowrap">
                                    <a href="{{ route('editar_tesoreria_crown_boveda', $mov->id) }}" class="btn-accion-tabla tooltipsC" title="{{ $mov->esBorrador() ? 'Editar' : 'Consultar' }}">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    @if ($puedeImprimirGrilla)
                                        <a href="{{ route('pdf_tesoreria_crown_boveda', $mov->id) }}" class="btn-accion-tabla tooltipsC" title="Imprimir" target="_blank" rel="noopener">
                                            <i class="fa fa-print"></i>
                                        </a>
                                    @endif
                                    @if ($puedeRevertirGrilla && $mov->puedeRevertir())
                                        <button type="button" class="btn-accion-tabla tooltipsC js-revertir-boveda" title="Revertir"
                                                data-url="{{ route('revertir_tesoreria_crown_boveda', $mov->id) }}"
                                                data-numero="{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}">
                                            <i class="fa fa-undo text-warning"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">
                                    {{ $tieneCriterios ? 'No hay movimientos para esta búsqueda.' : 'No hay movimientos en este lugar.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($movimientos->isNotEmpty())
                        <tfoot>
                            <tr style="background:#EAF2F8;color:#17202A;font-weight:bold;">
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td class="{{ $keyColumna === 'valor' ? 'text-right' : '' }}">
                                        @if ($keyColumna === 'estado')
                                            Totales
                                        @elseif ($keyColumna === 'valor')
                                            @foreach ($totalesMonedas as $codigo => $monto)
                                                @if (abs((float) $monto) >= 0.0001)
                                                    <div class="{{ (float) $monto < 0 ? 'text-danger' : '' }}">
                                                        {{ number_format((float) $monto, 2, ',', '.') }} {{ $codigo }}
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
        @if ($puedeRevertirGrilla)
            <div class="modal fade" id="modal-revertir-boveda" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" id="form-revertir-boveda" action="" autocomplete="off" data-enter-campos="1" data-enter-boton="1">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Revertir <span id="revertir-boveda-numero"></span></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">Se graba otro movimiento, con el número siguiente y las cantidades al revés. El original queda.</p>
                                <div class="form-group">
                                    <label class="control-label text-right pr-2 d-block" for="revertir-boveda-motivo">Motivo</label>
                                    <textarea name="motivo" id="revertir-boveda-motivo" class="form-control" rows="2" maxlength="500" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="control-label text-right pr-2 d-block" for="revertir-boveda-usuario">Usuario</label>
                                    <input type="text" name="firma_usuario" id="revertir-boveda-usuario" class="form-control" autocomplete="off" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="control-label text-right pr-2 d-block" for="revertir-boveda-clave">Clave</label>
                                    <input type="password" name="firma_password" id="revertir-boveda-clave" class="form-control" autocomplete="new-password" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-undo"></i> Revertir</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
        @include('includes.listado.workbench_columnas_dnd', [
            'rutaGuardarColumnasDnd' => 'guardar_columnas_tesoreria_crown_boveda',
        ])
        @include('caja.tesoreria_crown.partials.workbench_modales', [
            'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_boveda',
            'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_boveda',
            'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_boveda',
            'rutaPreview' => 'preview_tesoreria_crown_boveda',
            'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_boveda',
        ])
        <div class="modal fade" id="modal-arqueo-billete" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="arqueo-billete-titulo">Operaciones</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body p-0">
                        <p class="text-muted small px-3 pt-3 mb-2" id="arqueo-billete-hasta"></p>
                        <div class="table-responsive" style="max-height:65vh;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Número</th>
                                        <th>Tipo</th>
                                        <th>Efecto</th>
                                        <th class="text-right">Cantidad</th>
                                        <th class="text-right">Saldo</th>
                                        <th>Comentario</th>
                                    </tr>
                                </thead>
                                <tbody id="arqueo-billete-filas"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
