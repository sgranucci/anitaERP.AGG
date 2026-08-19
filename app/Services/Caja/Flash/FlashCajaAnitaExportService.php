<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\ApiAnita;
use App\Models\Caja\Flash\FlashCaja;
use App\Support\Caja\Flash\FlashCajaAnitaMapeoSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía a Anita (tabla flash) un flash generado en el ERP, solo si Informix
 * no tiene ya el registro nativo (clave empresa+sala+fecha).
 */
final class FlashCajaAnitaExportService
{
    public const RESULTADO_ENVIADO = 'enviado';

    public const RESULTADO_ACTUALIZADO = 'actualizado';

    public const RESULTADO_OMITIDO_EXISTE = 'omitido_existe';

    public const RESULTADO_OMITIDO_SALA = 'omitido_sala';

    public const RESULTADO_OMITIDO_DESHABILITADO = 'omitido_deshabilitado';

    public const RESULTADO_ERROR = 'error';

    /**
     * Alta ERP → Anita: inserta solo si Informix no tiene ya el registro nativo.
     *
     * @return array{resultado: string, mensaje: string}
     */
    public function enviarSiNoExisteEnAnita(FlashCaja $flash): array
    {
        $ctx = $this->resolverContexto($flash);
        if (isset($ctx['resultado'])) {
            return $ctx;
        }

        try {
            if ($this->existeEnAnita($ctx['flash_empresa'], $ctx['sala'], $ctx['fecha_entera'])) {
                return [
                    'resultado' => self::RESULTADO_OMITIDO_EXISTE,
                    'mensaje' => 'Anita ya tiene flash nativo sala '.$ctx['sala'].' fecha '.$ctx['fecha_iso'].'.',
                ];
            }

            $this->insertarEnAnita($flash, $ctx['sala'], $ctx['flash_empresa']);
            $this->logSync('flash.anita.export.enviado', $flash, $ctx);

            return [
                'resultado' => self::RESULTADO_ENVIADO,
                'mensaje' => 'Flash enviado a Anita (sala '.$ctx['sala'].').',
            ];
        } catch (Throwable $e) {
            return $this->errorSync($flash, $ctx, $e);
        }
    }

    /**
     * Edición ERP → Anita: actualiza el registro; si no existe, lo inserta.
     * Si cambió empresa/fecha, borra la clave anterior en Informix.
     *
     * @return array{resultado: string, mensaje: string}
     */
    public function enviarModificacionEnAnita(
        FlashCaja $flash,
        ?int $empresaIdAnterior = null,
        ?string $fechaAnterior = null,
    ): array {
        $ctx = $this->resolverContexto($flash);
        if (isset($ctx['resultado'])) {
            return $ctx;
        }

        try {
            $this->eliminarClaveAnteriorSiCambio($ctx, $empresaIdAnterior, $fechaAnterior);

            if ($this->existeEnAnita($ctx['flash_empresa'], $ctx['sala'], $ctx['fecha_entera'])) {
                $this->actualizarEnAnita($flash, $ctx['sala'], $ctx['flash_empresa'], $ctx['fecha_entera']);
                $this->logSync('flash.anita.export.actualizado', $flash, $ctx);

                return [
                    'resultado' => self::RESULTADO_ACTUALIZADO,
                    'mensaje' => 'Flash actualizado en Anita (sala '.$ctx['sala'].').',
                ];
            }

            $this->insertarEnAnita($flash, $ctx['sala'], $ctx['flash_empresa']);
            $this->logSync('flash.anita.export.enviado', $flash, $ctx);

            return [
                'resultado' => self::RESULTADO_ENVIADO,
                'mensaje' => 'Flash enviado a Anita (sala '.$ctx['sala'].').',
            ];
        } catch (Throwable $e) {
            return $this->errorSync($flash, $ctx, $e);
        }
    }

    public function existeEnAnita(int $flashEmpresa, int $sala, int $fechaEntera): bool
    {
        $sistema = (string) config('flash_caja_anita.sistema', 'caja');
        $tabla = (string) config('flash_caja_anita.tabla', 'flash');
        $where = FlashCajaAnitaMapeoSupport::whereClave($flashEmpresa, $sala, $fechaEntera);

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => 'flash_empresa, flash_sala, flash_fecha',
            'whereArmado' => $where,
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo consultar flash Anita: '.$parsed['error_lectura']);
        }

