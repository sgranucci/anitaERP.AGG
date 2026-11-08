@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
    $pct = fn ($valor) => $valor === null ? '' : number_format((float) $valor, 2, ',', '.').' %';
    $gruposSelector = \App\Support\Caja\TesoreriaCrownAnexo2Calculo::gruposPermitidos();
    $grupoActivo = $anexo2['grupo'] ?? array_key_first($gruposSelector);
    $anexosBaseUrl = $anexosBaseUrl ?? route('ver_tesoreria_crown_jornada', $jornada->id);
    $anexosBaseQuery = $anexosBaseQuery ?? [];
    $urlGrupo = function (string $grupo) use ($anexosBaseUrl, $anexosBaseQuery) {
        return $anexosBaseUrl.'?'.http_build_query(array_merge($anexosBaseQuery, [
            'panel' => 'anexo2',
            'grupo_anexo2' => $grupo,
        ]));
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2 d-flex flex-wrap align-items-center">
        <h3 class="card-title mb-0 mr-auto"><i class="fa fa-table"></i> Anexo II — Resultado</h3>
        @if ($gruposSelector !== [])
            <div class="btn-group btn-group-sm" role="group" aria-label="Grupo del anexo II">
                @foreach ($gruposSelector as $clave => $etiqueta)
                    <a href="{{ $urlGrupo($clave) }}"
                       class="btn {{ $grupoActivo === $clave ? 'btn-info' : 'btn-outline-info' }}"
                       title="Como l-resmesa.c — {{ $etiqueta }}">{{ $etiqueta }}</a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-resmesa.c</code>: mesas, máquinas (slots) y gastronomía/hotel por separado o todo junto.
        </p>
        @if ($anexo2 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo II. {{ $anexo2Error }}</div>
        @else
            @foreach ($anexo2['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach

            @forelse ($anexo2['secciones'] as $seccion)
                <h5 class="mt-3 mb-2">{{ $seccion['titulo'] }}</h5>

                @if (($seccion['tipo'] ?? '') === 'mesas')
                    <p class="small text-muted mb-1">
                        Tabla ancha: usá la <strong>barra de scroll de arriba</strong>, o Shift + rueda del mouse / deslizá el trackpad.
                    </p>
                    <div class="tc-orden-wrap">
                        @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                        <div class="tabla-ancha-grilla tabla-ancha--doble-cabecera mb-3" data-tabla-ancha
                             style="--tabla-ancha-c1: 10rem; --tabla-ancha-c2: 3.5rem;">
                            <div class="tabla-ancha-scroll-top" hidden>
                                <div class="tabla-ancha-scroll-top-inner"></div>
                            </div>
                            <div class="tabla-ancha-wrap">
                            <table class="table table-sm table-bordered mb-0 tabla-ancha tc-tabla-ordenable">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th rowspan="2" class="col-fija-1">Juego</th>
                                        <th rowspan="2" class="col-fija-2">Mesa</th>
                                        <th colspan="11" class="text-center">Diario</th>
                                        <th colspan="8" class="text-center">Acumulado</th>
                                    </tr>
                                    <tr>
                                        <th class="text-right">Banca tipo</th>
                                        <th class="text-right">Créditos</th>
                                        <th class="text-right">Reposiciones</th>
                                        <th class="text-right">Estado final</th>
                                        <th class="text-right">Drop</th>
                                        <th class="text-right">Pagos man.</th>
                                        <th class="text-right">Recomp. banca</th>
                                        <th class="text-right">Caja empl.</th>
                                        <th class="text-right">Resultado</th>
                                        <th class="text-right">% Mesa</th>
                                        <th class="text-right">% Prop.</th>
                                        <th class="text-right">Estado final</th>
                                        <th class="text-right">Drop</th>
                                        <th class="text-right">Pagos man.</th>
                                        <th class="text-right">Recomp. banca</th>
                                        <th class="text-right">Caja empl.</th>
                                        <th class="text-right">Resultado</th>
                                        <th class="text-right">% Mesa</th>
                                        <th class="text-right">% Prop.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($seccion['filas'] as $fila)
                                        @php
                                            $filaFijaMesa = ! empty($fila['es_subtotal']) || ! empty($fila['es_total']);
                                        @endphp
                                        @if ($filaFijaMesa)
                                            <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                        @else
                                            <tr>
                                        @endif
                                            <td class="col-fija-1">{{ $fila['juego'] }}</td>
                                            <td class="col-fija-2">{{ $fila['mesa'] }}</td>
                                            <td class="text-right">{{ $pesos($fila['banca_tipo'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['creditos']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['reposiciones']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['estado_final']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['drop']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['pagos_manuales']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['recomp_banca']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['caja_empleado']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['resultado']) }}</td>
                                            <td class="text-right">{{ $pct($fila['pct_mesa']) }}</td>
                                            <td class="text-right">{{ $pct($fila['pct_prop']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_estado_final'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_drop'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_pagos_manuales'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_recomp_banca'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_caja_empleado'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_resultado'] ?? null) }}</td>
                                            <td class="text-right">{{ $pct($fila['acum_pct_mesa'] ?? null) }}</td>
                                            <td class="text-right">{{ $pct($fila['acum_pct_prop'] ?? null) }}</td>
                                        </tr>
                                    @empty
                                        <tr data-tc-fija="1"><td colspan="21" class="text-center text-muted">Sin mesas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                    @if (!empty($seccion['resumen']))
                        <div class="table-responsive mb-3" style="max-width:42rem;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Totales mesas</th>
                                        <th class="text-right">Diario</th>
                                        <th class="text-right">Acumulado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($seccion['resumen'] as $fila)
                                        <tr @if (in_array($fila['concepto'], ['Total ventas', 'Total pagos', 'Total propinas'], true)) style="font-weight:bold;" @endif>
                                            <td>{{ $fila['concepto'] }}</td>
                                            <td class="text-right">
                                                @if (!empty($fila['es_pct']))
                                                    {{ $pct($fila['diario']) }}
                                                @else
                                                    {{ $pesos($fila['diario']) }}
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if (!empty($fila['es_pct']))
                                                    {{ $pct($fila['acumulado']) }}
                                                @else
                                                    {{ $pesos($fila['acumulado']) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                @elseif (($seccion['tipo'] ?? '') === 'maquinas')
                    <div class="tc-orden-wrap">
                        @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0 tc-tabla-ordenable" style="max-width:28rem;">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-right">Diario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($seccion['filas'] as $fila)
                                        @if (($fila['concepto'] ?? '') === 'Resultado')
                                            <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                        @else
                                            <tr>
                                        @endif
                                            <td>{{ $fila['concepto'] }}</td>
                                            <td class="text-right">{{ $pesos($fila['diario']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                @elseif (($seccion['tipo'] ?? '') === 'bingo')
                    <div class="tc-orden-wrap">
                        @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0 tc-tabla-ordenable">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th rowspan="2">Concepto</th>
                                        <th colspan="4" class="text-center">Diario</th>
                                        <th colspan="4" class="text-center">Acumulado</th>
                                    </tr>
                                    <tr>
                                        <th class="text-right">Cartones</th>
                                        <th class="text-right">Premios</th>
                                        <th class="text-right">RSE</th>
                                        <th class="text-right">Resultado</th>
                                        <th class="text-right">Cartones</th>
                                        <th class="text-right">Premios</th>
                                        <th class="text-right">RSE</th>
                                        <th class="text-right">Resultado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($seccion['filas'] as $fila)
                                        <tr>
                                            <td>{{ $fila['concepto'] }}</td>
                                            <td class="text-right">{{ $pesos($fila['carton'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['premio'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['rse'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['resultado'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_carton'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_premio'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_rse'] ?? null) }}</td>
                                            <td class="text-right">{{ $pesos($fila['acum_resultado'] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                @elseif (($seccion['tipo'] ?? '') === 'gastronomia')
                    @php
                        $pesosGastro = function ($importe) use ($pesos) {
                            if ($importe === null || $importe === '' || abs((float) $importe) < 0.00001) {
                                return '';
                            }

                            return $pesos($importe);
                        };
                    @endphp
                    <p class="small text-muted mb-1">
                        Como <code>l-resmesa.c</code>: sucursales (hotel, rendiciones de cajeros, facturas electrónicas).
                        Tabla ancha: usá la barra de scroll de arriba.
                    </p>
                    <div class="tc-orden-wrap">
                        @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                        <div class="tabla-ancha-grilla tabla-ancha--doble-cabecera mb-3" data-tabla-ancha
                             style="--tabla-ancha-c1: 16rem;">
                            <div class="tabla-ancha-scroll-top" hidden>
                                <div class="tabla-ancha-scroll-top-inner"></div>
                            </div>
                            <div class="tabla-ancha-wrap">
                            <table class="table table-sm table-bordered mb-0 tabla-ancha tc-tabla-ordenable">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th rowspan="2" class="col-fija-1">Sucursal</th>
                                        <th colspan="5" class="text-center">Diario</th>
                                        <th colspan="5" class="text-center">Acumulado</th>
                                    </tr>
                                    <tr>
                                        <th class="text-right">Ventas Z</th>
                                        <th class="text-right">Rendiciones</th>
                                        <th class="text-right">Anulados</th>
                                        <th class="text-right">Invitaciones</th>
                                        <th class="text-right">Tarjetas</th>
                                        <th class="text-right">Ventas Z</th>
                                        <th class="text-right">Rendiciones</th>
                                        <th class="text-right">Anulados</th>
                                        <th class="text-right">Invitaciones</th>
                                        <th class="text-right">Tarjetas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($seccion['filas'] as $fila)
                                        @if (! empty($fila['es_total']))
                                            <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                        @else
                                            <tr>
                                        @endif
                                            <td class="col-fija-1">{{ $fila['etiqueta'] }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['ventas_z']) : $pesosGastro($fila['ventas_z']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['rendiciones']) : $pesosGastro($fila['rendiciones']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['anulados']) : $pesosGastro($fila['anulados']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['invitaciones']) : $pesosGastro($fila['invitaciones']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['tarjetas']) : $pesosGastro($fila['tarjetas']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['acum_ventas_z']) : $pesosGastro($fila['acum_ventas_z']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['acum_rendiciones']) : $pesosGastro($fila['acum_rendiciones']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['acum_anulados']) : $pesosGastro($fila['acum_anulados']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['acum_invitaciones']) : $pesosGastro($fila['acum_invitaciones']) }}</td>
                                            <td class="text-right">{{ !empty($fila['es_total']) ? $pesos($fila['acum_tarjetas']) : $pesosGastro($fila['acum_tarjetas']) }}</td>
                                        </tr>
                                    @empty
                                        <tr data-tc-fija="1"><td colspan="11" class="text-center text-muted">Sin gastronomía/hotel.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-muted mb-0">Sin secciones para este grupo.</p>
            @endforelse
        @endif
    </div>
</div>
