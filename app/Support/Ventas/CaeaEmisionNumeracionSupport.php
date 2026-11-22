<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\VentaRepository;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeradorFiscalSupport;
use InvalidArgumentException;

/**
 * Numeración CAEA (PV mod A).
 *
 * Cada serie es venta.codigo_afip + punto de venta. codigo_afip es el tipo AFIP
 * (1/2/3 factura-ND-NC A, 6/7/8 factura-ND-NC B, 11/12/13 letra C).
 *
 * - El Bierzo: reserva en el numerador de Anita (misma serie que Anita sigue emitiendo);
 *   venta_serie_numerador acompaña.
 * - Crown: reserva atómica en venta_serie_numerador.
 * - Crown sin Anita nativo: siembra una vez desde Informix (cncadmin) + ERP.
 * - Crown con GASTRONOMIA_SINCRONIZAR_ANITA: refresca el piso desde Anita bridge
 *   (igual criterio AGG: último en venta Anita) antes de reservar.
 * - Resto AGG: max ERP (+ piso config) + 1, sin tabla de numerador.
 */
final class CaeaEmisionNumeracionSupport
{
    public static function tipoAnitaDesdeTipotransaccion(
        Tipotransaccion $tipotransaccion,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
        string $letra = 'A',
    ): string {
        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            $tipotransaccion->codigo ?? 0,
            $letra,
            $modoFacturacionCliente,
            $totalComprobante,
        );
        if ($codigoAfip >= 200) {
            return substr((string) ($tipotransaccion->abreviatura ?? 'F'), 0, 1).'CE';
        }

        $codigo = (string) ($tipotransaccion->codigo ?? '');
        if ($codigo >= '200') {
            return substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE';
        }

