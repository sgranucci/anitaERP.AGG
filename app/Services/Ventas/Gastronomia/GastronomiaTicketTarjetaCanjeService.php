<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Condicioniva;
use App\Models\Ventas\TickettarjetaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaCuentacajaCanjeTarjeta;
use App\Support\Ventas\GastronomiaTicketTarjetaAnitaBridgeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canje de tickets tarjeta gastronomía (tabla Informix tickettarj vía bridge Anita).
 */
final class GastronomiaTicketTarjetaCanjeService
{
  public function __construct(
    private readonly ApiAnita $apiAnita,
  ) {
  }

  /**
   * @return array{ticket_id:int,numeroticket:int,numerodocumento:string,fecha_emision:string,importe:float,montoticket:float,monto:float,numerocupon:string,estado_anita:string,monto_aplicar:float,cuentacaja:array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}}
   */
  public function validarParaCobranza(
    string $codigoBarras,
    int $empresaId,
    float $totalFacturaArs,
    float $montoCobranzaYaCargadoArs,
    array $ticketsYaSeleccionados,
  ): array {
    [$ticketId, $numeroTicket, $fila] = $this->resolverTicketDesdeCodigo($codigoBarras, $empresaId);
    $this->assertNoDuplicadoEnSeleccion($ticketId, $numeroTicket, $ticketsYaSeleccionados);
    $this->assertNoCanjeadoEnErp($ticketId, $numeroTicket);
    $this->validarEstadoAnitaPendiente($fila);
    $this->validarVencimiento($fila);

    $montoticket = $this->resolverMontoticket($fila);
    $saldoPendiente = max(0., round($totalFacturaArs - $montoCobranzaYaCargadoArs, 2));
    if ($saldoPendiente <= 0.) {
      throw new InvalidArgumentException('La factura ya está cubierta por la cobranza cargada.');
    }

    $montoAplicar = $this->calcularMontoAplicar($montoticket, $saldoPendiente);
    $cuenta = GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa($empresaId);
    if (! $cuenta) {
      $msg = GastronomiaCuentacajaCanjeTarjeta::mensajeErrorResolucion($empresaId);
      throw new InvalidArgumentException($msg ?: 'No se pudo resolver la cuenta de caja CTG.');
    }

    $fechaEmision = $this->anitaIntToDate((int) ($fila->ifecha ?? 0));

    return [
      'ticket_id' => $ticketId,
      'numeroticket' => $numeroTicket,
      'numerodocumento' => trim((string) ($fila->cnrodocumento ?? '')),
      'fecha_emision' => $fechaEmision,
      'importe' => $montoticket,
      'montoticket' => $montoticket,
      'monto' => round((float) ($fila->fmonto ?? 0.), 2),
      'numerocupon' => trim((string) ($fila->cnrocupon ?? '')),
      'estado_anita' => strtoupper(trim((string) ($fila->cestado ?? ''))),
      'monto_aplicar' => $montoAplicar,
      'cuentacaja' => $cuenta,
    ];
  }

