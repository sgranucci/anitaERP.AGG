<?php
namespace App\Services\Compras;

use App\Repositories\Compras\EncuestaRepositoryInterface;
use App\Repositories\Compras\Encuesta_PreguntaRepositoryInterface;
use App\Models\Compras\Encuesta_Pregunta;
use App\Models\Compras\Encuesta;
use App\Mail\Compras\MailArbolAprobacion;
use Carbon\Carbon;
use Mail;
use Auth;
use DB;

class EncuestaService 
{
	private $encuestaRepository;
	private $encuesta_preguntaRepository;

	public function __construct(EncuestaRepositoryInterface $encuestarepository,
								Encuesta_PreguntaRepositoryInterface $encuesta_preguntarepository)
	{
		$this->encuestaRepository = $encuestarepository;
		$this->encuesta_preguntaRepository = $encuesta_preguntarepository;
	}

	public function enviaCorreo()
	{
	}

}

