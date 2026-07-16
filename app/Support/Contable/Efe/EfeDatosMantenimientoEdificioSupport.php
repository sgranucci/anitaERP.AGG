<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use Carbon\Carbon;

/**
 * Ajuste EFE concepto 24 (MANTENIMIENTO DE EDIFICIO): OPP con gasto 521180 en subdiario
 * o FIB axp_concepto=24 sin puente 123010 (bienes de uso).
 */
class EfeDatosMantenimientoEdificioSupport
{
    public const CONCEPTO_MANTENIMIENTO_EDIFICIO = 24;

    private const CUENTA_MANT_EDIFICIO_ALT = 521150003;

    private const CUENTA_BIENES_USO_PUENTE_DESDE = 123010000;

    private const CUENTA_BIENES_USO_PUENTE_HASTA = 123011000;

    private const TIPOS_APLICACION_GASTO = ['FIB', 'FGA', 'COM', 'FIS', 'FNB', 'FNA', 'PEP'];

    /** Cuentas que Anita muestra en Datos bajo concepto 24 (solapa Datos mayo/2026). */
    private const CUENTAS_DATOS_C24 = [
        117010001,
        114040001,
        114010002,
        114010007,
        114010009,
        114010011,
    ];

    /** @var array<string, true> */
    private array $recEsConcepto24 = [];

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
        $this->recEsConcepto24 = $this->indexarRecConcepto24(
            $bridge['auxpag'] ?? [],
            $bridge['subdiario'] ?? [],
        );

        if ($this->recEsConcepto24 === []) {
            return $filas;
        }

        $nombreConcepto = $nombresConcepto[self::CONCEPTO_MANTENIMIENTO_EDIFICIO]
            ?? 'MANTENIMIENTO DE EDIFICIO';
        $clasificacion = $this->clasificacionSupport->formatearClave(
            self::CONCEPTO_MANTENIMIENTO_EDIFICIO,
            $nombreConcepto,
        );

        foreach ($filas as $indice => $fila) {
            if (! $this->cuentaAplicaMantenimientoEdificio($fila)) {
                continue;
            }

            if (! $this->operacionEsMantenimientoEdificio($fila)) {
                continue;
            }

            // Gaming (12) ya clasificado: Anita Datos prioriza c12 (ej. ERNESTO MAYER / FNS).
            if ((int) ($fila['concepto_id'] ?? 0) === EfeDatosGamingSuppliesSupport::CONCEPTO_GAMING_SUPPLIES) {
                continue;
            }

            $filas[$indice]['concepto_id'] = self::CONCEPTO_MANTENIMIENTO_EDIFICIO;
            $filas[$indice]['concepto_nombre'] = $nombreConcepto;
            $filas[$indice]['clasificacion_efe'] = $clasificacion;
        }

        return $filas;
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

    /**
     * @param  list<object>  $auxpag
     * @param  list<object>  $subdiario
     * @return array<string, true>
     */
    private function indexarRecConcepto24(array $auxpag, array $subdiario): array
    {
        /** @var array<string, array<int, true>> */
        $internosPorRec = [];
        /** @var array<int, list<string>> */
        $recsPorInterno = [];

        foreach ($auxpag as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));

            if (! in_array($tipoAp, self::TIPOS_APLICACION_GASTO, true)) {
                continue;
            }

            $interno = (int) ($aplicacion->axp_nro_interno ?? 0);
            if ($interno <= 0) {
                continue;
            }

            $internosPorRec[$rec][$interno] = true;
            $recsPorInterno[$interno][$rec] = true;
        }

        /** @var array<string, true> */
        $recTieneBienesUso = [];
        /** @var array<string, true> */
        $recTieneMantEdificio = [];
        /** @var array<int, true> */
        $internoFibConcepto24 = [];
        /** @var array<int, true> */
        $internoTieneBienesUso = [];

        foreach ($auxpag as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, self::TIPOS_APLICACION_GASTO, true)) {
                continue;
            }

            $interno = (int) ($aplicacion->axp_nro_interno ?? 0);
            if ($interno <= 0) {
                continue;
            }

            if ((int) ($aplicacion->axp_concepto ?? 0) === self::CONCEPTO_MANTENIMIENTO_EDIFICIO) {
                $internoFibConcepto24[$interno] = true;
            }
        }

        foreach ($subdiario as $linea) {
            $interno = (int) ($linea->subd_nro_interno ?? 0);
            if ($interno <= 0) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);

            if ($this->esCuentaBienesUsoPuente($cuenta)) {
                $internoTieneBienesUso[$interno] = true;
            }

            if (! isset($recsPorInterno[$interno])) {
                continue;
            }

            foreach (array_keys($recsPorInterno[$interno]) as $rec) {
                if ($this->esCuentaBienesUsoPuente($cuenta)) {
                    $recTieneBienesUso[$rec] = true;
                }

                if ($this->esCuentaMantenimientoEdificio($cuenta)) {
                    $recTieneMantEdificio[$rec] = true;
                }
            }
        }

        foreach ($internoFibConcepto24 as $interno => $_) {
            if (isset($internoTieneBienesUso[$interno])) {
                continue;
            }

            if (! isset($recsPorInterno[$interno])) {
                continue;
            }

            foreach (array_keys($recsPorInterno[$interno]) as $rec) {
                if (! isset($recTieneBienesUso[$rec])) {
                    $this->recEsConcepto24[$rec] = true;
                }
            }
        }

        foreach (array_keys($internosPorRec) as $rec) {
            if (isset($recTieneBienesUso[$rec])) {
                continue;
            }

            if (isset($recTieneMantEdificio[$rec])) {
                $this->recEsConcepto24[$rec] = true;
            }
        }

        return $this->recEsConcepto24;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function cuentaAplicaMantenimientoEdificio(array $fila): bool
    {
        $cuenta = (int) ($fila['cuenta'] ?? 0);

        if (in_array($cuenta, self::CUENTAS_DATOS_C24, true)) {
            return true;
        }

        return $this->esCuentaMantenimientoEdificio($cuenta);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function operacionEsMantenimientoEdificio(array $fila): bool
    {
        $asiento = (int) ($fila['nro_asiento'] ?? 0);
        $rec = $this->recPorAsiento[$asiento]
            ?? $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));

        return $rec !== '' && isset($this->recEsConcepto24[$rec]);
    }

    private function extraerRecComprobante(string $comprobante): string
    {
        if (preg_match('/-(\d+)\s*$/', trim($comprobante), $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function esCuentaMantenimientoEdificio(int $cuenta): bool
    {
        if ($cuenta === self::CUENTA_MANT_EDIFICIO_ALT) {
            return true;
        }

        return $cuenta >= 521180000 && $cuenta < 521190000;
    }

    private function esCuentaBienesUsoPuente(int $cuenta): bool
    {
        return $cuenta >= self::CUENTA_BIENES_USO_PUENTE_DESDE
            && $cuenta < self::CUENTA_BIENES_USO_PUENTE_HASTA;
    }

    private function esCuentaAnticipoEfe(int $cuenta): bool
    {
        return ($cuenta >= 114010000 && $cuenta < 114020000)
            || ($cuenta >= 114040000 && $cuenta < 114050000);
    }
}
