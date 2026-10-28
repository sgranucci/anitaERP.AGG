<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Support\Collection;

/**
 * Fórmulas Anita de concepto IVA compra: {@code con(CODIGO)*0.21}.
 *
 * Se usan en alta manual sin precarga para calcular la alícuota (IVA)
 * a partir del gravado correspondiente. También inferen tipoconcepto G/I
 * cuando Anita no trae concc_tipo_conc (todo queda en N).
 */
final class ConceptoIvacompraFormulaSupport
{
    /**
     * @return array{codigo_base: string, coeficiente: float}|null
     */
    public static function parse(?string $formula): ?array
    {
        $raw = trim((string) $formula);
        if ($raw === '' || $raw === '.') {
            return null;
        }

        // con(2)*0.21  |  con(205)*0.105  |  CON(11) * 0,27
        if (! preg_match(
            '/^con\s*\(\s*([0-9]+)\s*\)\s*\*\s*([0-9]+(?:[.,][0-9]+)?)\s*$/i',
            $raw,
            $m
        )) {
            return null;
        }

        $coef = (float) str_replace(',', '.', $m[2]);
        if ($coef <= 0) {
            return null;
        }

        return [
            'codigo_base' => (string) $m[1],
            'coeficiente' => $coef,
        ];
    }

    /**
     * Tipo a grabar en anitaERP cuando Anita manda N (o vacío) y la fórmula lo define.
     * El concepto de con(COD)*coef queda I; la base, G. Un tipo ya informado por Anita se respeta.
     *
     * @param  list<array{codigo?: string, formula?: string, tipoconcepto?: string}>  $filas
     * @return array<string, string> codigo => G|I
     */
    public static function tiposPersistidosDesdeFormulas(array $filas): array
    {
        $tipoPorCodigo = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $tipo = strtoupper(trim((string) ($fila['tipoconcepto'] ?? '')));
            $tipoPorCodigo[$codigo] = $tipo === '' ? 'N' : $tipo;
        }

        $corregidos = [];
        foreach ($filas as $fila) {
            $parsed = self::parse((string) ($fila['formula'] ?? ''));
            if ($parsed === null) {
                continue;
            }

            $codigoIva = trim((string) ($fila['codigo'] ?? ''));
            if ($codigoIva !== '' && self::esNetoInformadoPorAnita($tipoPorCodigo[$codigoIva] ?? 'N')) {
                $corregidos[$codigoIva] = 'I';
            }

            $claveBase = self::claveCodigoEnMapa($parsed['codigo_base'], $tipoPorCodigo);
            if ($claveBase !== null && self::esNetoInformadoPorAnita($tipoPorCodigo[$claveBase])) {
                $corregidos[$claveBase] = 'G';
            }
        }