  /**
   * Persiste canjes y marca tickets en Anita. Debe ejecutarse dentro de la misma transacción ERP de emisión.
   *
   * @param  list<array{cuentacaja_id?:int,moneda_id?:int,monto?:float,ticket_id?:int,numeroticket?:int}>  $mediosPago
   */
  public function registrarCanjesTrasCobranza(Venta $venta, array $mediosPago, int $empresaId): void
  {
    $tickets = $this->extraerTicketsDesdeMedios($mediosPago, $empresaId);
    if ($tickets === []) {
      return;
    }

    $usuarioId = (int) (Auth::id() ?? 0);
    if ($usuarioId <= 0) {
      throw new RuntimeException('Usuario no autenticado al registrar canje de ticket tarjeta.');
    }

    $venta->loadMissing(['tipotransacciones', 'puntoventas']);
    $this->validarMontosTicketsEnEmision($venta, $mediosPago, $empresaId, $tickets);

    $idsUsados = [];

    foreach ($tickets as $ticket) {
      $clave = $ticket['ticket_id'].'-'.$ticket['numeroticket'];
      if (isset($idsUsados[$clave])) {
        throw new InvalidArgumentException(
          'El ticket '.$ticket['ticket_id'].'/'.$ticket['numeroticket'].' está repetido en la cobranza.'
        );
      }
      $idsUsados[$clave] = true;

      $this->assertNoCanjeadoEnErp($ticket['ticket_id'], $ticket['numeroticket']);

      $fila = $this->buscarTicketEnAnita($ticket['ticket_id'], $ticket['numeroticket'], $empresaId);
      $this->validarEstadoAnitaPendiente($fila);
      $this->validarVencimiento($fila);

      $montoticket = $this->resolverMontoticket($fila);
      $montoCobranza = round((float) $ticket['monto_cobranza'], 2);
      if ($montoCobranza <= 0.) {
        throw new InvalidArgumentException('Monto de cobranza inválido para ticket '.$ticket['numeroticket'].'.');
      }

      TickettarjetaGastronomia::query()->create([
        'ticket_id' => $ticket['ticket_id'],
        'numerodocumento' => trim((string) ($fila->cnrodocumento ?? '')),
        'fecha' => $this->anitaIntToDate((int) ($fila->ifecha ?? 0)),
        'monto' => round((float) ($fila->fmonto ?? 0.), 2),
        'numerocupon' => trim((string) ($fila->cnrocupon ?? '')),
        'montoticket' => $montoticket,
        'numeroticket' => $ticket['numeroticket'],
        'estado' => TickettarjetaGastronomia::ESTADO_CANJEADO,
        'venta_id' => $venta->id,
        'usuario_id' => $usuarioId,
      ]);

      $this->marcarCanjeadoEnAnita($fila, $venta, $usuarioId, $empresaId);
    }
  }

  /**
   * @return list<TickettarjetaGastronomia>
   */
  public function listarPorVenta(int $ventaId): array
  {
    return TickettarjetaGastronomia::query()
      ->where('venta_id', $ventaId)
      ->orderBy('id')
      ->get()
      ->all();
  }

  /**
   * @return list<TickettarjetaGastronomia>
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

    $query = TickettarjetaGastronomia::query()
      ->with(['venta', 'usuario'])
      ->whereIn('venta_id', $ventaIds);

    if ($desde) {
      $query->where('created_at', '>=', $desde);
    }
    if ($hasta) {
      $query->where('created_at', '<=', $hasta);
    }

    return $query->orderByDesc('created_at')->orderBy('id')->get()->all();
  }

  /**
   * @return array{0:int,1:int}
   */
  public function parseCodigoBarras(string $codigoBarras): array
  {
    $candidatos = $this->candidatosParseoCodigo($codigoBarras);
    if ($candidatos === []) {
      throw new InvalidArgumentException('Código de barras inválido: debe tener al menos 7 dígitos.');
    }

    return $candidatos[0];
  }

  /**
   * Variantes de parseo (la primera es la preferida).
   *
   * El ticket impreso suele ser MOVIMIENTO-NROTICKET (ej. 554101-52547), pero el código de barras
   * EAN lleva el movimiento (6), el nroticket con cero adelante (6) y dígito verificador (1):
   * 5541010525473. OCR o lectura del pie del código suele devolver 554101-0525473.
   *
   * Kandiko (Wilde): movimiento(6) + 00000 + renglón(1) + verificador → 8324560000013 = 832456/1.
   * El último dígito (3) es verificador EAN, no parte del nroticket (evitar 13).
   *
   * @return list<array{0:int,1:int}>
   */
  public function candidatosParseoCodigo(string $codigoBarras): array
  {
    $raw = trim($codigoBarras);
    if ($raw === '') {
      return [];
    }

    $vistos = [];
    $agregar = static function (array &$lista, array &$vistos, int $ticketId, int $numeroTicket): void {
      if ($ticketId <= 0 || $numeroTicket <= 0) {
        return;
      }
      $clave = $ticketId.'/'.$numeroTicket;
      if (isset($vistos[$clave])) {
        return;
      }
      $vistos[$clave] = true;
      $lista[] = [$ticketId, $numeroTicket];
    };

    $lista = [];

    if (preg_match('/^(\d{5,6})\s*[-–]\s*(\d{4,9})$/u', $raw, $partes)) {
      $agregar($lista, $vistos, (int) $partes[1], self::normalizarNumeroTicketDesdeCola($partes[2]));
    }

    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === null || $digits === '' || strlen($digits) < 7) {
      return $lista;
    }

