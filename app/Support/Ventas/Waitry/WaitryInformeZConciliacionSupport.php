<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;

/**
 * Concilia totales Waitry/ERP (sistema) vs montos del Informe Z por tótem y medio de pago.
 */
final class WaitryInformeZConciliacionSupport
{
    public static function toleranciaMonto(): float
    {
        return max(0.0, (float) config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02));
    }

    /**
     * Plantilla de carga: un bloque por tótem de la empresa y medios presentes en sistema + catálogo Waitry.
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenSistema
     * @return list<array<string, mixed>>
     */
    public static function plantillaCarga(int $empresaId, array $resumenSistema): array
    {
        $porTotemSistema = [];
        foreach ($resumenSistema['por_totem'] ?? [] as $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            if ($tid > 0) {
                $porTotemSistema[$tid] = $bloque;
            }
        }

        $tiposCatalogo = self::tiposMedioCatalogo($empresaId);
        $bloques = [];

        $totems = TotemWaitryGastronomia::query()
            ->with('ubicacion')
            ->where('empresa_id', $empresaId)
            ->orderBy('ubicacion_id')
            ->get();

        foreach ($totems as $totem) {
            $tid = (int) $totem->id;
            $sistema = $porTotemSistema[$tid] ?? null;
            $mediosSistema = [];
            foreach ($sistema['por_medio_pago'] ?? [] as $m) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                if ($tipo === null || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)) {
                    continue;
                }
                $mediosSistema[$tipo] = $m;
            }

            $lineas = [];
            $tiposUsados = array_unique(array_merge(array_keys($mediosSistema), array_keys($tiposCatalogo)));
            $tiposUsados = array_values(array_filter(
                $tiposUsados,
                fn (string $tipo) => ! WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo),
            ));
            sort($tiposUsados);

            foreach ($tiposUsados as $tipo) {
                $mSis = $mediosSistema[$tipo] ?? null;
                $linea = self::lineaPlantillaDesdeTipo($empresaId, $tipo, $mSis);
                if (self::lineaInformeZValida($linea, $empresaId)) {
                    $lineas[] = $linea;
                }
            }

            $bloques[] = [
                'totem_id' => $tid,
                'ubicacion_nombre' => (string) ($totem->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($totem->detalle ?? '')),
                'waitry_table_id' => $totem->waitry_table_id,
                'total_ingreso_sistema' => round((float) ($sistema['total_ingreso'] ?? 0), 2),
                'lineas' => $lineas,
            ];
        }

        if ($bloques === [] && ($resumenSistema['por_totem'] ?? []) !== []) {
            foreach ($resumenSistema['por_totem'] as $sistema) {
                $bloques[] = [
                    'totem_id' => $sistema['totem_id'] ?? null,
                    'ubicacion_nombre' => $sistema['ubicacion_nombre'] ?? 'Tótem',
                    'detalle' => $sistema['detalle'] ?? '',
                    'waitry_table_id' => $sistema['waitry_table_id'] ?? null,
                    'total_ingreso_sistema' => round((float) ($sistema['total_ingreso'] ?? 0), 2),
                    'lineas' => array_values(array_filter(
                        array_map(function (array $m) use ($empresaId) {
                            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                            if ($tipo === null || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)) {
                                return null;
                            }

                            return self::lineaPlantillaDesdeTipo($empresaId, $tipo, $m);
                        }, $sistema['por_medio_pago'] ?? []),
                        fn (?array $linea) => $linea !== null && self::lineaInformeZValida($linea, $empresaId),
                    )),
                ];
            }
        }

        return $bloques;
    }

    /**
     * @param  list<array<string, mixed>>  $plantillaTotems  Salida de plantillaCarga con montos informe_z completados
     * @return array{ok:bool,tolerancia:float,totems:list<array<string,mixed>>}
     */
    public static function conciliar(array $plantillaTotems): array
    {
        $tol = self::toleranciaMonto();
        $resultadoTotems = [];
        $okGlobal = true;

        foreach ($plantillaTotems as $bloque) {
            $lineasConc = [];
            $okTotem = true;
            $totalSistema = 0.0;
            $totalZ = 0.0;

            foreach ($bloque['lineas'] ?? [] as $ln) {
                $mSistema = round((float) ($ln['monto_sistema'] ?? 0), 2);
                $mZ = round((float) ($ln['monto_informe_z'] ?? 0), 2);
                $diff = round($mZ - $mSistema, 2);
                $okLinea = abs($diff) <= $tol
                    || ($mSistema <= $tol && $mZ <= $tol);

                if (! $okLinea) {
                    $okTotem = false;
                    $okGlobal = false;
                }

                $totalSistema = round($totalSistema + $mSistema, 2);
                $totalZ = round($totalZ + $mZ, 2);

                $lineasConc[] = [
                    'tipo_waitry' => $ln['tipo_waitry'] ?? null,
                    'etiqueta' => $ln['etiqueta'] ?? '—',
                    'monto_sistema' => $mSistema,
                    'cantidad_sistema' => (int) ($ln['cantidad_sistema'] ?? 0),
                    'monto_informe_z' => $mZ,
                    'diferencia' => $diff,
                    'ok' => $okLinea,
                ];
            }

            $diffTotal = round($totalZ - $totalSistema, 2);
            if (abs($diffTotal) > $tol) {
                $okTotem = false;
                $okGlobal = false;
            }

            $resultadoTotems[] = [
                'totem_id' => $bloque['totem_id'] ?? null,
                'ubicacion_nombre' => $bloque['ubicacion_nombre'] ?? '',
                'detalle' => $bloque['detalle'] ?? '',
                'waitry_table_id' => $bloque['waitry_table_id'] ?? null,
                'ok' => $okTotem,
                'total_sistema' => $totalSistema,
                'total_informe_z' => $totalZ,
                'diferencia_total' => $diffTotal,
                'lineas' => $lineasConc,
            ];
        }

        return [
            'ok' => $okGlobal,
            'tolerancia' => $tol,
            'totems' => $resultadoTotems,
        ];
    }

    /**
     * Aplica montos guardados en informe_z_json sobre la plantilla.
     *
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<string, mixed>|null  $informeZ
     * @return list<array<string, mixed>>
     */
    public static function fusionarInformeZEnPlantilla(array $plantilla, ?array $informeZ): array
    {
        if ($informeZ === null || ($informeZ['totems'] ?? []) === []) {
            return $plantilla;
        }

        $mapZPorCuenta = [];
        $mapZPorTipo = [];
        foreach ($informeZ['totems'] ?? [] as $t) {
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $mapZPorCuenta[$tid] = [];
            $mapZPorTipo[$tid] = [];
            foreach ($t['lineas'] ?? [] as $ln) {
                $monto = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0) {
                    $mapZPorCuenta[$tid][$ccId] = $monto;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                $mapZPorTipo[$tid][$tipo] = $monto;
            }
        }

        foreach ($plantilla as &$bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $zCuentas = $mapZPorCuenta[$tid] ?? [];
            $zTipos = $mapZPorTipo[$tid] ?? [];
            foreach ($bloque['lineas'] as &$ln) {
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                if ($ccId > 0 && array_key_exists($ccId, $zCuentas)) {
                    $ln['monto_informe_z'] = $zCuentas[$ccId];
                } elseif (array_key_exists($tipo, $zTipos)) {
                    $ln['monto_informe_z'] = $zTipos[$tipo];
                }
            }
            unset($ln);
        }
        unset($bloque);

        return $plantilla;
    }

    /**
     * @param  array<string, mixed>|null  $medioSistema
     * @return array<string, mixed>
     */
    private static function lineaPlantillaDesdeTipo(int $empresaId, string $tipo, ?array $medioSistema): array
    {
        $cuenta = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($tipo, $empresaId);
        $etiquetaTipo = WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo);
        $etiquetaCuenta = $cuenta !== null
            ? trim(($cuenta['codigo'] ?? '').' — '.($cuenta['nombre'] ?? ''))
            : $etiquetaTipo;

        return [
            'tipo_waitry' => $tipo,
            'etiqueta' => (string) ($medioSistema['etiqueta'] ?? $etiquetaCuenta),
            'cuentacaja_id' => $cuenta['id'] ?? null,
            'cuentacaja_codigo' => $cuenta['codigo'] ?? '',
            'cuentacaja_nombre' => $cuenta['nombre'] ?? '',
            'moneda_abreviatura' => $cuenta['moneda_abreviatura'] ?? 'ARS',
            'monto_sistema' => round((float) ($medioSistema['total'] ?? 0), 2),
            'cantidad_sistema' => (int) ($medioSistema['cantidad'] ?? 0),
            'monto_informe_z' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function lineaInformeZValida(array $linea, int $empresaId): bool
    {
        $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
        if ($ccId > 0 && WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
            return false;
        }

        $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($linea['tipo_waitry'] ?? null);

        return $tipo !== null
            && ! WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo);
    }

    /**
     * Solo medios predefinidos (config waitry.tipo_pago_cuentacaja).
     *
     * @return array<string, string> tipo => etiqueta
     */
    private static function tiposMedioCatalogo(int $empresaId): array
    {
        $out = [];
        foreach (WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa($empresaId) as $tipo => $medio) {
            $out[$tipo] = $medio['etiqueta'];
        }

        return $out;
    }
}