        return (string) ($tipotransaccion->abreviatura ?? 'FAC');
    }

    public static function reservarSiguienteNumeroErp(
        int $puntoventaId,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        if ($puntoventaId <= 0 || (int) ($tipotransaccion->id ?? 0) <= 0) {
            return 0;
        }

        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            (int) ($tipotransaccion->codigo ?? 0),
            $letraComprobante,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        $ultimoErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            (int) ($tipotransaccion->codigo ?? 0),
            $letraComprobante,
            $empresaId,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        // La serie es por codigo_afip (FAC B = 6, NCB = 8). No subir al máximo de
        // todo el PV: una NC tomaría el siguiente de la factura y ARCA frena las
        // dos correlatividades (Rebisco PV 00030, sep/2026: NCB 54768/54769 con
        // último autorizado 25 y hueco en FAC 54768).

        if (EntornoEmpresaSupport::esElBierzo() && self::esPuntoventaModoCaea($puntoventaId)) {
            return self::reservarSiguienteNumeroAnitaElBierzo(
                $puntoventaId,
                $tipotransaccion,
                $letraComprobante,
                $codigoAfip,
                $empresaId,
                $ultimoErp,
                $modoFacturacionCliente,
                $totalComprobante,
            );
        }

        if (EntornoEmpresaSupport::esElBierzo() || EntornoEmpresaSupport::esCrown()) {
            $piso = self::aplicarPisoCaea($puntoventaId, $ultimoErp, $codigoAfip);

            if (EntornoEmpresaSupport::esCrown()) {
                $ifx = trim((string) config('gastronomia.crown.numerador_anita_ifx_server', 'cncadmin'));
                $path = trim((string) config('gastronomia.crown.numerador_anita_path_sistema', '/usr2/casino'));
                $syncAnita = filter_var(
                    config('gastronomia.sincronizar_anita_al_facturar', false),
                    FILTER_VALIDATE_BOOLEAN
                );
                // Sin sync: siembra una vez (Anita cncadmin + ERP). Con sync: refresca Anita cada vez.
                $semilla = VentaNumeradorFiscalSupport::sembrarSerieSiFalta(
                    $puntoventaId,
                    $codigoAfip,
                    $empresaId,
                    $ifx !== '' ? $ifx : null,
                    $syncAnita,
                    $path !== '' ? $path : null,
                );
                $piso = max($piso, $semilla);
            }

            return VentaNumeradorFiscalSupport::reservarSiguiente(
                $puntoventaId,
                $codigoAfip,
                $empresaId,
                $piso,
            );
        }

        return self::aplicarPisoCaea($puntoventaId, $ultimoErp, $codigoAfip) + 1;
    }

    private static function esPuntoventaModoCaea(int $puntoventaId): bool
    {
        return (string) (Puntoventa::query()->whereKey($puntoventaId)->value('modofacturacion') ?? '') === 'A';
    }

    /**
     * El Bierzo: la sucursal CAEA (00005) es la misma serie ARCA que Anita sigue emitiendo
     * (FAC A suc. 5 va por 360.000+). El número sale del numerador de Anita, que comparten
     * los dos sistemas; venta_serie_numerador solo acompaña. 0 = no emitir.
     */
    private static function reservarSiguienteNumeroAnitaElBierzo(
        int $puntoventaId,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante,
        int $codigoAfip,
        ?int $empresaId,
        int $ultimoErp,
        ?string $modoFacturacionCliente,
        ?float $totalComprobante,
    ): int {
        if (! FacturacionAnitaGrabacionSupport::habilitada()) {
            FacturacionAnitaGrabacionSupport::logSiApagada();
            Log::error('ventas.caea.numerador_bloqueado_anita_apagada', [
                'puntoventa_id' => $puntoventaId,
                'codigo_afip' => $codigoAfip,
            ]);

            return 0;
        }

        $sucursal = trim((string) (Puntoventa::query()->whereKey($puntoventaId)->value('codigo') ?? ''));
        if ($sucursal === '') {
            return 0;
        }

        $tipoAnita = self::tipoAnitaDesdeTipotransaccion(
            $tipotransaccion,
            $modoFacturacionCliente,
            $totalComprobante,
            $letraComprobante,
        );

        $numero = app(VentaRepository::class)->reservarNumeroAnitaConReintentos(
            $tipoAnita,
            strtoupper(trim($letraComprobante)),
            $sucursal,
            PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal),
            'ventas.caea',
        );
        if (! is_int($numero) || $numero <= 0) {
            return 0;
        }

        if ($numero <= $ultimoErp) {
            Log::error('ventas.caea.numerador_anita_atras_del_erp', [
                'puntoventa_id' => $puntoventaId,
                'tipo_anita' => $tipoAnita,
                'letra' => $letraComprobante,
                'numero_anita' => $numero,
                'ultimo_erp' => $ultimoErp,
            ]);

            return 0;
        }

        $erp = VentaNumeradorFiscalSupport::reservarSiguiente($puntoventaId, $codigoAfip, $empresaId, $numero - 1);
        if ($erp !== $numero) {
            Log::warning('ventas.caea.numerador_erp_desalineado', [
                'puntoventa_id' => $puntoventaId,
                'codigo_afip' => $codigoAfip,
                'numero_anita' => $numero,
                'numero_erp' => $erp,
            ]);
        }

        return $numero;
    }

    /**
     * Piso operativo solo de facturas A/B/C (AFIP 1, 6 y 11).
     * ND, NC, exportación y FCE siguen el máximo de su propio codigo_afip.
     */
    public static function aplicarPisoCaea(int $puntoventaId, int $ultimoErp, ?int $codigoAfip = null): int
    {
        if ($codigoAfip !== null && ! in_array($codigoAfip, [1, 6, 11], true)) {
            return $ultimoErp;
        }

        return max($ultimoErp, self::pisoCaeaPorPuntoventaId($puntoventaId));
    }

    public static function pisoCaeaPorPuntoventaId(int $puntoventaId): int
    {
        $pisos = config('facturacion.CAEA_PISO_NUMERO_POR_CODIGO');
        if ($puntoventaId <= 0 || ! is_array($pisos) || $pisos === []) {
            return 0;
        }

        $codigo = trim((string) (Puntoventa::query()->whereKey($puntoventaId)->value('codigo') ?? ''));
        if ($codigo === '') {
            return 0;
        }

        $padded = str_pad(ctype_digit($codigo) ? $codigo : (string) preg_replace('/\D+/', '', $codigo), 5, '0', STR_PAD_LEFT);

        return (int) ($pisos[$padded] ?? $pisos[$codigo] ?? 0);
    }

    /**
     * Reserva el siguiente número ERP y lo aplica al payload de emisión.
     *
     * @param  array<string, mixed>  $payload
     * @return null si ok; mensaje de error si falla (solo PV mod A)
     */
    public static function aplicarReservaNumeracionAlPayload(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante = 'B',
        bool $lockYaAdquirido = false,
    ): ?string {
        if (($puntoventa->modofacturacion ?? '') !== 'A') {
            return null;
        }

        if (! empty($payload['numerocomprobante_forzado'])) {
            // El número ya está fijado (reserva previa o reintento). Avanzar otra vez
            // en Anita dejaría un hueco en la serie CAEA/compemis.
            $payload['_omitir_numera_anita_fin'] = true;

            return null;
        }

        $lock = null;
        if (! $lockYaAdquirido) {
            try {
                $lock = PuntoventaEmisionLock::adquirir((int) $puntoventa->id);
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            }
        }

        try {
            $empresaId = (int) ($puntoventa->empresa_id ?? 0);
            $modoCliente = isset($payload['modofacturacion_cliente'])
                ? (string) $payload['modofacturacion_cliente']
                : null;
            $totalComprobante = isset($payload['total_comprobante'])
                ? (float) $payload['total_comprobante']
                : null;

            $numero = self::reservarSiguienteNumeroErp(
                (int) $puntoventa->id,
                $tipotransaccion,
                $letraComprobante,
                $empresaId > 0 ? $empresaId : null,
                $modoCliente,
                $totalComprobante,
            );

            if ($numero <= 0) {
                return 'No pudo reservar número de comprobante CAEA en ERP (PV '.($puntoventa->codigo ?? '').').';
            }

            $payload['numerocomprobante_forzado'] = $numero;
            // AGG/Crown: el ERP ya numeró. El Bierzo: el número ya se reservó en el numerador Anita.
            // Avanzarlo otra vez al grabar dejaría un hueco en la serie.
            $payload['_omitir_numera_anita_fin'] = true;
        } finally {
            if (! $lockYaAdquirido) {
                PuntoventaEmisionLock::liberar($lock);
            }
        }

        return null;
    }

    /**
     * Asigna numerocomprobante_forzado en payload (sin lock; caller debe serializar emisión).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function marcarNumerocomprobanteForzadoEnPayload(array &$payload, int $numero): void
    {
        if ($numero <= 0) {
            return;
        }

        $payload['numerocomprobante_forzado'] = $numero;
        $payload['_omitir_numera_anita_fin'] = true;
    }
}
