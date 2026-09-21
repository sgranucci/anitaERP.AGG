<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Retencion_Cobranza;
use App\Models\Configuracion\Retencion_Cobranza_Cuentacontable;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa retenciones de cobranza (clientes) desde Anita che_ban.tctes.
 *
 * Criterio: tctes_tipo_imp = R, excluye proveedores / percepciones / impuestos ajenos.
 * Cuenta contable: vía cuentacaja (tctes_imputacion → cuentacaja.codigo → cuentacontable_id).
 */
final class RetencionCobranzaAnitaImportService
{
    /** Claves Anita de retenciones a proveedores (no van a cobranza). */
    private const EXCLUIR_CLAVES = [
        'RGP', 'RIP', 'RSP', 'RTP', 'RIC', 'RBA',
        'PTF', 'PCO',
        'IDC', 'SIR', 'IMP', 'SIC', 'SIP', 'IAR',
    ];

    /**
     * @return array{
     *   en_anita: int,
     *   candidatos: int,
     *   crear: int,
     *   actualizar: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function analizar(): array
    {
        return $this->procesar(dryRun: true);
    }

    /**
     * @return array{
     *   en_anita: int,
     *   candidatos: int,
     *   crear: int,
     *   actualizar: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function ejecutar(): array
    {
        return $this->procesar(dryRun: false);
    }

    /**
     * @return array{
     *   en_anita: int,
     *   candidatos: int,
     *   crear: int,
     *   actualizar: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   filas: list<array<string, mixed>>
     * }
     */
    private function procesar(bool $dryRun): array
    {
        $resultado = [
            'en_anita' => 0,
            'candidatos' => 0,
            'crear' => 0,
            'actualizar' => 0,
            'omitidos' => 0,
            'errores' => [],
            'filas' => [],
        ];

        $filasAnita = $this->leerTctes();
        if ($filasAnita === null) {
            $resultado['errores'][] = 'No se pudo leer tctes desde Anita (che_ban).';

            return $resultado;
        }

        $resultado['en_anita'] = count($filasAnita);

        foreach ($filasAnita as $fila) {
            $clave = strtoupper(trim((string) ($fila->tctes_clave ?? '')));
            $desc = trim((string) ($fila->tctes_desc ?? ''));
            $tipoImp = strtoupper(trim((string) ($fila->tctes_tipo_imp ?? '')));
            $imputacion = trim((string) ($fila->tctes_imputacion ?? ''));

            if ($tipoImp !== 'R' || $clave === '' || $desc === '') {
                continue;
            }
            if (in_array($clave, self::EXCLUIR_CLAVES, true)) {
                continue;
            }
            $descUp = mb_strtoupper($desc, 'UTF-8');
            if (str_contains($descUp, 'PROVEED') || str_starts_with($descUp, 'PERCEP')) {
                continue;
            }

            $resultado['candidatos']++;

            $mapeo = $this->mapearTipoYProvincia($clave, $descUp);
            if ($mapeo === null) {
                $resultado['omitidos']++;
                $resultado['filas'][] = [
                    'accion' => 'omitir',
                    'clave' => $clave,
                    'nombre' => $desc,
                    'motivo' => 'No se pudo inferir tiporetencion',
                ];
                continue;
            }

            $cuenta = $this->resolverCuentacontable($imputacion);
            if ($cuenta === null) {
                $resultado['omitidos']++;
                $resultado['errores'][] = "{$clave}: sin cuentacaja/cuentacontable para imputación {$imputacion}";
                $resultado['filas'][] = [
                    'accion' => 'omitir',
                    'clave' => $clave,
                    'nombre' => $desc,
                    'motivo' => "Sin cuenta contable (imp={$imputacion})",
                ];
                continue;
            }

            $existente = Retencion_Cobranza::query()
                ->where('nombre', $desc)
                ->first();

            $detalle = [
                'clave' => $clave,
                'nombre' => $desc,
                'tiporetencion' => $mapeo['tiporetencion'],
                'provincia_id' => $mapeo['provincia_id'],
                'provincia' => $mapeo['provincia_nombre'],
                'cuentacontable_id' => $cuenta['id'],
                'cuentacontable' => $cuenta['codigo'].' '.$cuenta['nombre'],
                'empresa_id' => $cuenta['empresa_id'],
            ];

            if ($existente === null) {
                $resultado['crear']++;
                $detalle['accion'] = $dryRun ? 'crear (sim)' : 'crear';
                if (! $dryRun) {
                    $this->crearRetencion($detalle);
                }
            } else {
                $huboCambio = $this->sincronizarExistente($existente, $detalle, $dryRun);
                if ($huboCambio) {
                    $resultado['actualizar']++;
                    $detalle['accion'] = $dryRun ? 'actualizar (sim)' : 'actualizar';
                    $detalle['retencion_id'] = $existente->id;
                } else {
                    $resultado['omitidos']++;
                    $detalle['accion'] = 'sin cambio';
                    $detalle['retencion_id'] = $existente->id;
                }
            }

            $resultado['filas'][] = $detalle;
        }

        return $resultado;
    }

