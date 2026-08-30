<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\Log;

/**
 * Default de concepto en tipotransaccion (Anita tcomp_concepto).
 * Solo asigna si Anita trae tcomp_concepto > 0. No inventa FAC=4 ni otros fallbacks:
 * FAC/FCE de mercadería no tienen concepto en Anita.
 */
class ConceptoVentaTipotransaccionAsignacionService
{
    /**
     * @return array{
     *     en_tipos: int,
     *     asignar: int,
     *     ya_tenian: int,
     *     sin_concepto: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>,
     *     fuente_anita: bool
     * }
     */
    public function analizar(): array
    {
        return $this->procesar(false);
    }

    /**
     * @return array{
     *     en_tipos: int,
     *     asignar: int,
     *     ya_tenian: int,
     *     sin_concepto: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>,
     *     fuente_anita: bool
     * }
     */
    public function ejecutar(): array
    {
        return $this->procesar(true);
    }

    /**
     * @return array{
     *     en_tipos: int,
     *     asignar: int,
     *     ya_tenian: int,
     *     sin_concepto: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>,
     *     fuente_anita: bool
     * }
     */
    private function procesar(bool $persistir): array
    {
        $ret = [
            'en_tipos' => 0,
            'asignar' => 0,
            'ya_tenian' => 0,
            'sin_concepto' => 0,
            'errores' => [],
            'detalle' => [],
            'fuente_anita' => false,
        ];

        $conceptos = Concepto_Venta::query()
            ->whereNotNull('codigo_anita')
            ->get()
            ->keyBy(fn (Concepto_Venta $c) => (int) $c->codigo_anita);

        $anitaPorClave = $this->mapaAnitaPorClave();
        $ret['fuente_anita'] = $anitaPorClave !== [];

        $tipos = Tipotransaccion::query()
            ->whereIn('operacion', ['V', 'C', 'U'])
            ->orderBy('abreviatura')
            ->get();

        $ret['en_tipos'] = $tipos->count();

        foreach ($tipos as $tipo) {
            $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
            $actualId = (int) ($tipo->concepto_venta_id ?? 0);
            if ($actualId > 0) {
                $ret['ya_tenian']++;
                $ret['detalle'][] = [
                    'tipo_id' => $tipo->id,
                    'abreviatura' => $abrev,
                    'nombre' => $tipo->nombre,
                    'anita' => $anitaPorClave[$abrev] ?? null,
                    'concepto' => $actualId,
                    'accion' => 'ya tenía',
                ];
                continue;
            }

            $codigoAnita = $this->resolverCodigoAnita($tipo, $anitaPorClave);
            $concepto = $codigoAnita > 0 ? ($conceptos[$codigoAnita] ?? null) : null;
            if ($concepto === null) {
                $ret['sin_concepto']++;
                $ret['detalle'][] = [
                    'tipo_id' => $tipo->id,
                    'abreviatura' => $abrev,
                    'nombre' => $tipo->nombre,
                    'anita' => $codigoAnita ?: null,
                    'concepto' => null,
                    'accion' => 'sin concepto',
                ];
                continue;
            }

            $ret['asignar']++;
            $ret['detalle'][] = [
                'tipo_id' => $tipo->id,
                'abreviatura' => $abrev,
                'nombre' => $tipo->nombre,
                'anita' => $codigoAnita,
                'concepto' => $concepto->codigo,
                'accion' => $persistir ? 'asignado' : 'a asignar',
            ];

            if ($persistir) {
                $tipo->concepto_venta_id = $concepto->id;
                $tipo->save();
            }
        }

        return $ret;
    }

    /**
     * @param  array<string, int>  $anitaPorClave
     */
    private function resolverCodigoAnita(Tipotransaccion $tipo, array $anitaPorClave): int
    {
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (isset($anitaPorClave[$abrev]) && $anitaPorClave[$abrev] > 0) {
            return $anitaPorClave[$abrev];
        }

        return 0;
    }

    /**
     * @return array<string, int>
     */
    private function mapaAnitaPorClave(): array
    {
        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 't_comp',
                'campos' => 'tcomp_clave,tcomp_concepto',
                'orderBy' => 'tcomp_clave',
            ]);
            $error = ApiAnita::extraerMensajeError($raw);
            if ($error !== null) {
                Log::warning('concepto_venta tipos: Anita t_comp no disponible', ['error' => $error]);

                return [];
            }
            $filas = json_decode((string) $raw, true);
            if (! is_array($filas)) {
                return [];
            }
            $map = [];
            foreach ($filas as $fila) {
                $clave = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
                $concepto = (int) ($fila['tcomp_concepto'] ?? 0);
                if ($clave !== '' && $concepto > 0) {
                    $map[$clave] = $concepto;
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('concepto_venta tipos: Anita t_comp falló', ['e' => $e->getMessage()]);

            return [];
        }
    }
}
