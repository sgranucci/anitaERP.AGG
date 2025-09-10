<?php

namespace App\Traits\Ticket;

trait Ticket_Tarea_NovedadTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'P', 'nombre'  => 'Pendiente'],
		['id' => '2', 'valor' => 'E', 'nombre'  => 'En ejecución'],
		['id' => '3', 'valor' => 'F', 'nombre'  => 'Finalizada'],
		['id' => '4', 'valor' => 'W', 'nombre'  => 'En espera'],
		['id' => '5', 'valor' => 'S', 'nombre'  => 'Suspendida'],
		['id' => '6', 'valor' => 'B', 'nombre'  => 'Baja'],
		['id' => '7', 'valor' => 'R', 'nombre'  => 'Reasignar'],
		['id' => '8', 'valor' => 'A', 'nombre'  => 'Asignada'],
			];
}

