<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use InvalidArgumentException;

/**
 * Mapea medios de cobro Anita (cuentacaja) al enum Waitry syncStatusPOS: cash | credit_card | debit_card.
 */
final class WaitryPaymentTypeSupport
{
    public const TIPO_CASH = 'cash';

    public const TIPO_CREDIT_CARD = 'credit_card';

    public const TIPO_DEBIT_CARD = 'debit_card';

    /** @var list<string> */
    public const TIPOS_VALIDOS = [
        self::TIPO_CASH,
        self::TIPO_CREDIT_CARD,
        self::TIPO_DEBIT_CARD,
    ];

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function resolverDesdeMediosPago(array $mediosPago, int $empresaId): string
    {
        $medioPrincipal = $this->medioConMayorMonto($mediosPago);
        if ($medioPrincipal === null) {
            throw new InvalidArgumentException('Waitry: no hay medio de pago para sincronizar.');
        }

        $cuentacajaId = (int) ($medioPrincipal['cuentacaja_id'] ?? 0);
        if ($cuentacajaId <= 0) {
            throw new InvalidArgumentException('Waitry: cuenta de caja inválida en el medio de pago.');
        }

        $tipo = $this->resolverPorCuentacajaId($cuentacajaId, $empresaId);
        if ($tipo !== null) {
            return $tipo;
        }

        $cuenta = Cuentacaja::query()
            ->whereKey($cuentacajaId)
            ->paraEmpresa($empresaId)
            ->first(['id', 'nombre', 'codigo']);

        if ($cuenta === null) {
            throw new InvalidArgumentException(
                'Waitry: no se pudo determinar el tipo de pago para la cuenta de caja id '.$cuentacajaId.'.'
            );
        }

        $tipo = $this->inferirDesdeTexto(
            (string) $cuenta->codigo,
            (string) $cuenta->nombre,
        );
        if ($tipo !== null) {
            return $tipo;
        }

        throw new InvalidArgumentException(
            'Waitry: configure el tipo de pago para la cuenta «'.trim($cuenta->codigo.' '.$cuenta->nombre)
            .'» (WAITRY_CUENTACAJA_TIPO_PAGO o GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA para efectivo).'
        );
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}|null
     */
    public function medioConMayorMonto(array $mediosPago): ?array
    {
        $mejor = null;
        $maxMonto = -1.0;

        foreach ($mediosPago as $medio) {
            if (! is_array($medio)) {
                continue;
            }
            $monto = (float) ($medio['monto'] ?? 0);
            if ($monto <= 0.) {
                continue;
            }
            if ($monto > $maxMonto) {
                $maxMonto = $monto;
                $mejor = $medio;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function montoTotalMedioPrincipal(array $mediosPago): float
    {
        $medio = $this->medioConMayorMonto($mediosPago);

        return round((float) ($medio['monto'] ?? 0), 2);
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function monedaIdMedioPrincipal(array $mediosPago): int
    {
        $medio = $this->medioConMayorMonto($mediosPago);

        return (int) ($medio['moneda_id'] ?? 0);
    }

    public function resolverPorCuentacajaId(int $cuentacajaId, int $empresaId): ?string
    {
        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        if ($efectivoId !== null && $cuentacajaId === $efectivoId) {
            return self::TIPO_CASH;
        }

        $mapa = config('waitry.cuentacaja_tipo_pago', []);
        if (! is_array($mapa)) {
            return null;
        }

        $tipo = $mapa[$cuentacajaId] ?? $mapa[(string) $cuentacajaId] ?? null;
        if ($tipo === null || $tipo === '') {
            return null;
        }

        $tipo = mb_strtolower(trim((string) $tipo));
        if (! in_array($tipo, self::TIPOS_VALIDOS, true)) {
            return null;
        }

        return $tipo;
    }

    public function inferirDesdeTexto(string $codigo, string $nombre): ?string
    {
        $texto = mb_strtolower(trim($codigo.' '.$nombre), 'UTF-8');
        if ($texto === '') {
            return null;
        }

        if ($this->contieneAlguno($texto, ['debito', 'débito', 'debit', 'deb '])) {
            return self::TIPO_DEBIT_CARD;
        }

        if ($this->contieneAlguno($texto, ['credito', 'crédito', 'credit', 'tarjeta', 'visa', 'master', 'amex', 'cabal'])) {
            return self::TIPO_CREDIT_CARD;
        }

        if ($this->contieneAlguno($texto, ['efectivo', 'cash', 'caja chica', 'caja'])) {
            return self::TIPO_CASH;
        }

        return null;
    }

    /**
     * @param  list<string>  $palabras
     */
    private function contieneAlguno(string $texto, array $palabras): bool
    {
        foreach ($palabras as $palabra) {
            if ($palabra !== '' && str_contains($texto, $palabra)) {
                return true;
            }
        }

        return false;
    }
}
