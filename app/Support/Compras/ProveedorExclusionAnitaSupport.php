<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Mapeo de exclusiones de retención ERP ↔ Anita (promae + proexcl).
 *
 * Anita proex_tipo_ret: 0 = ganancias, 1 = IVA, 2 = ingresos brutos.
 * ERP tiporetencion: G, I, B (SUSS no tiene línea en proexcl).
 */
class ProveedorExclusionAnitaSupport
{
    public const TIPO_ANITA_GANANCIAS = '0';

    public const TIPO_ANITA_IVA = '1';

    public const TIPO_ANITA_IIBB = '2';

    public static function tipoRetencionErpAnitaCodigo(string $tipoErp): ?string
    {
        return match (strtoupper(trim($tipoErp))) {
            'G' => self::TIPO_ANITA_GANANCIAS,
            'I' => self::TIPO_ANITA_IVA,
            'B' => self::TIPO_ANITA_IIBB,
            default => null,
        };
    }

    public static function tipoRetencionAnitaErpCodigo(string $tipoAnita): ?string
    {
        return match (trim($tipoAnita)) {
            self::TIPO_ANITA_GANANCIAS => 'G',
            self::TIPO_ANITA_IVA => 'I',
            self::TIPO_ANITA_IIBB => 'B',
            default => null,
        };
    }

    /**
     * @return list<array{tipo_erp: string, tipo_anita: string, desde: string, hasta: string, porcentaje: mixed, comentario: string}>
     */
    public static function lineasDesdeRequest(array $data): array
    {
        if (! isset($data['desdefechas']) || ! is_array($data['desdefechas'])) {
            return [];
        }

        $lineas = [];
        foreach ($data['desdefechas'] as $i => $desdeRaw) {
            $desde = trim((string) $desdeRaw);
            if ($desde === '') {
                continue;
            }

            $tipoErp = strtoupper(trim((string) ($data['tiporetenciones'][$i] ?? '')));
            $tipoAnita = self::tipoRetencionErpAnitaCodigo($tipoErp);
            if ($tipoAnita === null) {
                continue;
            }

            $lineas[] = [
                'tipo_erp' => $tipoErp,
                'tipo_anita' => $tipoAnita,
                'desde' => $desde,
                'hasta' => trim((string) ($data['hastafechas'][$i] ?? '')),
                'porcentaje' => $data['porcentajeexclusiones'][$i] ?? 0,
                'comentario' => (string) ($data['comentarios'][$i] ?? ''),
            ];
        }

        return $lineas;
    }

    /**
     * Campos de cabecera promae (un slot por tipo de retención).
     *
     * @return array{
     *     exclusionretiva: int|float,
     *     fechaexclusionretiva: int|string,
     *     fechainicioexclusionretiva: int|string,
     *     exclusionretgan: int|float,
     *     fechaexclusionretgan: int|string,
     *     fechainicioexclusionretgan: int|string,
     *     exclusionretib: int|float,
     *     fechaexclusionretib: int|string,
     *     fechainicioexclusionretib: int|string
     * }
     */
    public static function camposPromaeDesdeLineas(array $lineas): array
    {
        $campos = [
            'exclusionretiva' => 0,
            'fechaexclusionretiva' => 0,
            'fechainicioexclusionretiva' => 0,
            'exclusionretgan' => 0,
            'fechaexclusionretgan' => 0,
            'fechainicioexclusionretgan' => 0,
            'exclusionretib' => 0,
            'fechaexclusionretib' => 0,
            'fechainicioexclusionretib' => 0,
        ];

        foreach ($lineas as $linea) {
            switch ($linea['tipo_erp']) {
                case 'I':
                    $campos['exclusionretiva'] = $linea['porcentaje'];
                    $campos['fechaexclusionretiva'] = self::fechaAnitaInformix($linea['hasta']);
                    $campos['fechainicioexclusionretiva'] = self::fechaAnitaInformix($linea['desde']);
                    break;
                case 'G':
                    $campos['exclusionretgan'] = $linea['porcentaje'];
                    $campos['fechaexclusionretgan'] = self::fechaAnitaInformix($linea['hasta']);
                    $campos['fechainicioexclusionretgan'] = self::fechaAnitaInformix($linea['desde']);
                    break;
                case 'B':
                    $campos['exclusionretib'] = $linea['porcentaje'];
                    $campos['fechaexclusionretib'] = self::fechaAnitaInformix($linea['hasta']);
                    $campos['fechainicioexclusionretib'] = self::fechaAnitaInformix($linea['desde']);
                    break;
            }
        }

        return $campos;
    }

    /**
     * @return int|string 0 si vacío; Ymd numérico para Informix.
     */
    public static function fechaAnitaInformix($fecha)
    {
        if ($fecha === null || $fecha === '' || $fecha === 0 || $fecha === '0') {
            return 0;
        }

        return Carbon::parse($fecha)->format('Ymd');
    }

