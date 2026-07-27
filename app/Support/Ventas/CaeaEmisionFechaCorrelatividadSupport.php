<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Condicioniva;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Evita ARCA 704 en PV CAEA: CbteFch debe ser ≥ a la del último comprobante del mismo PV+tipo.
 *
 * Si la fecha del último comprobante es mayor a la propuesta, eleva solo fechafactura.
 * fechajornada no se altera (sigue siendo la jornada operativa / del cierre).
 */
final class CaeaEmisionFechaCorrelatividadSupport
{
    /**
     * @return array{
     *     fechafactura: string,
     *     fechajornada: string,
     *     ajustada: bool,
     *     ultima_fecha: ?string,
     *     ultimo_numero: ?int
     * }
     */
    public static function resolverFechas(
        ?Puntoventa $puntoventa,
        string $fechaFacturaPropuesta,
        string $fechaJornadaPropuesta,
        Tipotransaccion|int|null $tipotransaccion = null,
        string $letra = 'B',
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): array {
        $fechaFactura = self::normalizarFecha($fechaFacturaPropuesta);
        $fechaJornada = self::normalizarFecha($fechaJornadaPropuesta);
        if ($fechaFactura === '') {
            $fechaFactura = $fechaJornada !== '' ? $fechaJornada : date('Y-m-d');
        }
        if ($fechaJornada === '') {
            $fechaJornada = $fechaFactura;
        }

        $base = [
            'fechafactura' => $fechaFactura,
            'fechajornada' => $fechaJornada,
            'ajustada' => false,
            'ultima_fecha' => null,
            'ultimo_numero' => null,
        ];

        if ($puntoventa === null || ($puntoventa->modofacturacion ?? '') !== 'A') {
            return $base;
        }

        $tipo = self::resolverTipotransaccion($tipotransaccion);
        if ($tipo === null) {
            return $base;
        }

        $empresa = $empresaId !== null && $empresaId > 0
            ? $empresaId
            : (int) ($puntoventa->empresa_id ?? 0);
        $empresa = $empresa > 0 ? $empresa : null;

        $ultimo = self::ultimoComprobante(
            (int) $puntoventa->id,
            $tipo,
            $letra !== '' ? $letra : 'B',
            $empresa,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        if ($ultimo === null) {
            return $base;
        }

        $base['ultima_fecha'] = $ultimo['fecha'];
        $base['ultimo_numero'] = $ultimo['numerocomprobante'];

        if ($ultimo['fecha'] === '' || $ultimo['fecha'] <= $fechaFactura) {
            return $base;
        }

        return [
            'fechafactura' => $ultimo['fecha'],
            'fechajornada' => $fechaJornada,
            'ajustada' => true,
            'ultima_fecha' => $ultimo['fecha'],
            'ultimo_numero' => $ultimo['numerocomprobante'],
        ];
    }

    /**
     * Ajusta fechafactura en un payload de emisión si el PV es CAEA (no toca fechajornada).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function aplicarAlPayload(array $payload): array
    {
        $puntoventaId = (int) ($payload['puntoventa_id'] ?? 0);
        if ($puntoventaId <= 0) {
            return $payload;
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        if ($puntoventa === null || ($puntoventa->modofacturacion ?? '') !== 'A') {
            return $payload;
        }

        $tipoId = (int) ($payload['tipotransaccion_id'] ?? 0);
        $letra = self::letraDesdePayload($payload);
        $fechaFactura = (string) ($payload['fechafactura'] ?? '');
        $fechaJornada = (string) ($payload['fechajornada'] ?? $fechaFactura);

        $modoCliente = isset($payload['modofacturacion_cliente'])
            ? (string) $payload['modofacturacion_cliente']
            : null;
        $total = isset($payload['total_comprobante'])
            ? (float) $payload['total_comprobante']
            : null;

        $resuelto = self::resolverFechas(
            $puntoventa,
            $fechaFactura,
            $fechaJornada,
            $tipoId > 0 ? $tipoId : null,
            $letra,
            (int) ($puntoventa->empresa_id ?? 0) ?: null,
            $modoCliente,
            $total,
        );

        $payload['fechafactura'] = $resuelto['fechafactura'];
        $payload['fechajornada'] = $resuelto['fechajornada'];
        if ($resuelto['ajustada']) {
            $payload['_caea_fecha_correlatividad_ajustada'] = [
                'ultima_fecha' => $resuelto['ultima_fecha'],
                'ultimo_numero' => $resuelto['ultimo_numero'],
            ];
        }

        return $payload;
    }

    /**
     * Fecha calendario del cierre de jornada (para post-Waitry / correlatividad).
     * Si aún no cerró, usa fecha_jornada.
     */
    public static function fechaCalendarioCierre(?\DateTimeInterface $cierreEn, ?\DateTimeInterface $fechaJornada): string
    {
        if ($cierreEn !== null) {
            return $cierreEn->format('Y-m-d');
        }
        if ($fechaJornada !== null) {
            return $fechaJornada->format('Y-m-d');
        }

        return date('Y-m-d');
    }

    /**
     * @return array{fecha:string,numerocomprobante:int}|null
     */
    public static function ultimoComprobante(
        int $puntoventaId,
        Tipotransaccion $tipotransaccion,
        string $letra,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): ?array {
        if ($puntoventaId <= 0) {
            return null;
        }

        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            (int) ($tipotransaccion->codigo ?? 0),
            $letra,
            $modoFacturacionCliente,
            $totalComprobante,
        );
        if ($codigoAfip <= 0) {
            return null;
        }

        $query = Venta::query()
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->where('venta.puntoventa_id', $puntoventaId)
            ->whereNull('venta.deleted_at')
            ->whereNull('tt.deleted_at');

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        $letraNorm = strtoupper(trim($letra));
        $bases = TipotransaccionCodigoAfipSupport::codigosBaseAlmacenadosPosibles($codigoAfip, $letraNorm);
        if ($bases !== []) {
            $query->whereIn(DB::raw('CAST(tt.codigo AS UNSIGNED)'), $bases);
        }
        if ($letraNorm !== '') {
            $query->where(static function ($q) use ($letraNorm): void {
                $q->where('venta.codigo', 'like', '% '.$letraNorm.'-%')
                    ->orWhere('venta.codigo', 'like', '% '.$letraNorm.' %');
            });
        }

        $row = $query
            ->orderByDesc('venta.numerocomprobante')
            ->first(['venta.numerocomprobante', 'venta.fecha']);

        if ($row === null) {
            return null;
        }

        $fecha = self::normalizarFecha((string) ($row->fecha ?? ''));
        $nro = (int) ($row->numerocomprobante ?? 0);
        if ($nro <= 0 || $fecha === '') {
            return null;
        }

        return [
            'fecha' => $fecha,
            'numerocomprobante' => $nro,
        ];
    }

