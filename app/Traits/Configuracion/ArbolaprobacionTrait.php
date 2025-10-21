<?php

namespace App\Traits\Configuracion;

trait ArbolaprobacionTrait {

	public static $enumTipoArbol = [
		['id' => '1', 'valor' => 'RE', 'nombre'  => 'Requisiciones'],
		['id' => '2', 'valor' => 'OC', 'nombre'  => 'Ordenes de compra'],
		['id' => '3', 'valor' => 'SP', 'nombre'  => 'Solicitudes de pago'],
		['id' => '4', 'valor' => 'OV', 'nombre'  => 'Ordenes de venta'],
			];

	public static $enumRecordatorio = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'No Envía mail recordatorio'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'Envía mail recordatorio'],
			];

	public static $enumEstado = [
		['id' => '1', 'valor' => 'A', 'nombre'  => 'Activo'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'Suspendido'],
			];

}

