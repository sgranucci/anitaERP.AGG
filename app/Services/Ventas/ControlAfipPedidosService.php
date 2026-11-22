<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Venta;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use App\Support\Ventas\ControlAfipPedidosAnitaBridgeReader;
use App\Support\Ventas\ControlAfipPedidosMailSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Arma el control AFIP de clientes con pedidos (merge ERP + Anita) y valida padrón ARCA.
 * Reemplazo de la lógica batch de l-pedrem -b / controla_contribuyente (sin leer contribuyente Anita).
 */
class ControlAfipPedidosService
{
    public function __construct(
        private readonly ControlAfipPedidosAnitaBridgeReader $anitaReader,
        private readonly ConstanciaInscripcionService $constanciaInscripcion,
    ) {
    }

    /**
     * @return array{
     *     fecha_control: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     filas: list<array<string, mixed>>,
     *     candidatos: int,
     *     problemas: int,
     *     omitidos_regularizados: int,
     *     omitidos_sin_fac: int,
     *     omitidos_condicion: int,
     *     anita_ok: bool,
     *     anita_error: ?string,
     *     arca_errores_servicio: int
     * }
     */
    public function generar(?string $fechaControl = null): array
    {
        $hasta = $fechaControl !== null && trim($fechaControl) !== ''
            ? Carbon::parse($fechaControl)->startOfDay()
            : Carbon::today();
        $desde = $hasta->copy()->subDays(ControlAfipPedidosMailSupport::DIAS_VENTANA);

        $filtros = [
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
        ];

        $codigosErp = $this->codigosClienteDesdeErp($filtros);
        $anitaOk = true;
        $anitaError = null;
        $codigosAnita = [];
        try {
            $codigosAnita = $this->anitaReader->listarCodigosCliente($filtros);
        } catch (Throwable $e) {
            $anitaOk = false;
            $anitaError = $e->getMessage();
            Log::warning('control_afip_pedidos: lectura Anita falló', ['error' => $anitaError]);
        }

        $codigos = array_values(array_unique(array_merge($codigosErp, $codigosAnita)));
        sort($codigos, SORT_STRING);

        $variantesCodigo = [];
        foreach ($codigos as $codigo) {
            $variantesCodigo[] = $codigo;
            $variantesCodigo[] = str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }
        $clientes = Cliente::query()
            ->with(['vendedores:id,codigo,nombre,email', 'condicionivas:id,nombre'])
            ->whereIn('codigo', array_values(array_unique($variantesCodigo)))
            ->get()
            ->keyBy(static fn (Cliente $c) => ltrim((string) $c->codigo, '0'));

        $ultimasFac = $this->ultimasFechasFacPorCliente(
            $clientes->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );

        $filas = [];
        $omitidosRegularizados = 0;
        $omitidosSinFac = 0;
        $omitidosCondicion = 0;
        $arcaErroresServicio = 0;

        foreach ($codigos as $codigo) {
            /** @var Cliente|null $cliente */
            $cliente = $clientes->get($codigo);
            if ($cliente === null) {
                continue;
            }

            $enErp = in_array($codigo, $codigosErp, true);
            $enAnita = in_array($codigo, $codigosAnita, true);
            $fuente = match (true) {
                $enErp && $enAnita => 'ERP+Anita',
                $enErp => 'ERP',
                default => 'Anita',
            };

            if ((string) ($cliente->estado ?? '') === Cliente::ESTADO_REGULARIZADO) {
                $omitidosRegularizados++;

                continue;
            }

            $condicionId = (int) ($cliente->condicioniva_id ?? 0);
            if (! ArcaPadronImpuestosClienteValidacion::aplicaParaCondicionIva($condicionId > 0 ? $condicionId : null)) {
                $omitidosCondicion++;

                continue;
            }

            $ultimaFac = $ultimasFac[(int) $cliente->id] ?? null;
            if ($ultimaFac === null || $ultimaFac < $desde->toDateString()) {
                $omitidosSinFac++;

                continue;
            }

            $cuit = preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? '')) ?? '';
            if (strlen($cuit) !== 11) {
                $filas[] = $this->filaProblema(
                    $cliente,
                    $fuente,
                    $ultimaFac,
                    'CUIT inválida (11 dígitos) para consultar padrón ARCA.',
                    false,
                );

                continue;
            }

