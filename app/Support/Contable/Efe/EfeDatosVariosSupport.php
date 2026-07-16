<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use Carbon\Carbon;

/**
 * Ajuste EFE concepto 20 (VARIOS): cheques 117010 y anticipos 114040 según auxpag (Anita mayo/2026).
 */
class EfeDatosVariosSupport
{
    public const CONCEPTO_VARIOS = 20;

    private const CONCEPTO_MANTENIMIENTO_EDIFICIO = 24;

    private const CUENTA_CHEQUE = 117010001;

    private const CUENTA_ANTICIPO = 114040001;

    private const TIPOS_APLICACION_GASTO = ['FIB', 'FGA', 'FIS', 'FNB', 'FNS', 'FNA', 'PEP', 'COM'];

    /** @var array<string, array<string, mixed>> */
    private array $auxpagPorRec = [];

    /** @var array<int, string> */
    private array $recPorAsiento = [];

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader,
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

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $filas;
        }

        $this->recPorAsiento = $this->indexarRecPorAsiento($filas);

        $inicio = Carbon::createFromDate($anio, $mes, 1);
        $bridge = $this->bridgeReader->cargarPeriodo(
            $empresaId,
            (int) $inicio->format('Ymd'),
            (int) $inicio->copy()->endOfMonth()->format('Ymd'),
        );
        $this->auxpagPorRec = $this->indexarAuxpagPorRec($bridge['auxpag'] ?? []);

        $nombreVarios = $nombresConcepto[self::CONCEPTO_VARIOS] ?? 'VARIOS';
        $nombreMant = $nombresConcepto[self::CONCEPTO_MANTENIMIENTO_EDIFICIO] ?? 'MANTENIMIENTO DE EDIFICIO';

        foreach ($filas as $indice => $fila) {
            $cuenta = (int) ($fila['cuenta'] ?? 0);
            if ($cuenta !== self::CUENTA_CHEQUE && $cuenta !== self::CUENTA_ANTICIPO) {
                continue;
            }

            $rec = $this->recPorAsiento[(int) ($fila['nro_asiento'] ?? 0)]
                ?? $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec === '') {
                continue;
            }

            $pagos = round((float) ($fila['pagos'] ?? 0), 2);
            $conceptoDestino = $this->resolverConceptoDestino($rec, $cuenta, $pagos);
            if ($conceptoDestino === null) {
                continue;
            }

            $conceptoActual = (int) ($fila['concepto_id'] ?? 0);

            if ($conceptoDestino === self::CONCEPTO_VARIOS
                && $conceptoActual === self::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                continue;
            }

            if ($conceptoDestino === self::CONCEPTO_VARIOS
                && in_array($conceptoActual, [13, 7, 44, 45], true)) {
                continue;
            }

            if ($conceptoDestino === $conceptoActual) {
                continue;
            }

            if ($conceptoDestino === self::CONCEPTO_VARIOS) {
                $filas[$indice]['concepto_id'] = self::CONCEPTO_VARIOS;
                $filas[$indice]['concepto_nombre'] = $nombreVarios;
                $filas[$indice]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave(
                    self::CONCEPTO_VARIOS,
                    $nombreVarios,
                );

                continue;
            }

            if ($conceptoDestino === self::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                $filas[$indice]['concepto_id'] = self::CONCEPTO_MANTENIMIENTO_EDIFICIO;
                $filas[$indice]['concepto_nombre'] = $nombreMant;
                $filas[$indice]['clasificacion_efe'] = $this->clasificacionSupport->formatearClave(
                    self::CONCEPTO_MANTENIMIENTO_EDIFICIO,
                    $nombreMant,
                );
            }
        }

        return $filas;
    }

    private function resolverConceptoDestino(string $rec, int $cuenta, float $pagos): ?int
    {
        if ($this->recEsVarios20($rec, $cuenta, $pagos)) {
            return self::CONCEPTO_VARIOS;
        }

        if ($cuenta === self::CUENTA_CHEQUE && $this->recChequeEsMantenimientoEdificio($rec, $pagos)) {
            return self::CONCEPTO_MANTENIMIENTO_EDIFICIO;
        }

        return null;
    }

    private function recEsVarios20(string $rec, int $cuenta, float $pagos): bool
    {
        $tipos = $this->auxpagPorRec[$rec]['tipos'] ?? [];
        $aplicacionesFib = $this->auxpagPorRec[$rec]['fib'] ?? [];
        if ($tipos === [] && $aplicacionesFib === []) {
            return false;
        }

        foreach (['FIS', 'FNS', 'FNB'] as $tipo) {
            if (! isset($tipos[$tipo])) {
                continue;
            }

            if ((int) ($tipos[$tipo]['concepto'] ?? 0) !== self::CONCEPTO_VARIOS) {
                continue;
            }

            if ($tipo === 'FNB' && isset($tipos['TMB']) && ! isset($tipos['FIS'])) {
                continue;
            }

            return true;
        }

        if ($cuenta === self::CUENTA_CHEQUE) {
            $fibConc5 = 0;
            $fibConc24 = 0;
            foreach ($aplicacionesFib as $dato) {
                $conc = (int) ($dato['concepto'] ?? 0);
                if ($conc === 5) {
                    $fibConc5++;
                }
                if ($conc === self::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                    $fibConc24++;
                }
            }

            // Uno o más FIB conc=5 sin FGA/CIB/FDT gastro → Varios (Anita: PAPELERA, etc.).
            if ($fibConc5 >= 1 && ! $this->recTieneFacturaGastroFuerteEnTipos($tipos)) {
                return true;
            }

            if ($fibConc24 === 1) {
                return true;
            }
        }

        if ($cuenta === self::CUENTA_ANTICIPO && isset($tipos['TMB'])) {
            if (isset($tipos['OPA'])) {
                return true;
            }

            $fibConc65 = 0;
            $fibConc24 = 0;
            foreach ($aplicacionesFib as $dato) {
                $conc = (int) ($dato['concepto'] ?? 0);
                if ($conc === 65) {
                    $fibConc65++;
                }
                if ($conc === self::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                    $fibConc24++;
                }
            }

            if ($fibConc65 === 1 && $fibConc24 === 0) {
                return true;
            }

            if ($fibConc24 === 1 && $fibConc65 === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $tipos
     */
    private function recTieneFacturaGastroFuerteEnTipos(array $tipos): bool
    {
        foreach (['FGA', 'CIB', 'FDT'] as $tipo) {
            if (! isset($tipos[$tipo])) {
                continue;
            }

            if ((int) ($tipos[$tipo]['concepto'] ?? 0) === EfeDatosGastronomiaSupport::CONCEPTO_GASTRONOMIA) {
                return true;
            }
        }

        return false;
    }

    private function recChequeEsMantenimientoEdificio(string $rec, float $pagos): bool
    {
        $tipos = $this->auxpagPorRec[$rec]['tipos'] ?? [];
        $aplicacionesFib = $this->auxpagPorRec[$rec]['fib'] ?? [];
        if (! isset($tipos['CHP']) || $aplicacionesFib === []) {
            return false;
        }

        if (abs((float) ($tipos['CHP']['monto'] ?? 0) - round($pagos, 2)) >= 0.02) {
            return false;
        }

        foreach ($aplicacionesFib as $dato) {
            if ((int) ($dato['concepto'] ?? 0) === self::CONCEPTO_VARIOS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<object>  $auxpag
     * @return array<string, array<string, mixed>>
     */
    private function indexarAuxpagPorRec(array $auxpag): array
    {
        /** @var array<string, array<string, mixed>> */
        $porRec = [];

        foreach ($auxpag as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            $dato = [
                'concepto' => (int) ($aplicacion->axp_concepto ?? 0),
                'monto' => round((float) ($aplicacion->axp_monto_ap ?? 0), 2),
            ];

            if ($tipoAp === 'FIB') {
                $porRec[$rec]['fib'][] = $dato;

                continue;
            }

            $porRec[$rec]['tipos'][$tipoAp] = $dato;
        }

        return $porRec;
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
