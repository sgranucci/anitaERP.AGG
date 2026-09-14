<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Tipodocumento;
use App\Models\Ventas\Cliente;

/**
 * Parsea clim_cuit de Anita → numerodocumento + tipodocumento_id del ERP,
 * y backfill de documentos faltantes en cliente.
 */
final class ClienteDocumentoAnitaSupport
{
    /**
     * @return array{numerodocumento: string, tipodocumento_id: ?int}
     */
    public static function desdeClimCuit(mixed $climCuit): array
    {
        $raw = trim((string) ($climCuit ?? ''));
        if ($raw === '') {
            return ['numerodocumento' => '', 'tipodocumento_id' => null];
        }

        if (preg_match('/^(CUIT|CUIL|DNI|LC|LE|PAS|CI)\s+(.+)$/iu', $raw, $m) === 1) {
            $abrev = strtoupper(trim($m[1]));
            $numero = trim($m[2]);

            return [
                'numerodocumento' => $numero,
                'tipodocumento_id' => self::idPorAbreviatura($abrev),
            ];
        }

        $soloDigitos = preg_replace('/\D+/', '', $raw) ?? '';

        if (preg_match('/^\d{2}-\d{8}-\d$/', $raw) === 1 || strlen($soloDigitos) === 11) {
            return [
                'numerodocumento' => $raw,
                'tipodocumento_id' => self::idPorCodigoExterno('80'),
            ];
        }

        if (strlen($soloDigitos) >= 7 && strlen($soloDigitos) <= 8 && ctype_digit($soloDigitos)) {
            return [
                'numerodocumento' => $soloDigitos,
                'tipodocumento_id' => self::idPorCodigoExterno('96'),
            ];
        }

        return [
            'numerodocumento' => $raw,
            'tipodocumento_id' => self::idPorCodigoExterno('80'),
        ];
    }

    /**
     * Resuelve tipodocumento desde un numerodocumento ya guardado en el ERP.
     */
    public static function tipodocumentoIdDesdeNumero(string $numerodocumento): ?int
    {
        $parsed = self::desdeClimCuit($numerodocumento);

        return $parsed['tipodocumento_id'];
    }

    public static function idPorCodigoExterno(string $codigoexterno): ?int
    {
        static $cache = [];
        $codigoexterno = trim($codigoexterno);
        if ($codigoexterno === '') {
            return null;
        }
        if (! array_key_exists($codigoexterno, $cache)) {
            $id = Tipodocumento::query()->where('codigoexterno', $codigoexterno)->value('id');
            $cache[$codigoexterno] = $id !== null ? (int) $id : null;
        }

        return $cache[$codigoexterno];
    }

    public static function idPorAbreviatura(string $abreviatura): ?int
    {
        static $cache = [];
        $abreviatura = strtoupper(trim($abreviatura));
        if ($abreviatura === '') {
            return null;
        }
        if ($abreviatura === 'CI') {
            $abreviatura = 'DNI';
        }
        if (! array_key_exists($abreviatura, $cache)) {
            $id = Tipodocumento::query()
                ->whereRaw('UPPER(abreviatura) = ?', [$abreviatura])
                ->value('id');
            $cache[$abreviatura] = $id !== null ? (int) $id : null;
        }

        return $cache[$abreviatura];
    }