    /**
     * @return list<object>|null
     */
    private function leerTctes(): ?array
    {
        try {
            $api = new ApiAnita();
            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => 'tctes',
                'campos' => 'tctes_clave,tctes_desc,tctes_imputacion,tctes_tipo_imp,tctes_numero',
            ]));

            if ($parsed['error_lectura'] !== null) {
                Log::warning('retencion_cobranza.import_anita.tctes', [
                    'error' => $parsed['error_lectura'],
                ]);

                return null;
            }

            return $parsed['filas'];
        } catch (\Throwable $e) {
            Log::warning('retencion_cobranza.import_anita.exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{tiporetencion: string, provincia_id: int|null, provincia_nombre: string}|null
     */
    private function mapearTipoYProvincia(string $clave, string $descUp): ?array
    {
        $tipo = null;
        if ($clave === 'REI' || str_contains($descUp, ' IVA') || str_ends_with($descUp, 'IVA') || str_contains($descUp, 'DE IVA')) {
            $tipo = Retencion_Cobranza::$enumTipoRetencion['I'];
        } elseif ($clave === 'RGC' || str_contains($descUp, 'GANAN')) {
            $tipo = Retencion_Cobranza::$enumTipoRetencion['G'];
        } elseif ($clave === 'RSC' || str_contains($descUp, 'SUSS') || str_contains($descUp, 'SEGURIDAD')) {
            $tipo = Retencion_Cobranza::$enumTipoRetencion['S'];
        } elseif (
            in_array($clave, ['RIB', 'RBB', 'RTF', 'RCO'], true)
            || str_contains($descUp, 'IIBB')
            || str_contains($descUp, 'INGRESOS BRUTOS')
            || str_contains($descUp, 'I.B.')
        ) {
            $tipo = Retencion_Cobranza::$enumTipoRetencion['B'];
        }

        if ($tipo === null) {
            return null;
        }

        $provinciaId = null;
        $provinciaNombre = '';
        if ($tipo === Retencion_Cobranza::$enumTipoRetencion['B']) {
            $codigoJuris = $this->inferirCodigoProvincia($clave, $descUp);
            if ($codigoJuris !== null) {
                $prov = Provincia::query()->where('codigo', $codigoJuris)->first();
                if ($prov) {
                    $provinciaId = (int) $prov->id;
                    $provinciaNombre = (string) $prov->nombre;
                }
            }
        }

        return [
            'tiporetencion' => $tipo,
            'provincia_id' => $provinciaId,
            'provincia_nombre' => $provinciaNombre,
        ];
    }

    private function inferirCodigoProvincia(string $clave, string $descUp): ?string
    {
        return match (true) {
            $clave === 'RIB', str_contains($descUp, 'CABA'), str_contains($descUp, 'CAPITAL') => '901',
            $clave === 'RBB', str_contains($descUp, 'BS AS'), str_contains($descUp, 'BUENOS AIRES'), str_contains($descUp, ' BA') => '902',
            $clave === 'RCO', str_contains($descUp, 'CORDOBA'), str_contains($descUp, 'CÓRDOBA') => '904',
            $clave === 'RTF', str_contains($descUp, 'TIERRA DEL FUEGO'), str_contains($descUp, 'TDF') => '923',
            default => null,
        };
    }

    /**
     * @return array{id: int, codigo: string, nombre: string, empresa_id: int}|null
     */
    private function resolverCuentacontable(string $imputacion): ?array
    {
        $codigo = ltrim($imputacion, '0');
        if ($codigo === '') {
            return null;
        }

        $variantes = array_values(array_unique(array_filter([
            $codigo,
            $imputacion,
            ltrim($imputacion, '0'),
            (string) (int) $codigo,
        ], static fn ($v) => $v !== '' && $v !== null)));

        $caja = Cuentacaja::query()->whereIn('codigo', $variantes)->first();

        if ($caja === null || ! $caja->cuentacontable_id) {
            return null;
        }

        $cc = Cuentacontable::query()->find($caja->cuentacontable_id);
        if ($cc === null) {
            return null;
        }

        $empresaId = (int) ($cc->empresa_id ?: $caja->empresa_id ?: 0);
        if ($empresaId <= 0) {
            return null;
        }

        return [
            'id' => (int) $cc->id,
            'codigo' => (string) $cc->codigo,
            'nombre' => (string) $cc->nombre,
            'empresa_id' => $empresaId,
        ];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function crearRetencion(array $detalle): void
    {
        DB::transaction(function () use ($detalle) {
            $retencion = Retencion_Cobranza::query()->create([
                'nombre' => $detalle['nombre'],
                'tiporetencion' => $detalle['tiporetencion'],
                'provincia_id' => $detalle['provincia_id'],
            ]);

            Retencion_Cobranza_Cuentacontable::query()->create([
                'retencion_cobranza_id' => $retencion->id,
                'empresa_id' => $detalle['empresa_id'],
                'cuentacontable_id' => $detalle['cuentacontable_id'],
                'creousuario_id' => auth()->id() ?: 1,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function sincronizarExistente(Retencion_Cobranza $existente, array $detalle, bool $dryRun): bool
    {
        $cambios = false;

        if ((string) $existente->tiporetencion !== (string) $detalle['tiporetencion']
            || (int) ($existente->provincia_id ?? 0) !== (int) ($detalle['provincia_id'] ?? 0)
        ) {
            $cambios = true;
            if (! $dryRun) {
                $existente->update([
                    'tiporetencion' => $detalle['tiporetencion'],
                    'provincia_id' => $detalle['provincia_id'],
                ]);
            }
        }

        $link = Retencion_Cobranza_Cuentacontable::query()
            ->where('retencion_cobranza_id', $existente->id)
            ->where('empresa_id', $detalle['empresa_id'])
            ->first();

        if ($link === null) {
            $cambios = true;
            if (! $dryRun) {
                Retencion_Cobranza_Cuentacontable::query()->create([
                    'retencion_cobranza_id' => $existente->id,
                    'empresa_id' => $detalle['empresa_id'],
                    'cuentacontable_id' => $detalle['cuentacontable_id'],
                    'creousuario_id' => auth()->id() ?: 1,
                ]);
            }
        } elseif ((int) $link->cuentacontable_id !== (int) $detalle['cuentacontable_id']) {
            $cambios = true;
            if (! $dryRun) {
                $link->update(['cuentacontable_id' => $detalle['cuentacontable_id']]);
            }
        }

        return $cambios;
    }
}
