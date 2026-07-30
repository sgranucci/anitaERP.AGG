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

        [$contablesLibres, $bancoLibre] = self::filtrarLibres(
            $contables,
            $banco,
            $hashesContablesConciliados,
            $hashesBancoConciliados,
        );

        $pares = [];
        $usadosCont = [];
        $usadosBanco = [];

        self::emparejarPorReferenciaFuerte(
            $contablesLibres,
            $bancoLibre,
            $tolerancia,
            $diasTol,
            $pares,
            $usadosCont,
            $usadosBanco,
        );

        self::emparejarPorImporteYFecha(
            $contablesLibres,
            $bancoLibre,
            $tolerancia,
            $diasTol,
            $pares,
            $usadosCont,
            $usadosBanco,
        );

        // Pasada 3: importe único en ambos lados (candidatos IA "cercanos") con ventana ampliada.
        self::emparejarPorImporteUnico(
            $contablesLibres,
            $bancoLibre,
            $tolerancia,
            $pares,
            $usadosCont,
            $usadosBanco,
        );

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
     * @param  list<array<string, mixed>>  $contables
     * @param  list<array<string, mixed>>  $banco
     * @param  array<string, true>  $hashesContablesConciliados
     * @param  array<string, true>  $hashesBancoConciliados
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>}
     */
    private static function filtrarLibres(
        array $contables,
        array $banco,
        array $hashesContablesConciliados,
        array $hashesBancoConciliados,
    ): array {
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

        return [$contablesLibres, $bancoLibre];
    }

    /**
     * Cheque (Ch: / voucher), CUIT y referencia explícita en texto contable.
     *
     * @param  list<array<string, mixed>>  $contablesLibres
     * @param  list<array<string, mixed>>  $bancoLibre
     * @param  list<array{contable: array<string,mixed>, banco: array<string,mixed>, score: int}>  $pares
     * @param  array<int, true>  $usadosCont
     * @param  array<int, true>  $usadosBanco
     */
    private static function emparejarPorReferenciaFuerte(
        array $contablesLibres,
        array $bancoLibre,
        float $tolerancia,
        int $diasTol,
        array &$pares,
        array &$usadosCont,
        array &$usadosBanco,
    ): void {
        foreach ($contablesLibres as $ic => $c) {
            if (isset($usadosCont[$ic])) {
                continue;
            }

            $impC = ConciliacionBancariaHashSupport::importeFirmadoContable($c);
            if (abs($impC) < 0.005) {
                continue;
            }

            $tieneCheque = ConciliacionBancariaReferenciaSupport::extraerChequeContable($c) !== null;
            $tieneCuit = ConciliacionBancariaReferenciaSupport::extraerCuitContable($c) !== null;

            if (! $tieneCheque && ! $tieneCuit) {
                continue;
            }

            $mejorIb = null;
            $mejorScore = -1;

            foreach ($bancoLibre as $ib => $b) {
                if (isset($usadosBanco[$ib])) {
                    continue;
                }

                $impB = ConciliacionBancariaHashSupport::importeFirmadoBanco($b);
                if (abs($impC - $impB) > $tolerancia) {
                    continue;
                }

                $refScore = ConciliacionBancariaReferenciaSupport::puntajeReferencia($c, $b);
                if ($refScore < 50) {
                    continue;
                }

                if (! self::fechasCompatibles($c, $b, $diasTol)) {
                    continue;
                }

                $score = 100 + $refScore;
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorIb = $ib;
                }
            }

            if ($mejorIb !== null && $mejorScore >= 150) {
                $pares[] = [
                    'contable' => $c,
                    'banco' => $bancoLibre[$mejorIb],
                    'score' => $mejorScore,
                ];
                $usadosCont[$ic] = true;
                $usadosBanco[$mejorIb] = true;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $contablesLibres
     * @param  list<array<string, mixed>>  $bancoLibre
     * @param  list<array{contable: array<string,mixed>, banco: array<string,mixed>, score: int}>  $pares
     * @param  array<int, true>  $usadosCont
     * @param  array<int, true>  $usadosBanco
     */
    private static function emparejarPorImporteYFecha(
        array $contablesLibres,
        array $bancoLibre,
        float $tolerancia,
        int $diasTol,
        array &$pares,
        array &$usadosCont,
        array &$usadosBanco,
    ): void {
        foreach ($contablesLibres as $ic => $c) {
            if (isset($usadosCont[$ic])) {
                continue;
            }

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
    }

    /**
     * Empareja cuando el importe firmado aparece una sola vez en contable y en banco
     * (dentro de la ventana de días configurada). Evita dejar candidatos IA sin persistir.
     *
     * @param  list<array<string, mixed>>  $contablesLibres
     * @param  list<array<string, mixed>>  $bancoLibre
     * @param  list<array{contable: array<string,mixed>, banco: array<string,mixed>, score: int}>  $pares
     * @param  array<int, true>  $usadosCont
     * @param  array<int, true>  $usadosBanco
     */
    private static function emparejarPorImporteUnico(
        array $contablesLibres,
        array $bancoLibre,
        float $tolerancia,
        array &$pares,
        array &$usadosCont,
        array &$usadosBanco,
    ): void {
        $diasMax = max(
            (int) config('conciliacion_bancaria.dias_tolerancia_fecha_pago', 7),
            (int) config('conciliacion_bancaria.dias_tolerancia_fecha_unico', 15),
        );

        $idxContPorImporte = [];
        foreach ($contablesLibres as $ic => $c) {
            if (isset($usadosCont[$ic])) {
                continue;
            }
            $imp = ConciliacionBancariaHashSupport::importeFirmadoContable($c);
            if (abs($imp) < 0.005) {
                continue;
            }
            $key = number_format($imp, 2, '.', '');
            $idxContPorImporte[$key][] = $ic;
        }

        $idxBancoPorImporte = [];
        foreach ($bancoLibre as $ib => $b) {
            if (isset($usadosBanco[$ib])) {
                continue;
            }
            $imp = ConciliacionBancariaHashSupport::importeFirmadoBanco($b);
            if (abs($imp) < 0.005) {
                continue;
            }
            $key = number_format($imp, 2, '.', '');
            $idxBancoPorImporte[$key][] = $ib;
        }

        foreach ($idxContPorImporte as $key => $ics) {
            if (count($ics) !== 1) {
                continue;
            }
            $ibs = $idxBancoPorImporte[$key] ?? [];
            if (count($ibs) !== 1) {
                // También probar signo invertido no aplica: firmados ya alineados debe-haber vs D/C.
                continue;
            }
            $ic = $ics[0];
            $ib = $ibs[0];
            if (isset($usadosCont[$ic]) || isset($usadosBanco[$ib])) {
                continue;
            }
            $c = $contablesLibres[$ic];
            $b = $bancoLibre[$ib];
            if (! self::fechasCompatibles($c, $b, $diasMax)) {
                continue;
            }
            $score = 35 + ConciliacionBancariaReferenciaSupport::puntajeReferencia($c, $b);
            $pares[] = [
                'contable' => $c,
                'banco' => $b,
                'score' => $score,
            ];
            $usadosCont[$ic] = true;
            $usadosBanco[$ib] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    private static function puntajeMatch(array $contable, array $banco, int $diasTol): int
    {
        if (! self::fechasCompatibles($contable, $banco, $diasTol)) {
            return -1;
        }

        $score = 10 + ConciliacionBancariaReferenciaSupport::puntajeReferencia($contable, $banco);

        $fechaC = self::fechaYmd($contable['fecha'] ?? null);
        $fechaB = self::fechaYmd($banco['process_date'] ?? null);
        if ($fechaC > 0 && $fechaB > 0) {
            $diff = self::diffDiasCalendario($fechaC, $fechaB);
            $maxDias = ConciliacionBancariaReferenciaSupport::diasToleranciaFecha($contable, $banco, $diasTol);
            if ($diff === 0) {
                $score += 30;
            } elseif ($diff <= $diasTol) {
                $score += 15;
            } elseif ($diff <= $maxDias) {
                $score += 8;
            }
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    private static function fechasCompatibles(array $contable, array $banco, int $diasTol): bool
    {
        $fechaC = self::fechaYmd($contable['fecha'] ?? null);
        $fechaB = self::fechaYmd($banco['process_date'] ?? null);

        if ($fechaC <= 0 || $fechaB <= 0) {
            return true;
        }

        $maxDias = ConciliacionBancariaReferenciaSupport::diasToleranciaFecha($contable, $banco, $diasTol);

        try {
            $c = \Carbon\Carbon::createFromFormat('Ymd', (string) $fechaC);
            $b = \Carbon\Carbon::createFromFormat('Ymd', (string) $fechaB);

            return $c->diffInDays($b) <= $maxDias;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function diffDiasCalendario(int $fechaC, int $fechaB): int
    {
        try {
            $c = \Carbon\Carbon::createFromFormat('Ymd', (string) $fechaC);
            $b = \Carbon\Carbon::createFromFormat('Ymd', (string) $fechaB);

            return (int) $c->diffInDays($b);
        } catch (\Throwable) {
            return 999;
        }
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
