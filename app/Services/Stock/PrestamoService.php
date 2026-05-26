<?php

namespace App\Services\Stock;

use App\Mail\Stock\PrestamoAprobacion;
use App\Mail\Stock\PrestamoCambioEstado;
use App\Mail\Stock\PrestamoRecordatorio;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Prestamo_Estado;
use App\Models\Stock\Prestamo_Item;
use App\Models\Stock\Prestamo_Token;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Stock\Deposito_AdministradorRepositoryInterface;
use App\Repositories\Stock\PrestamoRepositoryInterface;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


/**
 * Orquesta todas las transiciones del préstamo:
 *
 *  - guardar() / actualizar(): preparan la cabecera + ítems en estado BORRADOR.
 *  - confirmarEnvio(): genera la salida del depósito origen (movimiento
 *    de stock con tipo PRSAL) y dispara mail al destinatario.
 *  - aprobarRecepcion(): genera el ingreso al depósito destino (PRING)
 *    y notifica al solicitante.
 *  - rechazarRecepcion(): reversa la salida (PRRCH) y notifica.
 *  - registrarDevolucion(): genera salida del destino (PRDSL) y entrada
 *    al origen (PRDIN) por la cantidad devuelta de cada ítem.
 *  - cancelar(): solo para BORRADOR / PENDIENTE_APROBACION pendientes.
 *
 * Todos los cambios de estado se loguean en `prestamo_estado`.
 */
