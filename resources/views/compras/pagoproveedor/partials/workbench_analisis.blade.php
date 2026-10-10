@php
    use App\Support\Compras\PagoproveedorListadoAnalisisSupport;
    use App\Support\Compras\PagoproveedorListadoFiltros;

    $grafico = $filtros['grafico'] ?? PagoproveedorListadoAnalisisSupport::graficoVacio();
    $graficosPanel = array_values($filtros['graficos'] ?? []);
    if ($graficosPanel === [] && ($grafico['tipo'] ?? '') !== '') {
        $graficosPanel = [$grafico];
    }
    $medidasGrafico = \App\Support\Listado\ListadoMedidaSupport::catalogo(PagoproveedorListadoFiltros::camposOrdenables());
    $formato = $filtros['formato'] ?? [];
    $calculadas = $filtros['calculadas'] ?? [];
    $ejes = PagoproveedorListadoFiltros::camposOrdenables();
    unset($ejes['monto']);
    $calcFila = $calculadas[0] ?? ['etiqueta' => '', 'formula' => '', 'valida' => true];
    $calcFila2 = $calculadas[1] ?? ['etiqueta' => '', 'formula' => '', 'valida' => true];
@endphp
<div class="px-3 pb-2 collapse" id="lw-analisis-panel">
    <div class="border rounded p-2" style="background:#f8fbfd;">
        <div class="d-flex flex-wrap align-items-end" style="gap:.5rem;">
            <div>
                <label class="small mb-0 d-block">Gráficos (hasta tres, el mismo filtro)</label>
                @for ($iGrafico = 0; $iGrafico < 3; $iGrafico++)
                    @php $graficoFila = $graficosPanel[$iGrafico] ?? ['tipo' => '', 'dimension' => '', 'medida' => 'conteo']; @endphp
                    <div class="d-flex mb-1" style="gap:.25rem;">
                        <select name="graficos[{{ $iGrafico }}][tipo]" class="form-control form-control-sm">
                            <option value="">Sin gráfico</option>
                            <option value="barras" @selected(($graficoFila['tipo'] ?? '') === 'barras')>Barras</option>
                            <option value="linea" @selected(($graficoFila['tipo'] ?? '') === 'linea')>Línea</option>
                            <option value="torta" @selected(($graficoFila['tipo'] ?? '') === 'torta')>Torta</option>
                        </select>
                        <select name="graficos[{{ $iGrafico }}][dimension]" class="form-control form-control-sm">
                            @foreach ($ejes as $key => $meta)
                                <option value="{{ $key }}" @selected(($graficoFila['dimension'] ?? '') === $key)>{{ $meta['label'] ?? $key }}</option>
                            @endforeach
                        </select>
                        <select name="graficos[{{ $iGrafico }}][medida]" class="form-control form-control-sm">
                            @foreach ($medidasGrafico as $keyMedida => $metaMedida)
                                <option value="{{ $keyMedida }}" @selected(($graficoFila['medida'] ?? 'conteo') === $keyMedida)>{{ $metaMedida['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endfor
            </div>
            <div>
                <label class="small mb-0 d-block">Pintar fila si</label>
                @for ($iFormato = 0; $iFormato < 4; $iFormato++)
                    @php $formatoFila = $formato[$iFormato] ?? ['campo' => 'estado', 'op' => 'igual', 'valor' => '', 'tono' => 'warning']; @endphp
                    <div class="d-flex mb-1" style="gap:.25rem;">
                        <select name="formato[{{ $iFormato }}][campo]" class="form-control form-control-sm">
                            @foreach ($ejes as $key => $meta)
                                <option value="{{ $key }}" @selected(($formatoFila['campo'] ?? '') === $key)>{{ $meta['label'] ?? $key }}</option>
                            @endforeach
                        </select>
                        <select name="formato[{{ $iFormato }}][op]" class="form-control form-control-sm">
                            <option value="igual" @selected(($formatoFila['op'] ?? '') === 'igual')>es</option>
                            <option value="contiene" @selected(($formatoFila['op'] ?? '') === 'contiene')>contiene</option>
                        </select>
                        <input type="text" name="formato[{{ $iFormato }}][valor]" class="form-control form-control-sm" maxlength="80"
                               value="{{ $formatoFila['valor'] ?? '' }}" placeholder="{{ $iFormato === 0 ? 'CONFIRMADA' : '' }}">
                        <select name="formato[{{ $iFormato }}][tono]" class="form-control form-control-sm">
                            <option value="warning" @selected(($formatoFila['tono'] ?? '') === 'warning')>Ámbar</option>
                            <option value="danger" @selected(($formatoFila['tono'] ?? '') === 'danger')>Rojo</option>
                            <option value="success" @selected(($formatoFila['tono'] ?? '') === 'success')>Verde</option>
                        </select>
                    </div>
                @endfor
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar visual</button>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-end mt-2" style="gap:.5rem;">
            <div style="min-width:10rem;">
                <label class="small mb-0 d-block">Columna calculada</label>
                <input type="text" name="calculadas[0][etiqueta]" class="form-control form-control-sm" maxlength="40"
                       value="{{ $calcFila['etiqueta'] ?? '' }}" placeholder="Nombre">
            </div>
            <div class="flex-grow-1" style="min-width:16rem;">
                <label class="small mb-0 d-block">Fórmula</label>
                <input type="text" name="calculadas[0][formula]" class="form-control form-control-sm" maxlength="180"
                       value="{{ $calcFila['formula'] ?? '' }}" placeholder="CONCAT({proveedor}, ' ', {estado})">
            </div>
            <div style="min-width:10rem;">
                <label class="small mb-0 d-block">Segunda</label>
                <input type="text" name="calculadas[1][etiqueta]" class="form-control form-control-sm" maxlength="40"
                       value="{{ $calcFila2['etiqueta'] ?? '' }}" placeholder="Nombre">
            </div>
            <div class="flex-grow-1" style="min-width:16rem;">
                <label class="small mb-0 d-block">Fórmula</label>
                <input type="text" name="calculadas[1][formula]" class="form-control form-control-sm" maxlength="180"
                       value="{{ $calcFila2['formula'] ?? '' }}" placeholder="UPPER({estado})">
            </div>
        </div>
        <p class="small text-muted mb-0 mt-1">
            Fórmulas: LENGTH, UPPER, LOWER, TRIM, CONCAT y campos del listado, por ejemplo {proveedor}.
            Hasta tres gráficos comparten el filtro. Un clic en uno recorta los otros y la grilla; ese gráfico sigue mostrando el resto para elegir otro valor.
            La suma de monto se parte por moneda. Hasta cuatro colores de fila.
            El gráfico queda en la vista al guardarla. Para sacarlo de la pantalla usá Quitar gráfico.
            @if (isset($calcFila['valida']) && $calcFila['valida'] === false)
                La primera fórmula no se pudo leer.
            @endif
            @if (isset($calcFila2['valida']) && $calcFila2['valida'] === false)
                La segunda fórmula no se pudo leer.
            @endif
        </p>
    </div>
</div>
