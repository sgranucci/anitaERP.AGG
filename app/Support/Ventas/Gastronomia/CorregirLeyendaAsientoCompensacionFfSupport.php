<?php

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Corrige leyendas del asiento compensacion_efectivo_no_facturado ya grabadas (ERP + Anita ctamov).
 */
final class CorregirLeyendaAsientoCompensacionFfSupport
{
    public const NUEVA_LEYENDA = 'Reduccion FF Maquinas';

    private const CODIGO_ASIENTO = 'compensacion_efectivo_no_facturado';

    private const LEYENDA_LINEA_ANTIGUA = 'Compensación fondo fijo máquinas — efectivo no facturado';

    private const SUFIJO_CABECERA_ANTIGUO = 'Compensación efectivo no facturado (Waitry) vs fondo fijo máquinas';

    private const TITULO_ANTIGUO = 'Asiento de compensación';

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(): Collection
    {
        $ids = Asiento::query()
            ->where(function ($q) {
                $q->where('observacion', 'like', '%'.self::SUFIJO_CABECERA_ANTIGUO.'%')
                    ->orWhere('observacion', 'like', '%'.self::TITULO_ANTIGUO.'%');
            })
            ->pluck('id');

        $idsMovimiento = Asiento_Movimiento::query()
            ->where('observacion', self::LEYENDA_LINEA_ANTIGUA)
            ->orWhere('observacion', 'like', '%Compensación fondo fijo máquinas%')
            ->pluck('asiento_id');

        $idsSnapshot = $this->asientoIdsDesdeSnapshots();

        $todos = $ids->merge($idsMovimiento)->merge($idsSnapshot)->unique()->filter()->values();

        if ($todos->isEmpty()) {
            return collect();
        }

        return Asiento::query()->whereIn('id', $todos)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function asientoIdsDesdeSnapshots(): Collection
    {
        $ids = collect();

        GastronomiaCierreJornadaProcesoSnapshot::query()
            ->orderBy('id')
            ->chunkById(50, function ($snapshots) use (&$ids) {
                foreach ($snapshots as $snapshot) {
                    $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
                    $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
                    if (! is_array($asientos)) {
                        continue;
                    }
                    foreach ($asientos as $item) {
                        if (! is_array($item) || ($item['codigo'] ?? '') !== self::CODIGO_ASIENTO) {
                            continue;
                        }
                        $aid = (int) ($item['asiento_id'] ?? 0);
                        if ($aid > 0) {
                            $ids->push($aid);
                        }
                    }
                }
            });

        return $ids->unique()->values();
    }

    public function corregirObservacionCabecera(string $observacion): ?string
    {
        $nueva = str_replace(
            [' — '.self::SUFIJO_CABECERA_ANTIGUO, ' — '.self::TITULO_ANTIGUO],
            [' — '.self::NUEVA_LEYENDA, ' — '.self::NUEVA_LEYENDA],
            $observacion,
        );

        return $nueva !== $observacion ? $nueva : null;
    }

    public function corregirTituloSnapshot(string $titulo): ?string
    {
        $nueva = str_replace(
            [self::SUFIJO_CABECERA_ANTIGUO, self::TITULO_ANTIGUO],
            [self::NUEVA_LEYENDA, self::NUEVA_LEYENDA],
            $titulo,
        );

        return $nueva !== $titulo ? $nueva : null;
    }

    public function esLineaHaberCompensacion(string $observacion): bool
    {
        $obs = trim($observacion);

        return $obs === self::LEYENDA_LINEA_ANTIGUA
            || str_contains($obs, 'Compensación fondo fijo máquinas')
            || str_contains($obs, 'compensación fondo fijo');
    }

    public function sanitizarDescMovAnita(string $texto): string
    {
        return preg_replace('/([^A-Za-z0-9 ])/', '', $texto) ?? '';
    }

    /**
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   snapshots:int,
     *   errores:list<string>
     * }
     */
    public function ejecutar(bool $dryRun = false): array
    {
        $resultado = [
            'asientos_erp' => 0,
            'lineas_erp' => 0,
            'lineas_anita' => 0,
            'snapshots' => 0,
            'errores' => [],
        ];

        foreach ($this->asientosAfectados() as $asiento) {
            $nuevaCabecera = $this->corregirObservacionCabecera((string) ($asiento->observacion ?? ''));
            if ($nuevaCabecera !== null) {
                if (! $dryRun) {
                    $asiento->observacion = $nuevaCabecera;
                    $asiento->save();
                }
                $resultado['asientos_erp']++;
            }

            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            $lineaHaberIdx = null;
            foreach ($lineas as $idx => $linea) {
                if ((float) ($linea->monto ?? 0) >= -0.0001) {
                    continue;
                }
                $lineaHaberIdx = $idx;
                if ($this->esLineaHaberCompensacion((string) ($linea->observacion ?? ''))) {
                    if (! $dryRun) {
                        $linea->observacion = self::NUEVA_LEYENDA;
                        $linea->save();
                    }
                    $resultado['lineas_erp']++;
                }
            }

            if ($lineaHaberIdx === null) {
                continue;
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = (string) $asiento->numeroasiento;
            $nroLinea = (string) max(0, $lineaHaberIdx);

            if ($dryRun) {
                $resultado['lineas_anita']++;
                continue;
            }

            try {
                $actualizadas = $this->actualizarLineasAnita($codigoEmpresa, $nroAsiento, $nroLinea);
                $resultado['lineas_anita'] += $actualizadas;
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
            }
        }

        $resultado['snapshots'] = $this->corregirSnapshots($dryRun);

        return $resultado;
    }

    private function corregirSnapshots(bool $dryRun): int
    {
        $corregidos = 0;

        GastronomiaCierreJornadaProcesoSnapshot::query()
            ->orderBy('id')
            ->chunkById(50, function ($snapshots) use ($dryRun, &$corregidos) {
                foreach ($snapshots as $snapshot) {
                    $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
                    $grabacion = $payload['asientos_proceso_grabacion'] ?? null;
                    if (! is_array($grabacion) || ! is_array($grabacion['asientos'] ?? null)) {
                        continue;
                    }

                    $cambio = false;
                    foreach ($grabacion['asientos'] as $i => $item) {
                        if (! is_array($item) || ($item['codigo'] ?? '') !== self::CODIGO_ASIENTO) {
                            continue;
                        }
                        $tituloNuevo = $this->corregirTituloSnapshot((string) ($item['titulo'] ?? ''));
                        if ($tituloNuevo === null) {
                            continue;
                        }
                        $grabacion['asientos'][$i]['titulo'] = $tituloNuevo;
                        $cambio = true;
                    }

                    if (! $cambio) {
                        continue;
                    }

                    if (! $dryRun) {
                        $payload['asientos_proceso_grabacion'] = $grabacion;
                        $snapshot->payload = $payload;
                        $snapshot->save();
                    }
                    $corregidos++;
                }
            });

        return $corregidos;
    }

    /**
     * Actualiza ctav_desc_mov en Anita para la línea haber del asiento.
     */
    private function actualizarLineasAnita(string $codigoEmpresa, string $nroAsiento, string $nroLineaPreferida): int
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_d_h,ctav_desc_mov',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        $descNueva = $this->sanitizarDescMovAnita(self::NUEVA_LEYENDA);
        $actualizadas = 0;

        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $linea = (string) ($row['ctav_nro_linea'] ?? '');
            $desc = (string) ($row['ctav_desc_mov'] ?? '');
            $esHaber = strtoupper((string) ($row['ctav_d_h'] ?? '')) === 'H';
            $coincide = $linea === $nroLineaPreferida
                || str_contains(strtolower($desc), 'fondo fijo')
                || str_contains(strtolower($desc), 'compensacin fondo');

            if (! $esHaber || ! $coincide) {
                continue;
            }

            if ($desc === $descNueva) {
                $actualizadas++;
                continue;
            }

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_desc_mov = '".$descNueva."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$linea."' ",
            ], 'ctamov update leyenda compensacion ff');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        if ($actualizadas === 0) {
            throw new RuntimeException('No se actualizó ninguna línea haber en ctamov.');
        }

        return $actualizadas;
    }
}
