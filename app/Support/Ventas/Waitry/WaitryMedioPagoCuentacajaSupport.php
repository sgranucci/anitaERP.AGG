<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;

/**
 * Medios de pago Waitry (lecturas getOrdersPOS / getordersdetails) → cuenta de caja Anita.
 *
 * - **Cuenta puente TOTEM** ({@see TIPO_CUENTA_PUENTE_FACTURACION}): al facturar en Anita una comanda ya cobrada
 *   en el POS físico se imputa a esa cuenta para no duplicar el asiento. El medio real Waitry (MP, Totalcoin…)
 *   queda en `waitry_tipo_pago` solo como referencia. **No** agrupa cobros del Informe Z ni totales por medio.
 * - **Informe Z / cierre jornada**: {@see cuentaParaTipoInformeZ()} y {@see resolverTipoMedioInformeZDesdeLinea()}
 *   usan medios reales de `waitry.tipo_pago_cuentacaja` (MP, Totalcoin, etc.).
 */
final class WaitryMedioPagoCuentacajaSupport
{
    /** Cuenta puente de facturación Anita (no es medio de cobro del tótem físico para Informe Z). */
    public const TIPO_CUENTA_PUENTE_FACTURACION = 'totem';

    public const TIPO_MERCADOPAGO = 'mercadopago';

    public const TIPO_TOTALCOIN = 'totalcoin';

    public const TIPO_CREDIT_CARD = 'credit_card';

    /** Posnet del kiosco (Waitry payment.type dedicado, equivale a credit_card + gateway KIOSK MP). */
    public const TIPO_KIOSK_MP = 'kioskmp';

    /** QR MP en kiosco (equivale a credit_card + gateway KIOSK MPQR). */
    public const TIPO_KIOSK_MPQR = 'kioskmpqr';

    /** Categorías de desglose en Informe Z (columna Sistema / plantilla de carga). */
    public const CATEGORIA_QR_KIOSCO = 'qr_kiosco';

    public const CATEGORIA_POSNET_KIOSCO = 'posnet_kiosco';

    public const CATEGORIA_MERCADOPAGO = 'mercadopago';

    public const CATEGORIA_QR_CELULAR = 'qr_celular';

    public const CATEGORIA_MP_CELULAR = 'mp_celular';

    /** @var list<string> */
    public const CATEGORIAS_INFORME_Z_DESGLOSE = [
        self::CATEGORIA_QR_KIOSCO,
        self::CATEGORIA_POSNET_KIOSCO,
        self::CATEGORIA_MERCADOPAGO,
        self::CATEGORIA_QR_CELULAR,
        self::CATEGORIA_MP_CELULAR,
    ];

    /** @var list<string> tipos Waitry siempre QR (credit_card QR se distingue por gateway KIOSK MPQR). */
    public const TIPOS_QR_WAITRY_NORMALIZADOS = [
        'totalcoin',
    ];

    /** @var list<string> */
    public const TIPOS_CON_CUENTACAJA = [
        self::TIPO_MERCADOPAGO,
        self::TIPO_TOTALCOIN,
    ];

    /**
     * Excluidos del Informe Z: cuenta puente TOTEM (no medio del Z físico).
     * El efectivo Waitry en POS sin facturar Anita no entra al Z; el efectivo de cobranza Anita sí.
     *
     * @var list<string>
     */
    private const TIPOS_EXCLUIDOS_INFORME_Z = [
        self::TIPO_CUENTA_PUENTE_FACTURACION,
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
     * QR en kiosco Waitry: Totalcoin o credit_card con gateway KIOSK MPQR.
     */
    public static function esTipoQrWaitry(?string $tipo, ?string $gateway = null): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === null) {
            return false;
        }

        if ($tipoNorm === self::normalizarTipo(self::TIPO_KIOSK_MPQR)) {
            return true;
        }

        if (in_array($tipoNorm, self::TIPOS_QR_WAITRY_NORMALIZADOS, true)) {
            return true;
        }

