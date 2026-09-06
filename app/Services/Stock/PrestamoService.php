<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Prestamo_Estado;
use App\Models\Stock\Prestamo_Item;
use App\Models\Stock\Prestamo_Token;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Stock\Deposito_AdministradorRepositoryInterface;
use App\Repositories\Stock\PrestamoRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\ArticuloMovimientoPrecioHistoricoSupport;
use Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta el circuito de salida de bienes (evolución de préstamos).
 */
class PrestamoService
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepository,
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        private readonly ModuloAvisoService $moduloAvisoService,
        private readonly Deposito_AdministradorRepositoryInterface $depAdminRepository,
    ) {}

    public function listar()
    {
        return $this->prestamoRepository->all();
    }

    /**
     * Salidas pendientes de aprobación asignadas al usuario (destinatario o admin del depósito destino).
     *
     * @return Collection<int, Prestamo>
     */
    public function listarPendientesAprobacionParaUsuario(int $usuarioId): Collection
    {
        if ($usuarioId <= 0) {
            return collect();
        }

        $pendientes = Prestamo::query()
            ->with(['depositoDestino:id,nombre', 'destinatarioUsuario:id,nombre', 'solicitante:id,nombre'])
            ->where('estado', Prestamo::ESTADO_PENDIENTE_APROBACION)
            ->orderByDesc('updated_at')
            ->get();

        return $pendientes->filter(fn (Prestamo $p) => $this->usuarioPuedeAprobarSalida($p, $usuarioId))->values();
    }

    public function usuarioPuedeAprobarSalida(Prestamo $prestamo, int $usuarioId): bool
    {
        if ($usuarioId <= 0 || $prestamo->estado !== Prestamo::ESTADO_PENDIENTE_APROBACION) {
            return false;
        }

        if ($prestamo->esDestinoUsuario()) {
            return (int) ($prestamo->destinatario_usuario_id ?? 0) === $usuarioId;
        }

        if ($prestamo->esDestinoDeposito() && (int) ($prestamo->deposito_destino_id ?? 0) > 0) {
            return $this->depAdminRepository
                ->porDeposito((int) $prestamo->deposito_destino_id)
                ->contains(fn ($admin) => (int) ($admin->usuario_id ?? 0) === $usuarioId
                    || (int) (optional($admin->usuarios)->id ?? 0) === $usuarioId);
        }

        return false;
    }

    public function resumenKpis(): array
    {
        return $this->prestamoRepository->resumenKpis();
    }

    public function buscar(int $id): Prestamo
    {
        return $this->prestamoRepository->findConRelaciones($id);
    }

    public function guardar(array $data): Prestamo
    {
        $payload = $this->normalizarCabecera($data);

        return DB::transaction(function () use ($payload, $data) {
            $prestamo = $this->prestamoRepository->create(array_merge($payload, [
                'solicitante_id' => Auth::id() ?? (int) ($data['solicitante_id'] ?? 0),
                'estado' => Prestamo::ESTADO_BORRADOR,
            ]));

            if (empty($prestamo->codigo)) {
                $prestamo->codigo = 'SB-'.str_pad((string) $prestamo->id, 6, '0', STR_PAD_LEFT);
                $prestamo->save();
            }

            $this->reemplazarItems($prestamo, $data['items'] ?? []);
            $this->logEstado($prestamo, null, Prestamo::ESTADO_BORRADOR, 'Salida de bienes creada');

            return $prestamo->fresh(['items']);
        });
    }

    public function actualizar(int $id, array $data): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_BORRADOR) {
            throw new \RuntimeException(
                'Solo se puede editar una salida en BORRADOR. Estado actual: '.$prestamo->estado
            );
        }

        $payload = $this->normalizarCabecera($data);

        return DB::transaction(function () use ($prestamo, $payload, $data) {
            $prestamo->fill($payload)->save();
            $this->reemplazarItems($prestamo, $data['items'] ?? []);

            return $prestamo->fresh(['items']);
        });
    }

    public function confirmarEnvio(int $id): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_BORRADOR) {
            throw new \RuntimeException('Solo se puede confirmar el envío desde BORRADOR.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get();
        if ($items->isEmpty()) {
            throw new \RuntimeException('La salida no tiene ítems cargados.');
        }

        $itemsStock = $this->itemsConArticulo($items);
        $this->validarStockOrigen((int) $prestamo->deposito_origen_id, $itemsStock);

        return DB::transaction(function () use ($prestamo, $itemsStock) {
            if ($itemsStock->isNotEmpty()) {
                $movId = $this->generarMovimientoStock(
                    $prestamo,
                    $itemsStock,
                    'PRSAL',
                    'salida',
                    "Salida {$prestamo->codigo} - Salida de origen"
                );
                $prestamo->movimientostock_salida_id = $movId;
            }

            $estadoAnterior = $prestamo->estado;

            if ($prestamo->esDestinoExterno()) {
                $prestamo->estado = Prestamo::ESTADO_ENVIADO;
                $prestamo->fecha_aprobacion = now()->toDateString();
                $prestamo->save();
                $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, 'Envío a externo confirmado');
            } else {
                $prestamo->estado = Prestamo::ESTADO_PENDIENTE_APROBACION;
                $prestamo->save();
                $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, 'Confirmación de envío');
                $this->generarTokensYNotificarAprobacion($prestamo);
            }

            return $prestamo->fresh();
        });
    }

    public function aprobarRecepcion(int $id, ?int $usuarioAprobadorId = null, ?string $observaciones = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE_APROBACION) {
            throw new \RuntimeException('Solo se puede aprobar una salida en estado PENDIENTE_APROBACION.');
        }

        $itemsStock = $this->itemsConArticulo(
            Prestamo_Item::where('prestamo_id', $prestamo->id)->get()
        );

        return DB::transaction(function () use ($prestamo, $itemsStock, $usuarioAprobadorId, $observaciones) {
            if ($prestamo->esDestinoDeposito() && $itemsStock->isNotEmpty()) {
                $movId = $this->generarMovimientoStock(
                    $prestamo,
                    $itemsStock,
                    'PRING',
                    'ingreso_destino',
                    "Salida {$prestamo->codigo} - Ingreso a destino"
                );
                $prestamo->movimientostock_ingreso_id = $movId;
            }

            $estadoAnterior = $prestamo->estado;
            $prestamo->aprobador_id = $usuarioAprobadorId ?? Auth::id();
            $prestamo->fecha_aprobacion = now()->toDateString();
            $prestamo->estado = Prestamo::ESTADO_APROBADO;
            $prestamo->save();

            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, $observaciones ?? 'Aprobación de recepción');
            $this->invalidarTokens($prestamo);
            $this->notificarSolicitante($prestamo, 'aprobado', $observaciones);

            return $prestamo->fresh();
        });
    }

    public function rechazarRecepcion(int $id, ?int $usuarioId = null, ?string $motivo = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE_APROBACION) {
            throw new \RuntimeException('Solo se puede rechazar una salida en estado PENDIENTE_APROBACION.');
        }

        $itemsStock = $this->itemsConArticulo(
            Prestamo_Item::where('prestamo_id', $prestamo->id)->get()
        );

        return DB::transaction(function () use ($prestamo, $itemsStock, $usuarioId, $motivo) {
            if ($itemsStock->isNotEmpty()) {
                $this->generarMovimientoStock(
                    $prestamo,
                    $itemsStock,
                    'PRRCH',
                    'reverso',
                    "Salida {$prestamo->codigo} - Reverso por rechazo"
                );
            }

            $estadoAnterior = $prestamo->estado;
            $prestamo->aprobador_id = $usuarioId ?? Auth::id();
            $prestamo->motivo_rechazo = $motivo;
            $prestamo->estado = Prestamo::ESTADO_RECHAZADO;
            $prestamo->save();

            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, $motivo ?? 'Rechazo de recepción');
            $this->invalidarTokens($prestamo);
            $this->notificarSolicitante($prestamo, 'rechazado', $motivo);

            return $prestamo->fresh();
        });
    }

    /**
     * @param  array<int, array{prestamo_item_id:int, cantidad:float, condicion_devolucion?:string}>  $devoluciones
     */
    public function registrarDevolucion(int $id, array $devoluciones, ?string $observaciones = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if (! $prestamo->estaPendienteDevolucion()) {
            throw new \RuntimeException('La salida no admite devolución en este estado.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get()->keyBy('id');
        $movItems = [];
        $updatesCondicion = [];

        foreach ($devoluciones as $d) {
            $itemId = (int) ($d['prestamo_item_id'] ?? 0);
            $cantidad = (float) ($d['cantidad'] ?? 0);
            if ($cantidad <= 0 || ! isset($items[$itemId])) {
                continue;
            }
            $item = $items[$itemId];
            $pendiente = max(0, (float) $item->cantidad - (float) $item->cantidad_devuelta);
            if ($cantidad > $pendiente) {
                throw new \RuntimeException(
                    "Cantidad devuelta ({$cantidad}) excede pendiente ({$pendiente}) del ítem {$item->id}."
                );
            }
            if (! empty($d['condicion_devolucion'])) {
                $updatesCondicion[$itemId] = (string) $d['condicion_devolucion'];
            }
            if ($item->tieneArticulo()) {
                $movItems[] = (object) [
                    'articulo_id' => $item->articulo_id,
                    'cantidad' => $cantidad,
                    'item_origen' => $item,
                ];
            } else {
                $movItems[] = (object) [
                    'articulo_id' => null,
                    'cantidad' => $cantidad,
                    'item_origen' => $item,
                ];
            }
        }

        if (empty($movItems)) {
            throw new \RuntimeException('No se indicó ninguna cantidad de devolución válida.');
        }

        return DB::transaction(function () use ($prestamo, $movItems, $updatesCondicion, $observaciones) {
            $itemsConArticulo = collect($movItems)->filter(fn ($m) => (int) ($m->articulo_id ?? 0) > 0)->values();

            if ($itemsConArticulo->isNotEmpty()) {
                if ($prestamo->esDestinoDeposito()) {
                    $this->generarMovimientoStock(
                        $prestamo,
                        $itemsConArticulo,
                        'PRDSL',
                        'devolucion_salida',
                        "Salida {$prestamo->codigo} - Devolución salida destino"
                    );
                }
                $this->generarMovimientoStock(
                    $prestamo,
                    $itemsConArticulo,
                    'PRDIN',
                    'devolucion_ingreso',
                    "Salida {$prestamo->codigo} - Devolución ingreso origen"
                );
            }

            foreach ($movItems as $mov) {
                /** @var Prestamo_Item $item */
                $item = $mov->item_origen;
                $item->cantidad_devuelta = (float) $item->cantidad_devuelta + $mov->cantidad;
                if (isset($updatesCondicion[$item->id])) {
                    $item->condicion_devolucion = $updatesCondicion[$item->id];
                }
                $item->save();
            }

            $pendientes = Prestamo_Item::where('prestamo_id', $prestamo->id)
                ->whereColumn('cantidad_devuelta', '<', 'cantidad')
                ->count();

            $estadoAnterior = $prestamo->estado;
            $prestamo->estado = $pendientes === 0
                ? Prestamo::ESTADO_DEVUELTO
                : Prestamo::ESTADO_DEVUELTO_PARCIAL;

            if ($pendientes === 0) {
                $prestamo->fecha_devolucion_real = now()->toDateString();
            }

            $prestamo->save();
            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, $observaciones ?? 'Devolución registrada');

            return $prestamo->fresh();
        });
    }

    public function cerrarSinDevolucion(int $id, ?string $motivo = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if (! $prestamo->puedeCerrarSinDevolucion()) {
            throw new \RuntimeException('La salida no admite cierre en este estado.');
        }

        return DB::transaction(function () use ($prestamo, $motivo) {
            $estadoAnterior = $prestamo->estado;
            $prestamo->estado = Prestamo::ESTADO_CERRADO;
            $prestamo->fecha_devolucion_real = now()->toDateString();
            $prestamo->save();
            $this->logEstado(
                $prestamo,
                $estadoAnterior,
                $prestamo->estado,
                $motivo ?? 'Cierre sin devolución (custodia finalizada)'
            );

            return $prestamo->fresh();
        });
    }

    public function cancelar(int $id, ?string $motivo = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        $cancelables = [Prestamo::ESTADO_BORRADOR, Prestamo::ESTADO_PENDIENTE_APROBACION];
        if (! in_array($prestamo->estado, $cancelables, true)) {
            throw new \RuntimeException('Solo se puede cancelar en BORRADOR o PENDIENTE_APROBACION.');
        }

        return DB::transaction(function () use ($prestamo, $motivo) {
            if ($prestamo->estado === Prestamo::ESTADO_PENDIENTE_APROBACION) {
                $itemsStock = $this->itemsConArticulo(
                    Prestamo_Item::where('prestamo_id', $prestamo->id)->get()
                );
                if ($itemsStock->isNotEmpty()) {
                    $this->generarMovimientoStock(
                        $prestamo,
                        $itemsStock,
                        'PRRCH',
                        'reverso',
                        "Salida {$prestamo->codigo} - Cancelación: reverso de salida"
                    );
                }
            }
            $estadoAnterior = $prestamo->estado;
            $prestamo->estado = Prestamo::ESTADO_CANCELADO;
            $prestamo->save();
            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, $motivo ?? 'Salida cancelada');
            $this->invalidarTokens($prestamo);

            return $prestamo->fresh();
        });
    }

    public function eliminar(int $id): bool
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_BORRADOR) {
            throw new \RuntimeException(
                'Solo se puede borrar una salida en BORRADOR. Cancelar o devolver primero.'
            );
        }

        return $this->prestamoRepository->delete($id);
    }

    /**
     * @param  list<int>  $articuloIds
     * @param  list<int>  $depositoIds
     * @return array<int, array<int, float>>
     */
    public function saldosArticulos(array $articuloIds, array $depositoIds): array
    {
        $resultado = [];
        foreach ($articuloIds as $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId === 0) {
                continue;
            }
            $resultado[$articuloId] = $this->saldoRepository->saldosArticuloPorDeposito($articuloId, $depositoIds);
        }

        return $resultado;
    }

    public function reenviarCorreoAprobacion(int $id): void
    {
        $prestamo = $this->prestamoRepository->find($id);
        if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE_APROBACION) {
            throw new \RuntimeException('Solo se puede reenviar el correo si está pendiente de aprobación.');
        }
        $this->invalidarTokens($prestamo);
        $this->generarTokensYNotificarAprobacion($prestamo);
    }

    public function enviarRecordatorios(): int
    {
        $config = Configuracion_Prestamo::vigente();
        if (! $config->enviar_recordatorios) {
            return 0;
        }

        $prestamos = $this->prestamoRepository->pendientesParaRecordar();
        $enviados = 0;
        foreach ($prestamos as $prestamo) {
            try {
                $this->moduloAvisoService->enviar('stock', 'prestamo_recordatorio', (int) $prestamo->id, [
                    'vencido' => $prestamo->estaVencido(),
                ]);
                $prestamo->ultimo_recordatorio_enviado_el = now()->toDateString();
                $prestamo->save();
                $enviados++;
            } catch (\Throwable $e) {
                Log::error('PrestamoService::enviarRecordatorios falló', [
                    'prestamo_id' => $prestamo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enviados;
    }

    public function consumirToken(string $token, string $accionEsperada): Prestamo_Token
    {
        $row = Prestamo_Token::where('token', $token)->first();
        if (! $row) {
            throw new \RuntimeException('Enlace inválido.');
        }
        if ($row->accion !== $accionEsperada) {
            throw new \RuntimeException('Enlace para una acción distinta.');
        }
        if (! $row->estaActivo()) {
            throw new \RuntimeException('Este enlace ya fue utilizado o expiró.');
        }

        $row->usado_el = now();
        $row->save();

        return $row;
    }

    /* ============================================================ */

    /**
     * @return array<string, mixed>
     */
    private function normalizarCabecera(array $data): array
    {
        $tipo = (string) ($data['tipo'] ?? Prestamo::TIPO_PRESTAMO);
        $destTipo = (string) ($data['destinatario_tipo'] ?? Prestamo::DEST_DEPOSITO);
        $espera = array_key_exists('espera_devolucion', $data)
            ? (bool) $data['espera_devolucion']
            : ($tipo !== Prestamo::TIPO_ENTREGA);

        if (empty($data['fecha_prestamo'])) {
            throw new \RuntimeException('Campo requerido: fecha_prestamo');
        }
        if (empty($data['deposito_origen_id'])) {
            throw new \RuntimeException('Campo requerido: deposito_origen_id');
        }

        if ($espera && empty($data['fecha_devolucion_prometida'])) {
            throw new \RuntimeException('Campo requerido: fecha_devolucion_prometida');
        }
        if (! empty($data['fecha_devolucion_prometida'])
            && strtotime((string) $data['fecha_devolucion_prometida']) < strtotime((string) $data['fecha_prestamo'])) {
            throw new \RuntimeException('La fecha prometida de devolución no puede ser anterior a la fecha de la salida.');
        }

        $depositoDestinoId = null;
        $destUsuarioId = null;
        $externo = [
            'externo_nombre' => null,
            'externo_documento' => null,
            'externo_telefono' => null,
            'externo_email' => null,
            'externo_empresa' => null,
        ];

        switch ($destTipo) {
            case Prestamo::DEST_DEPOSITO:
                $depositoDestinoId = (int) ($data['deposito_destino_id'] ?? 0);
                if ($depositoDestinoId <= 0) {
                    throw new \RuntimeException('Campo requerido: deposito_destino_id');
                }
                if ($depositoDestinoId === (int) $data['deposito_origen_id']) {
                    throw new \RuntimeException('El depósito origen y destino deben ser distintos.');
                }
                break;
            case Prestamo::DEST_USUARIO:
                $destUsuarioId = (int) ($data['destinatario_usuario_id'] ?? 0);
                if ($destUsuarioId <= 0) {
                    throw new \RuntimeException('Campo requerido: destinatario_usuario_id');
                }
                break;
            case Prestamo::DEST_EXTERNO:
                $nombre = trim((string) ($data['externo_nombre'] ?? ''));
                if ($nombre === '') {
                    throw new \RuntimeException('Campo requerido: externo_nombre');
                }
                $externo = [
                    'externo_nombre' => $nombre,
                    'externo_documento' => $data['externo_documento'] ?? null,
                    'externo_telefono' => $data['externo_telefono'] ?? null,
                    'externo_email' => $data['externo_email'] ?? null,
                    'externo_empresa' => $data['externo_empresa'] ?? null,
                ];
                break;
            default:
                throw new \RuntimeException('destinatario_tipo inválido.');
        }

        return array_merge([
            'codigo' => $data['codigo'] ?? null,
            'tipo' => $tipo,
            'destinatario_tipo' => $destTipo,
            'fecha_prestamo' => $data['fecha_prestamo'],
            'fecha_devolucion_prometida' => $espera ? ($data['fecha_devolucion_prometida'] ?? null) : ($data['fecha_devolucion_prometida'] ?? null),
            'deposito_origen_id' => (int) $data['deposito_origen_id'],
            'deposito_destino_id' => $depositoDestinoId,
            'destinatario_usuario_id' => $destUsuarioId,
            'espera_devolucion' => $espera,
            'prioridad' => $data['prioridad'] ?? Prestamo::PRIORIDAD_NORMAL,
            'observaciones' => $data['observaciones'] ?? null,
        ], $externo);
    }

    /**
     * @param  list<array<string, mixed>>  $itemsData
     */
    private function reemplazarItems(Prestamo $prestamo, array $itemsData): void
    {
        Prestamo_Item::where('prestamo_id', $prestamo->id)->delete();
        foreach ($itemsData as $row) {
            $articuloId = (int) ($row['articulo_id'] ?? 0);
            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            $cantidad = (float) ($row['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }
            if ($articuloId <= 0 && $descripcion === '') {
                continue;
            }
            Prestamo_Item::create([
                'prestamo_id' => $prestamo->id,
                'articulo_id' => $articuloId > 0 ? $articuloId : null,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'nro_serie' => $row['nro_serie'] ?? null,
                'condicion_salida' => $row['condicion_salida'] ?? null,
                'cantidad' => $cantidad,
                'cantidad_devuelta' => 0,
                'observaciones' => $row['observaciones'] ?? null,
            ]);
        }

        if (Prestamo_Item::where('prestamo_id', $prestamo->id)->count() === 0) {
            throw new \RuntimeException('Debe cargar al menos un ítem con cantidad mayor a cero.');
        }
    }

    private function itemsConArticulo(Collection $items): Collection
    {
        return $items->filter(fn ($item) => (int) ($item->articulo_id ?? 0) > 0)->values();
    }

    private function validarStockOrigen(int $depositoOrigenId, Collection $items): void
    {
        foreach ($items as $item) {
            $saldo = $this->saldoRepository->saldo((int) $item->articulo_id, $depositoOrigenId);
            if ((float) $item->cantidad > $saldo) {
                throw new \RuntimeException(
                    "Stock insuficiente en el depósito origen para el artículo {$item->articulo_id}. ".
                    "Saldo: {$saldo}, solicitado: {$item->cantidad}."
                );
            }
        }
    }

    private function assertPeriodoContableStock(int $depositoId, string $fecha): void
    {
        $empresaId = (int) (Depmae::query()->whereKey($depositoId)->value('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_STOCK
        );
    }

    /**
     * @param  string  $abreviatura  PRSAL | PRING | PRRCH | PRDSL | PRDIN
     */
    private function generarMovimientoStock(Prestamo $prestamo, Collection $items, string $abreviatura, string $contexto, string $leyenda): int
    {
        if ($items->isEmpty()) {
            throw new \RuntimeException('No hay ítems con artículo para generar movimiento de stock.');
        }

        $tipo = Tipotransaccion_Stock::where('abreviatura', $abreviatura)->first();
        if (! $tipo) {
            throw new \RuntimeException("No existe tipo de transacción de stock {$abreviatura}.");
        }

        switch ($contexto) {
            case 'salida':
            case 'reverso':
            case 'devolucion_ingreso':
                $depositoId = (int) $prestamo->deposito_origen_id;
                break;
            case 'ingreso_destino':
            case 'devolucion_salida':
                $depositoId = (int) $prestamo->deposito_destino_id;
                if ($depositoId <= 0) {
                    throw new \RuntimeException('La salida no tiene depósito destino para este movimiento.');
                }
                break;
            default:
                $depositoId = (int) $prestamo->deposito_origen_id;
        }

        $signo = (int) $tipo->signo === -1 ? -1 : 1;
        $codigo = 'SB-'.$tipo->abreviatura.'-'.str_pad((string) $prestamo->id, 6, '0', STR_PAD_LEFT);

        $this->assertPeriodoContableStock($depositoId, now()->toDateString());

        $movimiento = MovimientoStock::create([
            'fecha' => now()->toDateString(),
            'fechajornada' => now()->toDateString(),
            'tipotransaccion_stock_id' => $tipo->id,
            'codigo' => $codigo,
            'leyenda' => $leyenda,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        $articuloIds = $items->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $preciosUltimaCompra = ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraMovimientoPorArticuloIds($articuloIds);

        foreach ($items as $item) {
            $cantidad = (float) abs((float) $item->cantidad) * $signo;
            $articuloId = (int) $item->articulo_id;
            $datoPrecio = $preciosUltimaCompra[$articuloId] ?? ['precio' => 0.0, 'costo' => 0.0, 'moneda_id' => null];
            Articulo_Movimiento::create([
                'fecha' => now()->toDateString(),
                'fechajornada' => now()->toDateString(),
                'tipotransaccion_stock_id' => $tipo->id,
                'movimientostock_id' => $movimiento->id,
                'lote' => 0,
                'articulo_id' => $articuloId,
                'concepto' => $tipo->nombre,
                'cantidad' => $cantidad,
                'precio' => $datoPrecio['precio'],
                'costo' => $datoPrecio['costo'],
                'moneda_id' => $datoPrecio['moneda_id'],
                'deposito_id' => $depositoId,
                'descuentointegrado' => ' ',
            ]);
        }

        return (int) $movimiento->id;
    }

    private function logEstado(Prestamo $prestamo, ?string $anterior, string $nuevo, ?string $obs): void
    {
        Prestamo_Estado::create([
            'prestamo_id' => $prestamo->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'usuario_id' => Auth::id(),
            'observaciones' => $obs,
            'ocurrio_el' => now(),
        ]);
    }

    private function generarTokensYNotificarAprobacion(Prestamo $prestamo): void
    {
        $this->moduloAvisoService->enviar('stock', 'prestamo_solicitud', (int) $prestamo->id);
    }

    private function notificarSolicitante(Prestamo $prestamo, string $tipo, ?string $mensaje): void
    {
        $codigo = $tipo === 'rechazado' ? 'prestamo_rechazado_solicitante' : 'prestamo_aprobado_solicitante';
        $this->moduloAvisoService->enviar('stock', $codigo, (int) $prestamo->id, [
            'mensaje' => $mensaje,
        ]);
    }

    private function invalidarTokens(Prestamo $prestamo): void
    {
        Prestamo_Token::where('prestamo_id', $prestamo->id)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);
    }
}
