<?php

namespace App\Support\Contable\Efe;

/**
 * Ajuste EFE: concepto/cuenta de gasto en OPP vía auxpag → aplicped → COM (facturas anticipadas incluidas).
 */
class EfeDatosOppGastoComSupport
{
    private const CUENTA_ANTICIPO = 114040001;

    private const CUENTA_CHEQUE = 117010001;

    private const CUENTA_HONORARIOS_ADELANTO = 114020009;

    /** Cuentas puente/reimputa que Anita muestra con el concepto del COM. */
    private const CUENTAS_PUENTE = [
        114040001,
        117010001,
        114010002,
        114010009,
        114010011,
        114020009,
    ];

    public function __construct(
        private readonly EfeOppComGastoResolverSupport $resolverSupport,
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $filtros, array $nombresConcepto): array
    {
        if ($filas === []) {
            return $filas;
        }

        $this->resolverSupport->preparar($filtros);

        $recPorAsiento = $this->indexarRecPorAsiento($filas);
        $gastoPorAsiento = [];

        foreach ($recPorAsiento as $asiento => $rec) {
            $gasto = $this->resolverSupport->resolverPorRec($rec);
            if ($gasto !== null) {
                $gastoPorAsiento[$asiento] = $gasto;
            }
        }

        if ($gastoPorAsiento === []) {
            return $filas;
        }

        foreach ($filas as $indice => $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0 || ! isset($gastoPorAsiento[$asiento])) {
                continue;
            }

            $gasto = $gastoPorAsiento[$asiento];
            $cuenta = (int) ($fila['cuenta'] ?? 0);
            if (! $this->cuentaAplica($cuenta)) {
                continue;
            }

            $rec = $recPorAsiento[$asiento] ?? '';
            if (! $this->debeAjustarFila($fila, $cuenta, $gasto, $rec)) {
                continue;
            }

            $conceptoId = (int) ($gasto['concepto_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }

            $nombre = $nombresConcepto[$conceptoId] ?? (string) ($fila['concepto_nombre'] ?? '');
            $filas[$indice]['concepto_id'] = $conceptoId;
            $filas[$indice]['concepto_nombre'] = $nombre;
            $filas[$indice]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave($conceptoId, $nombre);

            $cuentaGasto = (int) ($gasto['cuenta'] ?? 0);
            if ($cuentaGasto <= 0 || $cuentaGasto === $cuenta) {
                continue;
            }

            // Puentes anticipo/cheque: solo reemplazar cuenta si el COM/FIB trae bienes de uso (123010).
            if ($this->esCuentaPuente($cuenta) && ! $this->esCuentaBienesUsoPuente($cuentaGasto)) {
                continue;
            }

            $filas[$indice]['cuenta'] = $cuentaGasto;
            $filas[$indice]['cuenta_codigo'] = (string) ($gasto['cuenta_codigo'] ?? '');
            $filas[$indice]['cuenta_nombre'] = (string) ($gasto['cuenta_nombre'] ?? '');
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array{cuenta: int, concepto_id: int, cuenta_codigo: string, cuenta_nombre: string}  $gasto
     */
    private function debeAjustarFila(array $fila, int $cuenta, array $gasto, string $rec = ''): bool
    {
        if ($this->esCuentaIvaCredito($cuenta)) {
            return false;
        }

        $conceptoActual = (int) ($fila['concepto_id'] ?? 0);
        $conceptoDestino = (int) ($gasto['concepto_id'] ?? 0);
        if ($conceptoDestino <= 0) {
            return false;
        }

        // Gaming supplies (12) ya fijado: no pisar con axp_concepto 24 del FNS/FIB.
        if ($conceptoActual === EfeDatosGamingSuppliesSupport::CONCEPTO_GAMING_SUPPLIES) {
            return false;
        }

        // Concepto 5 solo por axp (sin cuenta COM): Anita Datos no pisa C20 PAPELERA
        // ni cheques con solo FIB conc=5 (van a varios/publicidad). Sí aplica con FGA/CIB.
        if ($conceptoDestino === EfeDatosGastronomiaSupport::CONCEPTO_GASTRONOMIA
            && (int) ($gasto['cuenta'] ?? 0) <= 0) {
            if ($conceptoActual === EfeDatosVariosSupport::CONCEPTO_VARIOS) {
                return false;
            }
            if ($rec !== '' && ! $this->resolverSupport->recTieneFacturaGastroFuerte($rec)) {
                return false;
            }
        }

        if ($this->esCuentaHonorariosAdelanto($cuenta)) {
            return $conceptoActual === 0;
        }

        if ($this->esCuentaPuente($cuenta)) {
            if ($conceptoActual === 0) {
                return true;
            }

            if ($conceptoActual === 55 && $cuenta === self::CUENTA_ANTICIPO) {
                return true;
            }

            return $conceptoDestino !== $conceptoActual
                && in_array($conceptoActual, [12, 20, 65], true);
        }

        if ($cuenta === self::CUENTA_ANTICIPO && $conceptoActual === 65 && $conceptoDestino === 24) {
            return true;
        }

        return $conceptoActual === 0;
    }

    private function cuentaAplica(int $cuenta): bool
    {
        if ($this->esCuentaPuente($cuenta) || $this->esCuentaHonorariosAdelanto($cuenta)) {
            return true;
        }

        return $cuenta === self::CUENTA_ANTICIPO || $cuenta === self::CUENTA_CHEQUE;
    }

    private function esCuentaHonorariosAdelanto(int $cuenta): bool
    {
        return $cuenta >= 114020000 && $cuenta < 114021000;
    }

    private function esCuentaPuente(int $cuenta): bool
    {
        return in_array($cuenta, self::CUENTAS_PUENTE, true);
    }

    private function esCuentaIvaCredito(int $cuenta): bool
    {
        return $cuenta >= 214010000 && $cuenta < 215000000;
    }

    private function esCuentaBienesUsoPuente(int $cuenta): bool
    {
        return $cuenta >= 123010000 && $cuenta < 123011000;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, string>
     */
    private function indexarRecPorAsiento(array $filas): array
    {
        $mapa = [];

        foreach ($filas as $fila) {
            $asiento = (int) ($fila['nro_asiento'] ?? 0);
            if ($asiento <= 0) {
                continue;
            }

            $rec = $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec !== '') {
                $mapa[$asiento] = $rec;
            }
        }

        return $mapa;
    }

    private function extraerRecComprobante(string $comprobante): string
    {
        if (preg_match('/-(\d+)\s*$/', trim($comprobante), $matches)) {
            return $matches[1];
        }

        if (preg_match('/#(\d+)/', $comprobante, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
