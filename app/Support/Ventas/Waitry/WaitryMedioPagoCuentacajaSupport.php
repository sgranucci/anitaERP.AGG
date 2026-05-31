<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaTotem;

/**
 * Medios de pago Waitry (lecturas getOrdersPOS / getordersdetails) → cuenta de caja Anita.
 *
 * mercadopago → cuentacaja 201, totalcoin → cuentacaja 226 (multiempresa, todas las sucursales).
 * Otros cobros en tótem sin tipo mapeado → cuenta TOTEM (GASTRONOMIA_CUENTACAJA_TOTEM_CODIGO).
 */
final class WaitryMedioPagoCuentacajaSupport
{
    public const TIPO_MERCADOPAGO = 'mercadopago';

    public const TIPO_TOTALCOIN = 'totalcoin';

    /** @var list<string> */
    public const TIPOS_CON_CUENTACAJA = [
        self::TIPO_MERCADOPAGO,
        self::TIPO_TOTALCOIN,
    ];

    /**
     * @return array<string, int> tipo_waitry_normalizado => cuentacaja_id
     */
    public static function mapaTipoCuentacaja(): array
    {
        $mapa = config('waitry.tipo_pago_cuentacaja', []);
        if (! is_array($mapa)) {
            return [];
        }

        $out = [];
        foreach ($mapa as $tipo => $cuentacajaId) {
            $tipoNorm = self::normalizarTipo((string) $tipo);
            $id = (int) $cuentacajaId;
            if ($tipoNorm !== null && $id > 0) {
                $out[$tipoNorm] = $id;
            }
        }

        return $out;
    }

    public static function normalizarTipo(?string $tipo): ?string
    {
        if ($tipo === null) {
            return null;
        }

        $tipo = mb_strtolower(trim($tipo));
        if ($tipo === '') {
            return null;
        }

        $tipo = str_replace([' ', '-', '_'], '', $tipo);

        return $tipo !== '' ? $tipo : null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerTipoPagoOrden(array $orden): ?string
    {
        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return null;
        }

        foreach (['type', 'paymentType', 'payment_type'] as $clave) {
            if (! empty($payment[$clave]) && is_string($payment[$clave])) {
                $norm = self::normalizarTipo($payment[$clave]);
                if ($norm !== null) {
                    return $norm;
                }
            }
        }

        return null;
    }

    public static function cuentacajaIdPorTipo(?string $tipo, int $empresaId): ?int
    {
        $tipo = self::normalizarTipo($tipo);
        if ($tipo === null || $empresaId <= 0) {
            return null;
        }

        $id = (int) (self::mapaTipoCuentacaja()[$tipo] ?? 0);
        if ($id <= 0) {
            return null;
        }

        if (! Cuentacaja::existeParaEmpresa($id, $empresaId)) {
            return null;
        }

        return $id;
    }

    /**
     * Cuenta de cobranza automática para orden Waitry ya pagada en tótem.
     *
     * @param  array<string, mixed>  $orden
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:?string}|null
     */
    public static function cuentaParaOrdenWaitryCobrada(array $orden, int $empresaId): ?array
    {
        $tipo = self::extraerTipoPagoOrden($orden);

        return self::cuentaParaTipoWaitry($tipo, $empresaId);
    }

    /**
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:?string}|null
     */
    public static function cuentaParaTipoWaitry(?string $tipo, int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $tipoNorm = self::normalizarTipo($tipo);
        $cuentacajaId = self::cuentacajaIdPorTipo($tipoNorm, $empresaId);

        if ($cuentacajaId !== null) {
            $cuenta = self::cuentaDesdeId($cuentacajaId, $empresaId);
            if ($cuenta !== null) {
                $cuenta['waitry_tipo_pago'] = $tipoNorm;

                return $cuenta;
            }
        }

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        if ($totem === null) {
            return null;
        }

        $totem['waitry_tipo_pago'] = $tipoNorm;

        return $totem;
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        return match (self::normalizarTipo($tipo)) {
            self::TIPO_MERCADOPAGO => 'Mercado Pago',
            self::TIPO_TOTALCOIN => 'Totalcoin',
            'cash' => 'Efectivo',
            'credit_card' => 'Tarjeta crédito',
            'debit_card' => 'Tarjeta débito',
            default => $tipo !== null && trim($tipo) !== '' ? trim($tipo) : '—',
        };
    }

    public static function mensajeErrorResolucion(?string $tipo, int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return 'Empresa no válida para resolver medio de pago Waitry.';
        }

        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm !== null && in_array($tipoNorm, self::TIPOS_CON_CUENTACAJA, true)) {
            $idEsperado = (int) (self::mapaTipoCuentacaja()[$tipoNorm] ?? 0);
            if ($idEsperado <= 0) {
                return 'No está configurado el mapeo Waitry → cuenta de caja para «'
                    .self::etiquetaTipo($tipoNorm).'».';
            }
            if (! Cuentacaja::existeParaEmpresa($idEsperado, $empresaId)) {
                return 'La cuenta de caja id '.$idEsperado.' ('.self::etiquetaTipo($tipoNorm)
                    .') no existe o no está disponible para la empresa '.$empresaId.'.';
            }

            return null;
        }

        return GastronomiaCuentacajaTotem::mensajeErrorResolucion($empresaId);
    }

    /**
     * Medios Waitry configurados con datos de cuenta para el POS / conciliación.
     *
     * @return array<string, array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:string,etiqueta:string}>
     */
    public static function mediosConfiguradosParaEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $out = [];
        foreach (self::mapaTipoCuentacaja() as $tipo => $cuentacajaId) {
            $cuenta = self::cuentaDesdeId($cuentacajaId, $empresaId);
            if ($cuenta === null) {
                continue;
            }
            $out[$tipo] = [
                ...$cuenta,
                'waitry_tipo_pago' => $tipo,
                'etiqueta' => self::etiquetaTipo($tipo),
            ];
        }

        return $out;
    }

    /**
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}|null
     */
    private static function cuentaDesdeId(int $cuentacajaId, int $empresaId): ?array
    {
        if ($cuentacajaId <= 0 || ! Cuentacaja::existeParaEmpresa($cuentacajaId, $empresaId)) {
            return null;
        }

        $cuenta = Cuentacaja::query()
            ->whereKey($cuentacajaId)
            ->paraEmpresa($empresaId)
            ->with('monedas:id,abreviatura,nombre')
            ->first(['id', 'nombre', 'codigo', 'moneda_id', 'empresa_id']);

        if ($cuenta === null) {
            return null;
        }

        return [
            'id' => (int) $cuenta->id,
            'nombre' => (string) $cuenta->nombre,
            'codigo' => (string) $cuenta->codigo,
            'moneda_id' => (int) $cuenta->moneda_id,
            'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
        ];
    }
}
