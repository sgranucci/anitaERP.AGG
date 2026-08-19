<?php
namespace App\Services\Ticket;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Ticket\TicketRepositoryInterface;
use App\Repositories\Ticket\Ticket_EstadoRepositoryInterface;
use App\Repositories\Ticket\Ticket_ArchivoRepositoryInterface;
use App\Repositories\Ticket\Ticket_TareaRepositoryInterface;
use App\Repositories\Ticket\Ticket_Tarea_NovedadRepositoryInterface;
use App\Repositories\Ticket\Ticket_ArticuloRepositoryInterface;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea_Novedad;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use App\Support\Ticket\TicketEstadisticaSupport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class TicketService 
{
	private $ticketRepository;
    private $ticket_estadoRepository;
    private $ticket_archivoRepository;
	private $ticket_tareaRepository;
	private $ticket_tarea_novedadRepository;
	private $ticket_articuloRepository;
	private $tecnico_ticketRepository;
	private $ticketTareaAsignadaNotificacionService;
	private $moduloAvisoService;

    public function __construct(TicketRepositoryInterface $ticketrepository,
                                Ticket_EstadoRepositoryInterface $ticket_estadorepository,
                                Ticket_ArchivoRepositoryInterface $ticket_archivorepository,
								Ticket_TareaRepositoryInterface $ticket_tarearepository,
								Ticket_Tarea_NovedadRepositoryInterface $ticket_tarea_novedadrepository,
								Tecnico_TicketRepositoryInterface $tecnico_ticketrepository,
								Ticket_ArticuloRepositoryInterface $ticket_articulorepository,
								TicketTareaAsignadaNotificacionService $ticketTareaAsignadaNotificacionService,
								ModuloAvisoService $moduloAvisoService
								)
    {
		$this->ticketRepository = $ticketrepository;
        $this->ticket_estadoRepository = $ticket_estadorepository;
        $this->ticket_archivoRepository = $ticket_archivorepository;
		$this->ticket_tareaRepository = $ticket_tarearepository;
		$this->ticket_tarea_novedadRepository = $ticket_tarea_novedadrepository;
		$this->ticket_articuloRepository = $ticket_articulorepository;
		$this->tecnico_ticketRepository = $tecnico_ticketrepository;
		$this->ticketTareaAsignadaNotificacionService = $ticketTareaAsignadaNotificacionService;
		$this->moduloAvisoService = $moduloAvisoService;
    }

	public function guardaTicket($request, $origen = null)
	{
		$data = $request->all();
		$tareasNotificar = [];

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Ticket_Estado::$enumEstado[0]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Ticket";

		// Estado del ticket en el alta como "pendiente"
		$data['estado_ticket'] = Ticket_Estado::$enumEstado[0]['nombre'];
		$data['usuario_id'] = $this->resolverUsuarioIdAlta($data, $origen);
		DB::beginTransaction();
		try
		{
			$ticket = $this->ticketRepository->create($data);

			if ($ticket == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			$tareasAsignacion = [];
			if ($ticket) {
				$resultadoTareas = Self::agrega($data, $ticket, $request);
				$tareasNotificar = $resultadoTareas['tareas_recien_creadas'] ?? [];
				$tareasAsignacion = $resultadoTareas['tareas_asignacion_tecnico'] ?? [];
			}

			DB::commit();

			$this->avisarAltaTecnologiaSiCorresponde($ticket);

			if ($origen === 'administracion') {
				$this->ticketTareaAsignadaNotificacionService->notificar($ticket->id, $tareasNotificar);
				$this->avisarAsignacionTecnico($tareasAsignacion);
			}
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('TicketService::guardaTicket', [
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	/**
	 * En administración se puede abrir el ticket a nombre de otro usuario operativo.
	 * En carga de tickets el dueño es siempre quien está logueado.
	 */
	private function resolverUsuarioIdAlta(array $data, ?string $origen): int
	{
		$authId = (int) Auth::id();
		if ($origen !== 'administracion') {
			return $authId;
		}

		$solicitanteId = (int) ($data['usuario_id'] ?? 0);
		if ($solicitanteId <= 0) {
			return $authId;
		}

		$usuario = UsuarioOperativoSupport::find($solicitanteId);

		return $usuario ? (int) $usuario->id : $authId;
	}

	private function avisarAltaTecnologiaSiCorresponde($ticket): void
	{
		if (! $ticket || ! isset($ticket->id)) {
			return;
		}

		if (! AdministracionTicketListadoFiltros::esAreaSistemas((int) ($ticket->areadestino_id ?? 0))) {
			return;
		}

		$this->moduloAvisoService->enviar('ticket', 'alta_tecnologia', (int) $ticket->id);
	}

	/**
	 * @param  list<int>  $ticketTareaIds
	 */
	private function avisarAsignacionTecnico(array $ticketTareaIds): void
	{
		foreach ($ticketTareaIds as $ticketTareaId) {
			$id = (int) $ticketTareaId;
			if ($id <= 0) {
				continue;
			}

			$this->moduloAvisoService->enviar('ticket', 'asignacion_tecnico', $id);
		}
	}

	// Agrega tablas asociadas
	private function agrega($data, $ticket, $request)
	{
		$ticket_estado = $this->ticket_estadoRepository->create($data, $ticket->id);
		$ticket_archivo = $this->ticket_archivoRepository->create($request, $ticket->id);

		$tareasNotificar = [];
		$tareasAsignacion = [];
		// Si existen las tareas asume que graba desde administracion de tickets
		if (isset($data['tarea_ticket_ids'])) {
			$result = $this->ticket_tareaRepository->create($data, $ticket->id);
			$tareasNotificar = $result['tareas_recien_creadas'] ?? [];
			$tareasAsignacion = $result['tareas_asignacion_tecnico'] ?? [];
		}

		if (isset($data['articulo_ids']))			
			$ticket_articulo = $this->ticket_ArticuloRepository->create($data, $ticket->id);

		return [
			'tareas_recien_creadas' => $tareasNotificar,
			'tareas_asignacion_tecnico' => $tareasAsignacion,
		];
	}

    public function actualizaTicket($request, $id, $origen = null)
    {
		$data = $request->all();
		$tareasNotificar = [];
		$tareasAsignacion = [];

		// Crea estado
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = Ticket_Estado::$enumEstado[0]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
		$data['observacionestados'][] = "Actualiza Ticket";

		DB::beginTransaction();
		try
		{
			$resultadoTareas = Self::actualiza($data, $id, $request);
			$tareasNotificar = $resultadoTareas['tareas_recien_creadas'] ?? [];
			$tareasAsignacion = $resultadoTareas['tareas_asignacion_tecnico'] ?? [];

			DB::commit();

			if ($origen === 'administracion') {
				$this->ticketTareaAsignadaNotificacionService->notificar($id, $tareasNotificar);
				$this->avisarAsignacionTecnico($tareasAsignacion);
			}
		} catch (\Exception $e) {
			DB::rollback();

			dd($e->getMessage());
			
			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		$ticketActual = $this->ticketRepository->find($id);
		$estadoAnterior = (string) ($ticketActual->estado_ticket ?? '');
		$estadoNuevo = (string) ($data['estado_ticket'] ?? $estadoAnterior);
		$data = TicketEstadisticaSupport::aplicarAlGuardar($ticketActual, $data);

		// Graba ticket
		$ticket = $this->ticketRepository->update($data, $id);

		if ($ticket === 'Error')
			throw new Exception('Error en grabacion ticket.');

		if ($estadoNuevo === TicketEstadisticaSupport::ESTADO_FINALIZADO
			&& $estadoAnterior !== TicketEstadisticaSupport::ESTADO_FINALIZADO) {
			$this->ticket_estadoRepository->creaEstado(
				$id,
				Carbon::now(),
				TicketEstadisticaSupport::ESTADO_FINALIZADO,
				Auth::user()->id,
				'Ticket finalizado'
			);
		}

		// Graba movimientos de estados y archivos
		$this->ticket_archivoRepository->update($request, $id);

		$result = $this->ticket_tareaRepository->update($data, $id);
		$ticket_articulo = $this->ticket_articuloRepository->update($data, $id);

		$ticketRefrescado = $this->ticketRepository->find($id);
		$ticketRefrescado->update([
			'tiempo_insumido_total' => TicketEstadisticaSupport::sumarTiempoInsumido((int) $id),
		]);

		return [
			'tareas_recien_creadas' => $result['tareas_recien_creadas'] ?? [],
			'tareas_asignacion_tecnico' => $result['tareas_asignacion_tecnico'] ?? [],
		];
	}

	public function grabaTicketTareaNovedad($data)
	{
		$datosNovedad = json_decode($data['datosNovedades']);

		$ticket_tarea_novedad_ids = [];
		if (count($datosNovedad) > 0)
			// Trae todos los id
        	$ticket_tarea_novedad_ids = $this->ticket_tarea_novedadRepository->
										traeIdPorTicketTarea($datosNovedad[0]->ticket_tarea_id);

		DB::beginTransaction();
		try
		{
			foreach ($datosNovedad as $novedad)
			{
				// Si no tiene id crea el registro
				if ($novedad->ticket_tarea_novedad_id == null || $novedad->ticket_tarea_novedad_id == 'undefined')
				{
					$tarea_novedad = $this->ticket_tarea_novedadRepository->createUnique((array) $novedad);	

					// Agrega historia al ticket
					// Busca la tarea para sacar datos
					$ticket_tarea = $this->ticket_tareaRepository->find($novedad->ticket_tarea_id);

					if ($ticket_tarea)
						$this->ticket_estadoRepository->creaEstado($ticket_tarea->ticket_id, Carbon::now(), $tarea_novedad->estado,
										Auth::user()->id, $tarea_novedad->comentario.' '.$ticket_tarea->tareas->nombre);
				}
				else
					$tarea_novedad = $this->ticket_tarea_novedadRepository->updateUnique((array) $novedad, 
						$novedad->ticket_tarea_novedad_id);	
			}
			// Borra registros anteriores que no esten en la tabla datosNovedad
			for ($i = 0; $i < count($ticket_tarea_novedad_ids); $i++)
			{
				// Busca que no exista en las novedades enviadas para grabar
				for ($j = 0, $flEncontro = false; $j < count($datosNovedad); $j++)
				{
					if ($ticket_tarea_novedad_ids[$i] == $datosNovedad[$j]->ticket_tarea_novedad_id)
					{
						$flEncontro = true;
						break;
					}
				}
				// Si no existe la borra
				if (!$flEncontro)
					$this->ticket_tarea_novedadRepository->delete($ticket_tarea_novedad_ids[$i]);
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			dd($e->getMessage());
			
			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	public function leeTicketTareaNovedad($ticket_tarea_id)
	{
		return $this->ticket_tarea_novedadRepository->leeTicketTareaNovedad($ticket_tarea_id);
	}

	public function leeHistoriaTicket($ticket_id)
	{
		return $this->ticket_estadoRepository->leeHistoriaTicket($ticket_id);
	}

	public function cambiarTecnico($ticket_tarea_id, $tecnico_ticket_id)
	{
		$tecnico_ticket = $this->tecnico_ticketRepository->findOperativo($tecnico_ticket_id);
		if ($tecnico_ticket === null) {
			return response('ng', 422);
		}

		$ticket_tarea = $this->ticket_tareaRepository->find($ticket_tarea_id);
		$tecnicoAnteriorId = (int) ($ticket_tarea->tecnico_id ?? 0);

		$novedad = [
					"ticket_tarea_id" => $ticket_tarea_id,
					"desdefecha" => Carbon::now(),
					"hastafecha" => Carbon::now(),
					"comentario" => "Asigna técnico ".$tecnico_ticket->nombre,
					"estado" => Ticket_Tarea_Novedad::$enumEstado[7]['nombre'],
					"usuario_id" => Auth::user()->id
		];

		$tarea_novedad = $this->ticket_tarea_novedadRepository->createUnique($novedad);

		if ($ticket_tarea)
		{
			$this->ticket_estadoRepository->creaEstado($ticket_tarea->ticket_id, Carbon::now(), $tarea_novedad->estado,
							Auth::user()->id, $tarea_novedad->comentario.' '.$ticket_tarea->tareas->nombre);

			$ticket_tarea->update(['tecnico_id' => $tecnico_ticket_id]);

			if ((int) $tecnico_ticket_id !== $tecnicoAnteriorId) {
				$this->avisarAsignacionTecnico([(int) $ticket_tarea->id]);
			}
		}

		return 'ok';
	}

	public function finalizarTarea($ticket_tarea_id, $fechafinalizacion, $tiempoinsumido)
	{
		$novedad = [
					"ticket_tarea_id" => $ticket_tarea_id,
					"desdefecha" => Carbon::now(),
					"hastafecha" => Carbon::now(),
					"comentario" => "Finaliza tarea",
					"estado" => Ticket_Tarea_Novedad::$enumEstado[2]['nombre'],
					"usuario_id" => Auth::user()->id
		];

		$tarea_novedad = $this->ticket_tarea_novedadRepository->createUnique($novedad);

		$ticket_tarea = $this->ticket_tareaRepository->find($ticket_tarea_id);

		$estadisticas = [
			'estado_ticket' => '',
			'fecha_resolucion' => '',
			'hora_resolucion' => '',
			'tiempo_insumido_total' => 0,
			'cerro_ticket' => false,
		];

		if ($ticket_tarea)
		{
			$this->ticket_estadoRepository->creaEstado($ticket_tarea->ticket_id, Carbon::now(), $tarea_novedad->estado,
							Auth::user()->id, $tarea_novedad->comentario.' '.$ticket_tarea->tareas->nombre);

			$ticket_tarea->update(['fechafinalizacion' => $fechafinalizacion,
									'tiempoinsumido' => $tiempoinsumido]);

			$ticket = $this->ticketRepository->find($ticket_tarea->ticket_id);
			$estadisticas = TicketEstadisticaSupport::sincronizarTrasFinalizarTarea($ticket);

			if (! empty($estadisticas['sello_nuevo'])) {
				$this->ticket_estadoRepository->creaEstado(
					$ticket->id,
					Carbon::now(),
					TicketEstadisticaSupport::ESTADO_FINALIZADO,
					Auth::user()->id,
					'Ticket finalizado: todas las tareas cerradas'
				);
			}
		}

		return [
			'mensaje' => 'ok',
			'estado_ticket' => $estadisticas['estado_ticket'],
			'fecha_resolucion' => $estadisticas['fecha_resolucion'],
			'hora_resolucion' => $estadisticas['hora_resolucion'],
			'tiempo_insumido_total' => $estadisticas['tiempo_insumido_total'],
			'cerro_ticket' => ! empty($estadisticas['cerro_ticket']),
		];
	}

	public function cambiarEstadoTarea($ticket_tarea_id, string $estado): array
	{
		$estadosValidos = array_column(Ticket_Tarea_Novedad::$enumEstado, 'nombre');
		if (! in_array($estado, $estadosValidos, true)) {
			throw new \InvalidArgumentException('Estado de tarea inválido.');
		}

		$ticket_tarea = $this->ticket_tareaRepository->find($ticket_tarea_id);
		if (! $ticket_tarea) {
			throw new \InvalidArgumentException('Tarea no encontrada.');
		}

		$estadoActual = $ticket_tarea->estadoVisual();
		if ($estadoActual === $estado) {
			return ['mensaje' => 'ok', 'estado' => $estado];
		}

		$novedad = [
			'ticket_tarea_id' => $ticket_tarea_id,
			'desdefecha' => Carbon::now(),
			'hastafecha' => Carbon::now(),
			'comentario' => 'Cambio de estado a '.$estado,
			'estado' => $estado,
			'usuario_id' => Auth::user()->id,
		];

		$tarea_novedad = $this->ticket_tarea_novedadRepository->createUnique($novedad);

		$this->ticket_estadoRepository->creaEstado(
			$ticket_tarea->ticket_id,
			Carbon::now(),
			$tarea_novedad->estado,
			Auth::user()->id,
			$tarea_novedad->comentario.' '.($ticket_tarea->tareas->nombre ?? '')
		);

		return ['mensaje' => 'ok', 'estado' => $estado];
	}
}