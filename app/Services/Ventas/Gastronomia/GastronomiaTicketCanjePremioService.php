<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\TicketcanjeGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Stock\PrecioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canje de premios Wigos en el POS gastronomía (ticketcanje_gastronomia).
 * Decodificación alineada a COMAND_pide_canje en comandera_view.fc.
 */
final class GastronomiaTicketCanjePremioService
{
    public function __construct(
        private readonly WigosCanjePremioService $wigosService,
        private readonly GastronomiaCuentaService $cuentaService,
    ) {
    }

    /**
     * @return array{
     *   numerocupon:string,
     *   items:list<array<string,mixed>>,
     *   resumen:array<string,mixed>,
     *   descuento:array{id:int,codigo:string,nombre:string},
     *   cliente:array{id:int,codigo:string,nombre:string}
     * }
     */
    public function validarParaAplicar(string $numerocupon, int $empresaId, int $listaprecioId): array
    {
        $cupon = $this->normalizarNumerocupon($numerocupon);
        $this->assertNoCanjeadoEnErp($cupon);

        $filasWigos = $this->wigosService->consultarPorCodigoBarras($cupon);
        $items = $this->decodificarFilasWigos($filasWigos, $cupon, $empresaId, $listaprecioId);

        if ($items === []) {
            throw new InvalidArgumentException('El ticket no contiene artículos válidos para canje.');
        }

        $descuento = $this->resolverDescuentoConfigurado();
        $cliente = $this->resolverClienteConfigurado();

        $primer = $items[0];
        $puntosTotal = 0.;
        $cantidadTotal = 0.;
        foreach ($items as $item) {
            $puntosTotal += (float) $item['puntos'] * (float) $item['cantidad'];
            $cantidadTotal += (float) $item['cantidad'];
        }

        return [
            'numerocupon' => $cupon,
            'items' => $items,
            'resumen' => [
                'premio_sku' => $primer['sku'],
                'premio_descripcion' => $primer['descripcion'],
                'puntos_unidad' => (int) $primer['puntos'],
                'cantidad' => round($cantidadTotal, 4),
                'puntos_total' => round($puntosTotal, 2),
                'fecha_canje' => $primer['fecha'],
                'fecha_canje_hora' => $primer['hora'],
                'cliente_wigos' => (int) $primer['nro_cliente'],
                'apellido' => $primer['apellido'],
                'nombre' => $primer['nombre'],
                'numerodocumento' => $primer['documento'],
            ],
            'descuento' => [
                'id' => (int) $descuento->id,
                'codigo' => (string) $descuento->codigo,
                'nombre' => (string) $descuento->nombre,
            ],
            'cliente' => [
                'id' => (int) $cliente->id,
                'codigo' => (string) $cliente->codigo,
                'nombre' => (string) $cliente->nombre,
            ],
        ];
    }

    /**
     * Agrega ítems a la cuenta, aplica descuento/cliente y guarda datos pendientes para persistir al emitir.
     *
     * @return array{cuenta:CuentaGastronomia,validacion:array<string,mixed>}
     */
    public function aplicarACuenta(CuentaGastronomia $cuenta, string $numerocupon, int $listaprecioId): array
    {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if ($cuenta->canje_premio_pendiente) {
            throw new InvalidArgumentException('La cuenta ya tiene un canje de premio pendiente de facturar.');
        }

        if ($cuenta->canje_fidelidad_pendiente) {
            throw new InvalidArgumentException(
                'La cuenta ya tiene un canje de fidelidad pendiente. Factúrelo antes de otro canje.'
            );
        }

        $validacion = $this->validarParaAplicar($numerocupon, (int) $cuenta->empresa_id, $listaprecioId);
        $descuento = $this->resolverDescuentoConfigurado();
        $cliente = $this->resolverClienteConfigurado();

        $clienteInternoId = (int) ($descuento->cliente_id ?? 0);
        if ($clienteInternoId <= 0) {
            $clienteInternoId = (int) $cliente->id;
        }

        DB::transaction(function () use ($cuenta, $validacion, $descuento, $cliente, $clienteInternoId) {
            foreach ($validacion['items'] as $item) {
                $this->cuentaService->agregarLinea(
                    $cuenta->fresh(['lineas']),
                    (int) $item['articulo_id'],
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                );
            }

            $this->cuentaService->actualizarCabecera($cuenta->fresh(), [
                'descuento_gastronomia_id' => $descuento->id,
                'cliente_id' => $cliente->id,
                'cliente_interno_descuento_id' => $clienteInternoId,
            ]);

            $cuenta->fresh()->update([
                'canje_premio_pendiente' => $validacion,
            ]);
        });

        return [
            'validacion' => $validacion,
            'cuenta' => $this->cuentaService->cuentaConLineas($cuenta->id),
        ];
    }

