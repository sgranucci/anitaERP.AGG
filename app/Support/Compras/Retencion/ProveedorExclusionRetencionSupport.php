<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor_Exclusion;
use Carbon\Carbon;

/**
 * Certificados de exclusión del ABM proveedor (`proveedor_exclusion`).
 *
 * tiporetencion: G Ganancias / I IVA / S SUSS / B IIBB.
 * Vigencia: desdefecha <= fecha pago <= hastafecha.
 * porcentajeexclusion 100 = no retiene; parcial reduce el importe calculado.
 */
final class ProveedorExclusionRetencionSupport
{
    public const TIPO_GANANCIAS = 'G';

    public const TIPO_IVA = 'I';

    public const TIPO_SUSS = 'S';

    public const TIPO_IIBB = 'B';

    /**
     * @return array{
     *   id:int,
     *   tiporetencion:string,
     *   porcentaje:float,
     *   desdefecha:string,
     *   hastafecha:string,
     *   comentario:?string
     * }|null
     */
    public static function vigente(int $proveedorId, string $tipo, ?string $fechaYmd): ?array
    {
        if ($proveedorId <= 0) {
            return null;
        }

        $tipo = strtoupper(substr(trim($tipo), 0, 1));
        if ($tipo === '') {
            return null;
        }

        $ymd = self::normalizarFecha($fechaYmd);
        if ($ymd === null) {
            return null;
        }

        $fila = Proveedor_Exclusion::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tiporetencion', $tipo)
            ->whereDate('desdefecha', '<=', $ymd)
            ->whereDate('hastafecha', '>=', $ymd)
            ->orderByDesc('porcentajeexclusion')
            ->orderByDesc('hastafecha')
            ->orderByDesc('id')
            ->first();

        if ($fila === null) {
            return null;
        }

        $pct = round((float) ($fila->porcentajeexclusion ?? 0), 2);
        if ($pct <= 0) {
            return null;
        }

        return [
            'id' => (int) $fila->id,
            'tiporetencion' => $tipo,
            'porcentaje' => min(100.0, $pct),
            'desdefecha' => substr((string) $fila->desdefecha, 0, 10),
            'hastafecha' => substr((string) $fila->hastafecha, 0, 10),
            'comentario' => $fila->comentario !== null ? trim((string) $fila->comentario) : null,
        ];
    }

    /**
     * @return array{G:?array,I:?array,S:?array,B:?array}
     */
    public static function mapaVigentes(int $proveedorId, ?string $fechaYmd): array
    {
        return [
            self::TIPO_GANANCIAS => self::vigente($proveedorId, self::TIPO_GANANCIAS, $fechaYmd),
            self::TIPO_IVA => self::vigente($proveedorId, self::TIPO_IVA, $fechaYmd),
            self::TIPO_SUSS => self::vigente($proveedorId, self::TIPO_SUSS, $fechaYmd),
            self::TIPO_IIBB => self::vigente($proveedorId, self::TIPO_IIBB, $fechaYmd),
        ];
    }

    public static function porcentaje(int $proveedorId, string $tipo, ?string $fechaYmd): float
    {
        $exc = self::vigente($proveedorId, $tipo, $fechaYmd);

        return $exc['porcentaje'] ?? 0.0;
    }

    private static function normalizarFecha(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha) === 1) {
            return substr($fecha, 0, 10);
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