    /**
     * @return array{
     *   anita: int,
     *   erp: int,
     *   sin_anita: int,
     *   completar_numero: int,
     *   completar_tipo: int,
     *   ya_completos: int,
     *   anita_sin_cuit: int,
     *   ejemplos: list<array<string, mixed>>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public static function analizar(?string $soloCodigo = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $anita = self::listarAnita();
        $query = Cliente::query()->select(['id', 'codigo', 'nombre', 'numerodocumento', 'tipodocumento_id']);
        if ($soloCodigo !== null && trim($soloCodigo) !== '') {
            $norm = self::normalizarCodigo($soloCodigo);
            $query->whereIn('codigo', self::variantesCodigo($norm));
        }
        $clientes = $query->get();

        $filas = [];
        $ejemplos = [];
        $stats = [
            'anita' => count($anita),
            'erp' => $clientes->count(),
            'sin_anita' => 0,
            'completar_numero' => 0,
            'completar_tipo' => 0,
            'ya_completos' => 0,
            'anita_sin_cuit' => 0,
        ];

        foreach ($clientes as $cliente) {
            $codigo = self::normalizarCodigo((string) $cliente->codigo);
            $filaAnita = $anita[$codigo] ?? null;
            $numeroActual = trim((string) ($cliente->numerodocumento ?? ''));
            $tipoActual = $cliente->tipodocumento_id !== null ? (int) $cliente->tipodocumento_id : null;

            if ($filaAnita === null) {
                $stats['sin_anita']++;
                // Sin Anita: igual se puede inferir tipo si ya hay número.
                if ($numeroActual !== '' && $tipoActual === null) {
                    $tipoNuevo = self::tipodocumentoIdDesdeNumero($numeroActual);
                    if ($tipoNuevo !== null) {
                        $fila = [
                            'cliente_id' => (int) $cliente->id,
                            'codigo' => (string) $cliente->codigo,
                            'nombre' => (string) $cliente->nombre,
                            'numerodocumento_nuevo' => null,
                            'tipodocumento_id_nuevo' => $tipoNuevo,
                            'anita_cuit' => '',
                        ];
                        $filas[] = $fila;
                        $stats['completar_tipo']++;
                        if (count($ejemplos) < 15) {
                            $ejemplos[] = $fila + ['numero_actual' => $numeroActual, 'tipo_actual' => $tipoActual];
                        }
                    }
                }

                continue;
            }

            $parsed = self::desdeClimCuit($filaAnita['cuit']);
            $numeroAnita = trim($parsed['numerodocumento']);
            $tipoAnita = $parsed['tipodocumento_id'];

            if ($numeroAnita === '') {
                $stats['anita_sin_cuit']++;
            }

            $numeroNuevo = null;
            $tipoNuevo = null;

            if ($numeroActual === '' && $numeroAnita !== '') {
                $numeroNuevo = $numeroAnita;
            }

            if ($tipoActual === null) {
                if ($tipoAnita !== null && ($numeroNuevo !== null || $numeroActual !== '')) {
                    $tipoNuevo = $tipoAnita;
                } elseif ($numeroActual !== '') {
                    $tipoNuevo = self::tipodocumentoIdDesdeNumero($numeroActual);
                }
            }

            if ($numeroNuevo === null && $tipoNuevo === null) {
                if ($numeroActual !== '' && $tipoActual !== null) {
                    $stats['ya_completos']++;
                }

                continue;
            }

            $fila = [
                'cliente_id' => (int) $cliente->id,
                'codigo' => (string) $cliente->codigo,
                'nombre' => (string) $cliente->nombre,
                'numerodocumento_nuevo' => $numeroNuevo,
                'tipodocumento_id_nuevo' => $tipoNuevo,
                'anita_cuit' => $filaAnita['cuit'],
            ];
            $filas[] = $fila;
            if ($numeroNuevo !== null) {
                $stats['completar_numero']++;
            }
            if ($tipoNuevo !== null) {
                $stats['completar_tipo']++;
            }
            if (count($ejemplos) < 15) {
                $ejemplos[] = $fila + ['numero_actual' => $numeroActual, 'tipo_actual' => $tipoActual];
            }
        }

        return $stats + [
            'ejemplos' => $ejemplos,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array{filas: list<array<string, mixed>>}  $analisis
     * @return array{numero: int, tipo: int}
     */
    public static function persistir(array $analisis): array
    {
        $ok = ['numero' => 0, 'tipo' => 0];

        foreach ($analisis['filas'] as $fila) {
            $cliente = Cliente::query()->find((int) $fila['cliente_id']);
            if (! $cliente) {
                continue;
            }

            $cambios = [];
            $numeroActual = trim((string) ($cliente->numerodocumento ?? ''));
            if ($numeroActual === '' && ! empty($fila['numerodocumento_nuevo'])) {
                $cambios['numerodocumento'] = (string) $fila['numerodocumento_nuevo'];
                $ok['numero']++;
            }
            if ($cliente->tipodocumento_id === null && ! empty($fila['tipodocumento_id_nuevo'])) {
                $cambios['tipodocumento_id'] = (int) $fila['tipodocumento_id_nuevo'];
                $ok['tipo']++;
            }
            if ($cambios !== []) {
                $cliente->update($cambios);
            }
        }

        return $ok;
    }

    /**
     * @return array<string, array{cuit: string, nombre: string}>
     */
    private static function listarAnita(): array
    {
        $api = new ApiAnita;
        $sistema = (string) config('cliente_anita.sistema', 'ventas');
        $tabla = (string) config('cliente_anita.tabla', 'climae');

        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => 'clim_cliente,clim_nombre,clim_cuit',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException($parsed['error_lectura']);
        }

        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $codigo = self::normalizarCodigo((string) ($fila->clim_cliente ?? ''));
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = [
                'cuit' => trim((string) ($fila->clim_cuit ?? '')),
                'nombre' => trim((string) ($fila->clim_nombre ?? '')),
            ];
        }

        return $out;
    }

    private static function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo)) {
            $norm = ltrim($codigo, '0');

            return $norm === '' ? '0' : $norm;
        }

        return $codigo;
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigoNorm): array
    {
        if ($codigoNorm === '') {
            return [];
        }
        if (! ctype_digit($codigoNorm) && ! ctype_digit(ltrim($codigoNorm, '0'))) {
            return [$codigoNorm];
        }
        $norm = ltrim($codigoNorm, '0');
        if ($norm === '') {
            $norm = '0';
        }

        return array_values(array_unique([$codigoNorm, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
    }
}