        if ($tipoNorm === self::normalizarTipo(self::TIPO_CREDIT_CARD)) {
            return WaitryPaymentGatewaySupport::esGatewayQrKiosko($gateway);
        }

        return false;
    }

    /**
     * credit_card cobrado en terminal Posnet del kiosco (sin MPQR), o payment.type kioskmp.
     */
    public static function esCreditCardPosnet(?string $tipo, ?string $gateway = null): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === null) {
            return false;
        }

        if ($tipoNorm === self::normalizarTipo(self::TIPO_KIOSK_MP)) {
            return ! WaitryPaymentGatewaySupport::esGatewayQrKiosko($gateway);
        }

        return $tipoNorm === self::normalizarTipo(self::TIPO_CREDIT_CARD)
            && WaitryPaymentGatewaySupport::esGatewayPosnetKiosko($gateway);
    }

    /**
     * Indica cuenta puente de facturación (comanda pagada en POS → asiento único en Anita).
     */
    public static function esTipoCuentaPuenteFacturacion(?string $tipo): bool
    {
        return self::normalizarTipo($tipo) === self::TIPO_CUENTA_PUENTE_FACTURACION;
    }

    /**
     * Cuenta puente de facturación no es medio del Informe Z.
     */
    public static function esTipoExcluidoInformeZ(?string $tipo): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);

        if ($tipoNorm === null) {
            return false;
        }

        return in_array($tipoNorm, self::TIPOS_EXCLUIDOS_INFORME_Z, true);
    }

    /**
     * Tipo de pago Waitry del POS (sin remapear cobranza Anita).
     *
     * @param  array<string, mixed>  $linea
     */
    public static function waitryTipoPagoDesdeLinea(array $linea): ?string
    {
        return self::normalizarTipo($linea['waitry_tipo_pago'] ?? null);
    }

    /**
     * Medios Waitry del kiosco que pueden entrar al Informe Z (QR, MP, Posnet).
     * El filtro de cobro Anita vs tótem está en {@see WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema()}.
     */
    public static function esTipoPagoInformeZSistema(?string $tipo): bool
    {
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === null || self::esTipoExcluidoInformeZ($tipoNorm)) {
            return false;
        }

        if ($tipoNorm === 'cash') {
            return false;
        }

        if ($tipoNorm === 'interface') {
            return false;
        }

        if (self::esTipoPredefinido($tipoNorm)) {
            return true;
        }

        if (in_array($tipoNorm, [
            self::normalizarTipo(self::TIPO_KIOSK_MP),
            self::normalizarTipo(self::TIPO_KIOSK_MPQR),
        ], true)) {
            return true;
        }

        return $tipoNorm === self::normalizarTipo(self::TIPO_CREDIT_CARD);
    }

    /**
     * interface Waitry (p. ej. QR por celular) → tipo canónico según gateway del cobro.
     */
    public static function tipoDesdeInterfaceGateway(?string $gateway): ?string
    {
        return match (WaitryPaymentGatewaySupport::normalizarGateway($gateway)) {
            'totalcoin' => self::TIPO_TOTALCOIN,
            'mercadopago' => self::TIPO_MERCADOPAGO,
            default => null,
        };
    }

    /**
     * Tipo Waitry válido para Informe Z (sin evaluar origen ni cobranza Anita).
     *
     * @param  array<string, mixed>  $linea
     */
    public static function lineaEntraInformeZSistema(array $linea): bool
    {
        if (WaitryPaymentGatewaySupport::esOrdenPushErp($linea)) {
            return false;
        }

        $tipo = self::waitryTipoPagoDesdeLinea($linea);
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === 'interface') {
            $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($linea);

            return self::tipoDesdeInterfaceGateway($gateway) !== null;
        }

        return self::esTipoPagoInformeZSistema($tipo);
    }

    /**
     * Medio canónico del Informe Z sistema para una línea Waitry.
     *
     * @param  array<string, mixed>  $linea
     */
    public static function tipoMedioInformeZSistemaDesdeLinea(array $linea, int $empresaId = 0): ?string
    {
        if (! self::lineaEntraInformeZSistema($linea)) {
            return null;
        }

        $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($linea);
        $tipo = self::resolverTipoMedioInformeZDesdeLinea($linea, $empresaId);

        return self::tipoRepresentativoInformeZ($tipo, $gateway);
    }

    /**
     * Medio para resúmenes operativos de cierre tótem (todos los medios Waitry mapeados).
     * Facturadas en Anita → cobranza real; sin facturar → tipo Waitry del POS.
     *
     * @param  array<string, mixed>  $linea
     */
    public static function resolverTipoMedioInformeZDesdeLinea(array $linea, int $empresaId): ?string
    {
        if (! empty($linea['facturada_erp']) && $empresaId > 0) {
            $desdeAnita = self::tipoMedioInformeZDesdeCobranzaAnita($linea, $empresaId);
            if ($desdeAnita !== null) {
                return self::tipoRepresentativoInformeZ($desdeAnita) ?? $desdeAnita;
            }
        }

        $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($linea);
        $tipo = self::normalizarTipo($linea['waitry_tipo_pago'] ?? null);
        if ($tipo === 'cash') {
            return null;
        }
        if ($tipo === 'interface') {
            $desdeInterface = self::tipoDesdeInterfaceGateway($gateway);

            return $desdeInterface !== null
                ? (self::tipoRepresentativoInformeZ($desdeInterface, $gateway) ?? $desdeInterface)
                : null;
        }
        if ($tipo !== null && ! self::esTipoExcluidoInformeZ($tipo)) {
            return self::tipoRepresentativoInformeZ($tipo, $gateway) ?? $tipo;
        }

        return self::tipoPredefinidoFallbackInformeZ($empresaId);
    }

    /**
     * @param  array<string, mixed>  $linea  Debe incluir anita_cuentacaja_id / anita_es_totem si facturada_erp.
     */
    public static function tipoMedioInformeZDesdeCobranzaAnita(array $linea, int $empresaId): ?string
    {
        $ccId = (int) ($linea['anita_cuentacaja_id'] ?? 0);
        if ($ccId <= 0) {
            return null;
        }

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $totemId = (int) ($totem['id'] ?? 0);

        if ($totemId > 0 && $ccId === $totemId) {
            $waitryReal = self::normalizarTipo($linea['waitry_tipo_pago'] ?? $linea['waitry_tipo_pago_cuenta'] ?? null);
            if ($waitryReal !== null && ! self::esTipoCuentaPuenteFacturacion($waitryReal)) {
                return $waitryReal;
            }

            return null;
        }

        return match (CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(['id' => $ccId], $empresaId)) {
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'cash',
            CierreJornadaProcesoMedioSupport::CLAVE_QR => self::TIPO_TOTALCOIN,
            CierreJornadaProcesoMedioSupport::CLAVE_MP => self::TIPO_MERCADOPAGO,
            default => null,
        };
    }

    /**
     * Primer medio configurado en waitry.tipo_pago_cuentacaja (MP, Totalcoin, etc.).
     */
    public static function tipoPredefinidoFallbackInformeZ(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return self::TIPO_MERCADOPAGO;
        }

        foreach (array_keys(self::mediosConfiguradosParaEmpresa($empresaId)) as $tipo) {
            if (! self::esTipoExcluidoInformeZ($tipo)) {
                return self::tipoRepresentativoInformeZ($tipo) ?? $tipo;
            }
        }

        return self::TIPO_MERCADOPAGO;
    }

    public static function esCategoriaInformeZDesglose(?string $valor): bool
    {
        return in_array($valor, self::CATEGORIAS_INFORME_Z_DESGLOSE, true);
    }

    /**
     * Medio del resumen Informe Z (categoría o tipo Waitry) válido para plantilla/conciliación.
     */
    public static function medioInformeZValidoEnResumen(?string $tipoOCategoria): bool
    {
        if (self::esCategoriaInformeZDesglose($tipoOCategoria)) {
            return true;
        }

        $tipoNorm = self::normalizarTipo($tipoOCategoria);

        return $tipoNorm !== null
            && ! self::esTipoExcluidoInformeZ($tipoNorm)
            && self::esTipoPagoInformeZSistema($tipoNorm);
    }

    /**
     * Categoría visible del Informe Z (QR Kiosco / Posnet Kiosco / MP / QR celular).
     */
    public static function categoriaInformeZDesglose(?string $tipo, ?string $gateway = null): ?string
    {
        if (self::esCategoriaInformeZDesglose($tipo)) {
            return $tipo;
        }

        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === null || self::esTipoExcluidoInformeZ($tipoNorm)) {
            return null;
        }

        if ($tipoNorm === 'interface') {
            return match (WaitryPaymentGatewaySupport::normalizarGateway($gateway)) {
                'totalcoin' => self::CATEGORIA_QR_CELULAR,
                'mercadopago' => self::CATEGORIA_MP_CELULAR,
                default => null,
            };
        }

        if (self::esTipoQrWaitry($tipoNorm, $gateway)) {
            return self::CATEGORIA_QR_KIOSCO;
        }

        if ($tipoNorm === self::normalizarTipo(self::TIPO_KIOSK_MP)
            || self::esCreditCardPosnet($tipoNorm, $gateway)
            || $tipoNorm === self::normalizarTipo(self::TIPO_CREDIT_CARD)) {
            return self::CATEGORIA_POSNET_KIOSCO;
        }

        if ($tipoNorm === self::normalizarTipo(self::TIPO_MERCADOPAGO)) {
            return self::CATEGORIA_MERCADOPAGO;
        }

        if (self::esTipoPagoInformeZSistema($tipoNorm)) {
            return $tipoNorm;
        }

        return null;
    }

    public static function etiquetaCategoriaInformeZ(string $categoria): string
    {
        return match ($categoria) {
            self::CATEGORIA_QR_KIOSCO => 'QR Kiosco',
            self::CATEGORIA_POSNET_KIOSCO => 'Posnet Kiosco',
            self::CATEGORIA_MERCADOPAGO => 'Mercado Pago',
            self::CATEGORIA_QR_CELULAR => 'QR celular',
            self::CATEGORIA_MP_CELULAR => 'MP celular',
            default => self::etiquetaTipo($categoria),
        };
    }

    /**
     * Tipo Waitry para resolver cuenta de caja desde categoría de desglose.
     */
    public static function tipoWaitryDesdeCategoriaInformeZ(string $categoria): string
    {
        return match ($categoria) {
            self::CATEGORIA_QR_KIOSCO, self::CATEGORIA_QR_CELULAR => self::TIPO_TOTALCOIN,
            self::CATEGORIA_POSNET_KIOSCO => self::TIPO_CREDIT_CARD,
            self::CATEGORIA_MERCADOPAGO, self::CATEGORIA_MP_CELULAR => self::TIPO_MERCADOPAGO,
            default => $categoria,
        };
    }

    /**
     * Tipo canónico para índices de mapas (fusión borrador, resúmenes). Nunca devuelve cuenta puente.
     */
    public static function tipoParaClaveMapaInformeZ(?string $tipo, int $empresaId, ?string $gateway = null): ?string
    {
        if (self::esCategoriaInformeZDesglose($tipo)) {
            return $tipo;
        }

        $categoria = self::categoriaInformeZDesglose($tipo, $gateway);
        if ($categoria !== null) {
            return $categoria;
        }

        $tipoCanon = self::tipoRepresentativoInformeZ($tipo, $gateway);

        return $tipoCanon ?? self::tipoPredefinidoFallbackInformeZ($empresaId);
    }

    /**
     * Clave única de medio para Informe Z: desglose por categoría (QR / Posnet / MP), no por cuenta de caja.
     */
    public static function claveMedioInformeZ(?string $tipo, int $empresaId, ?string $gateway = null): string
    {
        $categoria = self::categoriaInformeZDesglose($tipo, $gateway);
        if ($categoria !== null) {
            return 'cat:'.$categoria;
        }

        $tipoNorm = self::tipoRepresentativoInformeZ($tipo, $gateway);
        if ($tipoNorm === null) {
            return '__excl__';
        }

        return 'tipo:'.$tipoNorm;
    }

    /**
     * Tipo canónico en Informe Z / resumen tótem.
     * credit_card + KIOSK MPQR → totalcoin (QR); credit_card Posnet permanece credit_card.
     */
    public static function tipoRepresentativoInformeZ(?string $tipo, ?string $gateway = null): ?string
    {
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === null || self::esTipoExcluidoInformeZ($tipoNorm)) {
            return null;
        }

        if (self::esTipoQrWaitry($tipoNorm, $gateway)) {
            return self::TIPO_TOTALCOIN;
        }

        if (self::esCreditCardPosnet($tipoNorm, $gateway)) {
            return self::TIPO_CREDIT_CARD;
        }

        return $tipoNorm;
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
    public static function cuentaParaTipoInformeZ(?string $tipo, int $empresaId, ?string $gateway = null): ?array
    {
        if ($empresaId <= 0 || self::esTipoExcluidoInformeZ($tipo)) {
            return null;
        }

        if (self::normalizarTipo($tipo) === 'cash') {
            $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);

            return $efectivoId !== null ? self::cuentaDesdeId($efectivoId, $empresaId) : null;
        }

        $cuentacajaId = self::cuentacajaIdPorTipo($tipo, $empresaId, $gateway);
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
        $tipo = self::extraerTipoPagoOrdenSinNormalizarPush($orden);
        if ($tipo === null) {
            return null;
        }

        $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeOrden($orden);
        if ($tipo === self::normalizarTipo(self::TIPO_CREDIT_CARD)
            && WaitryPaymentGatewaySupport::esGatewayCobroExternoPushErp($gateway)) {
            return self::normalizarTipo(WaitryPaymentTypeSupport::TIPO_INTERFACE);
        }

        return $tipo;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private static function extraerTipoPagoOrdenSinNormalizarPush(array $orden): ?string
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

    public static function cuentacajaIdPorTipo(?string $tipo, int $empresaId, ?string $gateway = null): ?int
    {
        $tipo = self::normalizarTipo($tipo);
        if ($tipo === null || $empresaId <= 0) {
            return null;
        }

        if (self::esTipoQrWaitry($tipo, $gateway)) {
            $tipo = self::TIPO_TOTALCOIN;
        } elseif (self::esCreditCardPosnet($tipo, $gateway)) {
            $tipo = self::TIPO_MERCADOPAGO;
        }

        $id = (int) (self::mapaTipoCuentacaja()[$tipo] ?? 0);
        if ($id <= 0 && self::esTipoQrWaitry($tipo, $gateway)) {
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

    public static function etiquetaTipo(?string $tipo, ?string $gateway = null): string
    {
        $tipoNorm = self::normalizarTipo($tipo);
        if ($tipoNorm === self::normalizarTipo(self::TIPO_KIOSK_MPQR)) {
            return 'QR MP (kiosco)';
        }
        if ($tipoNorm === self::normalizarTipo(self::TIPO_KIOSK_MP)) {
            return 'Posnet (tótem)';
        }
        if ($tipoNorm === self::normalizarTipo(self::TIPO_CREDIT_CARD)) {
            if (WaitryPaymentGatewaySupport::esGatewayQrKiosko($gateway)) {
                return 'QR MP (kiosco)';
            }
            if (WaitryPaymentGatewaySupport::esGatewayPosnetKiosko($gateway)) {
                return 'Posnet (tótem)';
            }
        }

        if (self::esTipoQrWaitry($tipo, $gateway)) {
            return 'QR (Totalcoin / tótem)';
        }

        return match ($tipoNorm) {
            self::TIPO_MERCADOPAGO => 'Mercado Pago',
            self::TIPO_TOTALCOIN => 'Totalcoin',
            self::TIPO_CREDIT_CARD => 'Posnet (tótem)',
            'cash' => 'Efectivo',
            'interface' => 'Mostrador (integración)',
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
