<?php

namespace App\Support\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\SistemaNumerador;
use App\Support\Configuracion\SistemaNumeradorSupport;
use InvalidArgumentException;

/**
 * DTC de liquidación de tarjeta: numerador propio del ERP (sistema_numerador).
 * La serie ya cargada es letra A, punto de venta 1. El último número grabado es la semilla.
 */
final class IngresoEgresoDtcNumeracionSupport
{
    public const CODIGO = 'caja.dtc';

    public const ABREVIATURA = 'DTC';

    public const LETRA = 'A';

    /** Punto de venta de los DTC ya grabados. */
    public const SUCURSAL = 1;

    public static function reservarSiguiente(int $empresaId): int
    {
        $empresaId = self::resolverEmpresaId($empresaId);
        $piso = self::pisoDesdeComprobantes();
        $fila = self::asegurarFila($empresaId, $piso);

        return SistemaNumeradorSupport::reservarSiguiente((int) $fila->id, $piso);
    }

    public static function pisoDesdeComprobantes(): int
    {
        $tipoIds = Tipotransaccion_Compra::query()
            ->where('abreviatura', self::ABREVIATURA)
            ->pluck('id');
        if ($tipoIds->isEmpty()) {
            return 0;
        }

        return max(0, (int) Comprobante_Proveedor::query()
            ->whereIn('tipotransaccion_compra_id', $tipoIds->all())
            ->max('numerocomprobante'));
    }

    public static function asegurarFila(int $empresaId, ?int $piso = null): SistemaNumerador
    {
        $empresaId = self::resolverEmpresaId($empresaId);
        $piso = max(0, $piso ?? self::pisoDesdeComprobantes());

        $existente = SistemaNumerador::query()
            ->where('codigo', self::CODIGO)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($existente !== null) {
            $dirty = false;
            if (! $existente->activo) {
                $existente->activo = true;
                $dirty = true;
            }
            if (! $existente->numera_en_erp) {
                $existente->numera_en_erp = true;
                $dirty = true;
            }
            if ($piso > (int) $existente->ultimo_numero) {
                $existente->ultimo_numero = $piso;
                $dirty = true;
            }
            if ($existente->anita_sistema !== null
                || $existente->anita_fuente !== null
                || $existente->anita_clave !== null
            ) {
                $existente->anita_sistema = null;
                $existente->anita_fuente = null;
                $existente->anita_clave = null;
                $dirty = true;
            }
            if ($dirty) {
                $existente->save();
            }

            return $existente;
        }

        return SistemaNumerador::query()->create([
            'codigo' => self::CODIGO,
            'nombre' => 'DTC liquidación de tarjeta',
            'empresa_id' => $empresaId,
            'modulo' => 'caja',
            'ultimo_numero' => $piso,
            'numera_en_erp' => true,
            'anita_sistema' => null,
            'anita_fuente' => null,
            'anita_clave' => null,
            'activo' => true,
            'observacion' => 'Serie DTC en el ERP. Semilla = último comprobante DTC.',
        ]);
    }

    private static function resolverEmpresaId(int $empresaId): int
    {
        if ($empresaId > 0 && Empresa::query()->whereKey($empresaId)->exists()) {
            return $empresaId;
        }

        $primera = (int) (Empresa::query()->orderBy('id')->value('id') ?: 0);
        if ($primera <= 0) {
            throw new InvalidArgumentException('No hay empresa para el numerador DTC.');
        }

        return $primera;
    }
}
