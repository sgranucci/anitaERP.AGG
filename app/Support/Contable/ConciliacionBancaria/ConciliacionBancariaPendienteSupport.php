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
     * Solo cheques identificables (Ch: NNNNN o tip CH*).
     *
     * @param  array<string, mixed>  $contable
     */
    public static function esChequeNoAcreditado(array $contable): bool
    {
        if (ConciliacionBancariaReferenciaSupport::extraerChequeContable($contable) !== null) {
            return true;
        }

        $tipo = strtoupper(trim((string) ($contable['tipo_comp'] ?? '')));

        return in_array($tipo, ['CHP', 'CHD', 'CHQ', 'CHE'], true);
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

        // Solo transferencias/créditos chicos (evita impuestos u otros créditos sueltos).
        $esTransferencia = str_contains($concepto, 'TRF')
            || str_contains($concepto, 'CRED.INT')
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
     *   otros: list<array<string,mixed>>,
     *   suma_cheques: float,
     *   suma_otros: float
     * }
     */
    public static function particionarContables(array $pendientes, ?Carbon $fechaDesdeCobertura = null): array
    {
        $cheques = [];
        $otros = [];
        $sumaCheques = 0.0;
        $sumaOtros = 0.0;

        foreach ($pendientes as $mov) {
            $imp = ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            if (self::esChequeParaCaratula($mov, $fechaDesdeCobertura)) {
                $cheques[] = $mov;
                $sumaCheques += $imp;
            } else {
                $otros[] = $mov;
                $sumaOtros += $imp;
            }
        }

        return [
            'cheques' => $cheques,
            'otros' => $otros,
            'suma_cheques' => round($sumaCheques, 2),
            'suma_otros' => round($sumaOtros, 2),
        ];
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
