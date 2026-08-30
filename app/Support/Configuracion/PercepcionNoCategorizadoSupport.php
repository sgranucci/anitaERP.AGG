<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\ConfiguracionPercepcionNoCategorizado;
use Illuminate\Support\Facades\Schema;

/**
 * Percepción IVA a sujetos no categorizados (RG 2126 / RG 5710).
 * Distinta de la percepción IVA a RI (RG 5329) de PercepcionIvaSujetoSupport.
 *
 * RG 2126 art. 5 / a-comprob.c: base = tot_fact (precio neto + IVA + otros
 * tributos ya sumados, antes de esta percepción). Tasa llena o la mitad si
 * toda la operatoria es IVA reducido.
 */
final class PercepcionNoCategorizadoSupport
{
    /** Código AFIP CondicionIVAReceptor: Sujeto No Categorizado. */
    public const CODIGO_AFIP = 7;

    /** Código del régimen de percepción (catálogo, no impuesto nacional). */
    public const IMPUESTO_CODIGO = 'PNC';

    /** Tributo ARCA / FACEL (otros tributos). */
    public const CODIGO_TRIBUTO_ARCA = 99;

    public const CONCEPTO_PREFIJO = 'Percepcion no categorizado';

    private static ?object $fila = null;

    private static bool $filaLeida = false;

    public static function olvidarCache(): void
    {
        self::$fila = null;
        self::$filaLeida = false;
        RegimenPercepcionSupport::olvidarCache();
    }

    public static function corresponde(?object $condicioniva): bool
    {
        if ($condicioniva === null) {
            return false;
        }

        $codigo = trim((string) ($condicioniva->codigoexterno ?? ''));
        if ($codigo !== '') {
            return (int) $codigo === self::CODIGO_AFIP;
        }

        return (int) ($condicioniva->id ?? 0) === 6;
    }

    /**
     * Mostrador pone omitir_percepciones en letra B para no aplicar IIBB ni perc. IVA 3 % (RG 5329).
     * La RG 2126 sí corresponde a esa letra: no taparla, salvo que el caller ya haya pedido
     * omitir todas (gastronomía / estacionamiento).
     */
    public static function aplicarAunqueSeOmitanOtras(bool $omitirPercepcionesYaPedido, ?object $condicioniva): bool
    {
        return ! $omitirPercepcionesYaPedido && self::corresponde($condicioniva);
    }

    public static function habilitada(): bool
    {
        $fila = self::fila();
        if ($fila !== null) {
            return (bool) $fila->habilitado;
        }

        return strtolower(trim((string) config('anita.agente_percepcion_no_categorizado', 'no'))) === 'si';
    }

    public static function tasaBase(): float
    {
        $fila = self::fila();
        if ($fila !== null && (float) $fila->tasa > 0.0001) {
            return (float) $fila->tasa;
        }

        $tasa = (float) config('anita.tasa_percepcion_no_categorizado', 10.5);

        return $tasa > 0.0001 ? $tasa : 10.5;
    }

    public static function minimo(): float
    {
        $fila = self::fila();
        if ($fila !== null) {
            return (float) $fila->minimo;
        }

        return (float) config('anita.minimo_percepcion_no_categorizado', 0);
    }

    /**
     * Tasa a aplicar: 10,50 % (RG 2126 art. 5.a) o la mitad si todos los IVA gravados son reducidos (~10,5 %).
     *
     * @param  list<float>  $tasasIvaGravadas
     */
    public static function tasaParaOperacion(array $tasasIvaGravadas): float
    {
        $base = self::tasaBase();
        $gravadas = [];
        foreach ($tasasIvaGravadas as $tasa) {
            $tasa = (float) $tasa;
            if ($tasa > 0.0001) {
                $gravadas[] = $tasa;
            }
        }
        if ($gravadas === []) {
            return $base;
        }

        foreach ($gravadas as $tasa) {
            if (abs($tasa - 10.5) > 0.51) {
                return $base;
            }
        }

        return round($base / 2, 4);
    }

    /**
     * @param  list<float>  $tasasIvaGravadas
     * @return array{base: float, tasa: float, importe: float, impuesto_id: int|null}
     */
    public static function calcular(float $base, array $tasasIvaGravadas): array
    {
        $base = round($base, 2);
        $tasa = self::tasaParaOperacion($tasasIvaGravadas);
        $importe = round($base * $tasa / 100, 2);
        if ($importe <= self::minimo() + 0.00001) {
            $importe = 0.0;
        }

        return [
            'base' => $base,
            'tasa' => $tasa,
            'importe' => $importe,
            'impuesto_id' => null,
        ];
    }

    public static function concepto(float $tasa): string
    {
        return self::CONCEPTO_PREFIJO.' '.rtrim(rtrim(number_format($tasa, 2, '.', ''), '0'), '.').'%';
    }

    public static function esConcepto(string $concepto): bool
    {
        $c = mb_strtolower($concepto);

        return str_contains($c, 'percepcion no categ')
            || str_contains($c, 'perc. no categ');
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    public static function importeDesdeConceptos(array $conceptos): float
    {
        $total = 0.0;
        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            if (self::esConcepto((string) ($concepto['concepto'] ?? ''))) {
                $total += (float) ($concepto['importe'] ?? 0);
            }
        }

        return round($total, 2);
    }

    public static function impuestoId(): ?int
    {
        return null;
    }

    public static function codigoCuentaContable(): string
    {
        return RegimenPercepcionSupport::codigoCuentaFallback(RegimenPercepcionSupport::CODIGO_PNC);
    }

    /**
     * Códigos de cuenta del asiento: grilla del régimen PNC y fallback de config.
     *
     * @return list<string>
     */
    public static function codigosCuentaContableEmpresa(int $empresaId): array
    {
        return RegimenPercepcionSupport::codigosCuentaContableEmpresa(
            RegimenPercepcionSupport::CODIGO_PNC,
            $empresaId
        );
    }

    private static function fila(): ?object
    {
        if (self::$filaLeida) {
            return self::$fila;
        }
        self::$filaLeida = true;

        $catalogo = RegimenPercepcionSupport::porCodigo(RegimenPercepcionSupport::CODIGO_PNC);
        if ($catalogo !== null) {
            self::$fila = (object) [
                'habilitado' => (bool) $catalogo->habilitado,
                'tasa' => (float) $catalogo->tasa,
                'minimo' => (float) $catalogo->minimo_importe,
            ];

            return self::$fila;
        }

        if (! Schema::hasTable('configuracion_percepcion_no_categorizado')) {
            return null;
        }
        self::$fila = ConfiguracionPercepcionNoCategorizado::query()->first();

        return self::$fila;
    }
}