    public static function escaparSqlAnita(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    /**
     * Convierte fecha Anita (Ymd entero o ISO) a Y-m-d para PostgreSQL.
     */
    public static function fechaAnitaAIso($fecha): ?string
    {
        if ($fecha === null || $fecha === '' || $fecha === 0 || $fecha === '0') {
            return null;
        }

        $s = trim((string) $fecha);
        if (preg_match('/^\d{8}$/', $s)) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Líneas de exclusión listas para grabar en proveedor_exclusion (import Anita → ERP).
     *
     * @param  list<object>  $filasProexcl
     * @return list<array{tiporetencion: string, desdefecha: string, hastafecha: string, porcentajeexclusion: float, comentario: string}>
     */
    public static function lineasErpDesdeAnita(array $filasProexcl, object $promae): array
    {
        $lineas = [];

        foreach ($filasProexcl as $indice => $fila) {
            $tipo = self::tipoRetencionAnitaErpCodigo((string) ($fila->proex_tipo_ret ?? ''));
            if ($tipo === null) {
                $tipo = self::inferirTipoRetencionDesdePromaePorFechas($fila, $promae);
            }
            if ($tipo === null) {
                $tipo = self::inferirTipoRetencionDesdeComentario((string) ($fila->proex_comentario ?? ''));
            }
            if ($tipo === null) {
                continue;
            }

            $desde = self::fechaAnitaAIso($fila->proex_desde_fecha ?? null);
            $hasta = self::fechaAnitaAIso($fila->proex_hasta_fecha ?? null);
            if ($desde === null || $hasta === null) {
                continue;
            }

            $lineas[] = [
                'tiporetencion' => $tipo,
                'desdefecha' => $desde,
                'hastafecha' => $hasta,
                'porcentajeexclusion' => (float) ($fila->proex_porc_excl ?? 0),
                'comentario' => trim((string) ($fila->proex_comentario ?? '')),
                '_origen' => 'proexcl',
                '_indice' => $indice,
            ];
        }

        $tiposProexcl = array_column($lineas, 'tiporetencion');
        foreach (self::lineasErpDesdePromaeCabecera($promae) as $lineaPromae) {
            if (! in_array($lineaPromae['tiporetencion'], $tiposProexcl, true)) {
                $lineas[] = $lineaPromae;
            }
        }

        usort($lineas, function (array $a, array $b): int {
            $cmp = strcmp($a['tiporetencion'], $b['tiporetencion']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['desdefecha'], $b['desdefecha']);
        });

        return array_map(function (array $linea): array {
            unset($linea['_origen'], $linea['_indice']);

            return $linea;
        }, $lineas);
    }

    /**
     * @return list<array{tiporetencion: string, desdefecha: string, hastafecha: string, porcentajeexclusion: float, comentario: string, _origen: string}>
     */
    public static function lineasErpDesdePromaeCabecera(object $promae): array
    {
        $slots = [
            'I' => [
                'porcentaje' => $promae->prom_excl_retiva ?? 0,
                'desde' => $promae->prom_fe_ini_excl ?? 0,
                'hasta' => $promae->prom_fecha_excl ?? 0,
            ],
            'G' => [
                'porcentaje' => $promae->prom_excl_retgan ?? 0,
                'desde' => $promae->prom_fe_ini_exclrg ?? 0,
                'hasta' => $promae->prom_fecha_exclrg ?? 0,
            ],
            'B' => [
                'porcentaje' => $promae->prom_excl_retib ?? 0,
                'desde' => $promae->prom_fe_ini_exclib ?? 0,
                'hasta' => $promae->prom_fecha_exclib ?? 0,
            ],
        ];

        $lineas = [];
        foreach ($slots as $tipo => $slot) {
            if ((float) $slot['porcentaje'] <= 0) {
                continue;
            }
            $desde = self::fechaAnitaAIso($slot['desde']);
            $hasta = self::fechaAnitaAIso($slot['hasta']);
            if ($desde === null || $hasta === null) {
                continue;
            }
            $lineas[] = [
                'tiporetencion' => $tipo,
                'desdefecha' => $desde,
                'hastafecha' => $hasta,
                'porcentajeexclusion' => (float) $slot['porcentaje'],
                'comentario' => '',
                '_origen' => 'promae',
            ];
        }

        return $lineas;
    }

    public static function inferirTipoRetencionDesdePromaePorFechas(object $filaProexcl, object $promae): ?string
    {
        $desde = self::fechaAnitaInformix($filaProexcl->proex_desde_fecha ?? null);
        $hasta = self::fechaAnitaInformix($filaProexcl->proex_hasta_fecha ?? null);
        if ($desde === 0 || $hasta === 0) {
            return null;
        }

        $pares = [
            'I' => [
                self::fechaAnitaInformix($promae->prom_fe_ini_excl ?? null),
                self::fechaAnitaInformix($promae->prom_fecha_excl ?? null),
            ],
            'G' => [
                self::fechaAnitaInformix($promae->prom_fe_ini_exclrg ?? null),
                self::fechaAnitaInformix($promae->prom_fecha_exclrg ?? null),
            ],
            'B' => [
                self::fechaAnitaInformix($promae->prom_fe_ini_exclib ?? null),
                self::fechaAnitaInformix($promae->prom_fecha_exclib ?? null),
            ],
        ];

        foreach ($pares as $tipo => [$desdePromae, $hastaPromae]) {
            if ($desdePromae !== 0 && $hastaPromae !== 0 && $desde == $desdePromae && $hasta == $hastaPromae) {
                return $tipo;
            }
        }

        return null;
    }

    public static function inferirTipoRetencionDesdeComentario(string $comentario): ?string
    {
        $texto = mb_strtolower(trim($comentario));
        if ($texto === '') {
            return null;
        }
        if (preg_match('/\biva\b/u', $texto)) {
            return 'I';
        }
        if (preg_match('/gananc/u', $texto)) {
            return 'G';
        }
        if (preg_match('/ing\.?\s*brut|\biibb\b/u', $texto)) {
            return 'B';
        }

        return null;
    }

    public static function codigoAnitaParaBridge(string $codigo): string
    {
        return str_pad(ltrim(trim($codigo), '0') ?: '0', 6, '0', STR_PAD_LEFT);
    }

    public static function codigoErpDesdeAnita(string $codigoAnita): string
    {
        return ltrim(trim($codigoAnita), '0') ?: '0';
    }
}
