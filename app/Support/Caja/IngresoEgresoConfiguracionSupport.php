<?php

namespace App\Support\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Parametro_Sistema;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use RuntimeException;

/**
 * Parámetros del programa de ingresos y egresos.
 * La cobranza de tarjeta existe solo en Ferli; el operador elige si se ofrece en el alta.
 */
final class IngresoEgresoConfiguracionSupport
{
    public const CLAVE_MUESTRA_COBRANZA_TARJETA = 'ie_muestra_cobranza_tarjeta';

    public static function disponibleEnEsteEntorno(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    /**
     * Alta nueva: la tarjeta «Cobranza de tarjeta» y el botón de la planilla.
     * Sin fila cargada, en Ferli queda activa (es lo que ya se usa).
     */
    public static function muestraCobranzaTarjeta(): bool
    {
        if (! self::disponibleEnEsteEntorno()) {
            return false;
        }

        return ParametroSistemaSupport::boolean(self::CLAVE_MUESTRA_COBRANZA_TARJETA, true);
    }

    public static function valorFormulario(): string
    {
        return self::muestraCobranzaTarjeta() ? '1' : '0';
    }

    public static function guardarMuestraCobranzaTarjeta(bool $muestra): void
    {
        if (! self::disponibleEnEsteEntorno()) {
            throw new RuntimeException('La configuración de ingresos y egresos no está disponible en esta instalación.');
        }

        Parametro_Sistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_MUESTRA_COBRANZA_TARJETA],
            [
                'grupo' => 'Caja / Ingresos y egresos',
                'etiqueta' => 'Cobranza de tarjeta',
                'ayuda' => 'Si está activa, al cargar un ingreso o egreso aparece la opción Cobranza de tarjeta.',
                'tipo' => 'boolean',
                'valor' => $muestra ? '1' : '0',
                'orden' => 10,
            ]
        );

        ParametroSistemaSupport::olvidarCache();
    }

    /**
     * Con la opción apagada no se puede armar una cobranza nueva.
     * Un movimiento que ya la tiene se puede seguir guardando.
     *
     * @param  list<array<string, mixed>>  $comprobantes
     */
    public static function assertPuedeGrabarCobranzaTarjeta(array $comprobantes): void
    {
        if (self::muestraCobranzaTarjeta() || ! IngresoEgresoLiquidacionTarjetaSupport::hay($comprobantes)) {
            return;
        }

        $ids = [];
        foreach ($comprobantes as $comprobante) {
            if (! is_array($comprobante) || ! IngresoEgresoLiquidacionTarjetaSupport::es($comprobante)) {
                continue;
            }
            $id = (int) ($comprobante['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(self::mensajeApagada());
            }
            $ids[] = $id;
        }

        if ($ids === []) {
            return;
        }

        $existentes = Comprobante_Proveedor::query()
            ->whereIn('id', $ids)
            ->where(function ($query): void {
                $query->where('tipo_tesoreria', ComprobanteProveedorTipoTesoreria::LIQUIDACION_TARJETA)
                    ->orWhere('leyenda', 'like', 'LT:%');
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        foreach ($ids as $id) {
            if (! in_array($id, $existentes, true)) {
                throw new RuntimeException(self::mensajeApagada());
            }
        }
    }

    private static function mensajeApagada(): string
    {
        return 'La cobranza de tarjeta no está habilitada. Se activa en Configuración, Caja, Configuración de ingresos y egresos.';
    }
}
