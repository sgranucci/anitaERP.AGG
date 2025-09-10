<?php

use App\Models\Admin\Permiso;

if (!function_exists('getMenuActivo')) {
    function getMenuActivo($ruta)
    {
        if (request()->is($ruta) || request()->is($ruta . '/*')) {
            return 'active';
        } else {
            return '';
        }
    }
}

if (!function_exists('canUser')) {
    function can($permiso, $redirect = true)
    {
        if (session()->get('rol_nombre') == 'administrador') {
            return true;
        } else {
            $rolId = session()->get('rol_id');
            $permisos = cache()->tags('Permiso')->rememberForever("Permiso.rolid.$rolId", function () {
                return Permiso::whereHas('roles', function ($query) {
                    $query->where('rol_id', session()->get('rol_id'));
                })->get()->pluck('slug')->toArray();
            });

            if (!in_array($permiso, $permisos)) {
                if ($redirect) {
                    if (!request()->ajax())
                        return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para entrar en este modulo')->send();
                    abort(403, 'No tiene permiso');
                } else {
                    return false;
                }
            }
            return true;
        }
    }
}

/**
 * Funcion para devolver la fecha inicial y final de una
 * semana dada.
 *
 * @param integer $week
 * @param integer $year
 *
 * @return array array con clave->valor
 */
function getFirstDayWeek($week, $year)
{
    $dt = new DateTime();
    $return['start'] = $dt->setISODate($year, $week)->format('Y-m-d');
    $return['end'] = $dt->modify('+6 days')->format('Y-m-d');
    return $return;
}

// Calcula consumo 

function calculaConsumo(&$consumo, $nombretalle, $cantidad, $consumo1, $consumo2, $consumo3, $consumo4)
{
    $consumo = 0;
	if ($nombretalle >= config('consprod.DESDE_INTERVALO1') && $nombretalle <= config('consprod.HASTA_INTERVALO1'))
    	$consumo = $cantidad * $consumo1;
	if ($nombretalle >= config('consprod.DESDE_INTERVALO2') && $nombretalle <= config('consprod.HASTA_INTERVALO2'))
		$consumo = $cantidad * $consumo2;
	if ($nombretalle >= config('consprod.DESDE_INTERVALO3') && $nombretalle <= config('consprod.HASTA_INTERVALO3'))
		$consumo = $cantidad * $consumo3;
	if ($nombretalle >= config('consprod.DESDE_INTERVALO4') && $nombretalle <= config('consprod.HASTA_INTERVALO4'))
		$consumo = $cantidad * $consumo4;
}

// Genera rango de articulos para reportes

function generaRangoArticulo($desdearticulo_id, $hastaarticulo_id, $articuloQuery)
{
    // Prepara titulos de rangos
    $desdeArticuloRango = $hastaArticuloRango = '';
    if ($desdearticulo_id == 0)
        $desdeArticulo = 'Primero';
    else
    {
        $articulo = $articuloQuery->traeArticuloPorId($desdearticulo_id);
        if ($articulo)
        {
            $desdeArticulo = $articulo->descripcion;
            $desdeArticuloRango = $articulo->descripcion;
        }
        else	
        {
            $desdeArticulo = '--';
            $desdeArticuloRango = '';
        }
    }
    
    if ($hastaarticulo_id == 99999999)
        $hastaArticulo = 'Ultimo';
    else
    {
        $articulo = $articuloQuery->traeArticuloPorId($hastaarticulo_id);
        if ($articulo)
        {
            $hastaArticulo = $articulo->descripcion;
            $hastaArticuloRango = $articulo->descripcion;
        }
        else	
            $hastaArticulo = '--';
    }
    return ['desdearticulotitulo' => $desdeArticulo, 'hastaarticulotitulo' => $hastaArticulo,
            'desdearticulorango' => $desdeArticuloRango, 'hastaarticulorango' => $hastaArticuloRango];
}

// Genera keys para guardar datos en cache por usuario

function generaKey($key)
{
    return $key.'-'.auth()->id();
}

// Redondea numeros
function redondear($n, $dec, $prec) 
{
    $red = Round($n, $dec);
    $ent = floor($red); // Parte entera
    $dec = $red - $ent; // Parte decimal
    $r = ceil($dec / $prec) * $prec; // Decimal redondeado
    
    return $ent + ($r / 100);
}

// Extrae valores del checkbox para cuando se usan en un array y se pasan por formulario a php

function getAllChkboxValues($chk_name) {
    $found = array(); //create a new array 
    foreach($chk_name as $key => $val) {
        //echo "KEY::".$key."VALue::".$val."<br>";
        if($val == '1') { //replace '1' with the value you want to search
            $found[] = $key;
        }
    }
    foreach($found as $kev_f => $val_f) {
        unset($chk_name[$val_f-1]); //unset the index of un-necessary values in array
    }   
    $final_arr = array(); //create the final array
    return $final_arr = array_values($chk_name); //sort the resulting array again
}

function calculaCoeficienteMoneda($aMoneda, $deMoneda, $cotizacion)
{
    if ($aMoneda == $deMoneda)
        return 1.;

    if ($aMoneda == 1)
        return $cotizacion;

    if ($aMoneda > 1 && $deMoneda == 1)
        return 1/$cotizacion;

    // Faltaria definir bien conversiones entre monedas sin pasar por el peso
    if ($aMoneda > 1 && $deMoneda > 1)
        return $cotizacion;

    return 1.;
}

function chequeaPermisoTicket()
{
    // Verifica permisos
    $flUsuario = $flTecnico = $flSupervisor = $flEncargado = false;

    $rolId = session()->get('rol_id');
    $permisos = cache()->tags('Permiso')->rememberForever("Permiso.rolid.$rolId", function () {
            return Permiso::whereHas('roles', function ($query) {
                $query->where('rol_id', session()->get('rol_id'));
            })->get()->pluck('slug')->toArray();
        });
    $permiso = '';
    if (in_array('usuario-ticket', $permisos)) 
        $permiso = 'usuario';        

    if (in_array('tecnico-ticket', $permisos))   
        $permiso = 'tecnico';

    if (in_array('encargado-ticket', $permisos))   
        $permiso = 'encargado';

    if (in_array('supervisor-ticket', $permisos))   
        $permiso = 'supervisor';

    return $permiso;
}


