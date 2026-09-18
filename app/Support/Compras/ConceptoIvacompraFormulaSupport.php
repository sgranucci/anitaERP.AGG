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
            if (! in_array($tipoI, ['I', 'G', 'E', 'T', 'P', 'B', 'M', 'S', 'A', 'N'], true)) {
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
            if (! in_array($tipoI, ['I', 'G', 'E', 'T', 'P', 'B', 'M', 'S', 'A', 'N'], true)) {
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

        return self::tasaPorcentajeDesdeFormula((string) ($concepto->formula ?? ''));
    }
}
