<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\SistemaNumerador;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\SistemaNumeradorSupport;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ferli: remito interno (RIN) desde pedidos/facturación numera en ERP (sistema_numerador).
 * No consulta ARCA ni Anita. Distinto de Facturación Local (ventas.rin.{sucursal}).
 */
final class FerliRinNumeracionSupport
{
    public const CODIGO = 'ventas.rin.comprobante';

    public const ABREVIATURA = 'RIN';

    public static function aplica(?object $tipotransaccion): bool
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return false;
        }

        if ($tipotransaccion === null) {
            return false;
        }

        $abr = strtoupper(trim((string) ($tipotransaccion->abreviatura ?? '')));

        return $abr === self::ABREVIATURA;
    }

    /**
     * Venta RIN en Ferli (impresión PDF como remito interno, no FAC).
     */
    public static function esVentaRin(?object $venta): bool
    {
        if (! EntornoEmpresaSupport::esFerli() || $venta === null) {
            return false;
        }

        $prefijo = strtoupper(trim(explode(' ', (string) ($venta->codigo ?? ''), 2)[0] ?? ''));
        if ($prefijo === self::ABREVIATURA) {
            return true;
        }

        $abr = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));

        return $abr === self::ABREVIATURA;
    }

    /**
     * Reserva el siguiente número RIN (ya el que se graba en venta.numerocomprobante).
     * Debe llamarse dentro de una transacción DB si el caller la abre.
     */
    public static function reservarSiguiente(int $empresaId = 0): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Numerador RIN ERP solo aplica en Ferli.');
        }

        $empresaId = self::resolverEmpresaId($empresaId);
        $piso = self::pisoDesdeVentasRin();
        $row = self::asegurarFila($empresaId, $piso);

        // Sin puente Anita: max(ultimo_numero, piso ventas) + 1.
        return SistemaNumeradorSupport::reservarSiguiente((int) $row->id, $piso);
    }

    /**
     * Base para callers que hacen $numero++ (devuelve último usado / reservado-1).
     */
    public static function reservarBaseParaIncremento(int $empresaId = 0): int
    {
        return self::reservarSiguiente($empresaId) - 1;
    }

    public static function pisoDesdeVentasRin(): int
    {
        $tipoId = (int) Tipotransaccion::query()
            ->where('abreviatura', self::ABREVIATURA)
            ->value('id');

        if ($tipoId <= 0) {
            return 0;
        }

        return max(0, (int) Venta::query()
            ->where('tipotransaccion_id', $tipoId)
            ->max('numerocomprobante'));
    }

    public static function asegurarFila(int $empresaId, ?int $piso = null): SistemaNumerador
    {
        $empresaId = self::resolverEmpresaId($empresaId);
        $piso = max(0, $piso ?? self::pisoDesdeVentasRin());

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
            // Sin Anita: numeración solo ERP.
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
            'nombre' => 'Remito interno RIN (pedidos / facturación)',
            'empresa_id' => $empresaId,
            'modulo' => 'ventas',
            'ultimo_numero' => $piso,
            'numera_en_erp' => true,
            'anita_sistema' => null,
            'anita_fuente' => null,
            'anita_clave' => null,
            'activo' => true,
            'observacion' => 'Ferli: serie RIN en ERP (sin ARCA/Anita). Semilla = max venta RIN.',
        ]);
    }

    private static function resolverEmpresaId(int $empresaId): int
    {
        if ($empresaId > 0 && Empresa::query()->whereKey($empresaId)->exists()) {
            return $empresaId;
        }

        $primera = (int) (Empresa::query()->orderBy('id')->value('id') ?: 0);
        if ($primera <= 0) {
            throw new InvalidArgumentException('No hay empresa para el numerador RIN Ferli.');
        }

        return $primera;
    }
}