        return $corregidos;
    }

    /**
     * @param  array<string, string>  $tipoPorCodigo
     */
    private static function claveCodigoEnMapa(string $codigo, array $tipoPorCodigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo !== '' && array_key_exists($codigo, $tipoPorCodigo)) {
            return $codigo;
        }

        $normalizado = ltrim($codigo, '0');
        foreach (array_keys($tipoPorCodigo) as $clave) {
            if (ltrim($clave, '0') === $normalizado) {
                return $clave;
            }
        }

        return null;
    }

    private static function esNetoInformadoPorAnita(string $tipo): bool
    {
        $tipo = strtoupper(trim($tipo));

        return $tipo === '' || $tipo === 'N';
    }

    public static function tasaPorcentajeDesdeFormula(?string $formula): float
    {
        $parsed = self::parse($formula);
        if ($parsed === null) {
            return 0.0;
        }

        return round($parsed['coeficiente'] * 100, 3);
    }

    /**
     * Marca alícuotas (I) y gravados base (G + tasa) en el mapa meta del cliente.
     *
     * @param  array<int, array<string, mixed>>  $meta
     * @return array<int, array<string, mixed>>
     */
    public static function enriquecerMetaCliente(array $meta): array
    {
        $porCodigo = [];
        foreach ($meta as $id => $fila) {
            $cod = trim((string) ($fila['codigo'] ?? ''));
            if ($cod !== '') {
                $porCodigo[$cod] = (int) $id;
            }
        }

        foreach ($meta as $id => $fila) {
            $parsed = self::parse((string) ($fila['formula'] ?? ''));
            if ($parsed === null) {
                $base = trim((string) ($fila['formula_codigo_base'] ?? ''));
                $coef = (float) ($fila['formula_coeficiente'] ?? 0);
                if ($base !== '' && $coef > 0) {
                    $parsed = ['codigo_base' => $base, 'coeficiente' => $coef];
                }
            }
            if ($parsed === null) {
                continue;
            }

            $tipoI = strtoupper((string) ($meta[$id]['tipoconcepto'] ?? ''));
            // Fórmula con(COD)*coef = alícuota. Anita suele dejar tipoconcepto N:
            // forzar I (no tocar G/E/T/P/B/… ya clasificados).
            if (! in_array($tipoI, ['I', 'G', 'E', 'T', 'P', 'B', 'M', 'S', 'A'], true)) {
                $meta[$id]['tipoconcepto'] = 'I';
            }
            if ((float) ($meta[$id]['impuesto_tasa'] ?? 0) <= 0) {
                $meta[$id]['impuesto_tasa'] = round($parsed['coeficiente'] * 100, 3);
            }
            $meta[$id]['formula_codigo_base'] = $parsed['codigo_base'];
            $meta[$id]['formula_coeficiente'] = $parsed['coeficiente'];

            $gravadoId = $porCodigo[$parsed['codigo_base']] ?? null;
            if ($gravadoId === null) {
                continue;
            }
            $tipoG = strtoupper((string) ($meta[$gravadoId]['tipoconcepto'] ?? ''));
            if (! in_array($tipoG, ['G', 'E'], true)) {
                $meta[$gravadoId]['tipoconcepto'] = 'G';
            }
            if ((float) ($meta[$gravadoId]['impuesto_tasa'] ?? 0) <= 0) {
                $meta[$gravadoId]['impuesto_tasa'] = round($parsed['coeficiente'] * 100, 3);
            }
        }

        foreach ($meta as $id => $fila) {
            $tipoTributo = self::tipoconceptoTributoSiMaestroEnNeto(
                (string) ($fila['tipoconcepto'] ?? ''),
                (string) ($fila['nombre'] ?? ''),
                (int) ($fila['cuenta_debe_id'] ?? 0) > 0,
            );
            if ($tipoTributo !== null) {
                $meta[$id]['tipoconcepto'] = $tipoTributo;
            }
        }

        return $meta;
    }

    /**
     * Infere tipoconcepto G/I y tasa en memoria (no persiste) sobre una colección Eloquent.
     *
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     */
    public static function inferirTiposYTasasEnColeccion(Collection $conceptos): void
    {
        if ($conceptos->isEmpty()) {
            return;
        }

        $porCodigo = $conceptos->keyBy(
            static fn (Concepto_Ivacompra $c): string => (string) ($c->codigo ?? '')
        );

        foreach ($conceptos as $concepto) {
            $parsed = self::parse((string) ($concepto->formula ?? ''));
            if ($parsed === null) {
                continue;
            }

            $tipoI = strtoupper((string) ($concepto->tipoconcepto ?? ''));
            // Fórmula con(COD)*coef = alícuota. Anita suele dejar tipoconcepto N:
            // forzar I (no tocar G/E/T/P/B/… ya clasificados).
            if (! in_array($tipoI, ['I', 'G', 'E', 'T', 'P', 'B', 'M', 'S', 'A'], true)) {
                $concepto->setAttribute('tipoconcepto', 'I');
            }
            $concepto->setAttribute('_tasa_formula', round($parsed['coeficiente'] * 100, 3));

            $base = $porCodigo->get($parsed['codigo_base']);
            if (! $base instanceof Concepto_Ivacompra) {
                continue;
            }
            $tipoG = strtoupper((string) ($base->tipoconcepto ?? ''));
            if (! in_array($tipoG, ['G', 'E'], true)) {
                $base->setAttribute('tipoconcepto', 'G');
            }
            if ((float) ($base->getAttribute('_tasa_formula') ?? 0) <= 0) {
                $base->setAttribute('_tasa_formula', round($parsed['coeficiente'] * 100, 3));
            }
        }

        foreach ($conceptos as $concepto) {
            $tipoTributo = self::tipoconceptoTributoSiMaestroEnNeto(
                (string) ($concepto->tipoconcepto ?? ''),
                (string) ($concepto->nombre ?? ''),
                (int) ($concepto->cuentacontabledebe_id ?? 0) > 0
                    || (int) $concepto->cuentacontableDebeIdParaEmpresa(null) > 0,
            );
            if ($tipoTributo !== null) {
                $concepto->setAttribute('tipoconcepto', $tipoTributo);
            }
        }
    }

    /**
     * Anita deja casi todo en tipoconcepto N. La fórmula reclasifica el IVA;
     * percepción, retención e impuestos sin fórmula siguen en N y el asiento
     * los trata como neto (pide cuenta de gasto). Si el maestro ya tiene cuenta
     * DEBE, esa manda y el renglón no se edita.
     */
    public static function tipoconceptoTributoSiMaestroEnNeto(
        ?string $tipo,
        ?string $nombre,
        bool $tieneCuentaDebe,
    ): ?string {
        $tipo = strtoupper(trim((string) $tipo));
        if (! $tieneCuentaDebe || ! in_array($tipo, ['', 'N'], true)) {
            return null;
        }

        $n = self::foldNombre((string) $nombre);
        if ($n === '') {
            return null;
        }

        if (str_contains($n, 'sircreb')) {
            return ComprobanteProveedorConceptoIvaTipos::PERCEPCION_SIRCREB;
        }

        $esPercep = str_contains($n, 'percep');
        $esRet = (bool) preg_match('/\bret(\.|encion|\b)/', $n);
        $esIibb = (bool) preg_match('/i\.?\s*b\.?\b|iibb|\bibr\b|ingresos brutos/', $n);
        $esIva = (bool) preg_match('/\biva\b|i\.v\.a/', $n);

        if ($esPercep && ($esIva || str_contains($n, 'import'))) {
            return ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IVA;
        }
        if ($esIibb && $esPercep) {
            return ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB;
        }
        if ($esRet) {
            return 'V';
        }
        if ($esIva || str_contains($n, 'impuesto')) {
            return 'I';
        }

        return null;
    }

    private static function foldNombre(string $nombre): string
    {
        $n = mb_strtolower(trim($nombre));

        return strtr($n, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);
    }

    public static function tasaEfectiva(Concepto_Ivacompra $concepto): float
    {
        $tasa = round((float) ($concepto->impuestos->valor ?? 0), 3);
        if ($tasa > 0) {
            return $tasa;
        }
        $desdeAttr = round((float) ($concepto->getAttribute('_tasa_formula') ?? 0), 3);
        if ($desdeAttr > 0) {
            return $desdeAttr;
        }

        $desdeFormula = self::tasaPorcentajeDesdeFormula((string) ($concepto->formula ?? ''));
        if ($desdeFormula > 0) {
            return $desdeFormula;
        }

        return self::tasaPorcentajeDesdeNombre(
            (string) ($concepto->nombre ?? ''),
            (string) ($concepto->nombre_ia ?? ''),
        );
    }

    /**
     * Muchos gravados de Anita no tienen impuesto ni fórmula (el IVA sí: con(50)*0.21).
     * La alícuota queda en el nombre: "Gravado Bienes L.21", "Gravado al 10.5", "Gravado 21%".
     */
    public static function tasaPorcentajeDesdeNombre(string $nombre, string $nombreIa = ''): float
    {
        $texto = trim($nombre.' '.$nombreIa);
        if ($texto === '') {
            return 0.0;
        }

        if (preg_match('/10[.,]5/u', $texto)) {
            return 10.5;
        }
        if (preg_match('/(?:^|[^\d])27(?:\s*%|\b)/u', $texto)) {
            return 27.0;
        }
        if (preg_match('/(?:^|[^\d])21(?:\s*%|\b)/u', $texto)) {
            return 21.0;
        }
        if (preg_match('/(?:^|[^\d])2[.,]5(?:\s*%|\b)/u', $texto)) {
            return 2.5;
        }
        if (preg_match('/(?:^|[^\d])5\s*%/u', $texto)) {
            return 5.0;
        }

        return 0.0;
    }
}