    private static function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha, $m) === 1) {
            return substr($m[0], 0, 10);
        }
        $ts = strtotime($fecha);

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    private static function resolverTipotransaccion(Tipotransaccion|int|null $tipo): ?Tipotransaccion
    {
        if ($tipo instanceof Tipotransaccion) {
            return $tipo;
        }
        if (is_int($tipo) && $tipo > 0) {
            return Tipotransaccion::query()->find($tipo);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function letraDesdePayload(array $payload): string
    {
        $letra = strtoupper(trim((string) ($payload['letra'] ?? '')));
        if ($letra !== '') {
            return $letra;
        }

        $receptor = is_array($payload['venta_receptor'] ?? null) ? $payload['venta_receptor'] : [];
        $condicionId = (int) ($receptor['condicioniva_id'] ?? $payload['condicioniva_id'] ?? 0);
        if ($condicionId <= 0) {
            $clienteId = (int) ($payload['cliente_id'] ?? $receptor['cliente_id'] ?? 0);
            if ($clienteId > 0) {
                $condicionId = (int) (Cliente::query()->whereKey($clienteId)->value('condicioniva_id') ?? 0);
            }
        }
        if ($condicionId > 0) {
            $letraCond = strtoupper(trim((string) (Condicioniva::query()
                ->whereKey($condicionId)
                ->value('letra') ?? '')));
            if ($letraCond !== '') {
                return $letraCond;
            }
        }

        return 'B';
    }
}
