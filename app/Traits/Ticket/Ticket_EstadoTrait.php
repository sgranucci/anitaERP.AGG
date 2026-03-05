<?php

namespace App\Traits\Ticket;

trait Ticket_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'P', 'nombre'  => 'Sin Asignar'],
		['id' => '2', 'valor' => 'A', 'nombre'  => 'Pendiente'],
		['id' => '3', 'valor' => 'E', 'nombre'  => 'En ejecución'],
		['id' => '4', 'valor' => 'F', 'nombre'  => 'Finalizado'],
		['id' => '5', 'valor' => 'S', 'nombre'  => 'Suspendido'],
		//['id' => '6', 'valor' => 'B', 'nombre'  => 'Baja'],
		['id' => '6', 'valor' => 'R', 'nombre'  => 'Reasignar'],
			];
}

