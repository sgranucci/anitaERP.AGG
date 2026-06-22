<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;

/**
 * Reimputa anticipos al mayor gasto de la misma operación (l-mayorconc.c reimputa_cuentas).
 *
 * En el EFE también aplica a 114040-001: Anita muestra la cuenta de gasto del pago, no el anticipo.
 */
class EfeDatosReimputaAnticipoSupport
{
    /** @var array<int, true> */
    private array $cuentasReimputa;

    public function __construct(
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport = new EfeClasificacionConceptoSupport(),
    ) {
        $this->cuentasReimputa = array_fill_keys(array_merge(
            MayorConceptoMemoriaMotor::CUENTAS_REIMPUTA_CONCEPTO,
            [114040001],
        ), true);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $nombresConcepto): array
    {
        if ($filas === []) {
            return $filas;
        }

        $porOperacion = [];

        foreach ($filas as $indice => $fila) {
            $nroOperacion = (int) ($fila['nro_asiento'] ?? 0);
            if ($nroOperacion <= 0) {
                continue;
            }

            $porOperacion[$nroOperacion][] = $indice;
        }

        foreach ($porOperacion as $indices) {
            $destino = $this->resolverIndiceDestino($filas, $indices);
            if ($destino === null) {
                continue;
            }

            $filaDestino = $filas[$destino];
            $conceptoDestino = (int) ($filaDestino['concepto_id'] ?? 0);
            $nombreDestino = $nombresConcepto[$conceptoDestino]
                ?? (string) ($filaDestino['concepto_nombre'] ?? '');

            foreach ($indices as $indice) {
                $cuenta = (int) ($filas[$indice]['cuenta'] ?? 0);
                if (! isset($this->cuentasReimputa[$cuenta])) {
                    continue;
                }

                $filas[$indice]['cuenta'] = (int) ($filaDestino['cuenta'] ?? $cuenta);
                $filas[$indice]['cuenta_codigo'] = (string) ($filaDestino['cuenta_codigo'] ?? '');
                $filas[$indice]['cuenta_nombre'] = (string) ($filaDestino['cuenta_nombre'] ?? '');
                $filas[$indice]['concepto_id'] = $conceptoDestino;
                $filas[$indice]['concepto_nombre'] = $nombreDestino;
                $filas[$indice]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave(
                    $conceptoDestino,
                    $nombreDestino,
                );
            }
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $indices
     */
    private function resolverIndiceDestino(array $filas, array $indices): ?int
    {
        $indiceDestino = null;
        $maxMonto = 0.0;

        foreach ($indices as $indice) {
            $fila = $filas[$indice];
            $cuenta = (int) ($fila['cuenta'] ?? 0);

            if (isset($this->cuentasReimputa[$cuenta])) {
                continue;
            }

            if ($this->esDisponibilidad($fila)) {
                continue;
            }

            $monto = max((float) ($fila['pagos'] ?? 0), (float) ($fila['cobros'] ?? 0));
            if ($monto > $maxMonto) {
                $maxMonto = $monto;
                $indiceDestino = $indice;
            }
        }

        return $indiceDestino;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function esDisponibilidad(array $fila): bool
    {
        $cuenta = (int) ($fila['cuenta'] ?? 0);
        $cuentaDisp = (int) ($fila['cuenta_disponibilidad'] ?? 0);

        if ($cuentaDisp > 0) {
            return $cuenta === $cuentaDisp;
        }

        return $cuenta > 0 && $cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD;
    }
}
