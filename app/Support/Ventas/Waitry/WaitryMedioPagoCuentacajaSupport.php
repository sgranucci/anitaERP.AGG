<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaTotem;

/**
 * Medios de pago Waitry (lecturas getOrdersPOS / getordersdetails) → cuenta de caja Anita.
 *
 * - Facturación (comanda ya pagada en tótem): siempre cuenta puente TOTEM en Anita, sin importar
 *   si Waitry cobró con Mercado Pago, Totalcoin u otro (`waitry_tipo_pago` queda solo como referencia).
 * - Informe Z / cierre jornada: {@see cuentaParaTipoInformeZ()} usa los medios reales mapeados en config.
 */
final class WaitryMedioPagoCuentacajaSupport
{
    public const TIPO_MERCADOPAGO = 'mercadopago';

    public const TIPO_TOTALCOIN = 'totalcoin';

    public const TIPO_CREDIT_CARD = 'credit_card';

    /** @var list<string> tipos normalizados = QR en cierre / facturación gastronomía */
    public const TIPOS_QR_WAITRY_NORMALIZADOS = [
        'totalcoin',
        'creditcard',
    ];

    /** @var list<string> */
    public const TIPOS_CON_CUENTACAJA = [
        self::TIPO_MERCADOPAGO,
        self::TIPO_TOTALCOIN,
    ];

    /**
     * No son medios conciliables en Informe Z: cash (efectivo en tótem) y totem (cuenta puente).
     *
     * @var list<string>
     */
    private const TIPOS_EXCLUIDOS_INFORME_Z = [
        'cash',
        'totem',
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
     * Medios mapeados en config (p. ej. mercadopago, totalcoin).
     */
    public static function esTipoPredefinido(?string $tipo): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);

        return $tipoNorm !== null && array_key_exists($tipoNorm, self::mapaTipoCuentacaja());
    }

    /**
     * QR en tótem Waitry: Totalcoin o credit_card (Waitry no distingue QR físico de tarjeta en POS).
     */
    public static function esTipoQrWaitry(?string $tipo): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);

        return $tipoNorm !== null
            && in_array($tipoNorm, self::TIPOS_QR_WAITRY_NORMALIZADOS, true);
    }

    /**
     * Efectivo en tótem y fallback TOTEM no se concilian en el Informe Z.
     */
    public static function esTipoExcluidoInformeZ(?string $tipo): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);

        if ($tipoNorm === null) {
            return true;
        }

        return in_array($tipoNorm, self::TIPOS_EXCLUIDOS_INFORME_Z, true);
    }

    public static function esCuentacajaTotem(int $cuentacajaId, int $empresaId): bool
    {
        if ($cuentacajaId <= 0 || $empresaId <= 0) {
            return false;
        }

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);

        return $totem !== null && (int) $totem['id'] === $cuentacajaId;
    }

    /**
     * Cuenta de caja para Informe Z: solo tipos predefinidos; sin fallback TOTEM.
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}|null
     */
    public static function cuentaParaTipoInformeZ(?string $tipo, int $empresaId): ?array
    {
        if ($empresaId <= 0 || self::esTipoExcluidoInformeZ($tipo)) {
            return null;
        }

        $cuentacajaId = self::cuentacajaIdPorTipo($tipo, $empresaId);
        if ($cuentacajaId === null) {
            return null;
        }

        return self::cuentaDesdeId($cuentacajaId, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerTipoPagoOrden(array $orden): ?string
    {
        $payment = $orden['payment'] ?? null;
        if (is_array($payment)) {
            foreach (['type', 'paymentType', 'payment_type'] as $clave) {
                if (! empty($payment[$clave]) && is_string($payment[$clave])) {
                    $norm = self::normalizarTipo($payment[$clave]);
                    if ($norm !== null) {
                        return $norm;
                    }
                }
            }
        }

        foreach (['paymentType', 'payment_type', 'paymentMethod', 'tipoPago', 'tipo_pago'] as $clave) {
            if (! empty($orden[$clave]) && is_string($orden[$clave])) {
                $norm = self::normalizarTipo($orden[$clave]);
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
        if ($id <= 0 && self::esTipoQrWaitry($tipo)) {
            $id = (int) (self::mapaTipoCuentacaja()[self::TIPO_TOTALCOIN] ?? 0);
        }
        if ($id <= 0) {
            return null;
        }

        if (! Cuentacaja::existeParaEmpresa($id, $empresaId)) {
            return null;
        }

        return $id;
    }

    /**
     * Cuenta puente TOTEM para facturar en Anita una comanda Waitry ya cobrada en el tótem físico.
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:?string}|null
     */
    public static function cuentaPuenteTotemFacturacion(int $empresaId, ?string $waitryTipoPago = null): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        if ($totem === null) {
            return null;
        }

        $totem['waitry_tipo_pago'] = self::normalizarTipo($waitryTipoPago);

        return $totem;
    }

    /**
     * Cuenta de cobranza automática para orden Waitry ya pagada en tótem.
     *
     * @param  array<string, mixed>  $orden
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:?string}|null
     */
    public static function cuentaParaOrdenWaitryCobrada(array $orden, int $empresaId): ?array
    {
        return self::cuentaPuenteTotemFacturacion(
            $empresaId,
            self::extraerTipoPagoOrden($orden),
        );
    }

    /**
     * Alias de {@see cuentaPuenteTotemFacturacion()} — la cobranza Anita es siempre TOTEM.
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,waitry_tipo_pago:?string}|null
     */
    public static function cuentaParaTipoWaitry(?string $tipo, int $empresaId): ?array
    {
        return self::cuentaPuenteTotemFacturacion($empresaId, $tipo);
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        if (self::esTipoQrWaitry($tipo)) {
            return 'QR (Totalcoin / tótem)';
        }

        return match (self::normalizarTipo($tipo)) {
            self::TIPO_MERCADOPAGO => 'Mercado Pago',
            self::TIPO_TOTALCOIN => 'Totalcoin',
            'cash' => 'Efectivo',
            'debitcard' => 'Tarjeta débito',
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

        $totalcoin = $out[self::TIPO_TOTALCOIN] ?? null;
        if ($totalcoin !== null && ! isset($out[self::TIPO_CREDIT_CARD])) {
            $out[self::TIPO_CREDIT_CARD] = [
                ...$totalcoin,
                'waitry_tipo_pago' => self::TIPO_CREDIT_CARD,
                'etiqueta' => self::etiquetaTipo(self::TIPO_CREDIT_CARD),
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
