<?php

namespace App\Traits\Ticket;

trait Tarea_TicketTrait {

	public static $enumTipoTarea = [
		['id' => '1', 'valor' => 'M', 'nombre'  => 'Manual'],
		['id' => '2', 'valor' => 'P', 'nombre'  => 'Programada'],
			];

	public static $enumEnviaCorreo = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'No envía correo'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'Envía correo'],
			];				
}

