<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Repositories\Caja\Flash\FlashCajaRepositoryInterface;
use App\Support\Database\DbContencionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cálculo automático del flash de la jornada cerrada (Wigos + ERP).
 * El cron de las 14:30 usa la fecha de ayer. No pisa un registro que un usuario ya cargó o editó.
 */
final class FlashCajaCalculoDiarioService
{
    public const ESTADO_CREADO = 'creado';

    public const ESTADO_ACTUALIZADO = 'actualizado';

    public const ESTADO_OMITIDO_USUARIO = 'omitido_usuario';

    public const ESTADO_OMITIDO_EXISTE = 'omitido_existe';

    public const ESTADO_ERROR = 'error';

    public function __construct(
        private readonly FlashCajaCalculoService $calculoService,
        private readonly FlashCajaRepositoryInterface $repository,
        private readonly FlashCajaAnitaExportService $exportAnitaService,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   fecha: string,
     *   dry_run: bool,
     *   forzar: bool,
     *   empresas: list<array<string, mixed>>,
     *   resumen: array{creados: int, actualizados: int, omitidos: int, errores: int}
     * }
     */
    public function ejecutar(string $fecha, array $empresaIds, bool $dryRun = false, bool $forzar = false): array
    {
        $fechaSql = Carbon::parse($fecha)->toDateString();
        $empresas = [];
        $resumen = [
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => 0,
        ];

        Log::info('flash.calculo_diario.inicio', [
            'fecha' => $fechaSql,
            'empresas' => $empresaIds,
            'dry_run' => $dryRun,
            'forzar' => $forzar,
        ]);

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId < 1) {
                continue;
            }

            $resultado = $this->procesarEmpresa($empresaId, $fechaSql, $dryRun, $forzar);
            $empresas[] = $resultado;

            match ((string) ($resultado['estado'] ?? '')) {
                self::ESTADO_CREADO => $resumen['creados']++,
                self::ESTADO_ACTUALIZADO => $resumen['actualizados']++,
                self::ESTADO_ERROR => $resumen['errores']++,
                default => $resumen['omitidos']++,
            };
        }

        Log::info('flash.calculo_diario.fin', [
            'fecha' => $fechaSql,
            'dry_run' => $dryRun,
            'forzar' => $forzar,
            'resumen' => $resumen,
        ]);

        return [
            'fecha' => $fechaSql,
            'dry_run' => $dryRun,
            'forzar' => $forzar,
            'empresas' => $empresas,
            'resumen' => $resumen,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function procesarEmpresa(int $empresaId, string $fechaSql, bool $dryRun, bool $forzar): array
    {
        $nombre = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? 'empresa '.$empresaId);
        $existente = $this->repository->findPorEmpresaFecha($empresaId, $fechaSql);
        $base = [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $nombre,
            'fecha' => $fechaSql,
            'flash_id' => $existente?->id,
        ];

        $omitido = $this->omitirSiYaCargado($existente, $forzar);
        if ($omitido !== null) {
            return $base + $omitido;
        }

        if ($dryRun) {
            $estado = $existente === null ? self::ESTADO_CREADO : self::ESTADO_ACTUALIZADO;

            return $base + [
                'estado' => $estado,
                'mensaje' => $existente === null
                    ? 'Dry-run: se calcularía y crearía el flash.'
                    : 'Dry-run: se recalcularía y actualizaría el flash id '.$existente->id.'.',
            ];
        }

        try {
            $calculado = $this->calculoService->calcular($empresaId, $fechaSql);
            $payload = FlashCajaCalculoService::payloadPersistible($calculado);
            $payload['empresa_id'] = $empresaId;
            $payload['fecha'] = $fechaSql;

            return DB::transaction(function () use ($empresaId, $fechaSql, $payload, $forzar, $base) {
                $actual = $this->repository->findPorEmpresaFecha($empresaId, $fechaSql, true);
                $omitido = $this->omitirSiYaCargado($actual, $forzar);
                if ($omitido !== null) {
                    return $base + $omitido + ['flash_id' => $actual?->id];
                }

                if ($actual === null) {
                    if (! isset($payload['comentario']) || trim((string) $payload['comentario']) === '') {
                        $payload['comentario'] = 'cron diario';
                    }
                    try {
                        $flash = $this->repository->create($payload);
                    } catch (Throwable $e) {
                        if (! DbContencionSupport::esViolacionUnicidad($e, 'flash_caja_empresa_fecha_unique', 'flash_caja')) {
                            throw $e;
                        }
                        $carrera = $this->repository->findPorEmpresaFecha($empresaId, $fechaSql);
                        $omitidoCarrera = $this->omitirSiYaCargado($carrera, false);
                        if ($omitidoCarrera !== null) {
                            return $base + $omitidoCarrera + ['flash_id' => $carrera?->id];
                        }

                        throw $e;
                    }

                    $syncAnita = $this->exportAnitaService->enviarSiNoExisteEnAnita($flash);

                    return $base + [
                        'estado' => self::ESTADO_CREADO,
                        'flash_id' => $flash->id,
                        'mensaje' => 'Flash creado desde Wigos/ERP. '.$syncAnita['mensaje'],
                        'anita_sync' => $syncAnita['resultado'],
                    ];
                }

                unset($payload['comentario'], $payload['att'], $payload['cotizacion'], $payload['pos_online']);
                $this->repository->update($payload, $actual->id);

                return $base + [
                    'estado' => self::ESTADO_ACTUALIZADO,
                    'flash_id' => $actual->id,
                    'mensaje' => 'Flash recalculado (forzar).',
                ];
            });
        } catch (Throwable $e) {
            Log::error('flash.calculo_diario.error', [
                'empresa_id' => $empresaId,
                'fecha' => $fechaSql,
                'error' => $e->getMessage(),
            ]);

            return $base + [
                'estado' => self::ESTADO_ERROR,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{estado: string, mensaje: string}|null
     */
    private function omitirSiYaCargado(?FlashCaja $flash, bool $forzar): ?array
    {
        if ($flash === null || $forzar) {
            return null;
        }

        if ($flash->fueCargadoPorUsuario()) {
            return [
                'estado' => self::ESTADO_OMITIDO_USUARIO,
                'mensaje' => 'Usuario ya cargó el flash en el ERP ('.$this->describirUsuarioCarga($flash).'). No se pisa.',
            ];
        }

        return [
            'estado' => self::ESTADO_OMITIDO_EXISTE,
            'mensaje' => 'Ya existe flash para esa empresa/fecha (id '.$flash->id.'). No se pisa.',
        ];
    }

    private function describirUsuarioCarga(FlashCaja $flash): string
    {
        $flash->loadMissing(['creoUsuario', 'actualizoUsuario']);
        $usuario = $flash->creoUsuario ?? $flash->actualizoUsuario;
        $id = (int) ($flash->creousuario_id ?: $flash->actualizousuario_id);
        if ($usuario === null) {
            return 'id '.$id;
        }

        $etiqueta = trim((string) ($usuario->nombre ?: $usuario->usuario ?: ''));

        return ($etiqueta !== '' ? $etiqueta.' ' : '').'(id '.$usuario->id.')';
    }
}
