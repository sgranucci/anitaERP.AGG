<?php

namespace App\Support\Configuracion;

use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Configuracion\Parametro_Sistema;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\ArcaFceDatosAdicionalesSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Parámetros editables de Configuración general.
 * Lee BD y, si no hay fila, el config()/env vigente.
 */
final class ParametroSistemaSupport
{
    public const CLAVE_LIMITE_FCE = 'limite_fce';

    public const CLAVE_TOPE_CONSUMIDOR_FINAL = 'tope_consumidor_final';

    public const CLAVE_FCE_CUENTACAJA_ID = 'fce_cuentacaja_id';

    public const CLAVE_ARTICULO_APROBACION_ALTA = 'articulo_aprobacion_alta';

    public const CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO = 'suscripcion_comprobante_dia_escalamiento';

    public const CLAVE_OC_SOLICITANTE_EDITABLE = 'oc_solicitante_editable';

    public const CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD = 'recepcion_no_precargar_cantidad';

    public const CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID = 'ndr_cheque_concepto_ivacompra_id';

    public const CLAVE_NDR_CHEQUE_PUNTOVENTA_ID = 'ndr_cheque_puntoventa_id';

    public const CLAVE_NCP_PRONTO_PAGO_CUENTACONTABLE_ID = 'ncp_pronto_pago_cuentacontable_id';

    private const CACHE_KEY = 'parametro_sistema.mapa';

