<?php

namespace App\Support\Ventas;

/**
 * Valida impuestos del padrón ARCA (constancia de inscripción) según condición IVA del cliente.
 */
final class ArcaPadronImpuestosClienteValidacion
{
    public const ESTADO_IMPUESTO_ACTIVO = 'AC';

    /**
     * @param  array<string, mixed>  $padronData  Respuesta normalizada de ConstanciaInscripcionService
     * @return array{
     *     aplica: bool,
     *     ok: bool,
     *     mensaje: string|null,
     *     detalles: list<string>,
     *     debe_suspender: bool,
     *     condicioniva_id: int|null
     * }
     */
    public static function validar(?int $condicionivaId, array $padronData): array
    {
        $condicionivaId = $condicionivaId > 0 ? $condicionivaId : null;

        $riId = (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1);
        $monoId = (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4);

        if ($condicionivaId === null || ! in_array($condicionivaId, [$riId, $monoId], true)) {
            return [
                'aplica' => false,
                'ok' => true,
                'mensaje' => null,
                'detalles' => [],
                'debe_suspender' => false,
                'condicioniva_id' => $condicionivaId,
            ];
        }

        if (! empty($padronData['error'])) {
            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'Problemas en ARCA: '.(string) $padronData['error'],
                'detalles' => [],
                'debe_suspender' => true,
                'condicioniva_id' => $condicionivaId,
            ];
        }

        $impuestos = is_array($padronData['impuestos'] ?? null) ? $padronData['impuestos'] : [];
        $datosMonotributo = is_array($padronData['datosMonotributo'] ?? null) ? $padronData['datosMonotributo'] : null;

        if ($condicionivaId === $riId) {
            return self::validarResponsableInscripto($impuestos, $condicionivaId);
        }

        return self::validarMonotributo($impuestos, $datosMonotributo, $condicionivaId);
    }

    /**
     * @param  list<array<string, mixed>>  $impuestos
     * @return array{aplica: bool, ok: bool, mensaje: string|null, detalles: list<string>, debe_suspender: bool, condicioniva_id: int}
     */
    private static function validarResponsableInscripto(array $impuestos, int $condicionivaId): array
    {
        $ivaId = (int) config('arca.padron_validacion_cliente.impuesto_iva_id', 30);
        $detalles = [];

        if (! self::tieneImpuestoActivo($impuestos, $ivaId)) {
            $detalles[] = self::mensajeImpuestoFaltante($impuestos, $ivaId, 'IVA');

            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'Problemas en ARCA: el cliente no tiene impuestos activos. Falta el impuesto IVA (id '.$ivaId.') con estado '
                    .self::ESTADO_IMPUESTO_ACTIVO.' en el padrón.',
                'detalles' => $detalles,
                'debe_suspender' => true,
                'condicioniva_id' => $condicionivaId,
            ];
        }

        return [
            'aplica' => true,
            'ok' => true,
            'mensaje' => null,
            'detalles' => [],
            'debe_suspender' => false,
            'condicioniva_id' => $condicionivaId,
        ];
    }

    /**
     * Monotributo: impuesto Monotributo (20) activo en datosMonotributo y en régimen general.
     *
     * @param  list<array<string, mixed>>  $impuestos
     * @param  array<string, mixed>|null  $datosMonotributo
     * @return array{aplica: bool, ok: bool, mensaje: string|null, detalles: list<string>, debe_suspender: bool, condicioniva_id: int}
     */
    private static function validarMonotributo(array $impuestos, ?array $datosMonotributo, int $condicionivaId): array
    {
        $monoId = (int) config('arca.padron_validacion_cliente.impuesto_monotributo_id', 20);
        $detalles = [];

        if ($datosMonotributo === null) {
            $detalles[] = 'No figuran datos de Monotributo en la respuesta de ARCA.';

            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'Problemas en ARCA: el cliente no tiene impuestos activos. No se encontró el bloque Monotributo en el padrón.',
                'detalles' => $detalles,
                'debe_suspender' => true,
                'condicioniva_id' => $condicionivaId,
            ];
        }

        $monoEnBloqueMono = self::tieneImpuestoActivo($impuestos, $monoId, 'monotributo');
        $monoEnRegimen = self::tieneImpuestoActivo($impuestos, $monoId, 'regimen_general');
        $tieneCategoria = ! empty($datosMonotributo['categoriaMonotributo']['idCategoria']);

        if (! $monoEnBloqueMono) {
            $detalles[] = self::mensajeImpuestoFaltante($impuestos, $monoId, 'Monotributo', 'monotributo');
        }
        if (! $monoEnRegimen && ! $tieneCategoria) {
            $detalles[] = self::mensajeImpuestoFaltante($impuestos, $monoId, 'Monotributo', 'regimen_general');
            $detalles[] = 'No figura categoría monotributo en ARCA.';
        }

        if (! $monoEnBloqueMono || (! $monoEnRegimen && ! $tieneCategoria)) {
            return [
                'aplica' => true,
                'ok' => false,
                'mensaje' => 'Problemas en ARCA: el cliente no tiene impuestos activos. Para Monotributo debe figurar '
                    .'el impuesto Monotributo (id '.$monoId.') activo (estado '.self::ESTADO_IMPUESTO_ACTIVO
                    .') en el bloque Monotributo y además en régimen general o con categoría monotributo registrada.',
                'detalles' => $detalles,
                'debe_suspender' => true,
                'condicioniva_id' => $condicionivaId,
            ];
        }

        return [
            'aplica' => true,
            'ok' => true,
            'mensaje' => null,
            'detalles' => [],
            'debe_suspender' => false,
            'condicioniva_id' => $condicionivaId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $impuestos
     */
    public static function tieneImpuestoActivo(array $impuestos, int $idImpuesto, ?string $fuente = null): bool
    {
        $estadoActivo = (string) config('arca.padron_validacion_cliente.estado_impuesto_activo', self::ESTADO_IMPUESTO_ACTIVO);

        foreach ($impuestos as $imp) {
            if ((int) ($imp['idImpuesto'] ?? 0) !== $idImpuesto) {
                continue;
            }
            if ($fuente !== null && (string) ($imp['fuente'] ?? '') !== $fuente) {
                continue;
            }
            if (strtoupper((string) ($imp['estadoImpuesto'] ?? '')) === strtoupper($estadoActivo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $impuestos
     */
    private static function mensajeImpuestoFaltante(
        array $impuestos,
        int $idImpuesto,
        string $etiqueta,
        ?string $fuente = null
    ): string {
        $fuenteTxt = $fuente ? ' ('.$fuente.')' : '';
        foreach ($impuestos as $imp) {
            if ((int) ($imp['idImpuesto'] ?? 0) !== $idImpuesto) {
                continue;
            }
            if ($fuente !== null && (string) ($imp['fuente'] ?? '') !== $fuente) {
                continue;
            }
            $estado = (string) ($imp['estadoImpuesto'] ?? 'sin estado');

            return $etiqueta.' (id '.$idImpuesto.')'.$fuenteTxt.': figura con estado '.$estado.' (se requiere '
                .self::ESTADO_IMPUESTO_ACTIVO.').';
        }

        return $etiqueta.' (id '.$idImpuesto.')'.$fuenteTxt.': no figura en el padrón.';
    }
}
