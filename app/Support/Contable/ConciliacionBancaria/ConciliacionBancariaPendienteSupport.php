<?php

namespace App\Support\Contable\ConciliacionBancaria;

use Carbon\Carbon;

/**
 * Clasifica pendientes al estilo carátula Contaduría
 * (cheques con Ch:/tip CH* en ventana IB vs resto; créditos banco “soporte”).
 */
final class ConciliacionBancariaPendienteSupport
{
    /**
     * Reversa / anulación de OP con cheque (ej. "ANULA OPP … Ch: 70507390").
     * No es un CHP pendiente de débito: el mayor tiene el Debe de la anulación.
     *
     * @param  array<string, mixed>  $contable
     */
    public static function esAnulaChequeContable(array $contable): bool
    {
        $desc = (string) ($contable['descripcion'] ?? '');
        if ($desc === '' || ! preg_match('/\bANULA\b/i', $desc)) {
            return false;
        }

        return ConciliacionBancariaReferenciaSupport::extraerChequeContable($contable) !== null;
    }

    /**
     * Solo cheques identificables (Ch: NNNNN o tip CH*).
     * Las ANULA con Ch: van a "Pendientes otros" (no al stock cpromae).
     *
     * @param  array<string, mixed>  $contable
     */
    public static function esChequeNoAcreditado(array $contable): bool
    {
        if (self::esAnulaChequeContable($contable)) {
            return false;
        }

        if (ConciliacionBancariaReferenciaSupport::extraerChequeContable($contable) !== null) {
            return true;
        }

        $tipo = strtoupper(trim((string) ($contable['tipo_comp'] ?? '')));

        return in_array($tipo, ['CHP', 'CHD', 'CHQ', 'CHE'], true);
    }

    /**
     * Ajuste de carátula por ANULA de cheque ya conciliado (o sin contrapartida pendiente).
     * Si el mismo Ch: sigue pendiente en el mayor (anulación antes de acreditar), no ajusta:
     * el neto ya está en libros y el stock cpromae ya sacó el anulado.
     *
     * @param  list<array<string, mixed>>  $pendientesContables
     */
    public static function sumaAnulasChequeParaCaratula(array $pendientesContables): float
    {
        /** @var array<string, list<array<string, mixed>>> $porCheque */
        $porCheque = [];
        foreach ($pendientesContables as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $nro = ConciliacionBancariaReferenciaSupport::extraerChequeContable($mov);
            if ($nro === null) {
                continue;
            }
            $porCheque[$nro][] = $mov;
        }

        $suma = 0.0;
        foreach ($porCheque as $movs) {
            $anulas = [];
            foreach ($movs as $mov) {
                if (self::esAnulaChequeContable($mov)) {
                    $anulas[] = $mov;
                }
            }
            if ($anulas === []) {
                continue;
            }
            // Queda el original u otro movimiento del mismo cheque → no inflar carátula.
            if (count($movs) > count($anulas)) {
                continue;
            }
            foreach ($anulas as $mov) {
                $suma += ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            }
        }

        return round($suma, 2);
    }

    /**
     * Cheques a incluir en carátula: identificables y con fecha en/después de la cobertura IB
     * (los anteriores no tienen extracto para matchear y no deben inflar la carátula).
     *
     * @param  array<string, mixed>  $contable
     */
    public static function esChequeParaCaratula(array $contable, ?Carbon $fechaDesdeCobertura): bool
    {
        if (! self::esChequeNoAcreditado($contable)) {
            return false;
        }
        if ($fechaDesdeCobertura === null) {
            return true;
        }

        $fecha = self::fechaContable($contable);
        if ($fecha === null) {
            return true;
        }

        return ! $fecha->lt($fechaDesdeCobertura->copy()->startOfDay());
    }

    /**
     * Créditos IB pendientes de contabilizar típicos de carátula Contaduría (CABAL / montos chicos).
     * Los créditos grandes sin contrapartida 1:1 quedan fuera de carátula (anomalia IA / otros).
     *
     * @param  array<string, mixed>  $banco
     */
    public static function esCreditoBancoParaCaratula(array $banco): bool
    {
        $imp = ConciliacionBancariaHashSupport::importeFirmadoBanco($banco);
        if ($imp <= 0.005) {
            return false;
        }

        $concepto = strtoupper(trim((string) ($banco['code_description_ib'] ?? '')));
        if (str_contains($concepto, 'CABAL')) {
            return true;
        }

        // Contaduría (MAYO): TRF / CABAL / títulos. CRED.INT no entra en carátula
        // (quedan como pendientes operativos; en Macro KSA inflaban ~60k vs Excel).
        $esTransferencia = str_contains($concepto, 'TRF')
            || str_contains($concepto, 'CR.TITULOS');
        if (! $esTransferencia) {
            return false;
        }

        $umbral = (float) config('conciliacion_bancaria.caratula_credito_max_importe', 45000);

        return abs($imp) <= $umbral;
    }

    /**
     * Solapa mes (MAYO/…) “Movimientos bancarios pendientes de contabilizar”:
     * Contaduría solo lista transferencias/créditos de soporte (CABAL, TRF chicas).
     * Cheques → solapa Pendientes; gastos (imp./IIBB/IVA/comisiones) → ING-GTOS.
     *
     * @param  array<string, mixed>  $banco
     */
    public static function esPendienteBancoParaSolapaMes(array $banco): bool
    {
        return self::esCreditoBancoParaCaratula($banco);
    }