        return $parsed['filas'] !== [];
    }

    private function insertarEnAnita(FlashCaja $flash, int $sala, int $flashEmpresa): void
    {
        $sistema = (string) config('flash_caja_anita.sistema', 'caja');
        $tabla = (string) config('flash_caja_anita.tabla', 'flash');
        $valores = FlashCajaAnitaMapeoSupport::valoresDesdeFlash($flash, $sala, $flashEmpresa);

        (new ApiAnita)->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => implode(', ', FlashCajaAnitaMapeoSupport::camposInsertAnita()),
            'valores' => FlashCajaAnitaMapeoSupport::valoresSql($valores),
        ], 'flash insert sala '.$sala.' fecha '.$valores['flash_fecha'], 'flash.anita.export.fallo');
    }

    private function actualizarEnAnita(FlashCaja $flash, int $sala, int $flashEmpresa, int $fechaEntera): void
    {
        $sistema = (string) config('flash_caja_anita.sistema', 'caja');
        $tabla = (string) config('flash_caja_anita.tabla', 'flash');
        $valores = FlashCajaAnitaMapeoSupport::valoresDesdeFlash($flash, $sala, $flashEmpresa);

        (new ApiAnita)->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'valores' => FlashCajaAnitaMapeoSupport::valoresUpdateSql($valores),
            'whereArmado' => FlashCajaAnitaMapeoSupport::whereClave($flashEmpresa, $sala, $fechaEntera),
        ], 'flash update sala '.$sala.' fecha '.$fechaEntera, 'flash.anita.export.fallo');
    }

    /**
     * @return array{resultado: string, mensaje: string}|array{empresa_id: int, sala: int, flash_empresa: int, fecha_iso: string, fecha_entera: int}
     */
    private function resolverContexto(FlashCaja $flash): array
    {
        if (! (bool) config('flash_caja_anita.escritura_habilitada', true)) {
            return [
                'resultado' => self::RESULTADO_OMITIDO_DESHABILITADO,
                'mensaje' => 'Escritura Anita deshabilitada.',
            ];
        }

        $empresaId = (int) $flash->empresa_id;
        $sala = FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId($empresaId);
        if ($sala === null) {
            return [
                'resultado' => self::RESULTADO_OMITIDO_SALA,
                'mensaje' => 'Empresa '.$empresaId.' no tiene sala Anita mapeada.',
            ];
        }

        $fechaIso = $flash->fecha?->format('Y-m-d') ?? '';
        $fechaEntera = FlashCajaAnitaMapeoSupport::fechaEntera($fechaIso);
        if ($fechaEntera < 10000101) {
            return [
                'resultado' => self::RESULTADO_ERROR,
                'mensaje' => 'Fecha inválida para sync Anita.',
            ];
        }

        return [
            'empresa_id' => $empresaId,
            'sala' => $sala,
            'flash_empresa' => FlashCajaAnitaMapeoSupport::flashEmpresaDesdeEmpresaId($empresaId),
            'fecha_iso' => $fechaIso,
            'fecha_entera' => $fechaEntera,
        ];
    }

    /**
     * @param  array{sala: int, flash_empresa: int, fecha_entera: int, fecha_iso: string}  $ctx
     */
    private function eliminarClaveAnteriorSiCambio(array $ctx, ?int $empresaIdAnterior, ?string $fechaAnterior): void
    {
        if ($empresaIdAnterior === null || $fechaAnterior === null || $fechaAnterior === '') {
            return;
        }

        $salaAnterior = FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId($empresaIdAnterior);
        if ($salaAnterior === null) {
            return;
        }

        $fechaEnteraAnterior = FlashCajaAnitaMapeoSupport::fechaEntera($fechaAnterior);
        $flashEmpresaAnterior = FlashCajaAnitaMapeoSupport::flashEmpresaDesdeEmpresaId($empresaIdAnterior);
        $mismaClave = $flashEmpresaAnterior === $ctx['flash_empresa']
            && $salaAnterior === $ctx['sala']
            && $fechaEnteraAnterior === $ctx['fecha_entera'];
        if ($mismaClave || $fechaEnteraAnterior < 10000101) {
            return;
        }

        if (! $this->existeEnAnita($flashEmpresaAnterior, $salaAnterior, $fechaEnteraAnterior)) {
            return;
        }

        $sistema = (string) config('flash_caja_anita.sistema', 'caja');
        $tabla = (string) config('flash_caja_anita.tabla', 'flash');
        (new ApiAnita)->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'whereArmado' => FlashCajaAnitaMapeoSupport::whereClave(
                $flashEmpresaAnterior,
                $salaAnterior,
                $fechaEnteraAnterior
            ),
        ], 'flash delete sala '.$salaAnterior.' fecha '.$fechaEnteraAnterior, 'flash.anita.export.fallo');
    }

    /**
     * @param  array{empresa_id: int, sala: int, fecha_iso: string}  $ctx
     */
    private function logSync(string $evento, FlashCaja $flash, array $ctx): void
    {
        Log::info($evento, [
            'flash_id' => $flash->id,
            'empresa_id' => $ctx['empresa_id'],
            'fecha' => $ctx['fecha_iso'],
            'sala' => $ctx['sala'],
        ]);
    }

    /**
     * @param  array{empresa_id?: int, fecha_iso?: string}  $ctx
     * @return array{resultado: string, mensaje: string}
     */
    private function errorSync(FlashCaja $flash, array $ctx, Throwable $e): array
    {
        Log::warning('flash.anita.export.error', [
            'flash_id' => $flash->id,
            'empresa_id' => $ctx['empresa_id'] ?? $flash->empresa_id,
            'fecha' => $ctx['fecha_iso'] ?? null,
            'error' => $e->getMessage(),
        ]);

        return [
            'resultado' => self::RESULTADO_ERROR,
            'mensaje' => $e->getMessage(),
        ];
    }
}
