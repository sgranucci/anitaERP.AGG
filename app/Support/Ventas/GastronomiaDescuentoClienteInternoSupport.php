<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\ClienteVipGastronomia;

/**
 * Cliente interno del descuento gastronomía según circuito Anita / ERP.
 *
 * - Desc. 10 (canje premio Wigos / cortesía): resv_cliente = cliente.codigo ERP (500, 1500 CANJE PLATINO, …).
 * - Desc. 40 (invitación VIP / canje marketing): resv_cliente = numeroid VIP o cliente.codigo real;
 *   el beneficiario VIP va en cliente_vip_gastronomia_id — no imputar 1500 platino.
 */
final class GastronomiaDescuentoClienteInternoSupport
{
    /** @var array<string, int|null> */
    private static array $cacheClienteIdPorCodigo = [];

    /** @var array<string, bool> */
    private static array $cacheEsNumeroidVip = [];

    public static function resolverDesdeCodigoAnita(?string $codigoCliente, int $empresaId, ?string $codigoDescuento = null): ?int
    {
        $codigoCliente = self::normalizarCodigoAnita($codigoCliente);
        if ($codigoCliente === null) {
            return null;
        }

        if (self::esDescuentoInvitacionVip($codigoDescuento)) {
            return self::resolverClienteInternoDesc40($codigoCliente, $empresaId);
        }

        if (self::esDescuentoCanjePremioOCortesia($codigoDescuento)) {
            return self::buscarClienteIdPorCodigo($codigoCliente);
        }

        return self::buscarClienteIdPorCodigo($codigoCliente);
    }

    /**
     * Canje marketing (desc. 40): no hay cliente interno contable; el VIP es el beneficiario.
     */
    public static function clienteInternoIdCanjeMarketing(): ?int
    {
        return null;
    }

    public static function codigoClienteInternoCanjePremioPlatino(): string
    {
        return trim((string) config('gastronomia.canje_premio_platino_cliente_codigo', '1500'));
    }

    public static function codigoClienteInternoCanjePremioDefault(): string
    {
        return trim((string) config('gastronomia.canje_premio_cliente_codigo', '500'));
    }

    public static function clienteInternoIdCanjePremioPlatino(): ?int
    {
        $codigo = self::codigoClienteInternoCanjePremioPlatino();
        if ($codigo === '') {
            return null;
        }

        return self::buscarClienteIdPorCodigo($codigo);
    }

    public static function clienteInternoIdCanjePremioDefault(): ?int
    {
        $codigo = self::codigoClienteInternoCanjePremioDefault();
        if ($codigo === '') {
            return null;
        }

        return self::buscarClienteIdPorCodigo($codigo);
    }

    /**
     * Canje premio / fidelidad Wigos en POS: platino (levelCode Wigos, ej. 3) → cliente 1500; resto → 500.
     */
    public static function resolverClienteInternoCanjePremio(?int $levelCodeWigos): ?int
    {
        if ($levelCodeWigos !== null && $levelCodeWigos > 0 && self::levelCodeEsPlatinoCanjePremio($levelCodeWigos)) {
            $platino = self::clienteInternoIdCanjePremioPlatino();
            if ($platino !== null) {
                return $platino;
            }
        }

        return self::clienteInternoIdCanjePremioDefault();
    }

    public static function levelCodeEsPlatinoCanjePremio(int $levelCode): bool
    {
        $raw = trim((string) config('gastronomia.canje_premio_platino_level_codes', '3'));
        if ($raw === '') {
            return false;
        }

        $codes = array_filter(array_map('intval', preg_split('/[\s,;]+/', $raw) ?: []));

        return in_array($levelCode, $codes, true);
    }

    private static function resolverClienteInternoDesc40(string $codigoCliente, int $empresaId): ?int
    {
        $clienteId = self::buscarClienteIdPorCodigo($codigoCliente);
        if ($clienteId !== null) {
            return $clienteId;
        }

        if (self::esNumeroidVipEnEmpresa($codigoCliente, $empresaId)) {
            return null;
        }

        return null;
    }

    private static function esDescuentoInvitacionVip(?string $codigoDescuento): bool
    {
        $codigoDescuento = self::normalizarCodigoAnita($codigoDescuento);
        if ($codigoDescuento === null) {
            return false;
        }

        $codigoVip = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
        $altVip = ltrim($codigoVip, '0') ?: $codigoVip;

        return $codigoDescuento === $codigoVip || $codigoDescuento === $altVip;
    }

    private static function esDescuentoCanjePremioOCortesia(?string $codigoDescuento): bool
    {
        $codigoDescuento = self::normalizarCodigoAnita($codigoDescuento);
        if ($codigoDescuento === null) {
            return false;
        }

        foreach (self::codigosDescuentoCanjePremio() as $codigo) {
            if ($codigoDescuento === $codigo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function codigosDescuentoCanjePremio(): array
    {
        $codigos = [];
        foreach ([
            config('gastronomia.canje_premio_descuento_codigo', '10'),
            config('gastronomia.canje_fidelidad_descuento_codigo', '10'),
        ] as $raw) {
            $codigo = trim((string) $raw);
            if ($codigo === '') {
                continue;
            }
            $codigos[] = $codigo;
            $alt = ltrim($codigo, '0') ?: $codigo;
            if ($alt !== $codigo) {
                $codigos[] = $alt;
            }
        }

        return array_values(array_unique($codigos));
    }

    private static function esNumeroidVipEnEmpresa(string $codigoCliente, int $empresaId): bool
    {
        if ($empresaId <= 0 || ! ctype_digit($codigoCliente)) {
            return false;
        }

        $numeroid = (int) $codigoCliente;
        if ($numeroid <= 0) {
            return false;
        }

        $cacheKey = $empresaId.'|'.$numeroid;
        if (array_key_exists($cacheKey, self::$cacheEsNumeroidVip)) {
            return self::$cacheEsNumeroidVip[$cacheKey];
        }

        $existe = ClienteVipGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroid', $numeroid)
            ->exists();

        self::$cacheEsNumeroidVip[$cacheKey] = $existe;

        return $existe;
    }

    private static function buscarClienteIdPorCodigo(string $codigo): ?int
    {
        if (array_key_exists($codigo, self::$cacheClienteIdPorCodigo)) {
            return self::$cacheClienteIdPorCodigo[$codigo];
        }

        $cliente = Cliente::query()
            ->where('codigo', $codigo)
            ->first();

        if ($cliente === null) {
            $alt = ltrim($codigo, '0') ?: $codigo;
            if ($alt !== $codigo) {
                $cliente = Cliente::query()->where('codigo', $alt)->first();
            }
        }

        $id = $cliente !== null ? (int) $cliente->id : null;
        self::$cacheClienteIdPorCodigo[$codigo] = $id;

        return $id;
    }

    private static function normalizarCodigo(mixed $raw): ?string
    {
        $codigo = trim((string) ($raw ?? ''));
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return $codigo;
    }

    private static function normalizarCodigoAnita(mixed $raw): ?string
    {
        return self::normalizarCodigo($raw);
    }
}
