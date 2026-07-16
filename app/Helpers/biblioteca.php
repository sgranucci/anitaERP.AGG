<?php

use App\Models\Admin\Permiso;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

if (!function_exists('aplanarUrlsMenu')) {
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<string>
     */
    function aplanarUrlsMenu(array $items): array
    {
        $urls = [];

        foreach ($items as $item) {
            $url = trim((string) ($item['url'] ?? ''), '/');
            if ($url !== '' && $url !== '#') {
                $urls[] = $url;
            }

            if (! empty($item['submenu']) && is_array($item['submenu'])) {
                $urls = array_merge($urls, aplanarUrlsMenu($item['submenu']));
            }
        }

        return $urls;
    }
}

if (!function_exists('urlsMenuFrontResueltas')) {
    /**
     * URLs de menú visibles para el rol actual (misma fuente que el aside).
     *
     * @return list<string>
     */
    function urlsMenuFrontResueltas(): array
    {
        static $cacheRolId = null;
        static $cacheUrls = null;

        $rolId = (int) session()->get('rol_id', 0);
        if ($cacheRolId === $rolId && $cacheUrls !== null) {
            return $cacheUrls;
        }

        $cacheRolId = $rolId;
        if ($rolId <= 0) {
            $cacheUrls = [];

            return $cacheUrls;
        }

        $nivelActual = 0;
        $menus = \App\Models\Admin\Menu::getMenu(true, $nivelActual);
        $menus = \App\Support\Caja\Estacionamiento\EstacionamientoModuloSupport::filtrarMenuAside($menus);
        $menus = \App\Support\Caja\Bingo\BingoModuloSupport::filtrarMenuAside($menus);
        $cacheUrls = array_values(array_unique(aplanarUrlsMenu($menus)));

        return $cacheUrls;
    }
}

if (!function_exists('menuUrlCoincideConRuta')) {
    function menuUrlCoincideConRuta(string $menuUrl, string $path): bool
    {
        $menuUrl = trim($menuUrl, '/');
        if ($menuUrl === '' || $menuUrl === '#') {
            return false;
        }

        return $path === $menuUrl || str_starts_with($path, $menuUrl.'/');
    }
}

if (!function_exists('menuUrlMasEspecificaParaRuta')) {
    function menuUrlMasEspecificaParaRuta(string $path): ?string
    {
        $path = trim($path, '/');
        $mejor = null;
        $mejorLongitud = -1;

        foreach (urlsMenuFrontResueltas() as $url) {
            if (! menuUrlCoincideConRuta($url, $path)) {
                continue;
            }

            $longitud = strlen($url);
            if ($longitud > $mejorLongitud) {
                $mejor = $url;
                $mejorLongitud = $longitud;
            }
        }

        return $mejor;
    }
}

if (!function_exists('getMenuActivo')) {
    function getMenuActivo($ruta)
    {
        $ruta = trim((string) $ruta, '/');
        if ($ruta === '' || $ruta === '#') {
            return '';
        }

        $urlsMenu = urlsMenuFrontResueltas();
        if ($urlsMenu !== []) {
            $activa = menuUrlMasEspecificaParaRuta(trim(request()->path(), '/'));

            return $activa === $ruta ? 'active' : '';
        }

        if (request()->is($ruta) || request()->is($ruta.'/*')) {
            return 'active';
        }

        return '';
    }
}

