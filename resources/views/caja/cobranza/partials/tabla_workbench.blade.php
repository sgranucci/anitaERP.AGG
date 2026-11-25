{{--
    Tabla del workbench de cobranzas.
    Variables esperadas: $cobranza, $columnasVisibles, $grillaLayout, $etiquetasColumnas, $catalogoColumnas,
    $camposOrdenablesThead, $ordenActualThead, $filtrosQuery, $anchosEscalados (opcional).

    El confirm de borrado lo resuelve admin/index.js (#tabla-paginada .form-eliminar).
    El padre debe incluir al final de la página (después de la paginación):
        @include('includes.listado.workbench_columnas_dnd', [...]) y partials.workbench_modales.
--}}
@php
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;

    $columnasVisibles = $columnasVisibles ?? [];
    $catalogoColumnas = $catalogoColumnas ?? [];
    $etiquetasColumnas = $etiquetasColumnas ?? [];
    $grillaLayout = $grillaLayout ?? [];

    $layoutPorKey = [];
    foreach ($grillaLayout as $filaLayout) {
        $layoutPorKey[$filaLayout['key']] = $filaLayout;
    }
    $layoutPantalla = $anchosEscalados ?? LwGrilla::escalarAnchosParaPantalla($grillaLayout);
    $layoutPantallaPorKey = [];
    foreach ($layoutPantalla as $filaP) {
        $layoutPantallaPorKey[$filaP['key']] = $filaP;
    }
    $sumaAnchosPantalla = max(1, LwGrilla::sumaAnchosVisibles($layoutPantalla));
    $usaScrollHorizontal = LwGrilla::debeUsarScrollHorizontal($grillaLayout ?? []);
    $anchoAcciones = 100;
    $pctDatosDisponible = $usaScrollHorizontal
        ? 100.0
        : round(100 * $sumaAnchosPantalla / ($sumaAnchosPantalla + $anchoAcciones), 2);
@endphp
<div class="tabla-ancha-grilla cobranza-grilla-ancha" data-tabla-ancha>
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
        <table class="tabla-ancha table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    @foreach ($columnasVisibles as $keyColumna)
                        @php
                            $cfg = $layoutPorKey[$keyColumna] ?? null;
                            $cfgPantalla = $layoutPantallaPorKey[$keyColumna] ?? $cfg;
                            $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
                            $tituloCol = $etiquetasColumnas[$keyColumna] ?? ($catalogoColumnas[$keyColumna]['label'] ?? $keyColumna);
                            $minLegible = LwGrilla::anchoMinimoLegible(
                                $keyColumna,
                                $catalogoColumnas[$keyColumna] ?? [],
                                $tituloCol
                            );
                            if ($usaScrollHorizontal) {
                                $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
                            } else {
                                $pct = round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2);
                                $styleCol = 'width:'.$pct.'%;min-width:0;';
                            }
                        @endphp
                        @include('caja.cobranza.partials.workbench_th', [
                            'key' => $keyColumna,
                            'styleCol' => $styleCol,
                            'cfg' => $cfg,
                        ])
                    @endforeach
                    <th class="lw-col-acciones text-nowrap" data-orderable="false"
                        style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cobranza as $data)
                    <tr>
                        @foreach ($columnasVisibles as $keyColumna)
                            @include('caja.cobranza.partials.workbench_celda', [
                                'key' => $keyColumna,
                                'data' => $data,
                                'cfg' => $layoutPorKey[$keyColumna] ?? null,
                            ])
                        @endforeach
                        <td class="lw-col-acciones text-nowrap"
                            style="width:{{ $anchoAcciones }}px;min-width:{{ $anchoAcciones }}px;max-width:{{ $anchoAcciones }}px;white-space:nowrap;">
                            <div class="lw-acciones-inner">
                                @if (can('editar-cobranza', false))
                                    <a href="{{ route('editar_cobranza', ['id' => $data->id, 'origen' => 'cobranza']) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-cobranza', false))
                                    <form action="{{ route('eliminar_cobranza', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST" style="display:inline;">
                                        @csrf @method('delete')
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                                @if (can('listar-cobranza', false))
                                    <a href="{{ route('listar_una_cobranza', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Listar la Cobranza" target="_blank" rel="noopener">
                                        <i class="fa fa-print"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">
                            No hay cobranzas con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