    $ticketId = (int) substr($digits, 0, 6);
    $cola = substr($digits, 6);
    $agregar($lista, $vistos, $ticketId, self::normalizarNumeroTicketDesdeCola($cola));

    // Respaldo sin dígito verificador EAN (lecturas < 13 dígitos, ej. 55365952217).
    if (strlen($cola) < 7) {
      $agregar($lista, $vistos, $ticketId, (int) $cola);
    }

    return $lista;
  }

  /**
   * Quita relleno y dígito verificador del tramo posterior al movimiento (6 dígitos).
   */
  private static function normalizarNumeroTicketDesdeCola(string $cola): int
  {
    $cola = preg_replace('/\D/', '', $cola) ?? '';
    if ($cola === '') {
      return 0;
    }

    if (strlen($cola) >= 7) {
      $cola = substr($cola, 0, -1);
    }

    return (int) $cola;
  }

  /**
   * @param  list<array{ticket_id?:int,numeroticket?:int}>  $ticketsYaSeleccionados
   */
  private function assertNoDuplicadoEnSeleccion(int $ticketId, int $numeroTicket, array $ticketsYaSeleccionados): void
  {
    foreach ($ticketsYaSeleccionados as $t) {
      if ((int) ($t['ticket_id'] ?? 0) === $ticketId && (int) ($t['numeroticket'] ?? 0) === $numeroTicket) {
        throw new InvalidArgumentException('Este ticket ya fue agregado a la cobranza de esta factura.');
      }
    }
  }

  private function assertNoCanjeadoEnErp(int $ticketId, int $numeroTicket): void
  {
    $existe = TickettarjetaGastronomia::query()
      ->where('ticket_id', $ticketId)
      ->where('numeroticket', $numeroTicket)
      ->exists();
    if ($existe) {
      throw new InvalidArgumentException(
        'El ticket '.$ticketId.'/'.$numeroTicket.' ya fue canjeado en otra factura.'
      );
    }
  }

  /**
   * @return array{0:int,1:int,2:object}
   */
  private function resolverTicketDesdeCodigo(string $codigoBarras, int $empresaId): array
  {
    $candidatos = $this->candidatosParseoCodigo($codigoBarras);
    if ($candidatos === []) {
      throw new InvalidArgumentException('Código de barras inválido: debe tener al menos 7 dígitos.');
    }

    $ultimoError = null;
    foreach ($candidatos as [$ticketId, $numeroTicket]) {
      try {
        $fila = $this->buscarTicketEnAnita($ticketId, $numeroTicket, $empresaId);

        return [$ticketId, $numeroTicket, $fila];
      } catch (InvalidArgumentException $e) {
        $ultimoError = $e;
        if (! str_contains($e->getMessage(), 'No se encontró el ticket tarjeta')) {
          throw $e;
        }

        $resuelto = $this->buscarTicketEnAnitaPorSoloMovimientoId($ticketId, $empresaId);
        if ($resuelto !== null) {
          return $resuelto;
        }
      }
    }

    $movimientoId = $this->extraerMovimientoId($codigoBarras);
    if ($movimientoId > 0) {
      $numerosLeidos = array_values(array_unique(array_map(
        static fn (array $par): int => (int) ($par[1] ?? 0),
        $candidatos
      )));
      $resuelto = $this->buscarTicketEnAnitaPorSoloMovimientoId($movimientoId, $empresaId);
      if ($resuelto !== null) {
        return $resuelto;
      }

      $ultimoError = $this->errorTicketNoEncontradoEnAnita(
        $movimientoId,
        $numerosLeidos[0] ?? 0,
        $empresaId,
        $ultimoError,
      );
    }

    throw $ultimoError ?? new InvalidArgumentException('Código de barras inválido.');
  }

  /**
   * Fallback Kandiko/Wilde: si movimiento+nroticket exacto no existe, trae el pendiente del movimiento.
   *
   * @return array{0:int,1:int,2:object}|null
   */
  private function buscarTicketEnAnitaPorSoloMovimientoId(int $movimientoId, int $empresaId): ?array
  {
    if ($movimientoId <= 0) {
      return null;
    }

    $filas = $this->listarTicketsPorMovimientoEnAnita($movimientoId, $empresaId, false);
    if ($filas === []) {
      return null;
    }

    usort($filas, static fn (object $a, object $b): int => (int) ($b->ifecha ?? 0) <=> (int) ($a->ifecha ?? 0));
    $elegida = $filas[0];

    $ticketId = (int) ($elegida->imovimientoid ?? $movimientoId);
    $numeroTicket = (int) ($elegida->inroticket ?? 0);
    if ($ticketId <= 0 || $numeroTicket <= 0) {
      return null;
    }

    return [$ticketId, $numeroTicket, $elegida];
  }

  private function errorTicketNoEncontradoEnAnita(
    int $movimientoId,
    int $numeroLeido,
    int $empresaId,
    ?InvalidArgumentException $previo = null,
  ): InvalidArgumentException {
    $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId);
    $servidor = trim((string) ($params['servidor'] ?? ''));

    $partes = ['No se encontró el ticket tarjeta '.$movimientoId.'/'.$numeroLeido.' en Anita'];
    if ($servidor !== '') {
      $partes[] = '('.$servidor.')';
    }

    if ($numeroLeido > 0 && $numeroLeido <= 99) {
      $partes[] = '— el «-'.$numeroLeido.'» del vale es renglón; en Anita el inroticket suele ser otro número';
    }

    $pendientesExpirados = $this->contarTicketsPendientesVencidosPorMovimiento($movimientoId, $empresaId);
    if ($pendientesExpirados > 0) {
      $partes[] = '(hay '.$pendientesExpirados.' ticket(s) pendiente(s) vencido(s) para movimiento '.$movimientoId.')';
    } elseif ($this->contarTicketsPorMovimiento($movimientoId, $empresaId) === 0) {
      $partes[] = '(movimiento '.$movimientoId.' sin registros en tickettarj; verifique emisión en caja Anita)';
    }

    return new InvalidArgumentException(implode(' ', $partes).'.');
  }

  private function contarTicketsPendientesVencidosPorMovimiento(int $movimientoId, int $empresaId): int
  {
    $payload = $this->payloadAnitaTickettarj($empresaId, [
      'acc' => 'list',
      'tabla' => 'tickettarj',
      'campos' => 'imovimientoid, inroticket, ifecha, cestado',
      'whereArmado' => ' WHERE imovimientoid = '.$movimientoId
        .' AND '.$this->sqlFiltroCestadoPendiente(),
    ]);

    $filas = ApiAnita::decodificarListaFilas($this->apiAnita->apiCall($payload));
    $hoy = Carbon::today();
    $dias = max(1, (int) config('gastronomia.ticket_tarjeta_vencimiento_dias', 30));
    $vencidos = 0;

    foreach ($filas as $fila) {
      try {
        $fechaEmision = Carbon::parse($this->anitaIntToDate((int) ($fila->ifecha ?? 0)))->startOfDay();
      } catch (\Throwable) {
        continue;
      }
      if ($hoy->gt($fechaEmision->copy()->addDays($dias))) {
        $vencidos++;
      }
    }

    return $vencidos;
  }

  private function contarTicketsPorMovimiento(int $movimientoId, int $empresaId): int
  {
    $payload = $this->payloadAnitaTickettarj($empresaId, [
      'acc' => 'list',
      'tabla' => 'tickettarj',
      'campos' => 'imovimientoid',
      'whereArmado' => ' WHERE imovimientoid = '.$movimientoId,
    ]);

    return count(ApiAnita::decodificarListaFilas($this->apiAnita->apiCall($payload)));
  }

  private function extraerMovimientoId(string $codigoBarras): int
  {
    $raw = trim($codigoBarras);
    if ($raw === '') {
      return 0;
    }

    if (preg_match('/^(\d{5,6})\s*[-–]/u', $raw, $partes)) {
      return (int) $partes[1];
    }

    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === null || strlen($digits) < 6) {
      return 0;
    }

    return (int) substr($digits, 0, 6);
  }

  /**
   * @return list<object>
   */
  private function listarTicketsPendientesPorMovimientoEnAnita(int $movimientoId, int $empresaId): array
  {
    return $this->listarTicketsPorMovimientoEnAnita($movimientoId, $empresaId, true);
  }

  /**
   * @return list<object>
   */
  private function listarTicketsPorMovimientoEnAnita(int $movimientoId, int $empresaId, bool $excluirVencidos): array
  {
    $payload = $this->payloadAnitaTickettarj($empresaId, [
      'acc' => 'list',
      'tabla' => 'tickettarj',
      'campos' => implode(', ', [
        'imovimientoid',
        'cnrodocumento',
        'ifecha',
        'fmonto',
        'cnrocupon',
        'fmontoticket',
        'inroticket',
        'cestado',
        'ifechacanje',
      ]),
      'whereArmado' => ' WHERE imovimientoid = '.$movimientoId
        .' AND '.$this->sqlFiltroCestadoPendiente(),
    ]);

    $filas = ApiAnita::decodificarListaFilas($this->apiAnita->apiCall($payload));

    $filas = array_values(array_filter($filas, static fn (object $fila): bool => (int) ($fila->inroticket ?? 0) > 0));
    if ($filas === [] || ! $excluirVencidos) {
      return $filas;
    }

    $hoy = Carbon::today();
    $dias = max(1, (int) config('gastronomia.ticket_tarjeta_vencimiento_dias', 30));

    return array_values(array_filter($filas, function (object $fila) use ($hoy, $dias): bool {
      try {
        $fechaEmision = Carbon::parse($this->anitaIntToDate((int) ($fila->ifecha ?? 0)))->startOfDay();
      } catch (\Throwable) {
        return false;
      }

      return ! $hoy->gt($fechaEmision->copy()->addDays($dias));
    }));
  }

  private function buscarTicketEnAnita(int $ticketId, int $numeroTicket, int $empresaId): object
  {
    $payload = $this->payloadAnitaTickettarj($empresaId, [
      'acc' => 'list',
      'tabla' => 'tickettarj',
      'campos' => implode(', ', [
        'imovimientoid',
        'cnrodocumento',
        'ifecha',
        'fmonto',
        'cnrocupon',
        'fmontoticket',
        'inroticket',
        'cestado',
        'ifechacanje',
      ]),
      'whereArmado' => ' WHERE imovimientoid = '.$ticketId.' AND inroticket = '.$numeroTicket.' ',
    ]);

    $fila = ApiAnita::primeraFilaLista($this->apiAnita->apiCall($payload));
    if ($fila === null) {
      throw new InvalidArgumentException(
        'No se encontró el ticket tarjeta '.$ticketId.'/'.$numeroTicket.' en Anita.'
      );
    }

    return $fila;
  }

  private function validarEstadoAnitaPendiente(object $fila): void
  {
    $estado = strtoupper(trim((string) ($fila->cestado ?? '')));
    if ($estado === TickettarjetaGastronomia::ESTADO_CANJEADO) {
      throw new InvalidArgumentException('El ticket ya fue canjeado (estado C en Anita).');
    }
    if ($estado !== TickettarjetaGastronomia::ESTADO_PENDIENTE) {
      throw new InvalidArgumentException(
        'El ticket no está disponible para canje (estado Anita: '.($estado !== '' ? $estado : '?').').'
      );
    }
  }

  private function validarVencimiento(object $fila): void
  {
    $dias = max(1, (int) config('gastronomia.ticket_tarjeta_vencimiento_dias', 30));
    $fechaEmision = Carbon::parse($this->anitaIntToDate((int) ($fila->ifecha ?? 0)))->startOfDay();
    $limite = $fechaEmision->copy()->addDays($dias);
    if (Carbon::today()->gt($limite)) {
      throw new InvalidArgumentException(
        'El ticket está vencido (emisión '.$fechaEmision->format('d/m/Y').', límite '.$limite->format('d/m/Y').').'
      );
    }
  }

  private function resolverMontoticket(object $fila): float
  {
    $montoticket = round((float) ($fila->fmontoticket ?? 0.), 2);
    if ($montoticket <= 0.) {
      $montoticket = round((float) ($fila->fmonto ?? 0.), 2);
    }
    if ($montoticket <= 0.) {
      throw new InvalidArgumentException('El ticket no tiene importe válido en Anita.');
    }

    return $montoticket;
  }

  private function calcularMontoAplicar(float $montoticket, float $saldoPendienteArs): float
  {
    $tolerancia = max(0., (float) config('gastronomia.ticket_tarjeta_tolerancia_excedente_factura', 5.));
    if ($montoticket > $saldoPendienteArs + $tolerancia + 0.001) {
      throw new InvalidArgumentException(
        'El importe del ticket ($ '.number_format($montoticket, 2, ',', '.')
        .') supera el saldo pendiente ($ '.number_format($saldoPendienteArs, 2, ',', '.')
        .') más la tolerancia permitida ($ '.number_format($tolerancia, 2, ',', '.').').'
      );
    }

    return round(min($montoticket, $saldoPendienteArs), 2);
  }

  /**
   * @param  list<array{ticket_id:int,numeroticket:int,monto_cobranza:float}>  $tickets
   * @param  list<array{cuentacaja_id?:int,moneda_id?:int,monto?:float,ticket_id?:int,numeroticket?:int}>  $mediosPago
   */
  private function validarMontosTicketsEnEmision(Venta $venta, array $mediosPago, int $empresaId, array $tickets): void
  {
    if ($tickets === []) {
      return;
    }

    $totalFacturaArs = round(abs((float) $venta->total), 2);
    $montoSinTickets = 0.;
    foreach ($mediosPago as $medio) {
      if ((int) ($medio['ticket_id'] ?? 0) <= 0) {
        $montoSinTickets += round((float) ($medio['monto'] ?? 0.), 2);
      }
    }

    $saldoTickets = max(0., round($totalFacturaArs - $montoSinTickets, 2));

    foreach ($tickets as $ticket) {
      $fila = $this->buscarTicketEnAnita($ticket['ticket_id'], $ticket['numeroticket'], $empresaId);
      $montoticket = $this->resolverMontoticket($fila);
      $esperado = $this->calcularMontoAplicar($montoticket, $saldoTickets);
      $montoCobranza = round((float) $ticket['monto_cobranza'], 2);
      if (abs($montoCobranza - $esperado) > 0.021) {
        throw new InvalidArgumentException(
          'El monto del ticket '.$ticket['numeroticket']
          .' ($ '.number_format($montoCobranza, 2, ',', '.')
          .') no coincide con el permitido ($ '.number_format($esperado, 2, ',', '.').').'
        );
      }
      $saldoTickets = max(0., round($saldoTickets - $esperado, 2));
    }
  }

  /**
   * @param  list<array{cuentacaja_id?:int,moneda_id?:int,monto?:float,ticket_id?:int,numeroticket?:int}>  $mediosPago
   * @return list<array{ticket_id:int,numeroticket:int,monto_cobranza:float}>
   */
  private function extraerTicketsDesdeMedios(array $mediosPago, int $empresaId): array
  {
    $cuentaCtg = GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa($empresaId);
    $ctgId = $cuentaCtg['id'] ?? 0;

    $tickets = [];
    foreach ($mediosPago as $medio) {
      $ticketId = (int) ($medio['ticket_id'] ?? 0);
      $numeroTicket = (int) ($medio['numeroticket'] ?? 0);
      if ($ticketId <= 0 || $numeroTicket <= 0) {
        continue;
      }

      $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
      if ($ctgId > 0 && $cuentacajaId !== $ctgId) {
        throw new InvalidArgumentException(
          'Los tickets tarjeta deben cobrarse con la cuenta CTG (Canje ticket gastronomía).'
        );
      }

      $tickets[] = [
        'ticket_id' => $ticketId,
        'numeroticket' => $numeroTicket,
        'monto_cobranza' => (float) ($medio['monto'] ?? 0),
      ];
    }

    return $tickets;
  }

  private function marcarCanjeadoEnAnita(object $fila, Venta $venta, int $usuarioId, int $empresaId): void
  {
    $ticketId = (int) ($fila->imovimientoid ?? 0);
    $numeroTicket = (int) ($fila->inroticket ?? 0);
    if ($ticketId <= 0 || $numeroTicket <= 0) {
      throw new RuntimeException('Datos incompletos del ticket en Anita.');
    }

    [$tipoFact, $letraFact, $sucursalFact, $nroFact] = $this->resolverDatosFacturaAnita($venta);
    $usuarioFact = substr((string) (Auth::user()->usuario ?? Auth::user()->nombre ?? 'WEB'), 0, 15);
    $fechaCanje = (int) Carbon::today()->format('Ymd');

    $valores = implode(', ', [
      "cestado = '".TickettarjetaGastronomia::ESTADO_CANJEADO."'",
      'ifechacanje = '.$fechaCanje,
      "ctipo_fact = '".$tipoFact."'",
      "cletra_fact = '".$letraFact."'",
      'csucursal_fact = '.$sucursalFact,
      'cnro_fact = '.$nroFact,
      "cusuario_fact = '".$this->escaparSqlAnita($usuarioFact)."'",
    ]);

    $payload = $this->payloadAnitaTickettarj($empresaId, [
      'acc' => 'update',
      'tabla' => 'tickettarj',
      'valores' => $valores,
      'whereArmado' => ' WHERE imovimientoid = '.$ticketId
        .' AND inroticket = '.$numeroTicket
        .' AND '.$this->sqlFiltroCestadoPendiente(),
    ]);

    $this->apiAnita->apiCallEscritura($payload, 'tickettarj canje ticket '.$ticketId.'/'.$numeroTicket);
  }

  /**
   * @return array{0:string,1:string,2:int,3:int}
   */
  private function resolverDatosFacturaAnita(Venta $venta): array
  {
    $tipo = $venta->tipotransacciones;
    $codigoTipo = $tipo ? strtoupper(substr(trim((string) ($tipo->codigo ?? $tipo->abreviatura ?? 'FAC')), 0, 3)) : 'FAC';
    if (strlen($codigoTipo) < 3) {
      $codigoTipo = str_pad($codigoTipo, 3, ' ', STR_PAD_RIGHT);
    }

    $letra = 'B';
    $condicionId = (int) ($venta->condicioniva_id ?? 0);
    if ($condicionId > 0) {
      $letraDb = Condicioniva::query()->whereKey($condicionId)->value('letra');
      if ($letraDb) {
        $letra = strtoupper(substr(trim((string) $letraDb), 0, 1));
      }
    }

    $sucursal = (int) ($venta->puntoventas->codigo ?? 0);
    $nro = (int) ($venta->numerocomprobante ?? 0);

    return [$codigoTipo, $letra, $sucursal, $nro];
  }

  private function anitaIntToDate(int $yyyymmdd): string
  {
    if ($yyyymmdd < 19000101) {
      return Carbon::today()->format('Y-m-d');
    }
    $s = (string) $yyyymmdd;
    if (strlen($s) !== 8) {
      return Carbon::today()->format('Y-m-d');
    }

    return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
  }

  private function escaparSqlAnita(string $valor): string
  {
    return str_replace("'", "''", $valor);
  }

  /** Informix CHAR: cestado suele venir como 'P ' con espacios; = 'P' no matchea. */
  private function sqlFiltroCestadoPendiente(): string
  {
    return "TRIM(cestado) = '".TickettarjetaGastronomia::ESTADO_PENDIENTE."'";
  }

  /**
   * @param  array<string, mixed>  $base
   * @return array<string, mixed>
   */
  private function payloadAnitaTickettarj(int $empresaId, array $base): array
  {
    return array_merge(
      GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId),
      $base,
    );
  }
}
