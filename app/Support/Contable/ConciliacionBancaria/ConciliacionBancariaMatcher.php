<?php

namespace App\Support\Contable\ConciliacionBancaria;

final class ConciliacionBancariaMatcher
{
    /**
     * @param  list<array<string, mixed>>  $contables
     * @param  list<array<string, mixed>>  $banco
     * @param  array<string, true>  $hashesContablesConciliados
     * @param  array<string, true>  $hashesBancoConciliados
     * @return array{
     *   pares: list<array{contable: array<string,mixed>, banco: array<string,mixed>, score: int}>,
     *   contables_pendientes: list<array<string,mixed>>,
     *   banco_pendientes: list<array<string,mixed>>
     * }
     */
    public static function emparejar(
        array $contables,
        array $banco,
        array $hashesContablesConciliados = [],
        array $hashesBancoConciliados = [],
    ): array {
        $tolerancia = (float) config('conciliacion_bancaria.tolerancia_importe', 0.05);
        $diasTol = max(0, (int) config('conciliacion_bancaria.dias_tolerancia_fecha', 3));

        $contablesLibres = [];
        foreach ($contables as $c) {
            $hash = (string) ($c['hash'] ?? '');
            if ($hash !== '' && isset($hashesContablesConciliados[$hash])) {
                continue;
            }
            $contablesLibres[] = $c;
        }

        $bancoLibre = [];
        foreach ($banco as $b) {
            $hash = (string) ($b['hash'] ?? '');
            if ($hash !== '' && isset($hashesBancoConciliados[$hash])) {
                continue;
            }
            $bancoLibre[] = $b;
        }

        $pares = [];
        $usadosCont = [];
        $usadosBanco = [];

        foreach ($contablesLibres as $ic => $c) {
            $impC = ConciliacionBancariaHashSupport::importeFirmadoContable($c);
            if (abs($impC) < 0.005) {
                continue;
            }

            $mejor = null;
            $mejorScore = -1;
            $mejorIb = null;

            foreach ($bancoLibre as $ib => $b) {
                if (isset($usadosBanco[$ib])) {
                    continue;
                }

                $impB = ConciliacionBancariaHashSupport::importeFirmadoBanco($b);
                if (abs($impC - $impB) > $tolerancia) {
                    continue;
                }

                $score = self::puntajeMatch($c, $b, $diasTol);
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejor = $c;
                    $mejorIb = $ib;
                }
            }

            if ($mejor !== null && $mejorIb !== null && $mejorScore >= 10) {
                $pares[] = [
                    'contable' => $mejor,
                    'banco' => $bancoLibre[$mejorIb],
                    'score' => $mejorScore,
                ];
                $usadosCont[$ic] = true;
                $usadosBanco[$mejorIb] = true;
            }
        }

        $contPend = [];
        foreach ($contablesLibres as $ic => $c) {
            if (! isset($usadosCont[$ic])) {
                $contPend[] = $c;
            }
        }

        $bancoPend = [];
        foreach ($bancoLibre as $ib => $b) {
            if (! isset($usadosBanco[$ib])) {
                $bancoPend[] = $b;
            }
        }

        return [
            'pares' => $pares,
            'contables_pendientes' => $contPend,
            'banco_pendientes' => $bancoPend,
        ];
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    private static function puntajeMatch(array $contable, array $banco, int $diasTol): int
    {
        $score = 10;

        $fechaC = self::fechaYmd($contable['fecha'] ?? null);
        $fechaB = self::fechaYmd($banco['process_date'] ?? null);

        if ($fechaC > 0 && $fechaB > 0) {
            $diff = abs($fechaC - $fechaB);
            if ($diff === 0) {
                $score += 30;
            } elseif ($diff <= $diasTol) {
                $score += 15;
            } else {
                return -1;
            }
        }

        $refB = (string) ($banco['voucher_number'] ?? '');
        $descC = (string) ($contable['descripcion'] ?? '');
        $compC = (string) ($contable['comprobante'] ?? '');

        if ($refB !== '' && (str_contains($descC, $refB) || str_contains($compC, $refB))) {
            $score += 25;
        }

        $concepto = strtoupper((string) ($banco['code_description_ib'] ?? ''));
        if ($concepto !== '' && str_contains(strtoupper($descC), $concepto)) {
            $score += 10;
        }

        return $score;
    }

    private static function fechaYmd(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        if (is_int($valor)) {
            return $valor;
        }

        if ($valor instanceof \DateTimeInterface) {
            return (int) $valor->format('Ymd');
        }

        $ts = strtotime((string) $valor);

        return $ts !== false ? (int) date('Ymd', $ts) : 0;
    }
}
