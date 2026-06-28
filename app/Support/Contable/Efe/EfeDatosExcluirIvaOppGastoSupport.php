<?php

namespace App\Support\Contable\Efe;

/**
 * Anita no separa IVA crédito (214010) en concepto 55 cuando el mismo OPP ya tiene gasto 521xxx
 * en otro concepto (ej. REDGUARD c44 + IVA; Excel solo c44).
 */
class EfeDatosExcluirIvaOppGastoSupport
{
    private const CUENTA_IVA_CREDITO_DESDE = 214010000;

    private const CUENTA_IVA_CREDITO_HASTA = 215000000;

    private const CONCEPTO_IIBB_BANCO = 55;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas): array
    {
        if ($filas === []) {
            return $filas;
        }

        $asientosConGasto521 = $this->indexarAsientosConGasto521($filas);
        $asientosConHonorariosAdelanto = $this->indexarAsientosConHonorariosAdelanto($filas);

        if ($asientosConGasto521 === [] && $asientosConHonorariosAdelanto === []) {
            return $filas;
        }

        $resultado = [];

        foreach ($filas as $fila) {
            if ($this->debeExcluirFila($fila, $asientosConGasto521, $asientosConHonorariosAdelanto)) {
                continue;
            }

            $resultado[] = $fila;
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, true>
     */
    private function indexarAsientosConGasto521(array $filas): array
    {
        /** @var array<int, true> */
        $mapa = [];

        foreach ($filas as $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            $cuenta = (int) ($fila['cuenta'] ?? 0);
            if ($cuenta < 521000000 || $cuenta >= 600000000) {
                continue;
            }

            $concepto = (int) ($fila['concepto_id'] ?? 0);
            if ($concepto <= 0 || $concepto === self::CONCEPTO_IIBB_BANCO) {
                continue;
            }

            if (round((float) ($fila['pagos'] ?? 0), 2) <= 0) {
                continue;
            }

            $mapa[$asiento] = true;
        }

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, true>
     */
    private function indexarAsientosConHonorariosAdelanto(array $filas): array
    {
        /** @var array<int, true> */
        $mapa = [];

        foreach ($filas as $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            $cuenta = (int) ($fila['cuenta'] ?? 0);
            if ($cuenta < 114020000 || $cuenta >= 114021000) {
                continue;
            }

            $concepto = (int) ($fila['concepto_id'] ?? 0);
            if ($concepto <= 0 || $concepto === self::CONCEPTO_IIBB_BANCO) {
                continue;
            }

            if (round((float) ($fila['pagos'] ?? 0), 2) <= 0) {
                continue;
            }

            $mapa[$asiento] = true;
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<int, true>  $asientosConGasto521
     * @param  array<int, true>  $asientosConHonorariosAdelanto
     */
    private function debeExcluirFila(
        array $fila,
        array $asientosConGasto521,
        array $asientosConHonorariosAdelanto,
    ): bool {
        $asiento = (int) ($fila['nro_asiento'] ?? 0);
        if ($asiento <= 0) {
            return false;
        }

        if (! isset($asientosConGasto521[$asiento]) && ! isset($asientosConHonorariosAdelanto[$asiento])) {
            return false;
        }

        if (strtoupper(trim((string) ($fila['tipo_comp'] ?? ''))) !== 'OPP') {
            return false;
        }

        $cuenta = (int) ($fila['cuenta'] ?? 0);
        if ($cuenta < self::CUENTA_IVA_CREDITO_DESDE || $cuenta >= self::CUENTA_IVA_CREDITO_HASTA) {
            return false;
        }

        return (int) ($fila['concepto_id'] ?? 0) === self::CONCEPTO_IIBB_BANCO;
    }
}
