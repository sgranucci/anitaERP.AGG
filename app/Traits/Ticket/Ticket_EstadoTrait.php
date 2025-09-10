<?php

namespace App\Traits\Ticket;

trait Ticket_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'P', 'nombre'  => 'Pendiente'],
		['id' => '2', 'valor' => 'A', 'nombre'  => 'Asignado'],
		['id' => '3', 'valor' => 'E', 'nombre'  => 'En ejecución'],
		['id' => '3', 'valor' => 'F', 'nombre'  => 'Finalizado'],
		['id' => '4', 'valor' => 'S', 'nombre'  => 'Suspendido'],
		['id' => '5', 'valor' => 'B', 'nombre'  => 'Baja'],
		['id' => '6', 'valor' => 'R', 'nombre'  => 'Reasignar'],
			];
}

