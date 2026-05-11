@php
    $lineasTexto = [];
    foreach (($data->requisicion_articulos ?? collect()) as $item) {
        $sku = $item->articulos->sku ?? '';
        $descripcion = $item->articulos->descripcion ?? '';
        $mon = $item->monedas->abreviatura ?? '';
        $ccDestNombre = optional($item->centrocostos_destino)->nombre ?? '';
        $ccDestCod = optional($item->centrocostos_destino)->codigo ?? '';
        $ccDest = trim(implode(' ', array_filter([$ccDestCod, $ccDestNombre])));
        $cantidad = $item->cantidad ?? '';
        $precio = $item->precio ?? '';
        $subtotal = (float) ($item->cantidad ?? 0) * (float) ($item->precio ?? 0);
        $partidaCod = optional($item->partidagastos)->codigo ?? '';
        $partidaDesc = optional(optional($item->partidagastos)->articulos)->detalle ?? '';
        $capexCod = optional($item->capexs)->codigo ?? '';
        $capexNom = optional($item->capexs)->nombre ?? '';
        $detalleLinea = trim((string) ($item->detalle ?? ''));
        $fechaEntregaLinea = $item->fechaentrega ? substr((string) $item->fechaentrega, 0, 10) : '';
        $partes = [
            $sku !== '' ? 'SKU '.$sku : null,
            $descripcion !== '' ? $descripcion : null,
            'Cant. '.($cantidad !== '' ? $cantidad : '—'),
            'P. unit '.($precio !== '' ? $precio : '—').($mon !== '' ? ' '.$mon : ''),
            'Subt. '.number_format($subtotal, 2, ',', '.').($mon !== '' ? ' '.$mon : ''),
            $ccDest !== '' ? 'CC dest. '.$ccDest : null,
            $partidaCod !== '' || $partidaDesc !== '' ? 'Partida '.trim($partidaCod.' '.$partidaDesc) : null,
            $capexCod !== '' || $capexNom !== '' ? 'CAPEX '.trim($capexCod.' '.$capexNom) : null,
            $fechaEntregaLinea !== '' ? 'Entrega línea '.$fechaEntregaLinea : null,
            $detalleLinea !== '' ? 'Obs. '.$detalleLinea : null,
        ];
        $lineasTexto[] = implode(' · ', array_values(array_filter($partes)));
    }
@endphp
{!! implode($separator ?? "\n", array_map(static fn ($t) => e($t), $lineasTexto)) !!}
