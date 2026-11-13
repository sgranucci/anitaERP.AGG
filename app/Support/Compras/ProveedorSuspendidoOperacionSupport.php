<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Severidad al usar un proveedor suspendido en comprobante de proveedor.
 * Configuración general → Compras (por instalación / EMPRESA).
 */
final class ProveedorSuspendidoOperacionSupport
{
    public const SEVERIDAD_BLOQUEAR = 'bloquear';

    public const SEVERIDAD_AVISAR = 'avisar';

    public const ESTADO_SUSPENDIDO = 'Suspendido';

    /**
     * @return array<string, string>
     */
    public static function opcionesSeveridad(): array
    {
        return [
            self::SEVERIDAD_BLOQUEAR => 'Bloquear (no permite cargar)',
            self::SEVERIDAD_AVISAR => 'Avisar (permite cargar y notifica)',
        ];
    }

    public static function severidad(): string
    {
        return ParametroSistemaSupport::comprobanteProveedorSuspendidoSeveridad();
    }

    public static function bloquea(): bool
    {
        return self::severidad() === self::SEVERIDAD_BLOQUEAR;
    }

    public static function permiteCargaConAviso(): bool
    {
        return self::severidad() === self::SEVERIDAD_AVISAR;
    }

    public static function esSuspendido(?Proveedor $proveedor): bool
    {
        if ($proveedor === null) {
            return false;
        }

        return trim((string) ($proveedor->estado ?? '')) === self::ESTADO_SUSPENDIDO;
    }

    public static function mensajeBloqueo(): string
    {
        return 'El proveedor está suspendido. No se puede cargar el comprobante.';
    }

    public static function mensajeAviso(): string
    {
        return 'El proveedor está suspendido. Se permite cargar el comprobante; revise el padrón ARCA o el motivo de suspensión.';
    }

    /**
     * @return array{ok: bool, error: string|null, aviso: string|null}
     */
    public static function evaluar(?Proveedor $proveedor): array
    {
        if (! self::esSuspendido($proveedor)) {
            return ['ok' => true, 'error' => null, 'aviso' => null];
        }

        if (self::bloquea()) {
            return [
                'ok' => false,
                'error' => self::mensajeBloqueo(),
                'aviso' => null,
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'aviso' => self::mensajeAviso(),
        ];
    }

    public static function assertPermitido(?Proveedor $proveedor): ?string
    {
        $eval = self::evaluar($proveedor);
        if ($eval['error'] !== null) {
            throw new \RuntimeException($eval['error']);
        }

        return $eval['aviso'];
    }

    public static function assertPorProveedorId(int $proveedorId): ?string
    {
        if ($proveedorId <= 0) {
            return null;
        }

        return self::assertPermitido(Proveedor::query()->find($proveedorId));
    }
}