class PrestamoService
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepository,
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        private readonly Deposito_AdministradorRepositoryInterface $depAdminRepository,
    ) {}

    public function listar()
    {
        return $this->prestamoRepository->all();
    }

    public function buscar(int $id): Prestamo
    {
        return $this->prestamoRepository->findConRelaciones($id);
    }

    public function guardar(array $data): Prestamo
    {
        $this->validarDatosBasicos($data);

        return DB::transaction(function () use ($data) {
            $prestamo = $this->prestamoRepository->create([
                'codigo' => $data['codigo'] ?? null,
                'fecha_prestamo' => $data['fecha_prestamo'],
                'fecha_devolucion_prometida' => $data['fecha_devolucion_prometida'],
                'deposito_origen_id' => (int) $data['deposito_origen_id'],
                'deposito_destino_id' => (int) $data['deposito_destino_id'],
                'solicitante_id' => Auth::id() ?? (int) ($data['solicitante_id'] ?? 0),
                'estado' => Prestamo::ESTADO_BORRADOR,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            if (empty($prestamo->codigo)) {
                $prestamo->codigo = 'PR-'.str_pad((string) $prestamo->id, 6, '0', STR_PAD_LEFT);
                $prestamo->save();
            }

            $this->reemplazarItems($prestamo, $data['items'] ?? []);
            $this->logEstado($prestamo, null, Prestamo::ESTADO_BORRADOR, 'Préstamo creado');

            return $prestamo->fresh(['items']);
        });
    }

    public function actualizar(int $id, array $data): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_BORRADOR) {
            throw new \RuntimeException(
                'Solo se puede editar un préstamo mientras está en BORRADOR. Estado actual: '.$prestamo->estado
            );
        }

        $this->validarDatosBasicos($data, true);

        return DB::transaction(function () use ($prestamo, $data) {
            $prestamo->fill([
                'fecha_prestamo' => $data['fecha_prestamo'],
                'fecha_devolucion_prometida' => $data['fecha_devolucion_prometida'],
                'deposito_origen_id' => (int) $data['deposito_origen_id'],
                'deposito_destino_id' => (int) $data['deposito_destino_id'],
                'observaciones' => $data['observaciones'] ?? null,
            ])->save();

            $this->reemplazarItems($prestamo, $data['items'] ?? []);

            return $prestamo->fresh(['items']);
        });
    }

    public function confirmarEnvio(int $id): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if (! in_array($prestamo->estado, [Prestamo::ESTADO_BORRADOR], true)) {
            throw new \RuntimeException('Solo se puede confirmar el envío desde BORRADOR.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get();
        if ($items->isEmpty()) {
            throw new \RuntimeException('El préstamo no tiene ítems cargados.');
        }

        $this->validarStockOrigen($prestamo->deposito_origen_id, $items);

        return DB::transaction(function () use ($prestamo, $items) {
            $movId = $this->generarMovimientoStock(
                $prestamo,
                $items,
                'PRSAL',
                'salida',
                "Préstamo {$prestamo->codigo} - Salida de origen"
            );

            $prestamo->movimientostock_salida_id = $movId;
            $estadoAnterior = $prestamo->estado;
            $prestamo->estado = Prestamo::ESTADO_PENDIENTE_APROBACION;
            $prestamo->save();

            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, 'Confirmación de envío');
            $this->generarTokensYNotificarAprobacion($prestamo);

            return $prestamo->fresh();
        });
    }

    public function aprobarRecepcion(int $id, ?int $usuarioAprobadorId = null, ?string $observaciones = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_PENDIENTE_APROBACION) {
            throw new \RuntimeException('Solo se puede aprobar un préstamo en estado PENDIENTE_APROBACION.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get();

        return DB::transaction(function () use ($prestamo, $items, $usuarioAprobadorId, $observaciones) {
            $movId = $this->generarMovimientoStock(
                $prestamo,
                $items,
                'PRING',
                'ingreso_destino',
                "Préstamo {$prestamo->codigo} - Ingreso a destino"
            );

            $estadoAnterior = $prestamo->estado;
            $prestamo->movimientostock_ingreso_id = $movId;
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
            throw new \RuntimeException('Solo se puede rechazar un préstamo en estado PENDIENTE_APROBACION.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get();

        return DB::transaction(function () use ($prestamo, $items, $usuarioId, $motivo) {
            // Reversa la salida en el origen.
            $this->generarMovimientoStock(
                $prestamo,
                $items,
                'PRRCH',
                'reverso',
                "Préstamo {$prestamo->codigo} - Reverso por rechazo"
            );

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
     * Registra una devolución parcial o total.
     *
     * @param  array<int, array{prestamo_item_id:int, cantidad:float}>  $devoluciones
     */
    public function registrarDevolucion(int $id, array $devoluciones, ?string $observaciones = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if (! $prestamo->estaPendienteDevolucion()) {
            throw new \RuntimeException('El préstamo no admite devolución en este estado.');
        }

        $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get()->keyBy('id');
        $movItems = [];
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
            $movItems[] = (object) [
                'articulo_id' => $item->articulo_id,
                'cantidad' => $cantidad,
                'item_origen' => $item,
            ];
        }

        if (empty($movItems)) {
            throw new \RuntimeException('No se indicó ninguna cantidad de devolución válida.');
        }

        return DB::transaction(function () use ($prestamo, $movItems, $observaciones) {
            $itemsCol = collect($movItems);
            // Salida del depósito destino...
            $this->generarMovimientoStock(
                $prestamo,
                $itemsCol,
                'PRDSL',
                'devolucion_salida',
                "Préstamo {$prestamo->codigo} - Devolución salida destino"
            );
            // ...e ingreso al origen.
            $this->generarMovimientoStock(
                $prestamo,
                $itemsCol,
                'PRDIN',
                'devolucion_ingreso',
                "Préstamo {$prestamo->codigo} - Devolución ingreso origen"
            );

            // Actualiza ítems acumulando lo devuelto.
            foreach ($movItems as $mov) {
                /** @var Prestamo_Item $item */
                $item = $mov->item_origen;
                $item->cantidad_devuelta = (float) $item->cantidad_devuelta + $mov->cantidad;
                $item->save();
            }

            // Si no queda pendiente en ningún ítem el préstamo queda DEVUELTO.
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

    public function cancelar(int $id, ?string $motivo = null): Prestamo
    {
        $prestamo = $this->prestamoRepository->find($id);

        if (! in_array($prestamo->estado, [Prestamo::ESTADO_BORRADOR, Prestamo::ESTADO_PENDIENTE_APROBACION], true)) {
            throw new \RuntimeException('Solo se puede cancelar en BORRADOR o PENDIENTE_APROBACION.');
        }

        return DB::transaction(function () use ($prestamo, $motivo) {
            // Si ya generó la salida, la reversamos.
            if ($prestamo->estado === Prestamo::ESTADO_PENDIENTE_APROBACION) {
                $items = Prestamo_Item::where('prestamo_id', $prestamo->id)->get();
                $this->generarMovimientoStock(
                    $prestamo,
                    $items,
                    'PRRCH',
                    'reverso',
                    "Préstamo {$prestamo->codigo} - Cancelación: reverso de salida"
                );
            }
            $estadoAnterior = $prestamo->estado;
            $prestamo->estado = Prestamo::ESTADO_CANCELADO;
            $prestamo->save();
            $this->logEstado($prestamo, $estadoAnterior, $prestamo->estado, $motivo ?? 'Préstamo cancelado');
            $this->invalidarTokens($prestamo);

            return $prestamo->fresh();
        });
    }

    public function eliminar(int $id): bool
    {
        $prestamo = $this->prestamoRepository->find($id);

        if ($prestamo->estado !== Prestamo::ESTADO_BORRADOR) {
            throw new \RuntimeException(
                'Solo se puede borrar un préstamo en BORRADOR. Cancelar o devolver primero.'
            );
        }

        return $this->prestamoRepository->delete($id);
    }

    /**
     * Devuelve mapa deposito_id => cantidad para los artículos pasados,
     * útil para mostrar "saldo origen / saldo destino" al armar el préstamo.
     *
     * @param  list<int>  $articuloIds
     * @param  list<int>  $depositoIds
     * @return array<int, array<int, float>>  [articulo_id][deposito_id] = cantidad
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

    /**
     * Envía recordatorios para todos los préstamos pendientes que
     * cumplen las reglas de la configuración.
     */
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
                $this->enviarRecordatorio($prestamo, $config);
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
    /*                       Helpers privados                       */
    /* ============================================================ */

    private function validarDatosBasicos(array $data, bool $esActualizacion = false): void
    {
        foreach (['fecha_prestamo', 'fecha_devolucion_prometida', 'deposito_origen_id', 'deposito_destino_id'] as $campo) {
            if (empty($data[$campo])) {
                throw new \RuntimeException("Campo requerido: {$campo}");
            }
        }
        if ((int) $data['deposito_origen_id'] === (int) $data['deposito_destino_id']) {
            throw new \RuntimeException('El depósito origen y destino deben ser distintos.');
        }
        if (strtotime($data['fecha_devolucion_prometida']) < strtotime($data['fecha_prestamo'])) {
            throw new \RuntimeException('La fecha prometida de devolución no puede ser anterior a la fecha del préstamo.');
        }
    }

    /**
     * @param  list<array{articulo_id:int, cantidad:float, observaciones?:string}>  $itemsData
     */
    private function reemplazarItems(Prestamo $prestamo, array $itemsData): void
    {
        Prestamo_Item::where('prestamo_id', $prestamo->id)->delete();
        foreach ($itemsData as $row) {
            $articuloId = (int) ($row['articulo_id'] ?? 0);
            $cantidad = (float) ($row['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            Prestamo_Item::create([
                'prestamo_id' => $prestamo->id,
                'articulo_id' => $articuloId,
                'cantidad' => $cantidad,
                'cantidad_devuelta' => 0,
                'observaciones' => $row['observaciones'] ?? null,
            ]);
        }

        if (Prestamo_Item::where('prestamo_id', $prestamo->id)->count() === 0) {
            throw new \RuntimeException('Debe cargar al menos un ítem con cantidad mayor a cero.');
        }
    }

    private function validarStockOrigen(int $depositoOrigenId, $items): void
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

    /**
     * Genera un MovimientoStock + sus articulo_movimiento creando los
     * registros directamente. No usa MovimientoStockService porque
     * éste asume artículos con categoría de calzado que no aplican a
     * los materiales del laboratorio.
     *
     * El observer Articulo_MovimientoObserver actualiza la tabla de
     * saldos por (articulo, deposito) automáticamente.
     *
     * @param  string  $abreviatura  PRSAL | PRING | PRRCH | PRDSL | PRDIN
     * @param  string  $contexto  origen del movimiento (informativo)
     */
    private function generarMovimientoStock(Prestamo $prestamo, $items, string $abreviatura, string $contexto, string $leyenda): int
    {
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
                break;
            default:
                $depositoId = (int) $prestamo->deposito_origen_id;
        }

        $signo = (int) $tipo->signo === -1 ? -1 : 1;
        $codigo = 'PR-'.$tipo->abreviatura.'-'.str_pad((string) $prestamo->id, 6, '0', STR_PAD_LEFT);

        $movimiento = MovimientoStock::create([
            'fecha' => now()->toDateString(),
            'fechajornada' => now()->toDateString(),
            'tipotransaccion_stock_id' => $tipo->id,
            'codigo' => $codigo,
            'leyenda' => $leyenda,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        foreach ($items as $item) {
            $cantidad = (float) abs((float) $item->cantidad) * $signo;
            // create() dispara el observer Articulo_MovimientoObserver
            // que actualiza la tabla articulo_saldo_deposito.
            Articulo_Movimiento::create([
                'fecha' => now()->toDateString(),
                'fechajornada' => now()->toDateString(),
                'tipotransaccion_stock_id' => $tipo->id,
                'movimientostock_id' => $movimiento->id,
                'lote' => 0,
                'articulo_id' => (int) $item->articulo_id,
                'concepto' => $tipo->nombre,
                'cantidad' => $cantidad,
                'precio' => 0,
                'costo' => 0,
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
        $config = Configuracion_Prestamo::vigente();
        if (! $config->enviar_aprobacion) {
            return;
        }

        $admins = $this->depAdminRepository->porDeposito($prestamo->deposito_destino_id);
        if ($admins->isEmpty()) {
            Log::warning('PrestamoService: depósito destino sin administradores', [
                'prestamo_id' => $prestamo->id,
                'deposito_destino_id' => $prestamo->deposito_destino_id,
            ]);

            return;
        }

        $expira = now()->addHours((int) ($config->horas_validez_token ?? 168));

        foreach ($admins as $admin) {
            $usuario = $admin->usuarios;
            if (! $usuario || empty($usuario->email)) {
                continue;
            }

            $tokenAprobar = $this->crearToken($prestamo, Prestamo_Token::ACCION_APROBAR, (int) $usuario->id, $expira);
            $tokenRechazar = $this->crearToken($prestamo, Prestamo_Token::ACCION_RECHAZAR, (int) $usuario->id, $expira);
            $tokenVer = $this->crearToken($prestamo, Prestamo_Token::ACCION_VISUALIZAR, (int) $usuario->id, $expira);

            $links = [
                'aprobar' => route('prestamo_aprobar_publico', ['token' => $tokenAprobar->token]),
                'rechazar' => route('prestamo_rechazar_publico', ['token' => $tokenRechazar->token]),
                'visualizar' => route('prestamo_ver_publico', ['token' => $tokenVer->token]),
            ];

            try {
                $mailable = (new PrestamoAprobacion(
                    $prestamo->loadMissing(['items.articulos:id,sku,descripcion', 'depositoOrigen', 'depositoDestino', 'solicitante']),
                    $usuario,
                    $links,
                    $config
                ));
                if (! empty($config->mail_remitente)) {
                    $mailable = $mailable->from($config->mail_remitente);
                }
                $envio = Mail::to($usuario->email);
                if ($cc = $config->copiasComoArray()) {
                    $envio->cc($cc);
                }
                $envio->send($mailable);
            } catch (\Throwable $e) {
                Log::error('PrestamoService: falló envío de mail aprobación', [
                    'prestamo_id' => $prestamo->id,
                    'usuario_id' => $usuario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function notificarSolicitante(Prestamo $prestamo, string $tipo, ?string $mensaje): void
    {
        /** @var Usuario|null $solicitante */
        $solicitante = $prestamo->solicitante;
        if (! $solicitante || empty($solicitante->email)) {
            return;
        }

        $config = Configuracion_Prestamo::vigente();
        try {
            $mailable = new PrestamoCambioEstado(
                $prestamo->loadMissing(['items.articulos:id,sku,descripcion', 'depositoOrigen', 'depositoDestino', 'aprobador']),
                $tipo,
                $mensaje,
                $config
            );
            if (! empty($config->mail_remitente)) {
                $mailable = $mailable->from($config->mail_remitente);
            }
            Mail::to($solicitante->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('PrestamoService::notificarSolicitante falló', [
                'prestamo_id' => $prestamo->id,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enviarRecordatorio(Prestamo $prestamo, Configuracion_Prestamo $config): void
    {
        $admins = $this->depAdminRepository->porDeposito($prestamo->deposito_destino_id);
        $destinatarios = $admins->pluck('usuarios.email')->filter()->unique()->values()->all();
        if (empty($destinatarios)) {
            return;
        }

        $vencido = $prestamo->estaVencido();
        $mailable = new PrestamoRecordatorio($prestamo, $config, $vencido);
        if (! empty($config->mail_remitente)) {
            $mailable = $mailable->from($config->mail_remitente);
        }
        $envio = Mail::to($destinatarios);
        if ($cc = $config->copiasComoArray()) {
            $envio->cc($cc);
        }
        $envio->send($mailable);
    }

    private function crearToken(Prestamo $prestamo, string $accion, int $usuarioId, $expira): Prestamo_Token
    {
        return Prestamo_Token::create([
            'prestamo_id' => $prestamo->id,
            'token' => Str::random(60),
            'accion' => $accion,
            'usuario_destino_id' => $usuarioId,
            'expira_el' => $expira,
        ]);
    }

    private function invalidarTokens(Prestamo $prestamo): void
    {
        Prestamo_Token::where('prestamo_id', $prestamo->id)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);
    }
}