    /**
     * Persiste canjes tras emisión. Debe ejecutarse dentro de la transacción de emisión.
     */
    public function registrarTrasEmision(Venta $venta, CuentaGastronomia $cuenta): void
    {
        $pendiente = $cuenta->canje_premio_pendiente;
        if (! is_array($pendiente) || ($pendiente['numerocupon'] ?? '') === '') {
            return;
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId <= 0) {
            throw new RuntimeException('Usuario no autenticado al registrar canje de premio.');
        }

        $cupon = $this->normalizarNumerocupon((string) $pendiente['numerocupon']);
        $this->assertNoCanjeadoEnErp($cupon);

        $items = array_values((array) ($pendiente['items'] ?? []));
        if ($items === []) {
            throw new InvalidArgumentException('Datos incompletos del canje de premio pendiente.');
        }

        $mozoId = (int) ($cuenta->mozo_gastronomia_id ?? 0);
        if ($mozoId <= 0) {
            throw new InvalidArgumentException('La cuenta no tiene mozo asignado para registrar el canje.');
        }

        $ticketId = ((int) TicketcanjeGastronomia::query()->max('ticket_id')) + 1;
        $fechacanje = Carbon::now();
        $clienteWigos = (int) ($pendiente['resumen']['cliente_wigos'] ?? 0);
        $apellido = trim((string) ($pendiente['resumen']['apellido'] ?? ''));
        $nombre = trim((string) ($pendiente['resumen']['nombre'] ?? ''));
        $documento = trim((string) ($pendiente['resumen']['numerodocumento'] ?? ''));

        $venta->loadMissing(['venta_emisiones']);

        foreach ($items as $idx => $item) {
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                throw new InvalidArgumentException('Artículo inválido en canje de premio.');
            }

            $renglon = $idx + 1;
            $fechaItem = $this->parsearFechaItem((string) ($item['fecha'] ?? ''), (string) ($item['hora'] ?? ''));

            TicketcanjeGastronomia::query()->create([
                'numerocupon' => $cupon,
                'ticket_id' => $ticketId,
                'articulo_id' => $articuloId,
                'puntos' => (int) ($item['puntos'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 0),
                'fecha' => $fechaItem,
                'cliente_id' => $clienteWigos,
                'apellido' => $apellido,
                'nombre' => $nombre,
                'numerodocumento' => $documento,
                'mozo_id' => $mozoId,
                'fechacanje' => $fechacanje,
                'usuariocanje_id' => $usuarioId,
                'renglon' => $renglon,
                'venta_id' => $venta->id,
                'costo' => round((float) ($item['costo'] ?? 0.), 2),
                'precioventa' => round((float) ($item['precio_unitario'] ?? 0.), 2),
            ]);
        }

        $cuenta->update(['canje_premio_pendiente' => null]);
    }

    /**
     * @return list<TicketcanjeGastronomia>
     */
    public function listarPorVenta(int $ventaId): array
    {
        return TicketcanjeGastronomia::query()
            ->with(['articulo', 'mozo', 'usuarioCanje'])
            ->where('venta_id', $ventaId)
            ->orderBy('renglon')
            ->get()
            ->all();
    }

