<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Tiposuspensionproveedor;
use App\Services\Configuracion\ModuloAvisoService;
use Illuminate\Support\Facades\Log;

/**
 * Suspensión automática del ABM de proveedor cuando el padrón ARCA no cierra con la condición IVA.
 * Graba el tipo de suspensión y avisa. No copia el estado a Anita: eso ocurre al guardar el formulario.
 */
final class ProveedorPadronArcaSuspensionSupport
{
    public const MOTIVO_IVA = 'Padrón ARCA: IVA no activo';

    public const MOTIVO_MONOTRIBUTO = 'Padrón ARCA: monotributo no activo';

    public const MOTIVO_BAJA = 'Padrón ARCA: baja de impuestos';

    public const AVISO_CODIGO = 'proveedor_padron_arca_suspension';

    /**
     * @param  array<string, mixed>  $validacion
     * @return array{suspendido: bool, tiposuspension_id: int|null, tiposuspension_nombre: string|null}
     */
    public static function aplicar(Proveedor $proveedor, array $validacion): array
    {
        if (! ($validacion['debe_suspender'] ?? false) || ! ($validacion['aplica'] ?? false)) {
            return [
                'suspendido' => false,
                'tiposuspension_id' => null,
                'tiposuspension_nombre' => null,
            ];
        }

        $nombre = self::nombreMotivo($validacion);
        $tipoId = self::resolverTipoId($nombre);
        $yaSuspendido = (string) $proveedor->estado === 'Suspendido';
        $mismoMotivo = (int) $proveedor->tiposuspension_id === $tipoId;

        if (! $yaSuspendido || ! $mismoMotivo) {
            $proveedor->estado = 'Suspendido';
            $proveedor->tiposuspension_id = $tipoId;
            $proveedor->save();

            self::avisar($proveedor, $nombre, $validacion);
        }

        return [
            'suspendido' => true,
            'tiposuspension_id' => $tipoId,
            'tiposuspension_nombre' => $nombre,
        ];
    }

    /**
     * @param  array<string, mixed>  $validacion
     */
    public static function nombreMotivo(array $validacion): string
    {
        $mensaje = mb_strtolower((string) ($validacion['mensaje'] ?? ''));

        if (str_contains($mensaje, 'baja de impuestos')) {
            return self::MOTIVO_BAJA;
        }

        if (str_contains($mensaje, 'monotributo')) {
            return self::MOTIVO_MONOTRIBUTO;
        }

        return self::MOTIVO_IVA;
    }

    private static function resolverTipoId(string $nombre): int
    {
        $existente = Tiposuspensionproveedor::query()->where('nombre', $nombre)->value('id');
        if ($existente) {
            return (int) $existente;
        }

        return (int) Tiposuspensionproveedor::query()->create(['nombre' => $nombre])->id;
    }

    /**
     * @param  array<string, mixed>  $validacion
     */
    private static function avisar(Proveedor $proveedor, string $motivo, array $validacion): void
    {
        $mensaje = str_replace(
            ['el cliente', 'El cliente'],
            ['el proveedor', 'El proveedor'],
            (string) ($validacion['mensaje'] ?? '')
        );
        $detalles = $validacion['detalles'] ?? [];
        $detalle = is_array($detalles)
            ? implode("\n", array_map(static fn ($linea) => trim((string) $linea), $detalles))
            : '';

        try {
            app(ModuloAvisoService::class)->enviar('compras', self::AVISO_CODIGO, (int) $proveedor->id, [
                'motivo' => $motivo,
                'mensaje' => $mensaje,
                'detalle' => $detalle,
            ]);
        } catch (\Throwable $e) {
            Log::warning('proveedor.padron_arca.aviso', [
                'proveedor_id' => $proveedor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
