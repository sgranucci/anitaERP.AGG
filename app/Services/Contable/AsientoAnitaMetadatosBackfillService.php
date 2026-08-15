<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Support\Contable\Anita\AnitaAsientoImportBridgeReader;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class AsientoAnitaMetadatosBackfillService
{
    private const COLUMNAS = [
        'anita_origen',
        'anita_sistema',
        'anita_tipo',
        'anita_letra',
        'anita_sucursal',
        'anita_nro',
        'anita_emisor',
    ];

    public function __construct(
        private readonly AnitaAsientoImportBridgeReader $bridgeReader,
    ) {}

    /**
     * @param  list<int>  $empresas
     * @param  callable(string): void|null  $progreso
     * @return array<string, mixed>
     */
    public function ejecutar(
        string $desde,
        string $hasta,
        array $empresas,
        int $mesesBloque,
        bool $persistir,
        ?callable $progreso = null,
    ): array {
        $this->validarColumnas();
        [$desdeFecha, $hastaFecha] = $this->validarRango($desde, $hasta);
        $empresas = array_values(array_unique(array_filter(array_map('intval', $empresas), fn (int $id) => $id > 0)));
        if ($empresas === []) {
            throw new InvalidArgumentException('Indique al menos una empresa Anita válida.');
        }

        $resultado = [
            'desde' => $desdeFecha->format('Y-m-d'),
            'hasta' => $hastaFecha->format('Y-m-d'),
            'empresas' => $empresas,
            'persistir' => $persistir,
            'bloques' => 0,
            'asientos_revisados' => 0,
            'asientos_anita_confirmados' => 0,
            'asientos_actualizados' => 0,
            'asientos_sin_match_bridge' => 0,
            'asientos_no_parseables' => 0,
            'emisores_persistidos' => 0,
            'ctamov_filas' => 0,
            'subdiario_filas' => 0,
            'subhist_filas' => 0,
            'errores' => [],
            'muestra_sin_match' => [],
        ];

        foreach ($this->bloques($desdeFecha, $hastaFecha, max(1, $mesesBloque)) as [$bloqueDesde, $bloqueHasta]) {
            foreach ($empresas as $empresaId) {
                $resultado['bloques']++;
                if ($progreso !== null) {
                    $progreso(sprintf(
                        'Empresa %d | %s → %s',
                        $empresaId,
                        $bloqueDesde->format('Y-m-d'),
                        $bloqueHasta->format('Y-m-d'),
                    ));
                }

                $datos = $this->bridgeReader->cargarBloque(
                    $empresaId,
                    (int) $bloqueDesde->format('Ymd'),
                    (int) $bloqueHasta->format('Ymd'),
                );
                $resultado['errores'] = array_merge($resultado['errores'], $datos['errores'] ?? []);
                $resultado['ctamov_filas'] += count($datos['ctamov'] ?? []);
                $resultado['subdiario_filas'] += count($datos['subdiario'] ?? []);
                $resultado['subhist_filas'] += count($datos['subhist'] ?? []);

                [$ctamov, $subdiario, $subhist] = $this->indicesBridge($datos);
                $this->procesarAsientosBloque(
                    $empresaId,
                    $bloqueDesde,
                    $bloqueHasta,
                    $ctamov,
                    $subdiario,
                    $subhist,
                    $persistir,
                    $resultado,
                );
            }
        }

        return $resultado;
    }

    private function validarColumnas(): void
    {
        foreach (self::COLUMNAS as $columna) {
            if (! Schema::hasColumn('asiento', $columna)) {
                throw new RuntimeException(
                    "Falta asiento.{$columna}; ejecute primero la migración de metadatos históricos Anita.",
                );
            }
        }
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function validarRango(string $desde, string $hasta): array
    {
        try {
            $desdeFecha = CarbonImmutable::createFromFormat('Y-m-d', $desde)->startOfDay();
            $hastaFecha = CarbonImmutable::createFromFormat('Y-m-d', $hasta)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Las fechas deben tener formato Y-m-d.');
        }
        if ($hastaFecha->lt($desdeFecha)) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a desde.');
        }

        $cutoff = trim((string) config('contable.mayor_plano_cuenta.fuente_erp_hasta', ''));
        try {
            $cutoffFecha = match (true) {
                preg_match('/^\d{8}$/', $cutoff) === 1 => CarbonImmutable::createFromFormat('Ymd', $cutoff),
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff) === 1 => CarbonImmutable::createFromFormat('Y-m-d', $cutoff),
                default => throw new InvalidArgumentException('El cutoff ERP configurado no es válido.'),
            };
        } catch (\Throwable) {
            throw new InvalidArgumentException('El cutoff ERP configurado no es válido: '.$cutoff);
        }
        if ($hastaFecha->gt($cutoffFecha->startOfDay())) {
            throw new InvalidArgumentException(
                'El backfill no puede superar el cutoff ERP configurado: '.$cutoff,
            );
        }

        return [$desdeFecha, $hastaFecha];
    }

    /**
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    private function bloques(CarbonImmutable $desde, CarbonImmutable $hasta, int $meses): array
    {
        $bloques = [];
        $cursor = $desde;
        while ($cursor->lte($hasta)) {
            $fin = $cursor->addMonthsNoOverflow($meses)->startOfMonth()->subDay();
            if ($fin->gt($hasta)) {
                $fin = $hasta;
            }
            $bloques[] = [$cursor, $fin];
            $cursor = $fin->addDay();
        }

        return $bloques;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{array<string, array<string, true>>, array<string, string>, array<string, string>}
     */
    private function indicesBridge(array $datos): array
    {
        $ctamov = [];
        foreach ($datos['ctamov'] ?? [] as $fila) {
            $clave = AsientoAnitaMetadatosSupport::claveAsiento(
                (int) ($fila->ctav_empresa ?? 0),
                (int) ($fila->ctav_fecha ?? 0),
                (int) ($fila->ctav_nro_asiento ?? 0),
            );
            $firma = $this->firmaComprobante(
                (string) ($fila->ctav_sistema ?? ''),
                (string) ($fila->ctav_tipo ?? ''),
                (string) ($fila->ctav_letra ?? ' '),
                (int) ($fila->ctav_sucursal ?? 0),
                (int) ($fila->ctav_nro ?? 0),
            );
            $ctamov[$clave][$firma] = true;
        }

        $subdiario = [];
        $subhist = [];
        foreach (array_merge($datos['subdiario'] ?? [], $datos['subhist'] ?? []) as $fila) {
            $clave = AsientoAnitaMetadatosSupport::claveAsiento(
                (int) ($fila->subd_empresa ?? 0),
                (int) ($fila->subd_fecha ?? 0),
                (int) ($fila->subd_nro_operacion ?? 0),
            );
            $emisor = trim((string) ($fila->subd_emisor ?? ''));
            $destino = ! empty($fila->subd_origen_subhist) ? 'subhist' : 'subdiario';
            if ($destino === 'subhist') {
                if ($emisor !== '') {
                    $subhist[$clave] = $emisor;
                } else {
                    $subhist[$clave] ??= '';
                }
            } elseif ($emisor !== '') {
                $subdiario[$clave] = $emisor;
            } else {
                $subdiario[$clave] ??= '';
            }
        }

        return [$ctamov, $subdiario, $subhist];
    }

    private function firmaComprobante(
        string $sistema,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): string {
        return implode('|', [
            strtoupper(trim($sistema)),
            strtoupper(trim($tipo)),
            strtoupper(trim($letra)),
            $sucursal,
            $nro,
        ]);
    }

    /**
     * @param  array<string, array<string, true>>  $ctamov
     * @param  array<string, string>  $subdiario
     * @param  array<string, string>  $subhist
     * @param  array<string, mixed>  $resultado
     */
    private function procesarAsientosBloque(
        int $empresaId,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        array $ctamov,
        array $subdiario,
        array $subhist,
        bool $persistir,
        array &$resultado,
    ): void {
        DB::table('asiento')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->whereNull('deleted_at')
            ->whereNull('anita_origen')
            ->select(['id', 'empresa_id', 'numeroasiento', 'fecha', 'observacion'])
            ->orderBy('id')
            ->chunkById(500, function ($asientos) use (
                $ctamov,
                $subdiario,
                $subhist,
                $persistir,
                &$resultado,
            ) {
                $updates = [];
                foreach ($asientos as $asiento) {
                    $resultado['asientos_revisados']++;
                    $meta = AsientoAnitaMetadatosSupport::desdeObservacion((string) ($asiento->observacion ?? ''));
                    $esResumenSinComprobante = $meta['origen'] === AsientoAnitaMetadatosSupport::ORIGEN_CTAMOV_RESUMEN;
                    if (! $esResumenSinComprobante && ($meta['tipo'] === '' || $meta['nro'] <= 0)) {
                        $resultado['asientos_no_parseables']++;

                        continue;
                    }

                    $fechaYmd = (int) str_replace('-', '', substr((string) $asiento->fecha, 0, 10));
                    $clave = AsientoAnitaMetadatosSupport::claveAsiento(
                        (int) $asiento->empresa_id,
                        $fechaYmd,
                        (int) $asiento->numeroasiento,
                    );
                    $origen = $meta['origen'];
                    $firma = $this->firmaComprobante(
                        $meta['sistema'],
                        $meta['tipo'],
                        $meta['letra'],
                        $meta['sucursal'],
                        $meta['nro'],
                    );
                    $confirmado = match ($origen) {
                        AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST => array_key_exists($clave, $subhist),
                        AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO => array_key_exists($clave, $subdiario),
                        AsientoAnitaMetadatosSupport::ORIGEN_CTAMOV_RESUMEN => $meta['nro'] <= 0
                            ? isset($ctamov[$clave])
                            : isset($ctamov[$clave][$firma]),
                        default => isset($ctamov[$clave][$firma]),
                    };
                    if (! $confirmado) {
                        $resultado['asientos_sin_match_bridge']++;
                        if (count($resultado['muestra_sin_match']) < 30) {
                            $resultado['muestra_sin_match'][] = [
                                'id' => (int) $asiento->id,
                                'empresa_id' => (int) $asiento->empresa_id,
                                'numeroasiento' => (int) $asiento->numeroasiento,
                                'fecha' => substr((string) $asiento->fecha, 0, 10),
                                'observacion' => (string) $asiento->observacion,
                            ];
                        }

                        continue;
                    }

                    $emisor = match ($origen) {
                        AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST => $subhist[$clave] ?? '',
                        AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO => $subdiario[$clave] ?? '',
                        default => '',
                    };
                    $updates[] = [
                        'id' => (int) $asiento->id,
                        'anita_origen' => $origen,
                        'anita_sistema' => $meta['sistema'],
                        'anita_tipo' => $meta['tipo'] !== '' ? $meta['tipo'] : null,
                        'anita_letra' => $meta['letra'],
                        'anita_sucursal' => $meta['sucursal'],
                        'anita_nro' => $meta['nro'] > 0 ? $meta['nro'] : null,
                        'anita_emisor' => $emisor !== '' ? $emisor : null,
                    ];
                    $resultado['asientos_anita_confirmados']++;
                    $resultado['emisores_persistidos'] += $emisor !== '' ? 1 : 0;
                }

                if ($persistir && $updates !== []) {
                    DB::table('asiento')->upsert($updates, ['id'], self::COLUMNAS);
                    $resultado['asientos_actualizados'] += count($updates);
                } elseif (! $persistir) {
                    $resultado['asientos_actualizados'] += count($updates);
                }
            });
    }
}
