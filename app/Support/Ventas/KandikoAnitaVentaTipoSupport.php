<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;

/**
 * Kandiko CAEA (PV 00031, mod A) comparte sucursal Anita 00031 con Rebisco (CAE).
 * La tabla venta no admite duplicados (ven_tipo + ven_letra + ven_sucursal + ven_nro);
 * Kandiko CAEA 31 graba FAK solo en cabecera venta; Rebisco sigue con FAC.
 * El numerador (compemis / numeraAnita) sigue siendo FAC.
 */
final class KandikoAnitaVentaTipoSupport
{
    public const TIPO_VENTA_BRIDGE = 'FAK';

    public const TIPO_NUMERADOR = 'FAC';

    public const EMPRESA_CODIGO = '2';

    public const SUCURSAL_CAEA = '00031';

    public const MODO_FACTURACION_CAEA = 'A';

    /** Tipos Anita de gastronomía Kandiko (excluye FSL = slots). */
    public const TIPOS_GASTRONOMIA_ANITA = ['FAC', 'FAK', 'NCD', 'NCK'];

    public static function esTipoGastronomiaAnita(string $tipoAnita): bool
    {
        return in_array(strtoupper(trim($tipoAnita)), self::TIPOS_GASTRONOMIA_ANITA, true);
    }

    public static function debeUsarTipoVentaAlterno(
        string $tipoErp,
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
        ?string $modoFacturacionPuntoventa = null,
    ): bool {
        if (strtoupper(trim((string) config('app.empresa'))) !== 'AGG') {
            return false;
        }

        if (trim((string) $empresaCodigo) !== self::EMPRESA_CODIGO) {
            return false;
        }

        if (self::normalizarSucursal($puntoventaCodigo) !== self::SUCURSAL_CAEA) {
            return false;
        }

        if (strtoupper(trim($tipoErp)) !== self::TIPO_NUMERADOR) {
            return false;
        }

        $modo = self::modoFacturacionEfectivo($puntoventaCodigo, $empresaCodigo, $modoFacturacionPuntoventa);

        return $modo === self::MODO_FACTURACION_CAEA;
    }

    /**
     * Tipo ven_tipo al insertar/consultar/borrar cabecera venta en Anita bridge.
     */
    public static function tipoVentaAnitaBridge(
        string $tipoErp,
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
        ?string $modoFacturacionPuntoventa = null,
    ): string {
        if (self::debeUsarTipoVentaAlterno($tipoErp, $puntoventaCodigo, $empresaCodigo, $modoFacturacionPuntoventa)) {
            return self::TIPO_VENTA_BRIDGE;
        }

        return trim($tipoErp);
    }

    public static function esPvCaeaKandiko(
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
        ?string $modoFacturacionPuntoventa = null,
    ): bool {
        return self::debeUsarTipoVentaAlterno(
            self::TIPO_NUMERADOR,
            $puntoventaCodigo,
            $empresaCodigo,
            $modoFacturacionPuntoventa,
        );
    }

    /**
     * Clave ERP (FAC-n) para conciliar con cabecera Anita FAK-n o FAC-n en PV CAEA Kandiko.
     */
    public static function claveConciliacionDesdeNumero(int $numero): string
    {
        return self::TIPO_NUMERADOR.'-'.$numero;
    }

    /**
     * @return list<string>
     */
    public static function tiposAnitaEquivalentesFacErp(): array
    {
        return [self::TIPO_VENTA_BRIDGE, self::TIPO_NUMERADOR];
    }

    /**
     * Indica si una cabecera Anita debe participar de la conciliación del PV indicado.
     * En sucursal 00031: Kandiko CAEA usa FAK (o FAC legacy); Rebisco ignora FAK.
     */
    public static function cabeceraAnitaCorrespondeAlPv(
        string $tipoAnita,
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
        ?string $modoFacturacionPuntoventa = null,
    ): bool {
        $tipo = strtoupper(trim($tipoAnita));
        if ($tipo === '') {
            return false;
        }

        if (trim((string) $empresaCodigo) === self::EMPRESA_CODIGO && ! self::esTipoGastronomiaAnita($tipo)) {
            return false;
        }

        if (self::esPvCaeaKandiko($puntoventaCodigo, $empresaCodigo, $modoFacturacionPuntoventa)) {
            return in_array($tipo, array_merge(self::tiposAnitaEquivalentesFacErp(), ['NCD', 'NCK']), true);
        }

        if (
            self::normalizarSucursal($puntoventaCodigo) === self::SUCURSAL_CAEA
            && $tipo === self::TIPO_VENTA_BRIDGE
        ) {
            return false;
        }

        return true;
    }

    public static function normalizarSucursal(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        if (ctype_digit($codigo)) {
            return str_pad($codigo, 5, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }

    private static function modoFacturacionEfectivo(
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
        ?string $modoFacturacionPuntoventa,
    ): ?string {
        $modo = trim((string) $modoFacturacionPuntoventa);
        if ($modo !== '') {
            return $modo;
        }

        return self::resolverModoFacturacionPuntoventa($puntoventaCodigo, $empresaCodigo);
    }

    private static function resolverModoFacturacionPuntoventa(
        string $puntoventaCodigo,
        string|int|null $empresaCodigo,
    ): ?string {
        if (trim((string) $empresaCodigo) !== self::EMPRESA_CODIGO) {
            return null;
        }

        if (self::normalizarSucursal($puntoventaCodigo) !== self::SUCURSAL_CAEA) {
            return null;
        }

        $empresaId = Empresa::query()
            ->where('codigo', self::EMPRESA_CODIGO)
            ->value('id');

        if ($empresaId === null) {
            return null;
        }

        $modo = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', self::SUCURSAL_CAEA)
            ->where('modofacturacion', self::MODO_FACTURACION_CAEA)
            ->value('modofacturacion');

        return is_string($modo) && $modo !== '' ? $modo : null;
    }
}