    /**
     * @return array<string, array{grupo: string, etiqueta: string, ayuda: string, tipo: string, orden: int, opciones?: array<string, string>}>
     */
    public static function definiciones(): array
    {
        $defs = [
            self::CLAVE_LIMITE_FCE => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Tope FCE MiPyME',
                'ayuda' => 'Si el cliente es receptor de Factura de Crédito (FCE) y el total del comprobante alcanza este importe, se emite FCE (códigos AFIP 201 / 206).',
                'tipo' => 'decimal',
                'orden' => 10,
            ],
            self::CLAVE_TOPE_CONSUMIDOR_FINAL => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Tope consumidor final',
                'ayuda' => 'Umbral RG 5700/2025: a partir de este monto hay que identificar al comprador (DNI/CUIT) en Factura B, POS y Libro IVA Digital.',
                'tipo' => 'decimal',
                'orden' => 20,
            ],
            self::CLAVE_FCE_CUENTACAJA_ID => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Cuenta de caja FCE (CBU emisor)',
                'ayuda' => 'Cuenta cuyo CBU se envía a ARCA en FCE (dato adicional 21). Si queda vacía, cada empresa usa su cuenta en pesos de Banco Macro Gerli. F1 o lupa abren la consulta.',
                'tipo' => 'cuentacaja',
                'orden' => 30,
            ],
            self::CLAVE_ARTICULO_APROBACION_ALTA => [
                'grupo' => 'Aprobaciones / Stock',
                'etiqueta' => 'Circuito de aprobación al alta de artículos',
                'ayuda' => 'Si está activo, el alta nace PENDIENTE y sigue el árbol tipo Artículos (router por uso). Si está apagado, nace ACTIVO como siempre. Los usos se configuran en Stock → Uso de artículos (auto / arbol / default).',
                'tipo' => 'boolean',
                'orden' => 40,
            ],
            self::CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO => [
                'grupo' => 'Compras / Suscripciones',
                'etiqueta' => 'Escalamiento factura de portal',
                'ayuda' => 'Día del mes del período en que, si sigue faltando el PDF del portal, se avisa a gerencia (destinatarios en Configuración → Modulo aviso). Default: último día (cierre de tarjeta).',
                'tipo' => 'select',
                'orden' => 50,
                'opciones' => self::opcionesDiaEscalamientoSuscripcion(),
            ],
            self::CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD => [
                'grupo' => 'Recepción de proveedor',
                'etiqueta' => 'No precargar cantidad recibida',
                'ayuda' => 'Activo: al traer la orden de compra, la cantidad recibida arranca en blanco y hay que cargarla. Inactivo: se completa con el pendiente de la orden.',
                'tipo' => 'boolean',
                'orden' => 70,
            ],
            self::CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID => [
                'grupo' => 'Caja / Cheques',
                'etiqueta' => 'Concepto del débito por cheque rechazado',
                'ayuda' => 'Concepto de IVA compra del débito interno al proveedor cuando se rechaza un cheque entregado. Tiene que ser uno que no retenga ganancias ni ingresos brutos. F1 o la lupa abren la consulta.',
                'tipo' => 'concepto_ivacompra',
                'orden' => 80,
            ],
            self::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID => [
                'grupo' => 'Caja / Cheques',
                'etiqueta' => 'Sucursal de la NDR por cheque rechazado',
                'ayuda' => 'Punto de venta de esta instalación. En Ferli queda fijo para todas las empresas (sucursal 03333) y el rechazo no lo cambia. En las demás, si el .env tiene un solo punto de venta se propone ese id; si hay uno distinto por empresa, queda vacío y sigue el mapa CHEQUE_ND_PUNTOVENTA_POR_EMPRESA.',
                'tipo' => 'puntoventa',
                'orden' => 85,
            ],
            self::CLAVE_NCP_PRONTO_PAGO_CUENTACONTABLE_ID => [
                'grupo' => 'Caja / Cobranzas',
                'etiqueta' => 'Cuenta del neto — NC por pronto pago',
                'ayuda' => 'Cuenta imputable del neto de la nota de crédito que se emite al descontar en cobranza (NCP). El IVA sigue en la cuenta de IVA débito. En las otras empresas se usa el mismo código. F1 o la lupa abren la consulta.',
                'tipo' => 'cuentacontable',
                'orden' => 90,
            ],
        ];

        if (EntornoEmpresaSupport::esElBierzo()) {
            $defs[self::CLAVE_OC_SOLICITANTE_EDITABLE] = [
                'grupo' => 'Compras',
                'etiqueta' => 'Solicitante editable en orden de compra',
                'ayuda' => 'Si está activo, al cargar una OC el solicitante se elige con la consulta de usuarios. Arranca con quien carga la orden, o con el último solicitante que esa persona eligió. Si está inactivo, el solicitante es quien carga y no se puede cambiar.',
                'tipo' => 'boolean',
                'orden' => 60,
            ];
        }

        return $defs;
    }

    /**
     * @return array<string, string>
     */
    public static function opcionesDiaEscalamientoSuscripcion(): array
    {
        $ops = ['ultimo' => 'Último día del mes (cierre de tarjeta)'];
        for ($d = 1; $d <= 31; $d++) {
            $ops[(string) $d] = 'Día '.$d;
        }

        return $ops;
    }

    public static function limiteFce(): float
    {
        return self::decimal(self::CLAVE_LIMITE_FCE, (float) config('facturacion.LIMITE_FCE', 0));
    }

    public static function topeConsumidorFinal(): float
    {
        return self::decimal(
            self::CLAVE_TOPE_CONSUMIDOR_FINAL,
            (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 10_000_000)
        );
    }

    public static function fceCuentacajaId(): int
    {
        $valor = self::mapa()[self::CLAVE_FCE_CUENTACAJA_ID] ?? '';
        $id = (int) $valor;
        if ($id > 0) {
            return $id;
        }

        return (int) self::fallbackValor(self::CLAVE_FCE_CUENTACAJA_ID);
    }

    public static function fceCuentacaja(): ?Cuentacaja
    {
        $id = self::fceCuentacajaId();
        if ($id <= 0) {
            return null;
        }

        return Cuentacaja::query()->find($id);
    }

    public static function decimal(string $clave, float $fallback): float
    {
        $valor = self::mapa()[$clave] ?? null;
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        return (float) $valor;
    }

    public static function boolean(string $clave, bool $fallback): bool
    {
        $valor = self::mapa()[$clave] ?? null;
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        $v = strtolower(trim((string) $valor));

        return in_array($v, ['1', 'true', 's', 'si', 'sí', 'yes', 'on'], true);
    }

    public static function texto(string $clave, string $fallback): string
    {
        $valor = self::mapa()[$clave] ?? null;
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        return trim((string) $valor);
    }

    /**
     * Activo: la precarga de la OC deja la cantidad recibida en blanco.
     * Inactivo: se completa con el pendiente.
     */
    public static function noPrecargarCantidadRecibida(): bool
    {
        return self::boolean(self::CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD, false);
    }

    /**
     * Concepto de IVA compra del débito interno al proveedor por cheque rechazado.
     * 0 = todavía no se eligió en Configuración general.
     */
    public static function ndrChequeConceptoIvacompraId(): int
    {
        return max(0, (int) (self::mapa()[self::CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID] ?? 0));
    }

    /**
     * Punto de venta de la NDR por cheque rechazado (Configuración general).
     * 0 = no hay uno solo para esta instalación; el rechazo usa el mapa del .env.
     */
    public static function ndrChequePuntoventaId(): int
    {
        $valor = self::mapa()[self::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID] ?? null;
        if ($valor === null || $valor === '') {
            return (int) self::fallbackValor(self::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID);
        }

        return max(0, (int) $valor);
    }

    /**
     * Cuenta configurada del neto de la NCP de cobranza (id del plan elegido).
     * 0 = todavía no se eligió en Configuración general.
     */
    public static function ncpProntoPagoCuentacontableId(): int
    {
        return max(0, (int) (self::mapa()[self::CLAVE_NCP_PRONTO_PAGO_CUENTACONTABLE_ID] ?? 0));
    }

    /**
     * Esa cuenta en el plan de $empresaId. Si se eligió en otra empresa, busca el mismo código.
     */
    public static function ncpProntoPagoCuentaIdParaEmpresa(int $empresaId): int
    {
        $id = self::ncpProntoPagoCuentacontableId();
        if ($id <= 0 || $empresaId <= 0) {
            return 0;
        }

        $cuenta = Cuentacontable::query()->find($id);
        if (! $cuenta) {
            return 0;
        }

        if ((int) $cuenta->empresa_id === $empresaId) {
            return (int) $cuenta->id;
        }

        return (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $cuenta->codigo)
            ->value('id') ?? 0);
    }

    /**
     * Día de escalamiento de factura portal: "ultimo" | "1".."31".
     * Prioridad: Configuración general → config/.env.
     */
    public static function suscripcionComprobanteDiaEscalamiento(): string
    {
        return self::texto(
            self::CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO,
            (string) config('compras.suscripcion_comprobantes.dia_escalamiento', 'ultimo')
        );
    }

    /**
     * @return array<string, list<array{clave: string, grupo: string, etiqueta: string, ayuda: string, tipo: string, valor: string}>>
     */
    public static function listarParaFormulario(): array
    {
        $mapa = self::mapa();
        $grupos = [];

        foreach (self::definiciones() as $clave => $def) {
            $valor = $mapa[$clave] ?? self::fallbackValor($clave);
            $item = [
                'clave' => $clave,
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => (string) $valor,
            ];
            if ($def['tipo'] === 'cuentacaja') {
                $item['cuenta'] = self::cuentaParaFormulario((int) $valor);
            }
            if ($def['tipo'] === 'concepto_ivacompra') {
                $item['concepto'] = self::conceptoIvacompraParaFormulario((int) $valor);
            }
            if ($def['tipo'] === 'cuentacontable') {
                $item['cuentacontable'] = self::cuentaContableParaFormulario((int) $valor);
            }
            if ($def['tipo'] === 'puntoventa') {
                $item['puntoventa'] = self::puntoventaParaFormulario((int) $valor);
            }
            if ($def['tipo'] === 'select' && isset($def['opciones']) && is_array($def['opciones'])) {
                $item['opciones'] = $def['opciones'];
            }
            $grupos[$def['grupo']][] = $item;
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public static function guardar(array $valores): void
    {
        foreach (self::definiciones() as $clave => $def) {
            if (! array_key_exists($clave, $valores)) {
                continue;
            }
            $valorRaw = $valores[$clave];
            if ($def['tipo'] === 'cuentacaja' || $def['tipo'] === 'concepto_ivacompra' || $def['tipo'] === 'cuentacontable' || $def['tipo'] === 'puntoventa') {
                $valor = (string) max(0, (int) $valorRaw);
                if ($valor === '0') {
                    $valor = '';
                }
            } elseif ($def['tipo'] === 'boolean') {
                $valor = self::normalizarBooleanGuardar($valorRaw) ? '1' : '0';
            } elseif ($def['tipo'] === 'select') {
                $valor = trim((string) $valorRaw);
                $ops = $def['opciones'] ?? [];
                if ($ops !== [] && ! array_key_exists($valor, $ops)) {
                    $valor = (string) array_key_first($ops);
                }
            } else {
                $valor = is_numeric($valorRaw)
                    ? (string) $valorRaw
                    : trim((string) $valorRaw);
            }

            Parametro_Sistema::query()->updateOrCreate(
                ['clave' => $clave],
                [
                    'grupo' => $def['grupo'],
                    'etiqueta' => $def['etiqueta'],
                    'ayuda' => $def['ayuda'],
                    'tipo' => $def['tipo'],
                    'valor' => $valor,
                    'orden' => $def['orden'],
                ]
            );
        }

        self::olvidarCache();
    }

    public static function olvidarCache(): void
    {
        self::$tieneTabla = null;
        Cache::forget(self::CACHE_KEY);
    }

    private static ?bool $tieneTabla = null;

    /**
     * @return array<string, string>
     */
    private static function mapa(): array
    {
        try {
            if (self::$tieneTabla === null) {
                self::$tieneTabla = Schema::hasTable('parametro_sistema');
            }
            if (! self::$tieneTabla) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, 3600, static function (): array {
                $out = [];
                foreach (Parametro_Sistema::query()->get(['clave', 'valor']) as $fila) {
                    $out[(string) $fila->clave] = (string) $fila->valor;
                }

                return $out;
            });
        } catch (Throwable) {
            return [];
        }
    }

    private static function fallbackValor(string $clave): string
    {
        return match ($clave) {
            self::CLAVE_LIMITE_FCE => (string) (float) config('facturacion.LIMITE_FCE', 0),
            self::CLAVE_TOPE_CONSUMIDOR_FINAL => (string) (float) config(
                'arca_wsfe.receptor.consumidor_final_umbral_monto',
                10_000_000
            ),
            self::CLAVE_FCE_CUENTACAJA_ID => (string) self::cuentacajaIdFallbackAnita(),
            self::CLAVE_ARTICULO_APROBACION_ALTA => filter_var(
                config('articulo.aprobacion_alta.habilitado', false),
                FILTER_VALIDATE_BOOLEAN
            ) ? '1' : '0',
            self::CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO => (string) config(
                'compras.suscripcion_comprobantes.dia_escalamiento',
                'ultimo'
            ),
            self::CLAVE_OC_SOLICITANTE_EDITABLE => EntornoEmpresaSupport::esElBierzo() ? '1' : '0',
            self::CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD => '0',
            self::CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID => '',
            self::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID => (string) self::ndrChequePuntoventaIdFallback(),
            self::CLAVE_NCP_PRONTO_PAGO_CUENTACONTABLE_ID => '',
            default => '0',
        };
    }

    private static function normalizarBooleanGuardar(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        $v = strtolower(trim((string) $valor));

        return in_array($v, ['1', 'true', 's', 'si', 'sí', 'yes', 'on'], true);
    }

    /**
     * @return array{id:int, codigo:string, nombre:string}
     */
    public static function conceptoIvacompraParaFormulario(int $id): array
    {
        $concepto = $id > 0 ? Concepto_Ivacompra::query()->find($id) : null;

        return [
            'id' => (int) ($concepto->id ?? 0),
            'codigo' => (string) ($concepto->codigo ?? ''),
            'nombre' => (string) ($concepto->nombre ?? ''),
        ];
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, empresa_id:int}
     */
    public static function cuentaContableParaFormulario(int $id): array
    {
        $cuenta = $id > 0 ? Cuentacontable::query()->find($id) : null;

        return [
            'id' => (int) ($cuenta->id ?? 0),
            'codigo' => (string) ($cuenta->codigo ?? ''),
            'nombre' => (string) ($cuenta->nombre ?? ''),
            'empresa_id' => (int) ($cuenta->empresa_id ?? 0),
        ];
    }

    /**
     * @return array{id:int, codigo:string, nombre:string}
     */
    public static function puntoventaParaFormulario(int $id): array
    {
        $pv = $id > 0 ? Puntoventa::query()->find($id) : null;

        return [
            'id' => (int) ($pv->id ?? 0),
            'codigo' => (string) ($pv->codigo ?? ''),
            'nombre' => (string) ($pv->nombre ?? ''),
        ];
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, cbu:string}
     */
    public static function cuentaParaFormulario(int $id): array
    {
        $cta = $id > 0 ? Cuentacaja::query()->find($id) : null;

        return [
            'id' => (int) ($cta->id ?? 0),
            'codigo' => (string) ($cta->codigo ?? ''),
            'nombre' => (string) ($cta->nombre ?? ''),
            'cbu' => (string) ($cta->cbu ?? ''),
        ];
    }

    private static function ndrChequePuntoventaIdFallback(): int
    {
        if (EntornoEmpresaSupport::esFerli()) {
            return self::puntoventaActivoIdPorCodigo('03333');
        }

        $map = config('cheque.nd_puntoventa_por_empresa', []);
        if (! is_array($map)) {
            return 0;
        }

        $ids = [];
        foreach ($map as $pvId) {
            $id = (int) $pvId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (count($ids) !== 1) {
            return 0;
        }

        $id = (int) array_key_first($ids);
        $pv = Puntoventa::query()->find($id);
        if (! $pv || ! self::puntoventaActivo($pv)) {
            return 0;
        }

        return $id;
    }

    private static function puntoventaActivoIdPorCodigo(string $codigo): int
    {
        $normalizado = Puntoventa::normalizarCodigoArca($codigo);
        if ($normalizado === null) {
            return 0;
        }

        $pv = Puntoventa::query()
            ->where('codigo', $normalizado)
            ->get()
            ->first(static fn (Puntoventa $fila): bool => self::puntoventaActivo($fila));

        return (int) ($pv->id ?? 0);
    }

    private static function puntoventaActivo(Puntoventa $pv): bool
    {
        $estado = strtoupper(trim((string) ($pv->estado ?? 'A')));

        return $estado !== 'I' && $estado !== 'S';
    }

    private static function cuentacajaIdFallbackAnita(): int
    {
        $codigos = [
            ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA,
            ltrim(ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA, '0') ?: '0',
        ];
        $id = (int) (Cuentacaja::query()->whereIn('codigo', $codigos)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $cbu = preg_replace('/\D+/', '', (string) config('arca.caea.fce.cbu_emisor', '')) ?? '';
        if (strlen($cbu) === 22) {
            return (int) (Cuentacaja::query()->where('cbu', $cbu)->value('id') ?? 0);
        }

        return 0;
    }
}
