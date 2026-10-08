<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Database\DbContencionSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Defensa ante colisión de numerocomprobante (índice único venta).
 *
 * Unique vigente (todos los entornos): (codigo_afip, puntoventa_id, numerocomprobante).
 * La numeración max()+1 usa la misma clave (VentaNumeracionEmpresaSupport).
 * codigo_afip es el tipo ARCA efectivo (001+letra, 002 ND, 003 NC, 201 FCE A, 206 FCE B):
 * FAC A y FAG A (ambas 001) no pueden repetir sucursal+número; FAC A 10-1 y FAC B 10-1 sí (001 vs 006).
 * numerocomprobante guarda solo el número.
 */
final class VentaNumerocomprobanteUnicidadSupport
{
    public const UNIQUE_INDEX = 'venta_puntoventa_numerocomprobante_unique';

    public const UNIQUE_INDEX_ELBIERZO_TIPO = 'venta_puntoventa_tipotransaccion_numerocomprobante_unique';

    public const UNIQUE_INDEX_ELBIERZO_AFIP = 'venta_codigo_afip_puntoventa_numerocomprobante_unique';

    public const MARCA_ESPEJO_ANITA = ' · Anita sin CAE';

    public static function esViolacionNumerocomprobante(Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            self::UNIQUE_INDEX,
            self::UNIQUE_INDEX_ELBIERZO_TIPO,
            self::UNIQUE_INDEX_ELBIERZO_AFIP,
            'numerocomprobante',
            'puntoventa_numerocomprobante',
            'puntoventa_tipotransaccion_numerocomprobante',
            'codigo_afip_puntoventa_numerocomprobante',
        );
    }

    /**
     * Tras colisión en INSERT: descarta número reservado y pide el siguiente (lock PV ya adquirido).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function renumerarPayloadCaeaTrasColision(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipo,
        string $letraComprobante,
        bool $lockYaAdquirido = true,
    ): ?string {
        unset($payload['numerocomprobante_forzado'], $payload['_omitir_numera_anita_fin']);

        return CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $puntoventa,
            $tipo,
            $letraComprobante,
            $lockYaAdquirido,
        );
    }

    /**
     * El número que ARCA va a autorizar ya está en el ERP.
     * Si es solo un espejo de consulta importado de Anita (sin CAE ni movimientos),
     * suelta la clave fiscal para que la emisión real use ese número.
     * Si el ocupante es un comprobante de operación, corta con un mensaje claro.
     */
    public static function cederNumeroFiscalSiEspejoAnitaSinCae(
        int $puntoventaId,
        int $codigoAfip,
        int $numerocomprobante,
    ): void {
        if ($puntoventaId <= 0 || $codigoAfip <= 0 || $numerocomprobante <= 0) {
            return;
        }
        if (! Schema::hasColumn('venta', 'codigo_afip')) {
            return;
        }

        $ocupante = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo_afip', $codigoAfip)
            ->where('numerocomprobante', $numerocomprobante)
            ->first();
        if (! $ocupante) {
            return;
        }

        if (! self::espejoAnitaPuedeCederNumero($ocupante)) {
            throw new RuntimeException(
                'ARCA asignó el número '.$numerocomprobante
                .' pero ya existe '.$ocupante->codigo
                .' en el ERP. No se puede emitir otro comprobante con el mismo tipo y punto de venta.'
            );
        }

        $codigoVisible = self::codigoMarcadoEspejoAnita((string) $ocupante->codigo);
        $ocupante->codigo_afip = null;
        if ($codigoVisible !== (string) $ocupante->codigo) {
            $ocupante->codigo = $codigoVisible;
        }
        $ocupante->save();

        Log::warning('facturacion.numeracion.espejo_anita_cedio_numero', [
            'venta_id' => (int) $ocupante->id,
            'puntoventa_id' => $puntoventaId,
            'codigo_afip' => $codigoAfip,
            'numerocomprobante' => $numerocomprobante,
            'codigo' => $codigoVisible,
        ]);
    }

    /**
     * El número fiscal ya lo usa el comprobante autorizado. El espejo de Anita
     * queda solo como rastro de la importación y no entra al libro de IVA.
     */
    public static function esEspejoAnitaCedido(string $codigo): bool
    {
        return str_contains($codigo, self::MARCA_ESPEJO_ANITA);
    }

    public static function codigoMarcadoEspejoAnita(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || str_contains($codigo, self::MARCA_ESPEJO_ANITA)) {
            return $codigo;
        }

        $marcado = $codigo.self::MARCA_ESPEJO_ANITA;
        if (mb_strlen($marcado) <= 100) {
            return $marcado;
        }

        $base = rtrim(mb_substr($codigo, 0, 100 - mb_strlen(self::MARCA_ESPEJO_ANITA)));

        return $base.self::MARCA_ESPEJO_ANITA;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function esPayloadVinculoConsulta(?array $payload): bool
    {
        return is_array($payload) && ! empty($payload['vinculo_consulta']);
    }

    private static function espejoAnitaPuedeCederNumero(Venta $venta): bool
    {
        if (trim((string) ($venta->cae ?? '')) !== '') {
            return false;
        }

        $ventaId = (int) $venta->id;
        if ($ventaId <= 0 || ! self::emisionEsSoloVinculoConsulta($ventaId)) {
            return false;
        }

        if (Schema::hasTable('articulo_movimiento')
            && DB::table('articulo_movimiento')->where('venta_id', $ventaId)->exists()) {
            return false;
        }
        if (Schema::hasTable('caja_movimiento')
            && DB::table('caja_movimiento')->where('venta_id', $ventaId)->exists()) {
            return false;
        }
        if (Schema::hasTable('asiento')
            && Schema::hasColumn('asiento', 'venta_id')
            && DB::table('asiento')->where('venta_id', $ventaId)->exists()) {
            return false;
        }
        if (Schema::hasTable('cliente_cuentacorriente')
            && DB::table('cliente_cuentacorriente')->where('venta_id', $ventaId)->exists()) {
            return false;
        }

        return true;
    }

    private static function emisionEsSoloVinculoConsulta(int $ventaId): bool
    {
        if (! Schema::hasTable('facturacion_local_emision')) {
            return false;
        }

        if (FacturacionLocalEmision::query()->where('venta_nc_id', $ventaId)->exists()) {
            return false;
        }

        $emisiones = FacturacionLocalEmision::query()
            ->where('venta_id', $ventaId)
            ->get(['id', 'payload_resumen_json']);
        if ($emisiones->isEmpty()) {
            return false;
        }

        foreach ($emisiones as $emision) {
            $payload = $emision->payload_resumen_json;
            if (! is_array($payload) || ! self::esPayloadVinculoConsulta($payload)) {
                return false;
            }
        }

        return true;
    }
}
