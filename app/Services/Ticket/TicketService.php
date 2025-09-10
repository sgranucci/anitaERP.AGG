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

    public function __construct(TicketRepositoryInterface $ticketrepository,
                                Ticket_EstadoRepositoryInterface $ticket_estadorepository,
                                Ticket_ArchivoRepositoryInterface $ticket_archivorepository,
								Ticket_TareaRepositoryInterface $ticket_tarearepository,
								Ticket_Tarea_NovedadRepositoryInterface $ticket_tarea_novedadrepository,
								Tecnico_TicketRepositoryInterface $tecnico_ticketrepository,
								Ticket_ArticuloRepositoryInterface $ticket_articulorepository
								)
    {
		$this->ticketRepository = $ticketrepository;
        $this->ticket_estadoRepository = $ticket_estadorepository;
        $this->ticket_archivoRepository = $ticket_archivorepository;
		$this->ticket_tareaRepository = $ticket_tarearepository;
		$this->ticket_tarea_novedadRepository = $ticket_tarea_novedadrepository;
		$this->ticket_articuloRepository = $ticket_articulorepository;
		$this->tecnico_ticketRepository = $tecnico_ticketrepository;
    }

	public function guardaTicket($request, $origen = null)
	{
		$data = $request->all();

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Ticket_Estado::$enumEstado[0]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Ticket";

		DB::beginTransaction();
		try
		{
			$ticket = $this->ticketRepository->create($request->all());

			if ($ticket == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($ticket)
				Self::agrega($data, $ticket, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			// Borra el asiento creado

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	// Agrega tablas asociadas
	private function agrega($data, $ticket, $request)
	{
		$ticket_estado = $this->ticket_estadoRepository->create($data, $ticket->id);
		$ticket_archivo = $this->ticket_archivoRepository->create($request, $ticket->id);

		// Si existen las tareas asume que graba desde administracion de tickets
		if (isset($data['tarea_ids']))
			$ticket_tarea = $this->ticket_TareaRepository->create($data, $ticket->id);

		if (isset($data['articulo_ids']))			
			$ticket_articulo = $this->ticket_ArticuloRepository->create($data, $ticket->id);
	}

    public function actualizaTicket($request, $id, $origen = null)
    {
		$data = $request->all();

		// Crea estado
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = Ticket_Estado::$enumEstado[0]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
		$data['observacionestados'][] = "Actualiza Ticket";

		DB::beginTransaction();
		try
		{
			Self::actualiza($data, $id, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			dd($e->getMessage());
			
			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		// Graba ticket
		$ticket = $this->ticketRepository->update($data, $id);

		if ($ticket === 'Error')
			throw new Exception('Error en grabacion ticket.');

		// Graba movimientos de estados y archivos
		$this->ticket_archivoRepository->update($request, $id);

		$ticket_tarea = $this->ticket_tareaRepository->update($data, $id);
		$ticket_articulo = $this->ticket_articuloRepository->update($data, $id);
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
		$tecnico_ticket = $this->tecnico_ticketRepository->findOrFail($tecnico_ticket_id);

		$novedad = [
					"ticket_tarea_id" => $ticket_tarea_id,
					"desdefecha" => Carbon::now(),
					"hastafecha" => Carbon::now(),
					"comentario" => "Asigna técnico ".$tecnico_ticket->nombre,
					"estado" => Ticket_Tarea_Novedad::$enumEstado[7]['nombre'],
					"usuario_id" => Auth::user()->id
		];

		$tarea_novedad = $this->ticket_tarea_novedadRepository->createUnique($novedad);

		$ticket_tarea = $this->ticket_tareaRepository->find($ticket_tarea_id);

		if ($ticket_tarea)
		{
			$this->ticket_estadoRepository->creaEstado($ticket_tarea->ticket_id, Carbon::now(), $tarea_novedad->estado,
							Auth::user()->id, $tarea_novedad->comentario.' '.$ticket_tarea->tareas->nombre);

			$ticket_tarea->update(['tecnico_id' => $tecnico_ticket_id]);
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

		if ($ticket_tarea)
		{
			$this->ticket_estadoRepository->creaEstado($ticket_tarea->ticket_id, Carbon::now(), $tarea_novedad->estado,
							Auth::user()->id, $tarea_novedad->comentario.' '.$ticket_tarea->tareas->nombre);

			$ticket_tarea->update(['fechafinalizacion' => $fechafinalizacion,
									'tiempoinsumido' => $tiempoinsumido]);
		}

		return 'ok';
	}	
}