            try {
                $padron = $this->constanciaInscripcion->getPersonaV2($cuit);
                $validacion = ArcaPadronImpuestosClienteValidacion::validar(
                    $condicionId > 0 ? $condicionId : null,
                    $padron
                );
            } catch (Throwable $e) {
                $arcaErroresServicio++;
                Log::warning('control_afip_pedidos: padrón ARCA no disponible', [
                    'cliente_id' => $cliente->id,
                    'cuit' => $cuit,
                    'error' => $e->getMessage(),
                ]);
                // Igual que operación venta: servicio caído no marca problema fiscal.
                continue;
            }

            if (! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? true)) {
                continue;
            }

            $filas[] = $this->filaProblema(
                $cliente,
                $fuente,
                $ultimaFac,
                (string) ($validacion['mensaje'] ?? 'Problemas en ARCA.'),
                (bool) ($validacion['debe_suspender'] ?? false),
                $validacion['detalles'] ?? [],
            );
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['vendedor_nombre'] ?? ''), (string) ($b['vendedor_nombre'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['codigo'] ?? ''), (string) ($b['codigo'] ?? ''));
        });

        return [
            'fecha_control' => $hasta->toDateString(),
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'filas' => $filas,
            'candidatos' => count($codigos),
            'problemas' => count($filas),
            'omitidos_regularizados' => $omitidosRegularizados,
            'omitidos_sin_fac' => $omitidosSinFac,
            'omitidos_condicion' => $omitidosCondicion,
            'anita_ok' => $anitaOk,
            'anita_error' => $anitaError,
            'arca_errores_servicio' => $arcaErroresServicio,
        ];
    }

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string}  $filtros
     * @return list<string>
     */
    private function codigosClienteDesdeErp(array $filtros): array
    {
        $rows = DB::table('pedido')
            ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
            ->whereBetween('pedido.fechaentrega', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->whereNotNull('cliente.codigo')
            ->distinct()
            ->pluck('cliente.codigo');

        $out = [];
        foreach ($rows as $codigo) {
            $codigo = ltrim(trim((string) $codigo), '0');
            if ($codigo !== '') {
                $out[$codigo] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  list<int>  $clienteIds
     * @return array<int, string> cliente_id => Y-m-d
     */
    private function ultimasFechasFacPorCliente(array $clienteIds): array
    {
        if ($clienteIds === []) {
            return [];
        }

        $rows = Venta::query()
            ->selectRaw('venta.cliente_id, MAX(venta.fecha) as ultima_fecha')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->whereIn('venta.cliente_id', $clienteIds)
            ->whereIn('tipotransaccion.abreviatura', ['FAC', 'FCE', 'FAF'])
            ->groupBy('venta.cliente_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->cliente_id;
            $fecha = $row->ultima_fecha;
            if ($fecha instanceof Carbon) {
                $map[$id] = $fecha->toDateString();
            } else {
                $map[$id] = Carbon::parse((string) $fecha)->toDateString();
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $detalles
     * @return array<string, mixed>
     */
    private function filaProblema(
        Cliente $cliente,
        string $fuente,
        string $ultimaFac,
        string $motivo,
        bool $debeSuspender,
        array $detalles = [],
    ): array {
        $vendedor = $cliente->vendedores;

        return [
            'cliente_id' => (int) $cliente->id,
            'codigo' => (string) $cliente->codigo,
            'nombre' => (string) $cliente->nombre,
            'cuit' => (string) ($cliente->numerodocumento ?? ''),
            'condicioniva' => (string) (optional($cliente->condicionivas)->nombre ?? ''),
            'estado' => (string) ($cliente->estado ?? ''),
            'vendedor_id' => $vendedor ? (int) $vendedor->id : null,
            'vendedor_codigo' => $vendedor ? (string) $vendedor->codigo : '',
            'vendedor_nombre' => $vendedor ? (string) $vendedor->nombre : '',
            'vendedor_email' => $vendedor ? trim((string) ($vendedor->email ?? '')) : '',
            'ultima_fac' => $ultimaFac,
            'fuente' => $fuente,
            'motivo' => $motivo,
            'debe_suspender' => $debeSuspender,
            'detalles' => $detalles,
        ];
    }
}
