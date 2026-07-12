<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Ventas\CategoriafidelidadEntregaGastronomia;
use App\Models\Ventas\CategoriafidelidadGastronomia;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Repositories\Ventas\CategoriafidelidadEntregaGastronomiaRepositoryInterface;
use App\Repositories\Ventas\CategoriafidelidadGastronomiaRepositoryInterface;
use App\Services\Stock\PrecioService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaDescuentoClienteInternoSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Wigos\WigosTrackdataNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canje diario de premios por categoría de fidelidad (tarjeta Wigos + categoriafidelidad_*).
 */
final class GastronomiaCategoriafidelidadCanjeService
{
    public function __construct(
        private readonly WigosAccountInfoService $wigosAccountInfo,
        private readonly CategoriafidelidadGastronomiaRepositoryInterface $categoriaRepository,
        private readonly CategoriafidelidadEntregaGastronomiaRepositoryInterface $entregaRepository,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function validarTarjeta(string $trackdata, int $empresaId, int $listaprecioId, ?int $articuloId = null): array
    {
        $cuentaWigos = $this->wigosAccountInfo->consultarPorTrackdata($trackdata, $empresaId);
        $documento = $cuentaWigos['documento'];

        if ($this->entregaRepository->existeCanjeHoyPorDocumento($documento)) {
            throw new InvalidArgumentException(
                'El titular (DNI '.$documento.') ya canjeó un premio de fidelidad hoy.'
            );
        }

        $categoria = $this->resolverCategoriaPorLevelCode($cuentaWigos['level_code']);
        $articulos = $this->resolverArticulosCanje($categoria, $empresaId, $listaprecioId);

        if ($articulos === []) {
            throw new InvalidArgumentException(
                'No hay artículos configurados para canjear en la categoría «'
                .$categoria->nombre.'» (código '.$categoria->codigo.').'
            );
        }

        $articuloSeleccionadoId = null;
        if ($articuloId !== null && $articuloId > 0) {
            $articuloSeleccionadoId = (int) $this->resolverArticuloSeleccionado($articulos, $articuloId)['articulo_id'];
        } elseif (count($articulos) === 1) {
            $articuloSeleccionadoId = (int) $articulos[0]['articulo_id'];
        }

        $descuento = $this->resolverDescuentoConfigurado();
        $cliente = $this->resolverClienteInternoPorLevelCode((int) $cuentaWigos['level_code'], $descuento);

        return [
            'trackdata' => $this->normalizarTrackdata($trackdata),
            'tarjeta' => [
                'account_number' => $cuentaWigos['account_number'],
                'documento' => $documento,
                'apellido' => $cuentaWigos['apellido'],
                'nombre' => $cuentaWigos['nombre'],
                'email' => $cuentaWigos['email'],
                'level' => $cuentaWigos['level'],
                'level_code' => $cuentaWigos['level_code'],
            ],
            'categoria' => [
                'id' => (int) $categoria->id,
                'codigo' => (string) $categoria->codigo,
                'nombre' => (string) $categoria->nombre,
            ],
            'articulos' => $articulos,
            'articulo_seleccionado_id' => $articuloSeleccionadoId,
            'descuento' => [
                'id' => (int) $descuento->id,
                'codigo' => (string) $descuento->codigo,
                'nombre' => (string) $descuento->nombre,
                'tipovalor' => (string) $descuento->tipovalor,
                'valor' => (float) $descuento->valor,
            ],
            'cliente' => [
                'id' => (int) $cliente->id,
                'codigo' => (string) $cliente->codigo,
                'nombre' => (string) $cliente->nombre,
            ],
        ];
    }

    /**
     * @param  array<int|string, int|string|array|null>  $opcionalesPorOrden
     * @return array{cuenta:CuentaGastronomia,validacion:array<string,mixed>}
     */
    public function aplicarACuenta(
        CuentaGastronomia $cuenta,
        string $trackdata,
        int $articuloId,
        int $listaprecioId,
        array $opcionalesPorOrden = [],
        ?string $comentarioCocina = null,
    ): array {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if ($cuenta->canje_premio_pendiente) {
            throw new InvalidArgumentException(
                'La cuenta ya tiene un canje de premio Wigos pendiente. Factúrelo o anúlelo antes de otro canje.'
            );
        }

        if ($cuenta->canje_fidelidad_pendiente) {
            throw new InvalidArgumentException('La cuenta ya tiene un canje de fidelidad pendiente de facturar.');
        }

        $validacion = $this->validarTarjeta(
            $trackdata,
            (int) $cuenta->empresa_id,
            $listaprecioId,
            $articuloId,
        );

        if ($articuloId <= 0) {
            throw new InvalidArgumentException('Seleccione el artículo a canjear.');
        }

        $descuento = $this->resolverDescuentoConfigurado();
        $clienteInternoId = $this->resolverClienteInternoIdPorLevelCode(
            (int) ($validacion['tarjeta']['level_code'] ?? 0),
            $descuento,
        );

        $item = null;
        foreach ($validacion['articulos'] as $art) {
            if ((int) $art['articulo_id'] === $articuloId) {
                $item = $art;
                break;
            }
        }
        if ($item === null) {
            throw new InvalidArgumentException('El artículo seleccionado no pertenece a la categoría de fidelidad.');
        }

        $opcionalesPorOrden = GastronomiaFormulaOpcionalSeleccion::normalizarMapaDesdeRequest($opcionalesPorOrden);

        $articulo = Articulo::query()->find($articuloId);
        if ($articulo && FormulaArticuloGastronomia::opcionalesHabilitados()) {
            $grupos = $this->opcionalesService->gruposOpcionalesPorArticulo($articulo);
            if ($grupos !== []) {
                $this->opcionalesService->validarSeleccionOpcionales($articulo, $opcionalesPorOrden);
            }
        }

        DB::transaction(function () use ($cuenta, $validacion, $item, $descuento, $clienteInternoId, $opcionalesPorOrden, $comentarioCocina) {
            $this->cuentaService->agregarLinea(
                $cuenta->fresh(['lineas']),
                (int) $item['articulo_id'],
                1.,
                (float) $item['precio_unitario'],
                $opcionalesPorOrden,
                0.,
                $comentarioCocina,
            );

            $this->cuentaService->actualizarCabecera($cuenta->fresh(), [
                'descuento_gastronomia_id' => $descuento->id,
                'cliente_interno_descuento_id' => $clienteInternoId,
            ]);

            $cuenta->fresh()->update([
                'canje_fidelidad_pendiente' => $validacion,
            ]);
        });

        return [
            'validacion' => $validacion,
            'cuenta' => $this->cuentaService->cuentaConLineas($cuenta->id),
        ];
    }

    public function registrarTrasEmision(Venta $venta, CuentaGastronomia $cuenta): void
    {
        $pendiente = $cuenta->canje_fidelidad_pendiente;
        if (! is_array($pendiente) || ($pendiente['trackdata'] ?? '') === '') {
            return;
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId <= 0) {
            throw new RuntimeException('Usuario no autenticado al registrar canje de fidelidad.');
        }

        $tarjeta = (array) ($pendiente['tarjeta'] ?? []);
        $documento = trim((string) ($tarjeta['documento'] ?? ''));
        if ($documento === '') {
            throw new InvalidArgumentException('Datos incompletos del canje de fidelidad (documento).');
        }

        if ($this->entregaRepository->existeCanjeHoyPorDocumento($documento)) {
            throw new InvalidArgumentException(
                'El titular (DNI '.$documento.') ya registró un canje de fidelidad hoy.'
            );
        }

        $categoriaId = (int) ($pendiente['categoria']['id'] ?? 0);
        $articuloId = (int) ($pendiente['articulo_seleccionado_id'] ?? 0);
        if ($categoriaId <= 0 || $articuloId <= 0) {
            throw new InvalidArgumentException('Datos incompletos del canje de fidelidad pendiente.');
        }

        if ((int) ($cuenta->descuento_gastronomia_id ?? 0) <= 0) {
            throw new InvalidArgumentException(
                'Debe facturar el canje de fidelidad con descuento gastronomía configurado.'
            );
        }

        $accountNumber = (int) ($tarjeta['account_number'] ?? 0);
        $trackdata = mb_substr(trim((string) ($pendiente['trackdata'] ?? '')), 0, 128);

        $this->entregaRepository->create([
            'categoriafidelidad_id' => $categoriaId,
            'documento' => $documento,
            'tarjeta' => $accountNumber > 0 ? (string) $accountNumber : '',
            'trackdata' => $trackdata !== '' ? $trackdata : null,
            'fechacanje' => Carbon::now(),
            'articulo_id' => $articuloId,
            'venta_id' => $venta->id,
            'apellido' => mb_substr(trim((string) ($tarjeta['apellido'] ?? '')), 0, 40),
            'nombre' => mb_substr(trim((string) ($tarjeta['nombre'] ?? '')), 0, 40),
        ]);

        $cuenta->update(['canje_fidelidad_pendiente' => null]);
    }

    /**
     * @return list<CategoriafidelidadEntregaGastronomia>
     */
    public function listarPorVenta(int $ventaId): array
    {
        return CategoriafidelidadEntregaGastronomia::query()
            ->with(['categoriafidelidad', 'articulo', 'venta'])
            ->where('venta_id', $ventaId)
            ->orderBy('fechacanje')
            ->get()
            ->all();
    }

    /**
     * @return list<CategoriafidelidadEntregaGastronomia>
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

        $query = CategoriafidelidadEntregaGastronomia::query()
            ->with(['categoriafidelidad', 'articulo', 'venta'])
            ->whereIn('venta_id', $ventaIds);

        if ($desde) {
            $query->where('fechacanje', '>=', $desde);
        }
        if ($hasta) {
            $query->where('fechacanje', '<=', $hasta);
        }

        return $query->orderByDesc('fechacanje')->get()->all();
    }

    private function resolverCategoriaPorLevelCode(int $levelCode): CategoriafidelidadGastronomia
    {
        $categoria = $this->categoriaRepository->findPorCodigo((string) $levelCode);
        if (! $categoria) {
            throw new InvalidArgumentException(
                'No existe categoría de fidelidad para levelCode '.$levelCode
                .' en el ERP. Configure categoriafidelidad_gastronomia.'
            );
        }

        return $categoria->load(['articulos.articulo']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolverArticulosCanje(
        CategoriafidelidadGastronomia $categoria,
        int $empresaId,
        int $listaprecioId,
    ): array {
        $items = [];
        $fechaLista = Carbon::today()->format('Y-m-d');

        foreach ($categoria->articulos as $pivot) {
            $articulo = $pivot->articulo;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            // Catálogo compartido: empresa_id null/0 = válido en cualquier PV (igual que queryArticulosCatalogo).
            $artEmpresaId = (int) ($articulo->empresa_id ?? 0);
            if ($artEmpresaId > 0 && $artEmpresaId !== $empresaId) {
                continue;
            }

            $precios = PrecioService::asignaPrecioPorLista(
                (int) $articulo->id,
                $listaprecioId,
                $fechaLista,
            );
            $precio = 0.;
            if ($precios !== []) {
                $p = end($precios);
                $precio = (float) ($p['precio'] ?? 0.);
            }

            $items[] = [
                'articulo_id' => (int) $articulo->id,
                'sku' => trim((string) $articulo->sku),
                'descripcion' => trim((string) ($articulo->descripcion ?? '')),
                'precio_unitario' => round($precio, 2),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string,mixed>>  $articulos
     * @return array<string,mixed>
     */
    private function resolverArticuloSeleccionado(array $articulos, ?int $articuloId): array
    {
        if ($articuloId !== null && $articuloId > 0) {
            foreach ($articulos as $art) {
                if ((int) $art['articulo_id'] === $articuloId) {
                    return $art;
                }
            }
            throw new InvalidArgumentException('Artículo no válido para esta categoría de fidelidad.');
        }

        if (count($articulos) === 1) {
            return $articulos[0];
        }

        throw new InvalidArgumentException('Seleccione el artículo a canjear.');
    }

    private function normalizarTrackdata(string $trackdata): string
    {
        try {
            return WigosTrackdataNormalizer::normalizar($trackdata);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Datos de tarjeta vacíos.');
        }
    }

    private function resolverDescuentoConfigurado(): DescuentoGastronomia
    {
        $codigo = trim((string) config('gastronomia.canje_fidelidad_descuento_codigo', '10'));
        $descuento = DescuentoGastronomia::query()->where('codigo', $codigo)->first();
        if (! $descuento) {
            throw new InvalidArgumentException(
                'No existe el descuento gastronomía para canje de fidelidad (código '.$codigo.').'
            );
        }

        return $descuento;
    }

    private function resolverClienteConfigurado(): Cliente
    {
        $codigo = trim((string) config('gastronomia.canje_fidelidad_cliente_codigo', '500'));
        $cliente = Cliente::query()->where('codigo', $codigo)->first();
        if (! $cliente) {
            throw new InvalidArgumentException(
                'No existe el cliente configurado para canje de fidelidad (código '.$codigo.').'
            );
        }

        return $cliente;
    }

    /**
     * Platino (levelCode Wigos, ej. 3) → cliente 1500; resto → canje_fidelidad_cliente_codigo (500).
     */
    private function resolverClienteInternoIdPorLevelCode(int $levelCode, ?DescuentoGastronomia $descuento = null): int
    {
        $descuento ??= $this->resolverDescuentoConfigurado();
        $clienteInternoId = (int) ($descuento->cliente_id ?? 0);
        if ($clienteInternoId > 0) {
            return $clienteInternoId;
        }

        $resolved = GastronomiaDescuentoClienteInternoSupport::resolverClienteInternoCanjePremio(
            $levelCode > 0 ? $levelCode : null
        );
        if ($resolved !== null && $resolved > 0) {
            return $resolved;
        }

        return (int) $this->resolverClienteConfigurado()->id;
    }

    private function resolverClienteInternoPorLevelCode(int $levelCode, ?DescuentoGastronomia $descuento = null): Cliente
    {
        $cliente = Cliente::query()->find($this->resolverClienteInternoIdPorLevelCode($levelCode, $descuento));
        if ($cliente === null) {
            throw new InvalidArgumentException('No existe el cliente interno configurado para el canje de fidelidad.');
        }

        return $cliente;
    }
}
