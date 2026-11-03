<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Tipoasiento;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownComprobante;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownImputacionSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownImpuestoConceptos;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Graba la factura de una transferencia Crown: cabecera, renglón, impuestos y asiento.
 *
 * Solo ERP (MySQL). No pide CAE, no llama al bridge Anita y no escribe ctamov/subdiario/venta
 * en Informix: el comprobante ya existe en la sala o en ARCA.
 */
final class TransferenciaCrownFacturaWriter
{
    /** @var array<string, Tipotransaccion> */
    private array $tipos = [];

    /** @var array<string, Puntoventa> */
    private array $puntos = [];

    private ?Cliente $consumidor = null;

    private ?int $tipoAsientoVentasId = null;

    /** @var array<string, int> */
    private array $impuestos = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function marcarExistentes(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $numeros = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila['numero'] ?? 0);
            if ($nro > 0) {
                $numeros[$nro] = $nro;
            }
        }

        $existentes = [];
        if ($numeros !== []) {
            foreach (array_chunk(array_values($numeros), 2000) as $lote) {
                $rows = DB::table('venta as v')
                    ->join('puntoventa as p', 'p.id', '=', 'v.puntoventa_id')
                    ->whereIn('v.numerocomprobante', $lote)
                    ->get(['v.id', 'v.codigo_afip', 'v.numerocomprobante', 'v.codigo', 'p.codigo as pv']);
                foreach ($rows as $row) {
                    $pv = (int) $row->pv;
                    $nro = (int) $row->numerocomprobante;
                    $afip = (int) ($row->codigo_afip ?? 0);
                    $existentes[$afip.'|'.$pv.'|'.$nro] = (int) $row->id;
                    $existentes['cod|'.trim((string) $row->codigo)] = (int) $row->id;
                }
            }
        }

        $conAsiento = [];
        $ids = array_values(array_unique(array_filter($existentes)));
        if ($ids !== []) {
            foreach (array_chunk($ids, 2000) as $loteIds) {
                foreach (DB::table('asiento')->whereIn('venta_id', $loteIds)->pluck('venta_id') as $ventaId) {
                    $conAsiento[(int) $ventaId] = true;
                }
            }
        }

        foreach ($filas as &$fila) {
            $afip = (int) ($fila['codigo_afip'] ?? 0);
            $claveAfip = $afip.'|'.(int) $fila['sucursal'].'|'.(int) $fila['numero'];
            $claveCod = 'cod|'.TransferenciaCrownComprobante::codigoVisible($fila);
            $ventaId = $existentes[$claveAfip] ?? $existentes[$claveCod] ?? 0;
            if ($ventaId <= 0) {
                continue;
            }
            $fila['venta_id'] = $ventaId;
            if (isset($conAsiento[$ventaId])) {
                $fila['estado'] = 'existe';
                $fila['mensaje'] = 'Ya está en el ERP, con asiento.';
                continue;
            }
            if (($fila['estado'] ?? '') === 'nuevo') {
                $fila['estado'] = 'enganchar';
                $fila['mensaje'] = 'Ya está en el ERP y no tiene asiento. Se lo crea y engancha.';
                continue;
            }
            $previo = trim((string) ($fila['mensaje'] ?? ''));
            $fila['mensaje'] = trim('Ya está en el ERP sin asiento. '.$previo);
        }
        unset($fila);

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{venta_id: int, asiento_id: int, numeroasiento: string}
     */
    public function grabar(array $fila, int $usuarioId, string $leyenda): array
    {
        if (($fila['estado'] ?? '') !== 'nuevo') {
            throw new RuntimeException('El comprobante no está pendiente de grabar.');
        }

        $lineas = is_array($fila['lineas_asiento'] ?? null) ? $fila['lineas_asiento'] : [];
        if ($lineas === []) {
            throw new RuntimeException('Sin líneas de asiento.');
        }
        $desbalance = TransferenciaCrownComprobante::desbalance($lineas);
        if (abs($desbalance) > 0.05) {
            throw new RuntimeException('El asiento no balancea ('.$desbalance.').');
        }

        $tipo = $this->tipo((string) $fila['abreviatura_erp']);
        $puntoventa = $this->punto((int) $fila['sucursal']);
        $empresaId = (int) $puntoventa->empresa_id;
        $fecha = (string) $fila['fecha'];
        if (PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha, PeriodoContableCierreSupport::ALCANCE_FACTURACION)
            || PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha, PeriodoContableCierreSupport::ALCANCE_CONTABLE)) {
            throw new RuntimeException('La fecha está dentro de un período cerrado.');
        }

        $cliente = $this->consumidorFinal();
        $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
        $total = round(abs((float) $fila['total']), 2);
        if ($total <= 0) {
            $total = 0.01;
        }
        $monedaId = $this->monedaId((string) ($fila['moneda'] ?? '1'));
        $cotizacion = $this->cotizacion($monedaId, (float) ($fila['cotizacion'] ?? 0), $fecha);
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = (string) $cliente->nombre;
        }
        $codigo = TransferenciaCrownComprobante::codigoVisible($fila);
        $cuit = trim((string) ($fila['cuit'] ?? ''));
        if ($cuit !== '') {
            $leyenda .= ' CUIT '.$cuit;
        }
        $neto = TransferenciaCrownComprobante::neto($fila);
        if ($neto <= 0) {
            $neto = $total;
        }

        return DB::transaction(function () use (
            $fila, $usuarioId, $leyenda, $lineas, $tipo, $puntoventa, $empresaId,
            $fecha, $cliente, $signo, $total, $monedaId, $cotizacion, $nombre, $codigo, $neto
        ): array {
            $venta = Venta::query()->create([
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_id' => (int) $tipo->id,
                'puntoventa_id' => (int) $puntoventa->id,
                'numerocomprobante' => (int) $fila['numero'],
                'codigo_afip' => (int) ($fila['codigo_afip'] ?? 0) ?: null,
                'cliente_id' => (int) $cliente->id,
                'total' => $total * $signo,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'estado' => ' ',
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'leyenda' => mb_substr($leyenda, 0, 240),
                'codigo' => $codigo,
                'nombre' => mb_substr($nombre, 0, 120),
                'domicilio' => trim((string) ($fila['domicilio'] ?? '')) !== '' ? (string) $fila['domicilio'] : ' ',
                'pais_id' => (int) ($cliente->pais_id ?? 0) > 0 ? (int) $cliente->pais_id : 1,
                'condicioniva_id' => $cliente->condicioniva_id,
                'cae' => trim((string) ($fila['cae'] ?? '')) !== '' ? mb_substr(trim((string) $fila['cae']), 0, 50) : null,
                'numeroremito' => 0,
                'cantidadbulto' => 1,
            ]);

            Venta_Emision::query()->create([
                'venta_id' => (int) $venta->id,
                'numeroitem' => 1,
                'detalle' => $codigo,
                'cantidad' => 1,
                'precio' => $neto,
                'impuesto_id' => $this->impuestoId($fila),
                'incluyeimpuesto' => 'N',
                'moneda_id' => $monedaId,
            ]);

            $this->grabarImpuestos((int) $venta->id, $fila);
            $asiento = $this->crearAsiento($venta, $fila, $usuarioId, $leyenda, $lineas, $empresaId, $fecha, $monedaId, $cotizacion, $codigo);

            return [
                'venta_id' => (int) $venta->id,
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) $asiento->numeroasiento,
            ];
        });
    }

    /**
     * La factura ya está. Solo crea el asiento y lo deja enganchado.
     *
     * @param  array<string, mixed>  $fila
     * @return array{venta_id: int, asiento_id: int, numeroasiento: string}
     */
    public function enganchar(array $fila, int $usuarioId, string $leyenda): array
    {
        if (($fila['estado'] ?? '') !== 'enganchar') {
            throw new RuntimeException('El comprobante no está pendiente de enganchar el asiento.');
        }

        $lineas = is_array($fila['lineas_asiento'] ?? null) ? $fila['lineas_asiento'] : [];
        if ($lineas === []) {
            throw new RuntimeException('Sin líneas de asiento.');
        }
        $desbalance = TransferenciaCrownComprobante::desbalance($lineas);
        if (abs($desbalance) > 0.05) {
            throw new RuntimeException('El asiento no balancea ('.$desbalance.').');
        }

        $ventaId = (int) ($fila['venta_id'] ?? 0);
        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            throw new RuntimeException('No está la factura para engancharle el asiento.');
        }
        $puntoventa = $this->punto((int) $fila['sucursal']);
        $empresaId = (int) $puntoventa->empresa_id;
        $fecha = (string) ($venta->fecha ?? $fila['fecha']);
        if (PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha, PeriodoContableCierreSupport::ALCANCE_FACTURACION)
            || PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha, PeriodoContableCierreSupport::ALCANCE_CONTABLE)) {
            throw new RuntimeException('La fecha está dentro de un período cerrado.');
        }

        $monedaId = (int) ($venta->moneda_id ?? 0);
        if ($monedaId <= 0) {
            $monedaId = $this->monedaId((string) ($fila['moneda'] ?? '1'));
        }
        $cotizacion = $this->cotizacion($monedaId, (float) ($venta->cotizacion ?? $fila['cotizacion'] ?? 0), $fecha);
        $codigo = TransferenciaCrownComprobante::codigoVisible($fila);

        return DB::transaction(function () use (
            $fila, $usuarioId, $leyenda, $lineas, $venta, $empresaId, $fecha, $monedaId, $cotizacion, $codigo
        ): array {
            $venta = Venta::query()->whereKey((int) $venta->id)->lockForUpdate()->first();
            if ($venta === null) {
                throw new RuntimeException('No está la factura para engancharle el asiento.');
            }
            if (Asiento::query()->where('venta_id', (int) $venta->id)->exists()) {
                throw new RuntimeException('La factura ya tiene asiento.');
            }
            $asiento = $this->crearAsiento($venta, $fila, $usuarioId, $leyenda, $lineas, $empresaId, $fecha, $monedaId, $cotizacion, $codigo);

            return [
                'venta_id' => (int) $venta->id,
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) $asiento->numeroasiento,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<array<string, mixed>>  $lineas
     */
    private function crearAsiento(
        Venta $venta,
        array $fila,
        int $usuarioId,
        string $leyenda,
        array $lineas,
        int $empresaId,
        string $fecha,
        int $monedaId,
        float $cotizacion,
        string $codigo,
    ): Asiento {
        // Numeración solo en ERP (no reserva ni escribe en Anita).
        $numeroAsiento = $this->reservarNumeroErp($empresaId);
        $tipoAnita = strtoupper((string) ($fila['tipo_anita'] ?? $fila['tipo'] ?? ''));
        $asiento = Asiento::query()->create([
            'empresa_id' => $empresaId,
            'tipoasiento_id' => $this->tipoAsientoVentas(),
            'numeroasiento' => $numeroAsiento,
            'fecha' => $fecha,
            'venta_id' => (int) $venta->id,
            'observacion' => mb_substr($leyenda, 0, 240),
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'estado_aprobacion' => Asiento::ESTADO_APROBACION_CONFIRMADO,
            // Metadatos de origen en ERP; no implican escritura al bridge.
            'anita_origen' => 'transferencia',
            'anita_sistema' => 'V',
            'anita_tipo' => $tipoAnita,
            'anita_letra' => strtoupper((string) $fila['letra']),
            'anita_sucursal' => (int) $fila['sucursal'],
            'anita_nro' => (int) $fila['numero'],
            'anita_nro_asiento' => null,
        ]);

        $sucursal = (int) ($fila['sucursal'] ?? 0);
        foreach ($lineas as $linea) {
            $importe = round((float) $linea['importe'], 2);
            $monto = ($linea['dh'] ?? 'D') === 'H' ? -abs($importe) : abs($importe);
            $cuentaId = (int) $linea['cuentacontable_id'];
            Asiento_Movimiento::query()->create([
                'asiento_id' => (int) $asiento->id,
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => TransferenciaCrownImputacionSupport::centrocostoIdParaCuenta(
                    $empresaId,
                    $sucursal,
                    $cuentaId,
                ),
                'monto' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'observacion' => mb_substr((string) ($linea['descripcion'] ?? $codigo), 0, 240),
            ]);
        }

        return $asiento;
    }

    private function tipo(string $abreviatura): Tipotransaccion
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if (! isset($this->tipos[$abreviatura])) {
            $tipo = Tipotransaccion::query()
                ->where('abreviatura', $abreviatura)
                ->where('estado', 'A')
                ->orderBy('id')
                ->first();
            if ($tipo === null) {
                throw new RuntimeException('No existe el tipo de comprobante '.$abreviatura.'.');
            }
            $this->tipos[$abreviatura] = $tipo;
        }

        return $this->tipos[$abreviatura];
    }

    private function punto(int $sucursal): Puntoventa
    {
        $codigo = str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT);
        if (! isset($this->puntos[$codigo])) {
            $pv = Puntoventa::query()->where('codigo', $codigo)->orderBy('id')->first();
            if ($pv === null) {
                throw new RuntimeException('No existe el punto de venta '.$sucursal.'.');
            }
            $this->puntos[$codigo] = $pv;
        }

        return $this->puntos[$codigo];
    }

    private function consumidorFinal(): Cliente
    {
        if ($this->consumidor === null) {
            $this->consumidor = Cliente::query()
                ->where('nombre', 'CONSUMIDOR FINAL')
                ->orderBy('id')
                ->first();
        }
        if ($this->consumidor === null) {
            throw new RuntimeException('No está cargado el cliente CONSUMIDOR FINAL.');
        }

        return $this->consumidor;
    }

    private function tipoAsientoVentas(): int
    {
        if ($this->tipoAsientoVentasId === null) {
            $id = (int) (Tipoasiento::query()->where('nombre', 'Ventas')->value('id') ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('No está el tipo de asiento Ventas.');
            }
            $this->tipoAsientoVentasId = $id;
        }

        return $this->tipoAsientoVentasId;
    }

    private function monedaId(string $codigo): int
    {
        $id = (int) $codigo;
        if ($id > 0 && Moneda::query()->whereKey($id)->exists()) {
            return $id;
        }

        return (int) (Moneda::query()->orderBy('id')->value('id') ?? 1);
    }

    private function cotizacion(int $monedaId, float $delDocumento, string $fecha): float
    {
        if ($monedaId <= 1) {
            return 1.0;
        }
        if ($delDocumento > 1) {
            return $delDocumento;
        }

        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($vigente > 1) {
            return $vigente;
        }

        throw new RuntimeException('No hay cotización vigente para la moneda extranjera.');
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function impuestoId(array $fila): ?int
    {
        if ((float) ($fila['gravado27'] ?? 0) > 0 && (float) ($fila['gravado21'] ?? 0) <= 0) {
            return $this->impuestoPorValor(27);
        }
        if ((float) ($fila['gravado105'] ?? 0) > 0 && (float) ($fila['gravado21'] ?? 0) <= 0) {
            return $this->impuestoPorValor(10.5);
        }
        if (TransferenciaCrownComprobante::neto($fila) > 0 && TransferenciaCrownComprobante::iva($fila) <= 0) {
            return $this->impuestoPorValor(0);
        }

        return $this->impuestoPorValor(21);
    }

    private function impuestoPorValor(float $valor): ?int
    {
        $key = (string) $valor;
        if (! array_key_exists($key, $this->impuestos)) {
            $id = (int) (Impuesto::query()
                ->where('valor', $valor)
                ->orderBy('id')
                ->value('id') ?? 0);
            $this->impuestos[$key] = $id;
        }

        return $this->impuestos[$key] > 0 ? $this->impuestos[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function grabarImpuestos(int $ventaId, array $fila): void
    {
        foreach (TransferenciaCrownImpuestoConceptos::filas($fila) as $concepto) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => $concepto['concepto'],
                'baseimponible' => $concepto['base'],
                'tasa' => $concepto['tasa'],
                'importe' => $concepto['importe'],
                'impuesto_id' => $concepto['tasa'] > 0 ? $this->impuestoPorValor($concepto['tasa']) : $this->impuestoPorValor(0),
            ]);
        }
    }

    private function reservarNumeroErp(int $empresaId): string
    {
        $ultimo = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('numeroasiento');

        return (string) ((int) $ultimo + 1);
    }
}
