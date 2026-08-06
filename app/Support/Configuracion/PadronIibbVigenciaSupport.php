<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Estado de vigencia del padrón IIBB en las jurisdicciones donde la empresa
 * actúa como agente, unificando las tres tablas donde viven los padrones.
 *
 * Sirve tanto al panel de la pantalla de importación como al aviso automático
 * de padrón vencido: si una jurisdicción no cubre el período en curso, se
 * factura con la tasa de descarte en lugar de la alícuota del contribuyente.
 */
final class PadronIibbVigenciaSupport
{
    private const CACHE = 'padron_iibb:vigencia_agente';

    private const CACHE_MINUTOS = 60;

    /** Jurisdicciones con tabla propia. El resto resuelve por padron_iibb_tasa. */
    private const TABLAS_PROPIAS = [
        901 => 'padron_iibb_caba',
        902 => 'padron_iibb_arba',
    ];

    /** Única jurisdicción con descarga desatendida (DFE ARBA). */
    private const AUTOMATICAS = [902];

    /**
     * Jurisdicciones en las que la empresa percibe o retiene.
     *
     * @return list<int>
     */
    public static function jurisdiccionesAgente(): array
    {
        $juris = [];

        foreach (['agente_percepcion_iibb', 'agente_retencion_iibb'] as $clave) {
            foreach (explode(',', (string) config('anita.' . $clave, '')) as $valor) {
                $valor = trim($valor);
                if ($valor !== '' && ctype_digit($valor)) {
                    $juris[] = (int) $valor;
                }
            }
        }

        $juris = array_values(array_unique($juris));
        sort($juris);

        return $juris;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function estado(?string $fecha = null): array
    {
        $fecha = $fecha ?: date('Y-m-d');

        try {
            return Cache::remember(
                self::CACHE . ':' . $fecha,
                now()->addMinutes(self::CACHE_MINUTOS),
                static fn (): array => self::consultar($fecha)
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Jurisdicciones sin padrón vigente para la fecha.
     *
     * @return list<array<string,mixed>>
     */
    public static function vencidas(?string $fecha = null): array
    {
        return array_values(array_filter(
            self::estado($fecha),
            static fn (array $fila): bool => $fila['vigente'] === false
        ));
    }

    public static function olvidar(): void
    {
        try {
            foreach ([date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))] as $fecha) {
                Cache::forget(self::CACHE . ':' . $fecha);
            }
        } catch (Throwable $e) {
            // Se recalcula al vencer el TTL.
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function consultar(string $fecha): array
    {
        $provincias = DB::table('provincia')
            ->select('id', 'nombre', 'codigo', 'jurisdiccion')
            ->get()
            ->keyBy(static fn ($p) => (int) $p->jurisdiccion);

        $estado = [];

        foreach (self::jurisdiccionesAgente() as $jurisdiccion) {
            $provincia = $provincias->get($jurisdiccion);
            if ($provincia === null) {
                continue;
            }

            $tabla = self::TABLAS_PROPIAS[$jurisdiccion] ?? 'padron_iibb_tasa';
            $propia = isset(self::TABLAS_PROPIAS[$jurisdiccion]);

            $base = static function () use ($tabla, $propia, $provincia) {
                $query = DB::table($tabla);

                if (! $propia) {
                    $query->where('provincia_id', $provincia->id);
                }

                return $query;
            };

            $vigente = $base()
                ->where('desdefecha', '<=', $fecha)
                ->where('hastafecha', '>=', $fecha)
                ->limit(1)
                ->exists();

            $ultima = $base()->max('desdefecha');

            // El conteo por período solo es barato en padron_iibb_tasa, que tiene
            // índice (provincia_id, desdefecha, hastafecha). En ARBA y CABA se
            // toma el dato de la última importación registrada.
            $filas = null;
            if (! $propia && $ultima !== null) {
                $filas = (int) $base()->where('desdefecha', $ultima)->count();
            } else {
                $filas = DB::table('padron_iibb_carga')
                    ->where('jurisdiccion', $jurisdiccion)
                    ->where('estado', 'ok')
                    ->orderByDesc('id')
                    ->value('filas_insertadas');
                $filas = $filas === null ? null : (int) $filas;
            }

            $estado[] = [
                'jurisdiccion' => $jurisdiccion,
                'provincia_id' => (int) $provincia->id,
                'provincia' => $provincia->nombre,
                'codigo' => $provincia->codigo,
                'tabla' => $tabla,
                'ultimo_periodo' => $ultima,
                'vigente' => $vigente,
                'automatico' => in_array($jurisdiccion, self::AUTOMATICAS, true),
                'filas' => $filas,
            ];
        }

        return $estado;
    }
}