    /**
     * @return list<TicketcanjeGastronomia>
     */
    public function listarPorAlcanceTurno(
        int $empresaId,
        string $fechaJornada,
        ?string $identificadorPc = null,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
    ): array {
        // La empresa cuelga del punto de venta de la venta (venta.puntoventa_id → puntoventa.empresa_id).
        $ventaIds = VentaGastronomiaEmision::query()
            ->when($identificadorPc !== null && $identificadorPc !== '', function ($q) use ($identificadorPc) {
                $q->where('identificador_pc', $identificadorPc);
            })
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', function ($qq) use ($empresaId) {
                        $qq->where('empresa_id', $empresaId);
                    });
            })
            ->pluck('venta_id');

        if ($ventaIds->isEmpty()) {
            return [];
        }

        $query = TicketcanjeGastronomia::query()
            ->with(['articulo', 'mozo', 'venta', 'usuarioCanje'])
            ->whereIn('venta_id', $ventaIds);

        if ($desde) {
            $query->where('fechacanje', '>=', $desde);
        }
        if ($hasta) {
            $query->where('fechacanje', '<=', $hasta);
        }

        return $query->orderByDesc('fechacanje')->orderBy('renglon')->get()->all();
    }

    private function normalizarNumerocupon(string $numerocupon): string
    {
        $codigo = trim($numerocupon);
        if ($codigo !== '' && $codigo[0] === '*') {
            $codigo = trim(substr($codigo, 1));
        }
        if ($codigo === '') {
            throw new InvalidArgumentException('Número de cupón inválido.');
        }

        return $codigo;
    }

    private function assertNoCanjeadoEnErp(string $numerocupon): void
    {
        if (TicketcanjeGastronomia::query()->where('numerocupon', $numerocupon)->exists()) {
            throw new InvalidArgumentException('Ticket ya canjeado.');
        }
    }

  /**
   * @param  list<object>  $filasWigos
   * @return list<array<string,mixed>>
   */
    private function decodificarFilasWigos(array $filasWigos, string $numerocupon, int $empresaId, int $listaprecioId): array
    {
        $items = [];
        $diasVencimiento = max(1, (int) config('gastronomia.canje_premio_vencimiento_dias', 2));

        foreach ($filasWigos as $fila) {
            $giftId = trim(str_replace(["\r", "\n"], '', (string) ($fila->GIFT_ID ?? '')));
            if ($giftId === '' || strtoupper($giftId[0]) !== 'V') {
                continue;
            }

            $articulo = $this->resolverArticuloPorGiftId($giftId, $empresaId);

            if (! $articulo) {
                throw new InvalidArgumentException(
                    'El artículo que quiere canjear no existe (SKU Wigos: '.$giftId.').'
                );
            }

            $sku = trim((string) $articulo->sku);

            [$fecha, $hora] = $this->parsearFechaWigos((string) ($fila->REQUESTED ?? ''));
            $fechaCarbon = Carbon::parse($fecha.' '.$hora);
            $diffDias = abs(Carbon::today()->startOfDay()->diffInDays($fechaCarbon->copy()->startOfDay(), false));
            if ($diffDias > $diasVencimiento) {
                throw new InvalidArgumentException(
                    'Ticket vencido (fecha del ticket: '.$fechaCarbon->format('d/m/Y')
                    .', fecha del día: '.Carbon::today()->format('d/m/Y').').'
                );
            }

            $precios = PrecioService::asignaPrecioPorLista(
                (int) $articulo->id,
                $listaprecioId,
                Carbon::today()->format('Y-m-d'),
            );
            $precio = 0.;
            if ($precios !== []) {
                $p = end($precios);
                $precio = (float) ($p['precio'] ?? 0.);
            }
            $costo = round((float) ($articulo->costo ?? 0.), 2);

            $items[] = [
                'sku' => $sku,
                'articulo_id' => (int) $articulo->id,
                'descripcion' => trim((string) ($fila->GIFT_NAME ?? $articulo->descripcion ?? '')),
                'puntos' => (int) ($fila->SPENT_POINTS ?? 0),
                'cantidad' => round((float) ($fila->QUANTITY ?? 0.), 4),
                'nro_cupon' => $numerocupon,
                'fecha' => $fecha,
                'hora' => $hora,
                'nro_cliente' => (int) ($fila->ACCOUNT ?? 0),
                'apellido' => trim((string) ($fila->CUSTOMER ?? '')),
                'nombre' => trim((string) ($fila->nombre ?? '')),
                'documento' => trim((string) ($fila->DOCUMENT_NUMBER ?? '')),
                'precio_unitario' => round((float) $precio, 2),
                'costo' => $costo,
            ];
        }

        return $items;
    }

  /**
   * Busca artículo por GIFT_ID de Wigos (ERP: sku corto ej. V0277; legacy: 13 chars con dígito en pos. 12).
   * Catálogo compartido: empresa_id null/0 aplica a cualquier PV (igual que queryArticulosCatalogo).
   */
    private function resolverArticuloPorGiftId(string $giftId, int $empresaId): ?Articulo
    {
        $giftId = trim(str_replace(["\r", "\n"], '', $giftId));
        if ($giftId === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            $giftId,
            $this->normalizarSkuCanje($giftId),
        ], static fn (string $s) => $s !== '')));

        foreach ($candidatos as $skuBusqueda) {
            $skuUpper = mb_strtoupper(trim($skuBusqueda), 'UTF-8');
            $articulo = Articulo::query()
                ->where(function ($q) use ($empresaId) {
                    $q->whereNull('empresa_id')
                        ->orWhere('empresa_id', 0)
                        ->orWhere('empresa_id', $empresaId);
                })
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuUpper])
                ->first();

            if ($articulo) {
                return $articulo;
            }
        }

        return null;
    }

    /**
     * Formato legacy COMAND_pide_canje (Informix): 13 caracteres, dígito verificador en posición 12.
     */
    private function normalizarSkuCanje(string $sku): string
    {
        $xstr = substr(trim($sku), 0, 13);
        $xstr = str_pad($xstr, 13, ' ', STR_PAD_RIGHT);
        $xstr[12] = '0';

        return rtrim($xstr);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parsearFechaWigos(string $requested): array
    {
        $requested = trim($requested);
        if ($requested === '') {
            $hoy = Carbon::now();

            return [$hoy->format('Y-m-d'), $hoy->format('H:i:s')];
        }

        try {
            $dt = Carbon::parse($requested);

            return [$dt->format('Y-m-d'), $dt->format('H:i:s')];
        } catch (\Throwable) {
            if (strlen($requested) >= 8 && ctype_digit(substr($requested, 0, 8))) {
                $anio = substr($requested, 0, 4);
                $mes = substr($requested, 4, 2);
                $dia = substr($requested, 6, 2);
                $hora = strlen($requested) > 8 ? trim(substr($requested, 8)) : '00:00:00';

                return [sprintf('%04d-%02d-%02d', (int) $anio, (int) $mes, (int) $dia), $hora];
            }

            $hoy = Carbon::now();

            return [$hoy->format('Y-m-d'), $hoy->format('H:i:s')];
        }
    }

    private function parsearFechaItem(string $fecha, string $hora): Carbon
    {
        $hora = trim($hora) !== '' ? trim($hora) : '00:00:00';

        return Carbon::parse(trim($fecha).' '.$hora);
    }

    private function resolverDescuentoConfigurado(): DescuentoGastronomia
    {
        $codigo = trim((string) config('gastronomia.canje_premio_descuento_codigo', '10'));
        $descuento = DescuentoGastronomia::query()->where('codigo', $codigo)->first();
        if (! $descuento) {
            throw new InvalidArgumentException(
                'No existe el descuento gastronomía configurado para canje de premios (código '.$codigo.').'
            );
        }

        return $descuento;
    }

    private function resolverClienteConfigurado(): Cliente
    {
        $codigo = trim((string) config('gastronomia.canje_premio_cliente_codigo', '500'));
        $cliente = Cliente::query()->where('codigo', $codigo)->first();
        if (! $cliente) {
            throw new InvalidArgumentException(
                'No existe el cliente configurado para canje de premios (código '.$codigo.').'
            );
        }

        return $cliente;
    }
}
