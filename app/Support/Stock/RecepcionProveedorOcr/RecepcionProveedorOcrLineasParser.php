<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Interpreta texto OCR de remitos/facturas y devuelve líneas con código, cantidad y precio.
 */
class RecepcionProveedorOcrLineasParser
{
    /** Código interno del proveedor (ej. 2.1.2.1.1000, 8.2.2.1.0502). */
    public const PATRON_CODIGO_PROVEEDOR = '\d+(?:\.\d+)+';

    private const UNIDADES_CANTIDAD = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';

    /**
     * @return list<array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }>
     */
    public function parsear(string $texto): array
    {
        $texto = $this->normalizarTexto($texto);
        if ($texto === '') {
            return [];
        }

        $filasRaw = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $fila) {
            $fila = trim((string) $fila);
            if ($fila !== '') {
                $filasRaw[] = $fila;
            }
        }

        $layout = RecepcionProveedorOcrLayoutSupport::detectarDesdeFilas($filasRaw);

        $lineas = [];
        foreach ($this->prepararFilas($texto) as $fila) {
            $parseada = $this->parsearFila($fila, $layout);
            if ($parseada !== null) {
                $lineas[] = $parseada;
            }
        }

        return $this->deduplicar($lineas);
    }

    /**
     * Une prefijos de cantidad partidos por OCR (ej. "7 PACK x 6" + "2.1.2.1.1000 SANTOS...").
     *
     * @return list<string>
     */
    private function prepararFilas(string $texto): array
    {
        $rawFilas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $fila) {
            $fila = trim((string) $fila);
            if ($fila === '' || $this->esEncabezadoORuido($fila)) {
                continue;
            }
            $rawFilas[] = $this->normalizarPrefijoCantidadOcr($fila);
        }

        $fusionadas = [];
        $i = 0;
        $total = count($rawFilas);
        while ($i < $total) {
            $fila = $rawFilas[$i];
            if ($this->esPrefijoCantidadRemito($fila) && $i + 1 < $total) {
                $fila = trim($fila.' '.$rawFilas[$i + 1]);
                $i += 2;
            } else {
                $i++;
            }
            $fusionadas[] = $fila;
        }

        return $this->fusionarContinuacionDescripcionFactura(
            $this->fusionarContinuacionCodigoBarra($fusionadas)
        );
    }

    /**
     * Une líneas de descripción partidas en facturas AFIP (detalle multilínea bajo el ítem).
     *
     * @param  list<string>  $filas
     * @return list<string>
     */
    private function fusionarContinuacionDescripcionFactura(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $out = [];
        $continuacionesFactura = 0;
        foreach ($filas as $fila) {
            if ($out !== []
                && $this->esContinuacionDescripcionFactura($fila)
                && $this->esLineaFacturaAfip($out[count($out) - 1])
                && $continuacionesFactura < 4) {
                $out[count($out) - 1] = $this->insertarContinuacionEnDescripcionFactura(
                    $out[count($out) - 1],
                    $fila
                );
                $continuacionesFactura++;
                continue;
            }

            $continuacionesFactura = 0;
            $out[] = $fila;
        }

        return $out;
    }

    private function esLineaFacturaAfip(string $fila): bool
    {
        return $this->parsearFilaFacturaAfip($fila) !== null;
    }

    private function esContinuacionDescripcionFactura(string $fila): bool
    {
        if ($this->esEncabezadoORuido($fila) || $this->esPrefijoCantidadRemito($fila)) {
            return false;
        }

        if ($this->esLineaFacturaAfip($fila)) {
            return false;
        }

        if (preg_match('/^'.self::PATRON_CODIGO_PROVEEDOR.'\s+/u', $fila)) {
            return false;
        }

        if (preg_match('/^\d+(?:[.,]\d+)?\s+(?:'.self::UNIDADES_CANTIDAD.')\b/iu', $fila)) {
            return false;
        }

        if (preg_match('/^(?:DUPLICADO|TRIPLICADO|ORIGINAL)$/iu', trim($fila))) {
            return false;
        }

        if (preg_match('/\b(?:Importe\s+(?:Total|Neto|Otros)|IVA\s+\d|CAE\s*N|Comprobante|Punto de Venta|Raz[oó]n Social|COD\.\s*01)\b/iu', $fila)) {
            return false;
        }

        if (preg_match('/^".+"$/u', trim($fila))) {
            return false;
        }

        return mb_strlen(trim($fila)) >= 3;
    }

    private function insertarContinuacionEnDescripcionFactura(string $linea, string $continuacion): string
    {
        $unidades = self::UNIDADES_CANTIDAD;
        if (preg_match(
            '/^(?<prefijo>.+?)(?<sufijo>\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<unidad>'.$unidades.')\b.*)$/iu',
            $linea,
            $m
        )) {
            return trim($m['prefijo'].' '.$continuacion).$m['sufijo'];
        }

        return trim($linea.' '.$continuacion);
    }

    /**
     * Une filas partidas cuando el EAN GS1 quedó cortado al final de una línea OCR.
     *
     * @param  list<string>  $filas
     * @return list<string>
     */
    private function fusionarContinuacionCodigoBarra(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $out = [];
        $total = count($filas);
        for ($i = 0; $i < $total; $i++) {
            $fila = $filas[$i];
            if ($i + 1 < $total
                && $this->filaTerminaConCodigoBarraParcial($fila)
                && preg_match('/^[\dOIl|!SXBZGBo…\.]+(?:\s|$)/iu', $filas[$i + 1])
                && ! preg_match('/^\d+\s+(?:'.self::UNIDADES_CANTIDAD.')\b/iu', $filas[$i + 1])) {
                $fila = trim($fila.' '.$filas[$i + 1]);
                $i++;
            }
            $out[] = $fila;
        }

        return $out;
    }

    private function filaTerminaConCodigoBarraParcial(string $fila): bool
    {
        if (! preg_match('/7794520[\dOIl|!SXBZGBo…\.]{0,10}$/iu', $fila, $m)) {
            return false;
        }

        $digitos = RecepcionProveedorOcrCodigoBarraSupport::normalizarDigitos(
            preg_replace('/[…\.]+$/u', '', (string) $m[0]) ?? (string) $m[0]
        );

        return strlen($digitos) >= 8 && strlen($digitos) < 11;
    }

    /**
     * Corrige lecturas OCR frecuentes en la columna cantidad (PACK → K, CAJAS → AMS).
     */
    private function normalizarPrefijoCantidadOcr(string $fila): string
    {
        $fila = RecepcionProveedorOcrLayoutSupport::normalizarPrefijoCantidadRemito($fila);

        return preg_replace(
            '/^(\d+)\s+(?:K|PAC)\s+(?:X\s+)?(\d+)/iu',
            '$1 PACK X $2',
            $fila
        ) ?? $fila;
    }

    private function esPrefijoCantidadRemito(string $fila): bool
    {
        $fila = $this->normalizarPrefijoCantidadOcr($fila);
        $fila = $this->normalizarUnidadesOcr($fila);

        return (bool) preg_match(
            '/^\d+(?:[.,]\d+)?\s+(?:'.self::UNIDADES_CANTIDAD.'|K|PAC|\(?\'?AM[S5]?)(?:\s+X\s+\d+)?\s*$/iu',
            $fila
        );
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function esEncabezadoORuido(string $fila): bool
    {
        if (mb_strlen($fila) < 4) {
            return true;
        }

        $upper = mb_strtoupper($fila);
        $palabrasRuido = [
            'CANTIDAD', 'CANT.', 'PRECIO', 'IMPORTE', 'DESCRIPCION', 'DESCRIPCIÓN',
            'CODIGO', 'CÓDIGO', 'ARTICULO', 'ARTÍCULO', 'SUBTOTAL', 'TOTAL', 'IVA',
            'REMITO', 'FACTURA', 'PROVEEDOR', 'CUIT', 'FECHA', 'PAGINA', 'PÁGINA',
            'UNIDADES', 'UNIDAD', 'PESO', 'COD. ALT', 'COD ALT',
            'COD. BARRAS', 'COD BARRAS', 'EAN', 'BARRAS',
            'DUPLICADO', 'TRIPLICADO', 'ORIGINAL', 'COMPROBANTE AUTORIZADO',
            'ALICUOTA', 'NETO GRAVADO', 'PUNTO DE VENTA', 'RAZON SOCIAL', 'RAZÓN SOCIAL',
        ];
        foreach ($palabrasRuido as $palabra) {
            if ($upper === $palabra || str_starts_with($upper, $palabra.' ')) {
                return true;
            }
        }

        if (preg_match('/^(total|subtotal|iva|neto|exento|importe)\b/ui', $fila)) {
            return true;
        }

        if (preg_match('/^(?:DUPLICADO|TRIPLICADO|ORIGINAL)$/iu', $fila)) {
            return true;
        }

        if (preg_match('/\b(?:CAE\s*N|Fecha de Vto|Pág\.|Punto de Venta|Comp\. Nro)\b/iu', $fila)) {
            return true;
        }

        return (bool) preg_match('/^\d{13}$/', preg_replace('/\s+/', '', $fila) ?? '');
    }

    private function esCodigoProveedorBuho(?string $codigo): bool
    {
        $codigo = trim((string) $codigo);

        return $codigo !== '' && (bool) preg_match('/^'.self::PATRON_CODIGO_PROVEEDOR.'$/u', $codigo);
    }

    /**
     * @return array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }|null
     */
    private function parsearFila(string $fila, ?RecepcionProveedorOcrLayout $layout = null): ?array
    {
        $facturaAfip = $this->parsearFilaFacturaAfip($fila);
        if ($facturaAfip !== null) {
            return $facturaAfip;
        }

        $fila = $this->normalizarPrefijoCantidadOcr($fila);
        $fila = $this->normalizarUnidadesOcr($fila);
        $fila = $this->normalizarCodigosBuhoOcr($fila);

        $cantidadLayout = RecepcionProveedorOcrLayoutSupport::extraerCantidadColumna($fila, $layout);
        $cantidadPorUnidadEnFila = RecepcionProveedorOcrLayoutSupport::buscarCantidadPorUnidadMedidaEnFila($fila, $layout);

        $cantidadNoEsPrimeraColumna = $layout !== null && $layout->indiceCantidad() > 0;
        if (preg_match('/^'.self::PATRON_CODIGO_PROVEEDOR.'\s+/u', $fila)
            && ! preg_match('/^\d+\s+(?:'.self::UNIDADES_CANTIDAD.')\s+/iu', $fila)
            && ! $cantidadNoEsPrimeraColumna
            && $cantidadLayout === null
            && $cantidadPorUnidadEnFila === null) {
            return null;
        }

        $tieneCodigoBuho = (bool) preg_match('/'.self::PATRON_CODIGO_PROVEEDOR.'/u', $fila);
        $tieneColumnaCantidadAlInicio = (bool) preg_match('/^\d+(?:[.,]\d+)?\s*(?:'.self::UNIDADES_CANTIDAD.')\b/iu', $fila);

        $codigoProveedor = self::PATRON_CODIGO_PROVEEDOR;
        $patrones = [
            // Remito Buho: cant + unidad + [X factor] + código proveedor + desc + barcode + unid×bulto + unidades + peso
            '/^(?<cant>\d+)\s*(?<unidad>'.self::UNIDADES_CANTIDAD.')\s*(?:X\s+(?<factor>\d+)\s+)?(?<codigo>'.$codigoProveedor.')\s+(?<desc>.+?)(?:\s+(?<barcode>\d{13}))?(?:\s+(?<unidxbulto>\d+))?(?:\s+(?<unidades>\d+(?:[.,]\d+)?))?(?:\s+(?<peso>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?))?$/iu',
            // Cantidad + tokens + código interno + descripción (sin códigos Buho punteados)
            '/^(?<cant>\d+)\s+(?:\S+\s+)*?(?<codigo>\d[\d._]{4,})\s+(?<desc>.+)$/iu',
            // código largo + descripción + cant + precio
            '/^(?<codigo>\d{6,13})\s+(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<precio>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?)$/u',
            // descripción + cant + precio
            '/^(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s+(?<precio>\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?)$/u',
            // cant x precio al final
            '/^(?<codigo>\d{6,13})?\s*(?<desc>.+?)\s+(?<cant>\d+(?:[.,]\d+)?)\s*[xX×]\s*(?<precio>\d+(?:[.,]\d+)?)$/u',
        ];

        foreach ($patrones as $indicePatron => $patron) {
            if ($indicePatron >= 2 && $tieneCodigoBuho && ! $tieneColumnaCantidadAlInicio) {
                continue;
            }

            if (! preg_match($patron, $fila, $m)) {
                continue;
            }

            $cantBulto = RecepcionProveedorOcrNumeroSupport::parsear((string) ($m['cant'] ?? ''));
            if ($cantBulto === null || $cantBulto <= 0) {
                continue;
            }

            $codigo = trim((string) ($m['codigo'] ?? ''));
            $unidad = isset($m['unidad']) ? mb_strtoupper(trim((string) $m['unidad'])) : null;

            if ($indicePatron === 1 && $this->esCodigoBarraEan13($codigo)) {
                continue;
            }

            if ($indicePatron === 1 && $tieneColumnaCantidadAlInicio && ! $this->esCodigoProveedorBuho($codigo)) {
                continue;
            }

            $factor = isset($m['factor']) && trim((string) $m['factor']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor'])
                : null;

            if ($this->esCodigoProveedorBuho($codigo) && $unidad === null) {
                continue;
            }

            if ($unidad !== null && ! $this->esCodigoProveedorBuho($codigo)) {
                continue;
            }

            if ($unidad === null && $factor !== null && abs($cantBulto - $factor) < 0.000001) {
                continue;
            }

            $precio = isset($m['precio']) && trim((string) $m['precio']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['precio'])
                : 0.0;
            if ($precio === null) {
                $precio = 0.0;
            }

            $barcode = $this->resolverCodigoBarra(
                $fila,
                trim((string) ($m['barcode'] ?? '')),
                $layout,
                $cantBulto,
                $factor
            );
            $desc = $this->limpiarDescripcion(trim((string) ($m['desc'] ?? '')), $barcode);
            if ($desc === '') {
                continue;
            }

            $unidadesCol = isset($m['unidades']) && trim((string) $m['unidades']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['unidades'])
                : null;
            $pesoCol = isset($m['peso']) && trim((string) $m['peso']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['peso'])
                : null;

            $referenciaUnidadesTotales = $unidadesCol;
            if (($referenciaUnidadesTotales === null || $referenciaUnidadesTotales <= 0)
                && $pesoCol !== null && $pesoCol > 0 && $factor !== null && $factor > 1) {
                $referenciaUnidadesTotales = $pesoCol;
            }

            $esRemitoConColumnas = $unidad !== null && $this->esCodigoProveedorBuho($codigo);
            if ($cantidadLayout !== null) {
                $cantBulto = $cantidadLayout['cant'];
                if ($cantidadLayout['unidad'] !== null) {
                    $unidad = $cantidadLayout['unidad'];
                }
                if ($cantidadLayout['factor'] !== null) {
                    $factor = $cantidadLayout['factor'];
                }
                $esRemitoConColumnas = $esRemitoConColumnas || $unidad !== null;
            }

            $candidatos = $this->armarCantidadesCandidatas($cantBulto, $unidad, $factor, $unidadesCol, $esRemitoConColumnas, $pesoCol);

            return [
                'codigo' => $codigo !== '' ? $codigo : null,
                'descripcion' => $desc,
                'cantidad' => $cantBulto,
                'precio' => $precio,
                'codigobarra' => $barcode,
                'unidad_compra' => $unidad,
                'factor_embalaje' => $factor,
                'cantidad_columna_layout' => $cantidadLayout !== null,
                'cantidades_candidatas' => $candidatos,
            ];
        }

        if ($cantidadLayout !== null && $layout !== null) {
            return $this->parsearFilaDesdeLayout($fila, $layout, $cantidadLayout);
        }

        if ($cantidadPorUnidadEnFila !== null && $layout !== null) {
            return $this->parsearFilaDesdeLayout($fila, $layout, $cantidadPorUnidadEnFila);
        }

        if ($cantidadPorUnidadEnFila !== null) {
            return $this->parsearFilaRemitoCantidadConCodigoBuho($fila, $cantidadPorUnidadEnFila);
        }

        return null;
    }

    /**
     * Factura electrónica AFIP: código alfanumérico + descripción + cantidad + unidad + precio unitario.
     *
     * @return array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }|null
     */
    private function parsearFilaFacturaAfip(string $fila): ?array
    {
        $fila = trim($fila);
        if ($fila === '') {
            return null;
        }

        $unidades = self::UNIDADES_CANTIDAD;
        $precioPatron = '(?<precio>\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})';
        $patron = '/^(?<codigo>[A-Z0-9][A-Z0-9\/._-]{0,14})\s+'
            .'(?<desc>.+?)\s+'
            .'(?<cant>\d+(?:[.,]\d+)?)\s+'
            .'(?<unidad>'.$unidades.')\s+'
            .$precioPatron
            .'/iu';

        if (! preg_match($patron, $fila, $m)) {
            return null;
        }

        $cantBulto = RecepcionProveedorOcrNumeroSupport::parsear((string) ($m['cant'] ?? ''));
        if ($cantBulto === null || $cantBulto <= 0) {
            return null;
        }

        $precio = RecepcionProveedorOcrNumeroSupport::parsear((string) ($m['precio'] ?? ''));
        if ($precio === null) {
            $precio = 0.0;
        }

        $codigo = trim((string) ($m['codigo'] ?? ''));
        $desc = $this->limpiarDescripcion(trim((string) ($m['desc'] ?? '')));
        if ($desc === '') {
            return null;
        }

        $unidad = $this->normalizarUnidadCompraDesdeEtiqueta((string) ($m['unidad'] ?? ''));

        return [
            'codigo' => $codigo !== '' ? $codigo : null,
            'descripcion' => $desc,
            'cantidad' => $cantBulto,
            'precio' => $precio,
            'codigobarra' => null,
            'unidad_compra' => $unidad,
            'factor_embalaje' => null,
            'cantidad_columna_layout' => false,
            'cantidades_candidatas' => $this->armarCantidadesCandidatas($cantBulto, $unidad, null, null, false),
        ];
    }

    private function normalizarUnidadCompraDesdeEtiqueta(string $etiqueta): ?string
    {
        $etiqueta = mb_strtoupper(trim($etiqueta));
        if ($etiqueta === '') {
            return null;
        }

        if ($etiqueta === 'UNIDADES' || $etiqueta === 'UNIDAD' || $etiqueta === 'UNID') {
            return 'UNID';
        }

        if (in_array($etiqueta, ['CAJAS', 'PACK', 'BOL', 'BIDON', 'KG', 'LITRO', 'LITROS'], true)) {
            return $etiqueta === 'LITRO' ? 'LITROS' : $etiqueta;
        }

        return $etiqueta;
    }

    /**
     * Corrige códigos Buho con OCR confundido (ej. 2.1.2.1.1ooo → 2.1.2.1.1000).
     */
    private function normalizarCodigosBuhoOcr(string $fila): string
    {
        $patron = self::PATRON_CODIGO_PROVEEDOR;

        return preg_replace_callback(
            '/\d+(?:\.[\d.oO]+)+/u',
            static function (array $m) use ($patron): string {
                $raw = (string) $m[0];
                if (! preg_match('/[oO]/u', $raw)) {
                    return $raw;
                }

                $corregido = preg_replace('/[oO]/u', '0', $raw) ?? $raw;
                if (preg_match('/^'.$patron.'$/u', $corregido)) {
                    return $corregido;
                }

                return $raw;
            },
            $fila
        ) ?? $fila;
    }

    private function esCodigoBarraEan13(string $codigo): bool
    {
        return (bool) preg_match('/^\d{13}$/', trim($codigo));
    }

    /**
     * @param  array{cant: float, unidad: ?string, factor: ?float, celda: string}  $cantidadEnFila
     * @return array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }|null
     */
    private function parsearFilaRemitoCantidadConCodigoBuho(string $fila, array $cantidadEnFila): ?array
    {
        if (! preg_match('/'.self::PATRON_CODIGO_PROVEEDOR.'\s+(?<desc>.+)$/u', $fila, $m)) {
            return null;
        }

        $codigo = null;
        if (preg_match('/'.self::PATRON_CODIGO_PROVEEDOR.'/u', $fila, $cm)) {
            $codigo = trim((string) $cm[0]);
        }
        if ($codigo === null || ! $this->esCodigoProveedorBuho($codigo)) {
            return null;
        }

        $desc = $this->limpiarDescripcion(trim((string) $m['desc']));
        if ($desc === '') {
            return null;
        }

        $cantBulto = (float) $cantidadEnFila['cant'];
        $unidad = $cantidadEnFila['unidad'];
        $factor = $cantidadEnFila['factor'];
        $barcode = $this->resolverCodigoBarra($fila, '', null, $cantBulto, $factor);

        $unidadesCol = null;
        if (preg_match('/\s+(?<unidxbulto>\d+)\s+(?<unidades>\d+(?:[.,]\d+)?)\s*$/u', $fila, $um)) {
            $unidadesCol = RecepcionProveedorOcrNumeroSupport::parsear((string) $um['unidades']);
            if ($factor === null || $factor <= 1) {
                $factorUxB = RecepcionProveedorOcrNumeroSupport::parsear((string) $um['unidxbulto']);
                if ($factorUxB !== null && $factorUxB > 1) {
                    $factor = $factorUxB;
                }
            }
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $desc,
            'cantidad' => $cantBulto,
            'precio' => 0.0,
            'codigobarra' => $barcode,
            'unidad_compra' => $unidad,
            'factor_embalaje' => $factor,
            'cantidad_columna_layout' => true,
            'cantidades_candidatas' => $this->armarCantidadesCandidatas(
                $cantBulto,
                $unidad,
                $factor,
                $unidadesCol,
                true
            ),
        ];
    }

    /**
     * @param  array{cant: float, unidad: ?string, factor: ?float, celda: string}  $cantidadLayout
     * @return array{
     *   codigo: ?string,
     *   descripcion: string,
     *   cantidad: float,
     *   precio: float,
     *   cantidades_candidatas: list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     * }|null
     */
    private function parsearFilaDesdeLayout(
        string $fila,
        RecepcionProveedorOcrLayout $layout,
        array $cantidadLayout
    ): ?array {
        $celdas = RecepcionProveedorOcrLayoutSupport::partirFilaEnColumnas($fila, $layout);

        $codigo = null;
        $idxCodigo = $layout->indice('codigo');
        if ($idxCodigo !== null) {
            $rawCodigo = trim((string) ($celdas[$idxCodigo] ?? ''));
            if (preg_match('/^('.self::PATRON_CODIGO_PROVEEDOR.')(?:\s+(.+))?$/u', $rawCodigo, $cm)) {
                $codigo = trim((string) $cm[1]);
                if (trim((string) ($cm[2] ?? '')) !== '' && $layout->indice('articulo') === $idxCodigo) {
                    $celdas[$idxCodigo] = trim((string) $cm[2]);
                }
            } elseif (preg_match('/'.self::PATRON_CODIGO_PROVEEDOR.'/u', $fila, $cm)) {
                $codigo = trim((string) $cm[0]);
            }
        }

        $desc = '';
        $idxArticulo = $layout->indice('articulo');
        if ($idxArticulo !== null) {
            $desc = trim((string) ($celdas[$idxArticulo] ?? ''));
        }
        if ($desc === '' && $codigo !== null && preg_match(
            '/'.preg_quote($codigo, '/').'\s+(?<desc>.+)$/u',
            $fila,
            $dm
        )) {
            $desc = $this->limpiarDescripcion(trim((string) $dm['desc']));
        }
        if ($desc === '') {
            return null;
        }

        $unidadesCol = null;
        $idxUnidades = $layout->indice('unidades');
        if ($idxUnidades !== null) {
            $rawUnidades = trim((string) ($celdas[$idxUnidades] ?? ''));
            if ($rawUnidades !== '') {
                $unidadesCol = RecepcionProveedorOcrNumeroSupport::parsear($rawUnidades);
            }
        }

        $idxUnidBulto = $layout->indice('unidxbulto');
        $cantBulto = $cantidadLayout['cant'];
        $unidad = $cantidadLayout['unidad'];
        $factor = $cantidadLayout['factor'];

        if ($idxUnidBulto !== null && ($factor === null || $factor <= 1)) {
            $rawUxB = trim((string) ($celdas[$idxUnidBulto] ?? ''));
            if ($rawUxB !== '') {
                $factorUxB = RecepcionProveedorOcrNumeroSupport::parsear($rawUxB);
                if ($factorUxB !== null && $factorUxB > 1) {
                    $factor = $factorUxB;
                }
            }
        }

        $esRemitoConColumnas = $unidad !== null || ($codigo !== null && $this->esCodigoProveedorBuho($codigo));

        $candidatos = $this->armarCantidadesCandidatas(
            $cantBulto,
            $unidad,
            $factor,
            $unidadesCol,
            $esRemitoConColumnas,
            null
        );

        $barcode = $this->resolverCodigoBarra($fila, '', null, $cantBulto, $factor);

        return [
            'codigo' => $codigo,
            'descripcion' => $this->limpiarDescripcion($desc, $barcode),
            'cantidad' => $cantBulto,
            'precio' => 0.0,
            'codigobarra' => $barcode,
            'unidad_compra' => $unidad,
            'factor_embalaje' => $factor,
            'cantidad_columna_layout' => true,
            'cantidades_candidatas' => $candidatos,
        ];
    }

    private function normalizarUnidadesOcr(string $fila): string
    {
        $fila = str_ireplace(['€AMS', 'CAAMS', 'C A J A S', "('AMS", "('AM5"], 'CAJAS', $fila);
        $fila = preg_replace('/\bCAJAS?\b/iu', 'CAJAS', $fila) ?? $fila;
        $fila = preg_replace('/\bPACKS?\b/iu', 'PACK', $fila) ?? $fila;
        $fila = preg_replace('/\bUNID(?:ADES)?\b/iu', 'UNID', $fila) ?? $fila;
        $fila = preg_replace('/\bBOLS?\b/iu', 'BOL', $fila) ?? $fila;

        return $fila;
    }

    /**
     * @return list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     */
    private function armarCantidadesCandidatas(
        float $cantBulto,
        ?string $unidad,
        ?float $factor,
        ?float $unidadesCol,
        bool $esRemitoConColumnas,
        ?float $pesoCol = null
    ): array {
        $tipoPrincipal = $esRemitoConColumnas ? 'cantidad_columna' : 'bulto';
        $candidatos = [
            ['valor' => $cantBulto, 'tipo' => $tipoPrincipal, 'unidad' => $unidad],
        ];

        $totalCalculado = null;
        if ($factor !== null && $factor > 1) {
            $totalCalculado = round($cantBulto * $factor, 6);
            $candidatos[] = [
                'valor' => $totalCalculado,
                'tipo' => 'total_unidades',
                'unidad' => $unidad,
                'factor' => $factor,
            ];
        }

        $unidadesReferencia = $unidadesCol;
        if (($unidadesReferencia === null || $unidadesReferencia <= 0)
            && $pesoCol !== null && $pesoCol > 0 && $factor !== null && $factor > 1
            && $pesoCol > $cantBulto) {
            $unidadesReferencia = $pesoCol;
        }

        if ($unidadesReferencia !== null && $unidadesReferencia > $cantBulto && $unidadesReferencia !== $factor) {
            if ($totalCalculado === null || abs($unidadesReferencia - $totalCalculado) > 0.000001) {
                $candidatos[] = [
                    'valor' => $unidadesReferencia,
                    'tipo' => 'unidades_columna',
                    'unidad' => 'UNID',
                ];
            }
        }

        return RecepcionProveedorOcrCantidadSupport::deduplicarCandidatos($candidatos);
    }

    private function resolverCodigoBarra(
        string $fila,
        string $barcodeRegex,
        ?RecepcionProveedorOcrLayout $layout,
        ?float $cantBulto = null,
        ?float $factorEmbalaje = null
    ): ?string {
        if ($barcodeRegex !== '') {
            $ean = RecepcionProveedorOcrCodigoBarraSupport::extraerDeCelda($barcodeRegex);
            if ($ean !== null) {
                return $ean;
            }
        }

        return RecepcionProveedorOcrLayoutSupport::extraerCodigoBarraColumna(
            $fila,
            $layout,
            $cantBulto,
            $factorEmbalaje
        );
    }

    private function limpiarDescripcion(string $desc, ?string $codigoBarra = null): string
    {
        if ($codigoBarra !== null && $codigoBarra !== '') {
            $desc = str_replace($codigoBarra, '', $desc) ?? $desc;
            $desc = preg_replace('/'.preg_quote($codigoBarra, '/').'/u', '', $desc) ?? $desc;
        }

        $desc = preg_replace('/\s+\d{13}(?:\s+\d+(?:[.,]\d+)?(?:\s+[\d\-]+)?)?\s*$/u', '', $desc) ?? $desc;
        $desc = preg_replace('/\s+7794520[\dOIl|!SXBZGBo…\.]{0,12}(?:\s+\d{1,3})?\s*$/iu', '', $desc) ?? $desc;
        $desc = preg_replace('/\s+[\d\-]+\s*$/u', '', $desc) ?? $desc;

        return trim(preg_replace('/\s{2,}/u', ' ', $desc) ?? $desc);
    }

    /**
     * @param  list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float, cantidades_candidatas: list<array<string, mixed>>}>  $lineas
     * @return list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float, cantidades_candidatas: list<array<string, mixed>>}>
     */
    private function deduplicar(array $lineas): array
    {
        $vistas = [];
        $out = [];

        foreach ($lineas as $linea) {
            $codigo = (string) ($linea['codigo'] ?? '');
            $esCodigoBuho = $codigo !== '' && preg_match('/^'.self::PATRON_CODIGO_PROVEEDOR.'$/u', $codigo);
            $clave = $esCodigoBuho
                ? RecepcionProveedorOcrNumeroSupport::normalizarSku($codigo)
                    .'|'.mb_strtoupper((string) ($linea['descripcion'] ?? ''))
                    .'|'.$linea['cantidad']
                    .'|'.$linea['precio']
                : mb_strtoupper($codigo)
                    .'|'.$linea['cantidad']
                    .'|'.$linea['precio'];
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $out[] = $linea;
        }

        return $this->preferirLineasRemitoBuho($out);
    }

    /**
     * Si hay línea Buho válida (código punteado + columna cantidad) descarta duplicados OCR corruptos.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function preferirLineasRemitoBuho(array $lineas): array
    {
        $buhoValidas = array_filter($lineas, static fn (array $l): bool => ! empty($l['unidad_compra'])
            && preg_match('/^'.self::PATRON_CODIGO_PROVEEDOR.'$/u', (string) ($l['codigo'] ?? '')));

        if ($buhoValidas === []) {
            return $lineas;
        }

        $descBuho = array_map(
            static fn (array $l): string => mb_strtoupper((string) ($l['descripcion'] ?? '')),
            $buhoValidas
        );

        return array_values(array_filter($lineas, function (array $linea) use ($descBuho): bool {
            if (! empty($linea['unidad_compra']) && $this->esCodigoProveedorBuho($linea['codigo'] ?? null)) {
                return true;
            }

            $desc = mb_strtoupper((string) ($linea['descripcion'] ?? ''));
            foreach ($descBuho as $descValida) {
                if ($desc === '' || $descValida === '') {
                    continue;
                }
                if (str_contains($desc, $descValida) || str_contains($descValida, $desc)) {
                    return false;
                }
                if (similar_text($desc, $descValida) / max(mb_strlen($desc), mb_strlen($descValida), 1) > 0.45) {
                    return false;
                }
            }

            return true;
        }));
    }
}