if (!function_exists('menuItemEsActivoOAncestro')) {
    /**
     * Indica si el ítem o algún descendiente coincide con la ruta actual (para expandir el árbol en servidor).
     *
     * @param  array<string, mixed>  $item
     */
    function menuItemEsActivoOAncestro(array $item): bool
    {
        $url = $item['url'] ?? '';
        if ($url !== '' && getMenuActivo($url) === 'active') {
            return true;
        }

        foreach ($item['submenu'] ?? [] as $submenu) {
            if (menuItemEsActivoOAncestro($submenu)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('clasesIconoMenu')) {
    /**
     * Clases CSS Font Awesome para un ícono de menú (mismo criterio que admin/menu y sidebar).
     */
    function clasesIconoMenu(?string $icono, string $default = 'fa-circle'): string
    {
        $icono = trim((string) $icono);
        if ($icono === '') {
            $icono = $default;
        }

        if (preg_match('/^(fa|fas|far|fab|fal|fad)\s+/', $icono)) {
            return $icono;
        }

        if (str_contains($icono, 'fa-')) {
            return 'fa '.$icono;
        }

        return 'fa fa-'.ltrim($icono, '-');
    }
}

if (!function_exists('canUser')) {
    function can($permiso, $redirect = true)
    {
        $url = Request::url();
        $urlPermitida = "anitaERP/public/ordenventa/visualizar";
        $pos = strpos($url, $urlPermitida);
        if ($pos !== false)
            return(true);
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

function traePermisosUsuario()
{
    $rolId = session()->get('rol_id');
    $permisos = cache()->tags('Permiso')->rememberForever("Permiso.rolid.$rolId", function () {
        return Permiso::whereHas('roles', function ($query) {
            $query->where('rol_id', session()->get('rol_id'));
        })->get()->pluck('slug')->toArray();
    });

    return ['rol_id' => $rolId, 'permisos' => $permisos];
}

/**
 * Perfil UIF para vistas cliente_uif: supervisor (completo), cajero (restringido) u operador.
 */
if (!function_exists('perfilClienteUif')) {
    function perfilClienteUif(): string
    {
        if (session()->get('rol_nombre') == 'administrador') {
            return 'supervisor';
        }
        $p = traePermisosUsuario()['permisos'];
        if (in_array('supervisor-uif', $p, true)) {
            return 'supervisor';
        }
        if (in_array('cajero-uif', $p, true)) {
            return 'cajero';
        }

        return 'operador';
    }
}

if (!function_exists('esSupervisorUif')) {
    function esSupervisorUif(): bool
    {
        return perfilClienteUif() === 'supervisor';
    }
}

/** Cajero UIF sin permiso de supervisor (ni administrador). */
if (!function_exists('esCajeroUifSinSupervisor')) {
    function esCajeroUifSinSupervisor(): bool
    {
        return perfilClienteUif() === 'cajero';
    }
}

/** Solo listado/visualización: sin slug cajero-uif ni supervisor-uif (perfil operador). */
if (!function_exists('esSoloVisualizacionClienteUif')) {
    function esSoloVisualizacionClienteUif(): bool
    {
        return perfilClienteUif() === 'operador';
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

/**
 * Grilla de ítems estilo pedido en factura (EL BIERZO).
 * Usa config/facturacion.php y, si falta en caché, el nombre de empresa.
 */
function facturaUsaLayoutItemsPedido(): bool
{
    $layout = config('facturacion.LAYOUT_ITEMS_PEDIDO');

    if ($layout !== null) {
        return (bool) $layout;
    }

    return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
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
    if ($aMoneda == $deMoneda) {
        return 1.;
    }

    // leeCotizacionDiaria (y similares) puede devolver ['cotizacionventa' => null, ...].
    // isset(null) es false y antes se asignaba el array entero → TypeError en float * array.
    if (is_array($cotizacion)) {
        $cotizacionVenta = $cotizacion['cotizacionventa'] ?? 0;
    } else {
        $cotizacionVenta = $cotizacion;
    }
    $cotizacionVenta = (float) $cotizacionVenta;

    if ($aMoneda == 1) {
        return $cotizacionVenta;
    }

    if ($aMoneda > 1 && $deMoneda == 1) {
        return $cotizacionVenta != 0.0 ? 1 / $cotizacionVenta : 0.;
    }

    // Faltaria definir bien conversiones entre monedas sin pasar por el peso
    if ($aMoneda > 1 && $deMoneda > 1) {
        return $cotizacionVenta;
    }

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

function validarHora($hora, $formato = 'H:i') {
    $d = DateTime::createFromFormat($formato, $hora);
    return $d && $d->format($formato) === $hora;
}

function validarFormatoHora(string $hora): bool {
    // La expresión regular busca un patrón H:M:S
    // HH: 00 a 23
    // MM: 00 a 59
    // SS: 00 a 59
    $patron = '/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/';
    return preg_match($patron, $hora) === 1;
}

function parsePeriodoMesAnio($periodo): array
{
    $periodo = trim((string) $periodo);
    if ($periodo === '') {
        throw new InvalidArgumentException('Período vacío');
    }

    if (strpos($periodo, '-') !== false) {
        $per = explode('-', $periodo, 2);
    } elseif (strpos($periodo, '/') !== false) {
        $per = explode('/', $periodo, 2);
    } else {
        throw new InvalidArgumentException('Formato de período no válido: '.$periodo);
    }

    $a = (int) $per[0];
    $b = (int) $per[1];

    // YYYY-MM (input type=month / ISO)
    if ($a > 12) {
        return ['mes' => $b, 'anio' => $a];
    }

    // MM-YYYY o MM/YYYY (legacy datepicker)
    return ['mes' => $a, 'anio' => $b];
}

function normalizarPeriodoParaUrl($periodo): string
{
    $partes = parsePeriodoMesAnio($periodo);

    return sprintf('%04d-%02d', $partes['anio'], $partes['mes']);
}

function conviertePeriodoEnRangoFecha($periodo, $flHora = null)
{
    // En base al periodo arma rango de fechas (MM/YYYY, MM-YYYY o YYYY-MM)
    $partes = parsePeriodoMesAnio($periodo);
    $mes = $partes['mes'];
    $anio = $partes['anio'];
    $dias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $fecha = sprintf('%04d-%02d-01', $anio, $mes);
    if ($flHora)
    {
        $hora_string = '00:00:00';
        $desdeFecha = Carbon::createFromFormat('Y-m-d H:i:s', $fecha.' '.$hora_string); // Pasa a formato fecha
    }
    else
    {
        $fechaFormateada = Carbon::createFromFormat('Y-m-d', $fecha); // Pasa a formato fecha
        $desdeFecha = $fechaFormateada->format('Y-m-d');
    }

    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dias);
    if ($flHora)
    {
        $hora_string = '23:59:59';
        $hastaFecha = Carbon::createFromFormat('Y-m-d H:i:s', $fecha.' '.$hora_string); // Pasa a formato fecha
    }
    else
    {
        $fechaFormateada = Carbon::createFromFormat('Y-m-d', $fecha); // Pasa a formato fecha
        $hastaFecha = $fechaFormateada->format('Y-m-d');
    }

    return ['desdefecha' => $desdeFecha, 'hastafecha' => $hastaFecha];
}

function specialChars($str, $chars = null) {

    if ($chars)
        $specialChars = $chars;
    else
        $specialChars = '!@#$%^&*()-_=+[{]};:\'",<.>/?\\|';
    
    return strpbrk($str, $specialChars) !== false;
}

function convertirFecha($fechaString, $formatoEntrada) {
    // 1. Validar el formato
    $d = DateTime::createFromFormat($formatoEntrada, $fechaString);
    
    // 2. Verificar si es una fecha válida y si el formato coincide
    if ($d && $d->format($formatoEntrada) === $fechaString) {
        // 3. Convertir al formato AAAA-MM-DD
        return $d->format('Y-m-d');
    }
    
    return false; // Formato incorrecto
}

if (! function_exists('urlAppCarpeta')) {
    /**
     * URL bajo APP_CARPETA (ej. /anitaERP/public/compras/...).
     */
    function urlAppCarpeta(string $path = ''): string
    {
        $base = rtrim((string) config('app.app_carpeta', ''), '/');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}

if (! function_exists('rutaAppRelativa')) {
    /**
     * Ruta relativa sin APP_CARPETA (para AJAX: carpetaBase + '/' + rutaAppRelativa(...)).
     */
    function rutaAppRelativa(string $routeName, array $params = []): string
    {
        $path = ltrim(parse_url(route($routeName, $params), PHP_URL_PATH) ?: '', '/');
        $carpeta = ltrim(rtrim((string) config('app.app_carpeta', ''), '/'), '/');
        if ($carpeta !== '' && ($path === $carpeta || str_starts_with($path, $carpeta.'/'))) {
            $path = ltrim(substr($path, strlen($carpeta)), '/');
        }

        return $path;
    }
}

if (! function_exists('urlAppDesdeRoute')) {
    /**
     * Path bajo APP_CARPETA a partir de una ruta nombrada (AJAX / navegación interna).
     * En petición HTTP, route() puede devolver el path ya prefijado con APP_CARPETA.
     */
    function urlAppDesdeRoute(string $routeName, array $params = []): string
    {
        $path = parse_url(route($routeName, $params), PHP_URL_PATH) ?: '';
        $carpeta = rtrim((string) config('app.app_carpeta', ''), '/');
        if ($carpeta !== '' && ($path === $carpeta || str_starts_with($path, $carpeta.'/'))) {
            return $path;
        }

        return urlAppCarpeta(ltrim($path, '/'));
    }
}

if (! function_exists('urlAppAbsoluta')) {
    /**
     * URL absoluta con host y APP_CARPETA (para mails y enlaces externos).
     */
    function urlAppAbsoluta(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/').urlAppCarpeta($path);
    }
}

if (! function_exists('puedeVerPrecargaFacturaPdf')) {
    function puedeVerPrecargaFacturaPdf(): bool
    {
        return can('listar-precarga-proveedores', false)
            || can('editar-precarga-proveedores', false);
    }
}