    /**
     * @param  list<array<string, mixed>>  $pendientes
     * @return list<array<string, mixed>>
     */
    public static function filtrarPendientesBancoParaSolapaMes(array $pendientes): array
    {
        return array_values(array_filter(
            $pendientes,
            static fn (array $mov) => self::esPendienteBancoParaSolapaMes($mov),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $pendientes
     * @return array{
     *   cheques: list<array<string,mixed>>,
     *   anulas: list<array<string,mixed>>,
     *   otros: list<array<string,mixed>>,
     *   suma_cheques: float,
     *   suma_anulas: float,
     *   suma_otros: float
     * }
     */
    public static function particionarContables(array $pendientes, ?Carbon $fechaDesdeCobertura = null): array
    {
        $cheques = [];
        $anulas = [];
        $otros = [];
        $sumaCheques = 0.0;
        $sumaAnulas = 0.0;
        $sumaOtros = 0.0;

        foreach ($pendientes as $mov) {
            $imp = ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            if (self::esAnulaChequeContable($mov)) {
                // Contaduría las busca en “pendiente de conciliar” (cheques), no en otros.
                $anulas[] = $mov;
                $sumaAnulas += $imp;
            } elseif (self::esChequeParaCaratula($mov, $fechaDesdeCobertura)) {
                $cheques[] = $mov;
                $sumaCheques += $imp;
            } else {
                $otros[] = $mov;
                $sumaOtros += $imp;
            }
        }

        return [
            'cheques' => $cheques,
            'anulas' => $anulas,
            'otros' => $otros,
            'suma_cheques' => round($sumaCheques, 2),
            'suma_anulas' => round($sumaAnulas, 2),
            'suma_otros' => round($sumaOtros, 2),
        ];
    }

    /**
     * ANULA de cheque ya conciliado (sin el original pendiente): Contaduría las necesita
     * en “pendiente de conciliar”. Si el Ch: todavía tiene el OPP/CHP pendiente, no se lista
     * acá (el neto ya está en libros).
     *
     * @param  list<array<string, mixed>>  $pendientesContables
     * @return list<array<string, mixed>>
     */
    public static function filasAnulasParaPendientesCheques(array $pendientesContables): array
    {
        /** @var array<string, list<array<string, mixed>>> $porCheque */
        $porCheque = [];
        foreach ($pendientesContables as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $nro = ConciliacionBancariaReferenciaSupport::extraerChequeContable($mov);
            if ($nro === null) {
                continue;
            }
            $porCheque[$nro][] = $mov;
        }

        $out = [];
        foreach ($porCheque as $nro => $movs) {
            $anulas = array_values(array_filter(
                $movs,
                static fn (array $m): bool => self::esAnulaChequeContable($m),
            ));
            if ($anulas === [] || count($movs) > count($anulas)) {
                continue;
            }
            foreach ($anulas as $mov) {
                $imp = ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
                $fecha = self::fechaContable($mov);
                $out[] = [
                    'tip' => 'ANULA',
                    'numero_cheque' => (string) $nro,
                    'fecha_emision' => $fecha?->toDateString(),
                    'fecha_cheque' => $fecha?->toDateString(),
                    'fecha_entrega' => null,
                    'fecha_conciliacion' => null,
                    'entregado_a' => trim((string) ($mov['descripcion'] ?? '')),
                    'importe' => round($imp, 2),
                    'incluye_caratula' => true,
                    'es_anula_cheque' => true,
                    'debe' => $imp > 0.005 ? round($imp, 2) : null,
                    'haber' => $imp < -0.005 ? round(abs($imp), 2) : null,
                    'nro_asiento_fmt' => (string) ($mov['nro_asiento_fmt'] ?? $mov['nro_asiento'] ?? ''),
                    'tipo_comp' => (string) ($mov['tipo_comp'] ?? 'OPP'),
                    'hash' => (string) ($mov['hash'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pendientes
     * @return array{
     *   creditos: list<array<string,mixed>>,
     *   debitos: list<array<string,mixed>>,
     *   caratula: list<array<string,mixed>>,
     *   suma_creditos: float,
     *   suma_debitos: float,
     *   suma_caratula: float
     * }
     */
    public static function particionarBanco(array $pendientes): array
    {
        $creditos = [];
        $debitos = [];
        $caratula = [];
        $sumaC = 0.0;
        $sumaD = 0.0;
        $sumaCar = 0.0;

        foreach ($pendientes as $mov) {
            $imp = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
            if ($imp > 0.005) {
                $creditos[] = $mov;
                $sumaC += $imp;
                if (self::esCreditoBancoParaCaratula($mov)) {
                    $caratula[] = $mov;
                    $sumaCar += $imp;
                }
            } elseif ($imp < -0.005) {
                $debitos[] = $mov;
                $sumaD += $imp;
            }
        }

        return [
            'creditos' => $creditos,
            'debitos' => $debitos,
            'caratula' => $caratula,
            'suma_creditos' => round($sumaC, 2),
            'suma_debitos' => round($sumaD, 2),
            'suma_caratula' => round($sumaCar, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $contable
     */
    private static function fechaContable(array $contable): ?Carbon
    {
        $raw = $contable['fecha'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            if (is_int($raw) || (is_string($raw) && preg_match('/^\d{8}$/', $raw))) {
                return Carbon::createFromFormat('Ymd', (string) $raw)->startOfDay();
            }

            return Carbon::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
