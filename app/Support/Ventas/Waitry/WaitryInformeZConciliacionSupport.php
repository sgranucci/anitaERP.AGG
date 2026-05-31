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
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null) ?? 'totem';
                $mediosSistema[$tipo] = $m;
            }

            $lineas = [];
            $tiposUsados = array_unique(array_merge(array_keys($mediosSistema), array_keys($tiposCatalogo)));
            sort($tiposUsados);

            foreach ($tiposUsados as $tipo) {
                $mSis = $mediosSistema[$tipo] ?? null;
                $lineas[] = [
                    'tipo_waitry' => $tipo,
                    'etiqueta' => (string) ($mSis['etiqueta'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo)),
                    'monto_sistema' => round((float) ($mSis['total'] ?? 0), 2),
                    'cantidad_sistema' => (int) ($mSis['cantidad'] ?? 0),
                    'monto_informe_z' => null,
                ];
            }

            if ($lineas === []) {
                $lineas[] = [
                    'tipo_waitry' => 'totem',
                    'etiqueta' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo('totem'),
                    'monto_sistema' => 0.0,
                    'cantidad_sistema' => 0,
                    'monto_informe_z' => null,
                ];
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
                    'lineas' => array_map(fn (array $m) => [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null) ?? 'totem',
                        'etiqueta' => $m['etiqueta'] ?? '',
                        'monto_sistema' => round((float) ($m['total'] ?? 0), 2),
                        'cantidad_sistema' => (int) ($m['cantidad'] ?? 0),
                        'monto_informe_z' => null,
                    ], $sistema['por_medio_pago'] ?? []),
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

        $mapZ = [];
        foreach ($informeZ['totems'] ?? [] as $t) {
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $mapZ[$tid] = [];
            foreach ($t['lineas'] ?? [] as $ln) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                $mapZ[$tid][$tipo] = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
            }
        }

        foreach ($plantilla as &$bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $zLineas = $mapZ[$tid] ?? [];
            foreach ($bloque['lineas'] as &$ln) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                if (array_key_exists($tipo, $zLineas)) {
                    $ln['monto_informe_z'] = $zLineas[$tipo];
                }
            }
            unset($ln);
        }
        unset($bloque);

        return $plantilla;
    }

    /**
     * @return array<string, string> tipo => etiqueta
     */
    private static function tiposMedioCatalogo(int $empresaId): array
    {
        $out = [];
        foreach (WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa($empresaId) as $tipo => $medio) {
            $out[$tipo] = $medio['etiqueta'];
        }
        $out['totem'] = WaitryMedioPagoCuentacajaSupport::etiquetaTipo('totem');
        $out['cash'] = WaitryMedioPagoCuentacajaSupport::etiquetaTipo('cash');

        return $out;
    }
}
