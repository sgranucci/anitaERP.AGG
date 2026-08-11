@php
    use App\Support\Compras\OrdencompraTotalesCabecera;
    use App\Support\Compras\OrdencompraUiConfigSupport;
    $mostrarPeso = OrdencompraUiConfigSupport::mostrarPesoArticulo();
    $pedirPartidaCapex = OrdencompraUiConfigSupport::pedirPartidaCapex();
    $lineasTexto = [];
    $lineasOc = collect($data->ordencompra_articulos ?? [])->sortBy('id');
    $monedaRefId = (int) (optional($lineasOc->first())->moneda_id ?: 1);
    foreach ($lineasOc as $item) {
        $sku = $item->articulos->sku ?? '';
        $descripcion = $item->articulos->descripcion ?? '';
        $colorNom = optional($item->color)->nombre ?? '';
        $talleNom = optional($item->talle)->nombre ?? '';
        $mon = $item->monedas->abreviatura ?? '';
        $ccDestNombre = optional($item->centrocostos_destino)->nombre ?? '';
        $ccDestCod = optional($item->centrocostos_destino)->codigo ?? '';
        $ccDest = trim(implode(' ', array_filter([$ccDestCod, $ccDestNombre])));
        $cantidad = $item->cantidad ?? '';
        $precio = $item->precio ?? '';
        $subtotal = OrdencompraTotalesCabecera::importeLineaEnMonedaReferencia(
            $monedaRefId,
            (int) ($item->moneda_id ?: $monedaRefId ?: 1),
            (float) ($item->cantidad ?? 0),
            (float) ($item->precio ?? 0),
            (float) ($item->cotizacion ?? 1),
        );
        $partidaCod = optional($item->partidagastos)->codigo ?? '';
        $partidaDesc = optional(optional($item->partidagastos)->articulos)->detalle ?? '';
        $capexCod = optional($item->capexs)->codigo ?? '';
        $capexNom = optional($item->capexs)->nombre ?? '';
        $detalleLinea = trim((string) ($item->detalle ?? ''));
        $fechaEntregaLinea = $item->fechaentrega ? substr((string) $item->fechaentrega, 0, 10) : '';
        $pesoUnit = (float) ($item->peso_unitario ?? 0);
        $pesoTot = (float) ($item->peso_total ?? 0);
        if ($mostrarPeso && $pesoTot <= 0 && $pesoUnit > 0 && (float) ($item->cantidad ?? 0) > 0) {
            $pesoTot = $pesoUnit * (float) $item->cantidad;
        }
        $partes = [
            $sku !== '' ? 'SKU '.$sku : null,
            $descripcion !== '' ? $descripcion : null,
            $colorNom !== '' ? 'Color '.$colorNom : null,
            $talleNom !== '' ? 'Talle '.$talleNom : null,
            'Cant. '.($cantidad !== '' ? $cantidad : '—'),
            $mostrarPeso && $pesoUnit > 0 ? 'Peso unit. '.rtrim(rtrim(number_format($pesoUnit, 6, '.', ''), '0'), '.') : null,
            $mostrarPeso && $pesoTot > 0 ? 'Peso tot. '.rtrim(rtrim(number_format($pesoTot, 6, '.', ''), '0'), '.') : null,
            'P. unit '.($precio !== '' ? $precio : '—').($mon !== '' ? ' '.$mon : ''),
            'Subt. '.number_format($subtotal, 2, ',', '.').($mon !== '' ? ' '.$mon : ''),
            $ccDest !== '' ? 'CC dest. '.$ccDest : null,
            $pedirPartidaCapex && ($partidaCod !== '' || $partidaDesc !== '') ? 'Partida '.trim($partidaCod.' '.$partidaDesc) : null,
            $pedirPartidaCapex && ($capexCod !== '' || $capexNom !== '') ? 'CAPEX '.trim($capexCod.' '.$capexNom) : null,
            $fechaEntregaLinea !== '' ? 'Entrega línea '.$fechaEntregaLinea : null,
            $detalleLinea !== '' ? 'Obs. '.$detalleLinea : null,
        ];
        $lineasTexto[] = implode(' · ', array_values(array_filter($partes)));
    }
@endphp
{!! implode($separator ?? "\n", array_map(static fn ($t) => e($t), $lineasTexto)) !!}
