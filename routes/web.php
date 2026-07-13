<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.update');

Route::get('/', 'InicioController@index')->name('inicio');
Route::get('seguridad/login', 'Seguridad\LoginController@index')->name('login');
Route::get('seguridad/cambia_password', 'Seguridad\HomeController@cambiaPassword')->name('cambia_password');
Route::post('seguridad/graba_password', 'Seguridad\HomeController@grabaPassword')->name('graba_password');
Route::post('seguridad/login', 'Seguridad\LoginController@login')->name('login_post');
Route::get('seguridad/logout', 'Seguridad\LoginController@logout')->name('logout');
Route::post('ajax-sesion', 'AjaxController@setSession')->name('ajax')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('seguridad/barra-tareas', 'Seguridad\BarraTareasController@index')->name('barra_tareas_index');
    Route::get('seguridad/barra-tareas/menus', 'Seguridad\BarraTareasController@menusDisponibles')->name('barra_tareas_menus');
    Route::post('seguridad/barra-tareas/anclar', 'Seguridad\BarraTareasController@anclar')->name('barra_tareas_anclar');
    Route::post('seguridad/barra-tareas/desanclar', 'Seguridad\BarraTareasController@desanclar')->name('barra_tareas_desanclar');
    Route::post('seguridad/barra-tareas/reordenar', 'Seguridad\BarraTareasController@reordenar')->name('barra_tareas_reordenar');
});

Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('auth')->name('csrf_token_refresh');

// ARCA - Padrón (Constancia de Inscripción)
Route::post('arca/constancia-inscripcion', 'Arca\ConstanciaInscripcionController@consultar')
    ->name('arca_constancia_inscripcion')
    ->middleware('auth');

Route::group(['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => ['auth', 'superadmin']], function () {
    Route::get('', 'AdminController@index');
    /* RUTAS DE USUARIO */
    Route::get('usuario', 'UsuarioController@index')->name('usuario');
    Route::get('usuario/crear', 'UsuarioController@crear')->name('crear_usuario');
    Route::post('usuario', 'UsuarioController@guardar')->name('guardar_usuario');
    Route::get('usuario/{id}/editar', 'UsuarioController@editar')->name('editar_usuario');
    Route::put('usuario/{id}', 'UsuarioController@actualizar')->name('actualizar_usuario');
    Route::delete('usuario/{id}', 'UsuarioController@eliminar')->name('eliminar_usuario');

    /* RUTAS DE PERMISO */
    Route::get('permiso', 'PermisoController@index')->name('permiso');
    Route::get('permiso/crear', 'PermisoController@crear')->name('crear_permiso');
    Route::post('permiso', 'PermisoController@guardar')->name('guardar_permiso');
    Route::get('permiso/{id}/editar', 'PermisoController@editar')->name('editar_permiso');
    Route::put('permiso/{id}', 'PermisoController@actualizar')->name('actualizar_permiso');
    Route::delete('permiso/{id}', 'PermisoController@eliminar')->name('eliminar_permiso');
    /* RUTAS DEL MENU */
    Route::get('menu', 'MenuController@index')->name('menu');
    Route::get('menu/crear', 'MenuController@crear')->name('crear_menu');
    Route::post('menu', 'MenuController@guardar')->name('guardar_menu');
    Route::get('menu/{id}/editar', 'MenuController@editar')->name('editar_menu');
    Route::put('menu/{id}', 'MenuController@actualizar')->name('actualizar_menu');
    Route::get('menu/{id}/eliminar', 'MenuController@eliminar')->name('eliminar_menu');
    Route::post('menu/guardar-orden', 'MenuController@guardarOrden')->name('guardar_orden');
    /* RUTAS ROL */
    Route::get('rol', 'RolController@index')->name('rol');
    Route::get('rol/crear', 'RolController@crear')->name('crear_rol');
    Route::post('rol', 'RolController@guardar')->name('guardar_rol');
    Route::get('rol/{id}/editar', 'RolController@editar')->name('editar_rol');
    Route::put('rol/{id}', 'RolController@actualizar')->name('actualizar_rol');
    Route::delete('rol/{id}', 'RolController@eliminar')->name('eliminar_rol');
    /* RUTAS MENU_ROL */
    Route::get('menu-rol/permisos', 'MenuRolController@permisosPorMenu')->name('menu_rol_permisos');
    Route::get('menu-rol', 'MenuRolController@index')->name('menu_rol');
    Route::post('menu-rol', 'MenuRolController@guardar')->name('guardar_menu_rol');
    /* RUTAS PERMISO_ROL */
    Route::get('permiso-rol', 'PermisoRolController@index')->name('permiso_rol');
    Route::post('permiso-rol', 'PermisoRolController@guardar')->name('guardar_permiso_rol');
});

/* Rutas de configuracion */

Route::post('configuracion/crearusuarioremoto', 'Admin\UsuarioController@crearUsuarioRemoto')->name('crear_usuario_remoto');
Route::get('configuracion/leerusuario', 'Admin\UsuarioController@leerUsuario')->name('leer_usuario');
Route::post('configuracion/consultausuario', 'Admin\UsuarioController@consultaUsuario')->name('consultar_usuario');
Route::get('configuracion/leerunusuario/{usuario_id}', 'Admin\UsuarioController@leeUnUsuario')->name('leer_un_usuario');
Route::get('configuracion/resolverusuario', 'Admin\UsuarioController@resolverUsuario')->name('resolver_usuario');

/*
 * Salidas
 */

Route::get('configuracion/salida', 'Configuracion\SalidaController@index')->name('salida');
Route::get('configuracion/salida/crear', 'Configuracion\SalidaController@crear')->name('crear_salida');
Route::post('configuracion/salida', 'Configuracion\SalidaController@guardar')->name('guardar_salida');
Route::get('configuracion/salida/{id}/editar', 'Configuracion\SalidaController@editar')->name('editar_salida');
Route::put('configuracion/salida/{id}', 'Configuracion\SalidaController@actualizar')->name('actualizar_salida');
Route::delete('configuracion/salida/{id}', 'Configuracion\SalidaController@eliminar')->name('eliminar_salida');
Route::get('configuracion/ubicacion-impresora', 'Configuracion\UbicacionImpresoraController@index')->name('ubicacion_impresora');
Route::get('configuracion/lista-ubicacion-impresora/{formato?}/{busqueda?}', 'Configuracion\UbicacionImpresoraController@listar')->name('lista_ubicacion_impresora');
Route::get('configuracion/ubicacion-impresora/crear', 'Configuracion\UbicacionImpresoraController@crear')->name('crear_ubicacion_impresora');
Route::post('configuracion/ubicacion-impresora', 'Configuracion\UbicacionImpresoraController@guardar')->name('guardar_ubicacion_impresora');
Route::get('configuracion/ubicacion-impresora/{id}/editar', 'Configuracion\UbicacionImpresoraController@editar')->name('editar_ubicacion_impresora');
Route::put('configuracion/ubicacion-impresora/{id}', 'Configuracion\UbicacionImpresoraController@actualizar')->name('actualizar_ubicacion_impresora');
Route::delete('configuracion/ubicacion-impresora/{id}', 'Configuracion\UbicacionImpresoraController@eliminar')->name('eliminar_ubicacion_impresora');
Route::get('configuracion/uso-salida-impresora', 'Configuracion\UsoSalidaImpresoraController@index')->name('uso_salida_impresora');
Route::get('configuracion/lista-uso-salida-impresora/{formato?}/{busqueda?}', 'Configuracion\UsoSalidaImpresoraController@listar')->name('lista_uso_salida_impresora');
Route::get('configuracion/uso-salida-impresora/crear', 'Configuracion\UsoSalidaImpresoraController@crear')->name('crear_uso_salida_impresora');
Route::post('configuracion/uso-salida-impresora', 'Configuracion\UsoSalidaImpresoraController@guardar')->name('guardar_uso_salida_impresora');
Route::get('configuracion/uso-salida-impresora/{id}/editar', 'Configuracion\UsoSalidaImpresoraController@editar')->name('editar_uso_salida_impresora');
Route::put('configuracion/uso-salida-impresora/{id}', 'Configuracion\UsoSalidaImpresoraController@actualizar')->name('actualizar_uso_salida_impresora');
Route::delete('configuracion/uso-salida-impresora/{id}', 'Configuracion\UsoSalidaImpresoraController@eliminar')->name('eliminar_uso_salida_impresora');
Route::get('configuracion/configurarsalida/{programa?}', 'Configuracion\SalidaController@configurarSalida')->name('configurar_salida');
Route::get('configuracion/setearsalida/{programa}/{salida}', 'Configuracion\SalidaController@setearSalida')->name('setear_salida');
Route::get('configuracion/buscarsalida/{programa?}', 'Configuracion\SalidaController@buscarSalida')->name('buscar_salida');

/*
 * Monedas
 */

Route::get('configuracion/moneda', 'Configuracion\MonedaController@index')->name('moneda');
Route::get('configuracion/moneda/crear', 'Configuracion\MonedaController@crear')->name('crear_moneda');
Route::post('configuracion/moneda', 'Configuracion\MonedaController@guardar')->name('guardar_moneda');
Route::get('configuracion/moneda/{id}/editar', 'Configuracion\MonedaController@editar')->name('editar_moneda');
Route::put('configuracion/moneda/{id}', 'Configuracion\MonedaController@actualizar')->name('actualizar_moneda');
Route::delete('configuracion/moneda/{id}', 'Configuracion\MonedaController@eliminar')->name('eliminar_moneda');
Route::get('configuracion/leermoneda', 'Configuracion\MonedaController@leerMoneda')->name('leer_moneda');

/*
 * Cotizacion
 */

Route::get('configuracion/cotizacion', 'Configuracion\CotizacionController@index')->name('cotizacion');
Route::get('configuracion/cotizacion/crear', 'Configuracion\CotizacionController@crear')->name('crear_cotizacion');
Route::post('configuracion/cotizacion', 'Configuracion\CotizacionController@guardar')->name('guardar_cotizacion');
Route::get('configuracion/cotizacion/{id}/editar', 'Configuracion\CotizacionController@editar')->name('editar_cotizacion');
Route::put('configuracion/cotizacion/{id}', 'Configuracion\CotizacionController@actualizar')->name('actualizar_cotizacion');
Route::delete('configuracion/cotizacion/{id}', 'Configuracion\CotizacionController@eliminar')->name('eliminar_cotizacion');
Route::get('configuracion/cotizacion/{formato?}/{busqueda?}', 'Configuracion\CotizacionController@listar')->name('lista_cotizacion');
Route::get('configuracion/leercotizacion/{fecha}/{moneda_id}', 'Configuracion\CotizacionController@leeCotizacionDiaria')->name('leer_cotizacion');
/*
 * Paises
 */

Route::get('configuracion/pais', 'Configuracion\PaisController@index')->name('pais');
Route::get('configuracion/pais/crear', 'Configuracion\PaisController@crear')->name('crear_pais');
Route::post('configuracion/pais', 'Configuracion\PaisController@guardar')->name('guardar_pais');
Route::get('configuracion/pais/{id}/editar', 'Configuracion\PaisController@editar')->name('editar_pais');
Route::put('configuracion/pais/{id}', 'Configuracion\PaisController@actualizar')->name('actualizar_pais');
Route::delete('configuracion/pais/{id}', 'Configuracion\PaisController@eliminar')->name('eliminar_pais');

/*
 * Provincias
 */

Route::get('configuracion/provincia', 'Configuracion\ProvinciaController@index')->name('provincia');
Route::get('configuracion/provincia/crear', 'Configuracion\ProvinciaController@crear')->name('crear_provincia');
Route::post('configuracion/provincia', 'Configuracion\ProvinciaController@guardar')->name('guardar_provincia');
Route::get('configuracion/provincia/{id}/editar', 'Configuracion\ProvinciaController@editar')->name('editar_provincia');
Route::put('configuracion/provincia/{id}', 'Configuracion\ProvinciaController@actualizar')->name('actualizar_provincia');
Route::delete('configuracion/provincia/{id}', 'Configuracion\ProvinciaController@eliminar')->name('eliminar_provincia');

Route::get('configuracion/listaprovincia/{formato?}/{busqueda?}', 'Configuracion\ProvinciaController@listar')->name('lista_provincia');
Route::post('configuracion/provincia/consultaprovincia', 'Configuracion\ProvinciaController@consultaProvincia')->name('consulta_provincia');
Route::get('configuracion/leerunaprovincia/{provincia_id}', 'Configuracion\ProvinciaController@leeUnaProvincia')->name('leer_una_provincia');

/*
 * Localidades
 */

Route::get('configuracion/localidad', 'Configuracion\LocalidadController@index')->name('localidad');
Route::get('configuracion/localidad/crear', 'Configuracion\LocalidadController@crear')->name('crear_localidad');
Route::post('configuracion/localidad', 'Configuracion\LocalidadController@guardar')->name('guardar_localidad');
Route::get('configuracion/localidad/{id}/editar', 'Configuracion\LocalidadController@editar')->name('editar_localidad');
Route::put('configuracion/localidad/{id}', 'Configuracion\LocalidadController@actualizar')->name('actualizar_localidad');
Route::delete('configuracion/localidad/{id}', 'Configuracion\LocalidadController@eliminar')->name('eliminar_localidad');
Route::get('configuracion/leerlocalidades/{id}', 'Configuracion\LocalidadController@leerLocalidades')->name('leer_localidad');
Route::get('configuracion/leercodigopostal/{id}', 'Configuracion\LocalidadController@leerCodigoPostal')->name('leer_codigo_postal');

Route::get('configuracion/listalocalidad/{formato?}/{busqueda?}', 'Configuracion\LocalidadController@listar')->name('lista_localidad');
Route::post('configuracion/localidad/consultalocalidad', 'Configuracion\LocalidadController@consultaLocalidad')->name('consulta_localidad');
Route::get('configuracion/leerlocalidad/{localidad_id}', 'Configuracion\LocalidadController@leeUnaLocalidad')->name('leer_una_localidad');

/*
 * Condiciones de iva
 */

Route::get('configuracion/condicioniva', 'Configuracion\CondicionivaController@index')->name('condicioniva');
Route::get('configuracion/condicioniva/crear', 'Configuracion\CondicionivaController@crear')->name('crear_condicioniva');
Route::post('configuracion/condicioniva', 'Configuracion\CondicionivaController@guardar')->name('guardar_condicioniva');
Route::get('configuracion/condicioniva/{id}/editar', 'Configuracion\CondicionivaController@editar')->name('editar_condicioniva');
Route::put('configuracion/condicioniva/{id}', 'Configuracion\CondicionivaController@actualizar')->name('actualizar_condicioniva');
Route::delete('configuracion/condicioniva/{id}', 'Configuracion\CondicionivaController@eliminar')->name('eliminar_condicioniva');

/*
 * Condiciones de IIBB
 */

Route::get('configuracion/condicionIIBB', 'Configuracion\CondicionIIBBController@index')->name('condicionIIBB');
Route::get('configuracion/condicionIIBB/crear', 'Configuracion\CondicionIIBBController@crear')->name('crear_condicionIIBB');
Route::post('configuracion/condicionIIBB', 'Configuracion\CondicionIIBBController@guardar')->name('guardar_condicionIIBB');
Route::get('configuracion/condicionIIBB/{id}/editar', 'Configuracion\CondicionIIBBController@editar')->name('editar_condicionIIBB');
Route::put('configuracion/condicionIIBB/{id}', 'Configuracion\CondicionIIBBController@actualizar')->name('actualizar_condicionIIBB');
Route::delete('configuracion/condicionIIBB/{id}', 'Configuracion\CondicionIIBBController@eliminar')->name('eliminar_condicionIIBB');

/*
 * Tipos de documentos de personas fisicas y juridicas
 */

Route::get('configuracion/tipodocumento', 'Configuracion\TipodocumentoController@index')->name('tipodocumento');
Route::get('configuracion/tipodocumento/crear', 'Configuracion\TipodocumentoController@crear')->name('crear_tipodocumento');
Route::post('configuracion/tipodocumento', 'Configuracion\TipodocumentoController@guardar')->name('guardar_tipodocumento');
Route::get('configuracion/tipodocumento/{id}/editar', 'Configuracion\TipodocumentoController@editar')->name('editar_tipodocumento');
Route::put('configuracion/tipodocumento/{id}', 'Configuracion\TipodocumentoController@actualizar')->name('actualizar_tipodocumento');
Route::delete('configuracion/tipodocumento/{id}', 'Configuracion\TipodocumentoController@eliminar')->name('eliminar_tipodocumento');

/*
 * Actividades ARCA (compras y ventas)
 */

Route::get('configuracion/actividad_arca', 'Configuracion\Actividad_ArcaController@index')->name('consultar_actividad_arca');
Route::get('configuracion/actividad_arca/crear', 'Configuracion\Actividad_ArcaController@crear')->name('crear_actividad_arca');
Route::post('configuracion/actividad_arca', 'Configuracion\Actividad_ArcaController@guardar')->name('guardar_actividad_arca');
Route::get('configuracion/actividad_arca/{id}/editar', 'Configuracion\Actividad_ArcaController@editar')->name('editar_actividad_arca');
Route::put('configuracion/actividad_arca/{id}', 'Configuracion\Actividad_ArcaController@actualizar')->name('actualizar_actividad_arca');
Route::delete('configuracion/actividad_arca/{id}', 'Configuracion\Actividad_ArcaController@eliminar')->name('eliminar_actividad_arca');

/*
 * Feriados
 */

Route::get('configuracion/feriado', 'Configuracion\FeriadoController@index')->name('feriado');
Route::get('configuracion/feriado/crear', 'Configuracion\FeriadoController@crear')->name('crear_feriado');
Route::post('configuracion/feriado', 'Configuracion\FeriadoController@guardar')->name('guardar_feriado');
Route::get('configuracion/feriado/{id}/editar', 'Configuracion\FeriadoController@editar')->name('editar_feriado');
Route::put('configuracion/feriado/{id}', 'Configuracion\FeriadoController@actualizar')->name('actualizar_feriado');
Route::delete('configuracion/feriado/{id}', 'Configuracion\FeriadoController@eliminar')->name('eliminar_feriado');

/*
 * Retenciones de cobranza
 */

Route::get('configuracion/retencion_cobranza', 'Configuracion\Retencion_CobranzaController@index')->name('retencion_cobranza');
Route::get('configuracion/retencion_cobranza/crear', 'Configuracion\Retencion_CobranzaController@crear')->name('crear_retencion_cobranza');
Route::post('configuracion/retencion_cobranza', 'Configuracion\Retencion_CobranzaController@guardar')->name('guardar_retencion_cobranza');
Route::get('configuracion/retencion_cobranza/{id}/editar', 'Configuracion\Retencion_CobranzaController@editar')->name('editar_retencion_cobranza');
Route::put('configuracion/retencion_cobranza/{id}', 'Configuracion\Retencion_CobranzaController@actualizar')->name('actualizar_retencion_cobranza');
Route::delete('configuracion/retencion_cobranza/{id}', 'Configuracion\Retencion_CobranzaController@eliminar')->name('eliminar_retencion_cobranza');

Route::get('contable/sicore-config', 'Contable\SicoreConfigController@index')->name('sicore_config');
Route::get('contable/sicore-config/crear', 'Contable\SicoreConfigController@crear')->name('crear_sicore_config');
Route::post('contable/sicore-config', 'Contable\SicoreConfigController@guardar')->name('guardar_sicore_config');
Route::get('contable/sicore-config/{id}/editar', 'Contable\SicoreConfigController@editar')->name('editar_sicore_config');
Route::put('contable/sicore-config/{id}', 'Contable\SicoreConfigController@actualizar')->name('actualizar_sicore_config');
Route::delete('contable/sicore-config/{id}', 'Contable\SicoreConfigController@eliminar')->name('eliminar_sicore_config');

/*
 * Control de Retenciones impositivas ARCA
 */

Route::get('contable/control-retencion', 'Contable\ControlRetencionController@index')->name('retencion_impositiva_arca');
Route::get('contable/control-retencion/crear', 'Contable\ControlRetencionController@crear')->name('crear_retencion_impositiva_arca');
Route::post('contable/control-retencion', 'Contable\ControlRetencionController@guardar')->name('guardar_retencion_impositiva_arca');
Route::get('contable/control-retencion/importacion/crear', 'Contable\ControlRetencionController@crearImportacionRetencionimpositiva_Arca')->name('crear_importacion_retencion_impositiva_arca');
Route::post('contable/control-retencion/importacion', 'Contable\ControlRetencionController@importarRetencionimpositiva_Arca')->name('importar_retencion_impositiva_arca');
Route::get('contable/control-retencion/conciliar', 'Contable\ControlRetencionController@conciliarRetencionimpositiva_Arca')->name('conciliar_retencion_impositiva_arca');
Route::post('contable/control-retencion/conciliar', 'Contable\ControlRetencionController@procesarConciliacionRetencionimpositiva_Arca')->name('procesar_conciliacion_retencion_impositiva_arca');
Route::get('contable/control-retencion/{id}/editar', 'Contable\ControlRetencionController@editar')->name('editar_retencion_impositiva_arca');
Route::put('contable/control-retencion/{id}', 'Contable\ControlRetencionController@actualizar')->name('actualizar_retencion_impositiva_arca');
Route::delete('contable/control-retencion/{id}', 'Contable\ControlRetencionController@eliminar')->name('eliminar_retencion_impositiva_arca');

Route::get('contable/listar-control-retencion/{formato?}/{busqueda?}', 'Contable\ControlRetencionController@listar')->name('lista_retencion_impositiva_arca');

/*
 * Padron Mipyme
 */

Route::get('configuracion/padron_mipyme', 'Configuracion\Padron_MipymeController@index')->name('padron_mipyme');
Route::get('configuracion/padron_mipyme/crear', 'Configuracion\Padron_MipymeController@crear')->name('crear_padron_mipyme');
Route::post('configuracion/padron_mipyme', 'Configuracion\Padron_MipymeController@guardar')->name('guardar_padron_mipyme');
Route::get('configuracion/padron_mipyme/{id}/editar', 'Configuracion\Padron_MipymeController@editar')->name('editar_padron_mipyme');
Route::put('configuracion/padron_mipyme/{id}', 'Configuracion\Padron_MipymeController@actualizar')->name('actualizar_padron_mipyme');
Route::delete('configuracion/padron_mipyme/{id}', 'Configuracion\Padron_MipymeController@eliminar')->name('eliminar_padron_mipyme');

Route::get('configuracion/listapadron_mipyme/{formato?}/{busqueda?}', 'Configuracion\Padron_MipymeController@listar')->name('lista_padron_mipyme');

Route::get('configuracion/crea_importacion_padron_mipyme', 'Configuracion\Padron_MipymeController@crearImportacionPadron_Mipyme')->name('crear_importacion_padron_mipyme');
Route::post('configuracion/importa_padron_mipyme', 'Configuracion\Padron_MipymeController@importarPadron_Mipyme')->name('importar_padron_mipyme');

/*
 * Padron Exclusion Percepcion Iva
 */

Route::get('configuracion/padron_exclusionpercepcioniva', 'Configuracion\Padron_ExclusionpercepcionivaController@index')->name('padron_exclusionpercepcioniva');
Route::get('configuracion/padron_exclusionpercepcioniva/crear', 'Configuracion\Padron_ExclusionpercepcionivaController@crear')->name('crear_padron_exclusionpercepcioniva');
Route::post('configuracion/padron_exclusionpercepcioniva', 'Configuracion\Padron_ExclusionpercepcionivaController@guardar')->name('guardar_padron_exclusionpercepcioniva');
Route::get('configuracion/padron_exclusionpercepcioniva/{id}/editar', 'Configuracion\Padron_ExclusionpercepcionivaController@editar')->name('editar_padron_exclusionpercepcioniva');
Route::put('configuracion/padron_exclusionpercepcioniva/{id}', 'Configuracion\Padron_ExclusionpercepcionivaController@actualizar')->name('actualizar_padron_exclusionpercepcioniva');
Route::delete('configuracion/padron_exclusionpercepcioniva/{id}', 'Configuracion\Padron_ExclusionpercepcionivaController@eliminar')->name('eliminar_padron_exclusionpercepcioniva');

Route::get('configuracion/listapadron_exclusionpercepcioniva/{formato?}/{busqueda?}', 'Configuracion\Padron_ExclusionpercepcionivaController@listar')->name('lista_padron_exclusionpercepcioniva');

Route::get('configuracion/crea_importacion_padron_exclusionpercepcioniva', 'Configuracion\Padron_ExclusionpercepcionivaController@crearImportacionPadron_Exclusionpercepcioniva')->name('crear_importacion_padron_exclusionpercepcioniva');
Route::post('configuracion/importa_padron_exclusionpercepcioniva', 'Configuracion\Padron_ExclusionpercepcionivaController@importarPadron_Exclusionpercepcioniva')->name('importar_padron_exclusionpercepcioniva');

/*
 * Padron Tasas IIBB
 */

Route::get('configuracion/padron_iibb', 'Configuracion\Padron_IibbController@index')->name('padron_iibb');
Route::get('configuracion/padron_iibb/crear', 'Configuracion\Padron_IibbController@crear')->name('crear_padron_iibb');
Route::post('configuracion/padron_iibb', 'Configuracion\Padron_IibbController@guardar')->name('guardar_padron_iibb');
Route::get('configuracion/padron_iibb/{id}/editar', 'Configuracion\Padron_IibbController@editar')->name('editar_padron_iibb');
Route::put('configuracion/padron_iibb/{id}', 'Configuracion\Padron_IibbController@actualizar')->name('actualizar_padron_iibb');
Route::delete('configuracion/padron_iibb/{id}', 'Configuracion\Padron_IibbController@eliminar')->name('eliminar_padron_iibb');

Route::get('configuracion/listapadron_iibb/{formato?}/{busqueda?}', 'Configuracion\Padron_IibbController@listar')->name('lista_padron_iibb');

Route::get('configuracion/crea_importacion_padron_iibb', 'Configuracion\Padron_IibbController@crearImportacionPadron_Iibb')->name('crear_importacion_padron_iibb');
Route::post('configuracion/importa_padron_iibb', 'Configuracion\Padron_IibbController@importarPadron_Iibb')->name('importar_padron_iibb');

/*
 * Fondos
 */

Route::get('stock/fondo', 'Stock\FondoController@index')->name('fondo');
Route::get('stock/fondo/crear', 'Stock\FondoController@crear')->name('crear_fondo');
Route::post('stock/fondo', 'Stock\FondoController@guardar')->name('guardar_fondo');
Route::get('stock/fondo/{id}/editar', 'Stock\FondoController@editar')->name('editar_fondo');
Route::put('stock/fondo/{id}', 'Stock\FondoController@actualizar')->name('actualizar_fondo');
Route::delete('stock/fondo/{id}', 'Stock\FondoController@eliminar')->name('eliminar_fondo');

/*
 * Forro
 */

Route::get('stock/forro', 'Stock\ForroController@index')->name('forro');
Route::get('stock/forro/crear', 'Stock\ForroController@crear')->name('crear_forro');
Route::post('stock/forro', 'Stock\ForroController@guardar')->name('guardar_forro');
Route::get('stock/forro/{id}/editar', 'Stock\ForroController@editar')->name('editar_forro');
Route::put('stock/forro/{id}', 'Stock\ForroController@actualizar')->name('actualizar_forro');
Route::delete('stock/forro/{id}', 'Stock\ForroController@eliminar')->name('eliminar_forro');

/*
 * Subcategorias
 */

Route::get('stock/subcategoria', 'Stock\SubcategoriaController@index')->name('subcategoria');
Route::get('stock/subcategoria/crear', 'Stock\SubcategoriaController@crear')->name('crear_subcategoria');
Route::post('stock/subcategoria', 'Stock\SubcategoriaController@guardar')->name('guardar_subcategoria');
Route::get('stock/subcategoria/{id}/editar', 'Stock\SubcategoriaController@editar')->name('editar_subcategoria');
Route::put('stock/subcategoria/{id}', 'Stock\SubcategoriaController@actualizar')->name('actualizar_subcategoria');
Route::delete('stock/subcategoria/{id}', 'Stock\SubcategoriaController@eliminar')->name('eliminar_subcategoria');

/*
 * Marcas de venta
 */

Route::get('stock/mventa', 'Stock\MventaController@index')->name('mventa');
Route::get('stock/mventa/crear', 'Stock\MventaController@crear')->name('crear_mventa');
Route::post('stock/mventa', 'Stock\MventaController@guardar')->name('guardar_mventa');
Route::get('stock/mventa/{id}/editar', 'Stock\MventaController@editar')->name('editar_mventa');
Route::put('stock/mventa/{id}', 'Stock\MventaController@actualizar')->name('actualizar_mventa');
Route::delete('stock/mventa/{id}', 'Stock\MventaController@eliminar')->name('eliminar_mventa');

/*
 * Maestros SIFAB compra (INTERFORMING)
 */
Route::get('stock/rubro', 'Stock\RubroController@index')->name('rubro');
Route::get('stock/rubro/crear', 'Stock\RubroController@crear')->name('crear_rubro');
Route::post('stock/rubro', 'Stock\RubroController@guardar')->name('guardar_rubro');
Route::get('stock/rubro/{id}/editar', 'Stock\RubroController@editar')->name('editar_rubro');
Route::put('stock/rubro/{id}', 'Stock\RubroController@actualizar')->name('actualizar_rubro');
Route::delete('stock/rubro/{id}', 'Stock\RubroController@eliminar')->name('eliminar_rubro');

Route::get('stock/subrubro', 'Stock\SubrubroController@index')->name('subrubro');
Route::get('stock/subrubro/crear', 'Stock\SubrubroController@crear')->name('crear_subrubro');
Route::post('stock/subrubro', 'Stock\SubrubroController@guardar')->name('guardar_subrubro');
Route::get('stock/subrubro/{id}/editar', 'Stock\SubrubroController@editar')->name('editar_subrubro');
Route::put('stock/subrubro/{id}', 'Stock\SubrubroController@actualizar')->name('actualizar_subrubro');
Route::delete('stock/subrubro/{id}', 'Stock\SubrubroController@eliminar')->name('eliminar_subrubro');

Route::get('stock/grupoproducto', 'Stock\GrupoproductoController@index')->name('grupoproducto');
Route::get('stock/grupoproducto/crear', 'Stock\GrupoproductoController@crear')->name('crear_grupoproducto');
Route::post('stock/grupoproducto', 'Stock\GrupoproductoController@guardar')->name('guardar_grupoproducto');
Route::get('stock/grupoproducto/{id}/editar', 'Stock\GrupoproductoController@editar')->name('editar_grupoproducto');
Route::put('stock/grupoproducto/{id}', 'Stock\GrupoproductoController@actualizar')->name('actualizar_grupoproducto');
Route::delete('stock/grupoproducto/{id}', 'Stock\GrupoproductoController@eliminar')->name('eliminar_grupoproducto');

Route::get('stock/centroemisor', 'Stock\CentroemisorController@index')->name('centroemisor');
Route::get('stock/centroemisor/crear', 'Stock\CentroemisorController@crear')->name('crear_centroemisor');
Route::post('stock/centroemisor', 'Stock\CentroemisorController@guardar')->name('guardar_centroemisor');
Route::get('stock/centroemisor/{id}/editar', 'Stock\CentroemisorController@editar')->name('editar_centroemisor');
Route::put('stock/centroemisor/{id}', 'Stock\CentroemisorController@actualizar')->name('actualizar_centroemisor');
Route::delete('stock/centroemisor/{id}', 'Stock\CentroemisorController@eliminar')->name('eliminar_centroemisor');

/*
 * Depositos
 */

Route::get('stock/depmae', 'Stock\DepmaeController@index')->name('depmae');
Route::get('stock/listadepmae/{formato?}/{busqueda?}', 'Stock\DepmaeController@listar')->name('lista_depmae');
Route::get('stock/depmae/crear', 'Stock\DepmaeController@crear')->name('crear_depmae');
Route::post('stock/depmae/consultadeposito', 'Stock\DepmaeController@consultaDeposito')->name('consulta_depmae');
Route::get('stock/depmae/leer/{codigo}', 'Stock\DepmaeController@leeUnDepositoPorCodigo')->name('leer_depmae');
Route::post('stock/depmae', 'Stock\DepmaeController@guardar')->name('guardar_depmae');
Route::get('stock/depmae/{id}/editar', 'Stock\DepmaeController@editar')->name('editar_depmae')->middleware('modo.consulta');
Route::put('stock/depmae/{id}', 'Stock\DepmaeController@actualizar')->name('actualizar_depmae')->middleware('modo.consulta');
Route::delete('stock/depmae/{id}', 'Stock\DepmaeController@eliminar')->name('eliminar_depmae');

/*
 * Numeracion
 */

Route::get('stock/numeracion', 'Stock\NumeracionController@index')->name('numeracion');
Route::get('stock/numeracion/crear', 'Stock\NumeracionController@crear')->name('crear_numeracion');
Route::post('stock/numeracion', 'Stock\NumeracionController@guardar')->name('guardar_numeracion');
Route::get('stock/numeracion/{id}/editar', 'Stock\NumeracionController@editar')->name('editar_numeracion');
Route::put('stock/numeracion/{id}', 'Stock\NumeracionController@actualizar')->name('actualizar_numeracion');
Route::delete('stock/numeracion/{id}', 'Stock\NumeracionController@eliminar')->name('eliminar_numeracion');

/*
 * Hormas
 */

Route::get('stock/horma', 'Stock\HormaController@index')->name('horma');
Route::get('stock/horma/crear', 'Stock\HormaController@crear')->name('crear_horma');
Route::post('stock/horma', 'Stock\HormaController@guardar')->name('guardar_horma');
Route::get('stock/horma/{id}/editar', 'Stock\HormaController@editar')->name('editar_horma');
Route::put('stock/horma/{id}', 'Stock\HormaController@actualizar')->name('actualizar_horma');
Route::delete('stock/horma/{id}', 'Stock\HormaController@eliminar')->name('eliminar_horma');

/*
 * Plantillas de armado
 */

Route::get('stock/plarmado', 'Stock\PlarmadoController@index')->name('plarmado');
Route::get('stock/plarmado/crear', 'Stock\PlarmadoController@crear')->name('crear_plarmado');
Route::post('stock/plarmado', 'Stock\PlarmadoController@guardar')->name('guardar_plarmado');
Route::get('stock/plarmado/{id}/editar', 'Stock\PlarmadoController@editar')->name('editar_plarmado');
Route::put('stock/plarmado/{id}', 'Stock\PlarmadoController@actualizar')->name('actualizar_plarmado');
Route::delete('stock/plarmado/{id}', 'Stock\PlarmadoController@eliminar')->name('eliminar_plarmado');

/*
 * Colores
 */

Route::get('stock/color', 'Stock\ColorController@index')->name('color');
Route::get('stock/color/crear', 'Stock\ColorController@crear')->name('crear_color');
Route::post('stock/color', 'Stock\ColorController@guardar')->name('guardar_color');
Route::get('stock/color/{id}/editar', 'Stock\ColorController@editar')->name('editar_color');
Route::put('stock/color/{id}', 'Stock\ColorController@actualizar')->name('actualizar_color');
Route::delete('stock/color/{id}', 'Stock\ColorController@eliminar')->name('eliminar_color');

/*
 * Composicion de fondos
 */

Route::get('stock/compfondo', 'Stock\CompfondoController@index')->name('compfondo');
Route::get('stock/compfondo/crear', 'Stock\CompfondoController@crear')->name('crear_compfondo');
Route::post('stock/compfondo', 'Stock\CompfondoController@guardar')->name('guardar_compfondo');
Route::get('stock/compfondo/{id}/editar', 'Stock\CompfondoController@editar')->name('editar_compfondo');
Route::put('stock/compfondo/{id}', 'Stock\CompfondoController@actualizar')->name('actualizar_compfondo');
Route::delete('stock/compfondo/{id}', 'Stock\CompfondoController@eliminar')->name('eliminar_compfondo');

/*
 * Tipo de cortes
 */

Route::get('stock/tipocorte', 'Stock\TipocorteController@index')->name('tipocorte');
Route::get('stock/tipocorte/crear', 'Stock\TipocorteController@crear')->name('crear_tipocorte');
Route::post('stock/tipocorte', 'Stock\TipocorteController@guardar')->name('guardar_tipocorte');
Route::get('stock/tipocorte/{id}/editar', 'Stock\TipocorteController@editar')->name('editar_tipocorte');
Route::put('stock/tipocorte/{id}', 'Stock\TipocorteController@actualizar')->name('actualizar_tipocorte');
Route::delete('stock/tipocorte/{id}', 'Stock\TipocorteController@eliminar')->name('eliminar_tipocorte');

/*
 * Tipo de producto
 */

Route::get('stock/tipoproducto', 'Stock\TipoproductoController@index')->name('consultar_tipoproducto');
Route::get('stock/tipoproducto/crear', 'Stock\TipoproductoController@crear')->name('crear_tipoproducto');
Route::post('stock/tipoproducto', 'Stock\TipoproductoController@guardar')->name('guardar_tipoproducto');
Route::get('stock/tipoproducto/{id}/editar', 'Stock\TipoproductoController@editar')->name('editar_tipoproducto');
Route::put('stock/tipoproducto/{id}', 'Stock\TipoproductoController@actualizar')->name('actualizar_tipoproducto');
Route::delete('stock/tipoproducto/{id}', 'Stock\TipoproductoController@eliminar')->name('eliminar_tipoproducto');

/*
 * Capacidad
 */

Route::get('stock/capacidad', 'Stock\CapacidadController@index')->name('consultar_capacidad');
Route::get('stock/capacidad/crear', 'Stock\CapacidadController@crear')->name('crear_capacidad');
Route::post('stock/capacidad', 'Stock\CapacidadController@guardar')->name('guardar_capacidad');
Route::get('stock/capacidad/{id}/editar', 'Stock\CapacidadController@editar')->name('editar_capacidad');
Route::put('stock/capacidad/{id}', 'Stock\CapacidadController@actualizar')->name('actualizar_capacidad');
Route::delete('stock/capacidad/{id}', 'Stock\CapacidadController@eliminar')->name('eliminar_capacidad');

/*
 * Tipo líquido de freno
 */

Route::get('stock/tipoliquido', 'Stock\TipoliquidoController@index')->name('consultar_tipoliquido');
Route::get('stock/tipoliquido/crear', 'Stock\TipoliquidoController@crear')->name('crear_tipoliquido');
Route::post('stock/tipoliquido', 'Stock\TipoliquidoController@guardar')->name('guardar_tipoliquido');
Route::get('stock/tipoliquido/{id}/editar', 'Stock\TipoliquidoController@editar')->name('editar_tipoliquido');
Route::put('stock/tipoliquido/{id}', 'Stock\TipoliquidoController@actualizar')->name('actualizar_tipoliquido');
Route::delete('stock/tipoliquido/{id}', 'Stock\TipoliquidoController@eliminar')->name('eliminar_tipoliquido');

/*
 * Materiales
 */

Route::get('stock/material', 'Stock\MaterialController@index')->name('material');
Route::get('stock/material/crear', 'Stock\MaterialController@crear')->name('crear_material');
Route::post('stock/material', 'Stock\MaterialController@guardar')->name('guardar_material');
Route::get('stock/material/{id}/editar', 'Stock\MaterialController@editar')->name('editar_material');
Route::put('stock/material/{id}', 'Stock\MaterialController@actualizar')->name('actualizar_material');
Route::delete('stock/material/{id}', 'Stock\MaterialController@eliminar')->name('eliminar_material');

/*
 * Serigrafias
 */

Route::get('stock/serigrafia', 'Stock\SerigrafiaController@index')->name('serigrafia');
Route::get('stock/serigrafia/crear', 'Stock\SerigrafiaController@crear')->name('crear_serigrafia');
Route::post('stock/serigrafia', 'Stock\SerigrafiaController@guardar')->name('guardar_serigrafia');
Route::get('stock/serigrafia/{id}/editar', 'Stock\SerigrafiaController@editar')->name('editar_serigrafia');
Route::put('stock/serigrafia/{id}', 'Stock\SerigrafiaController@actualizar')->name('actualizar_serigrafia');
Route::delete('stock/serigrafia/{id}', 'Stock\SerigrafiaController@eliminar')->name('eliminar_serigrafia');

/*
 * Materiales de capelladas
 */

Route::get('stock/materialcapellada', 'Stock\MaterialcapelladaController@index')->name('materialcapellada');
Route::get('stock/materialcapellada/crear', 'Stock\MaterialcapelladaController@crear')->name('crear_materialcapellada');
Route::post('stock/materialcapellada', 'Stock\MaterialcapelladaController@guardar')->name('guardar_materialcapellada');
Route::get('stock/materialcapellada/{id}/editar', 'Stock\MaterialcapelladaController@editar')->name('editar_materialcapellada');
Route::put('stock/materialcapellada/{id}', 'Stock\MaterialcapelladaController@actualizar')->name('actualizar_materialcapellada');
Route::delete('stock/materialcapellada/{id}', 'Stock\MaterialcapelladaController@eliminar')->name('eliminar_materialcapellada');

/*
 * Materiales de avios
 */

Route::get('stock/materialavio', 'Stock\MaterialavioController@index')->name('materialavio');
Route::get('stock/materialavio/crear', 'Stock\MaterialavioController@crear')->name('crear_materialavio');
Route::post('stock/materialavio', 'Stock\MaterialavioController@guardar')->name('guardar_materialavio');
Route::get('stock/materialavio/{id}/editar', 'Stock\MaterialavioController@editar')->name('editar_materialavio');
Route::put('stock/materialavio/{id}', 'Stock\MaterialavioController@actualizar')->name('actualizar_materialavio');
Route::delete('stock/materialavio/{id}', 'Stock\MaterialavioController@eliminar')->name('eliminar_materialavio');

/*
 * Talles
 */

Route::get('stock/talle', 'Stock\TalleController@index')->name('talle');
Route::get('stock/talle/crear', 'Stock\TalleController@crear')->name('crear_talle');
Route::post('stock/talle', 'Stock\TalleController@guardar')->name('guardar_talle');
Route::get('stock/talle/{id}/editar', 'Stock\TalleController@editar')->name('editar_talle');
Route::put('stock/talle/{id}', 'Stock\TalleController@actualizar')->name('actualizar_talle');
Route::delete('stock/talle/{id}', 'Stock\TalleController@eliminar')->name('eliminar_talle');

/*
 * Modulos
 */

Route::get('stock/modulo', 'Stock\ModuloController@index')->name('modulo');
Route::get('stock/modulo/crear', 'Stock\ModuloController@crear')->name('crear_modulo');
Route::post('stock/modulo', 'Stock\ModuloController@guardar')->name('guardar_modulo');
Route::get('stock/modulo/{id}/editar', 'Stock\ModuloController@editar')->name('editar_modulo');
Route::put('stock/modulo/{id}', 'Stock\ModuloController@actualizar')->name('actualizar_modulo');
Route::delete('stock/modulo/{id}', 'Stock\ModuloController@eliminar')->name('eliminar_modulo');

/*
 * Tipo de articulos
 */

Route::get('stock/tipoarticulo', 'Stock\TipoarticuloController@index')->name('tipoarticulo');
Route::get('stock/tipoarticulo/crear', 'Stock\TipoarticuloController@crear')->name('crear_tipoarticulo');
Route::post('stock/tipoarticulo', 'Stock\TipoarticuloController@guardar')->name('guardar_tipoarticulo');
Route::get('stock/tipoarticulo/{id}/editar', 'Stock\TipoarticuloController@editar')->name('editar_tipoarticulo');
Route::put('stock/tipoarticulo/{id}', 'Stock\TipoarticuloController@actualizar')->name('actualizar_tipoarticulo');
Route::delete('stock/tipoarticulo/{id}', 'Stock\TipoarticuloController@eliminar')->name('eliminar_tipoarticulo');

/*
 * Categorias
 */

Route::get('stock/categoria', 'Stock\CategoriaController@index')->name('categoria');
Route::get('stock/categoria/crear', 'Stock\CategoriaController@crear')->name('crear_categoria');
Route::post('stock/categoria', 'Stock\CategoriaController@guardar')->name('guardar_categoria');
Route::get('stock/categoria/{id}/editar', 'Stock\CategoriaController@editar')->name('editar_categoria');
Route::put('stock/categoria/{id}', 'Stock\CategoriaController@actualizar')->name('actualizar_categoria');
Route::delete('stock/categoria/{id}', 'Stock\CategoriaController@eliminar')->name('eliminar_categoria');

/*
 * Listas de precio
 */

Route::get('stock/listaprecio', 'Stock\ListaprecioController@index')->name('listaprecio');
Route::get('stock/listaprecio/crear', 'Stock\ListaprecioController@crear')->name('crear_listaprecio');
Route::post('stock/listaprecio', 'Stock\ListaprecioController@guardar')->name('guardar_listaprecio');
Route::get('stock/listaprecio/{id}/editar', 'Stock\ListaprecioController@editar')->name('editar_listaprecio');
Route::put('stock/listaprecio/{id}', 'Stock\ListaprecioController@actualizar')->name('actualizar_listaprecio');
Route::delete('stock/listaprecio/{id}', 'Stock\ListaprecioController@eliminar')->name('eliminar_listaprecio');
Route::post('stock/listaprecio/consultalistaprecio', 'Stock\ListaprecioController@consultaListaprecio')->name('consulta_listaprecio');
Route::get('stock/leerlistaprecio/{codigo}', 'Stock\ListaprecioController@leeUnListaprecioPorCodigo')->name('leer_listaprecio');

/*
 * Tipos de numeracion
 */

Route::get('stock/tiponumeracion', 'Stock\TiponumeracionController@index')->name('tiponumeracion');
Route::get('stock/tiponumeracion/crear', 'Stock\TiponumeracionController@crear')->name('crear_tiponumeracion');
Route::post('stock/tiponumeracion', 'Stock\TiponumeracionController@guardar')->name('guardar_tiponumeracion');
Route::get('stock/tiponumeracion/{id}/editar', 'Stock\TiponumeracionController@editar')->name('editar_tiponumeracion');
Route::put('stock/tiponumeracion/{id}', 'Stock\TiponumeracionController@actualizar')->name('actualizar_tiponumeracion');
Route::delete('stock/tiponumeracion/{id}', 'Stock\TiponumeracionController@eliminar')->name('eliminar_tiponumeracion');

/*
 * Lineas
 */

Route::get('stock/linea', 'Stock\LineaController@index')->name('linea');
Route::get('stock/linea/crear', 'Stock\LineaController@crear')->name('crear_linea');
Route::post('stock/linea', 'Stock\LineaController@guardar')->name('guardar_linea');
Route::get('stock/linea/{id}/editar', 'Stock\LineaController@editar')->name('editar_linea');
Route::put('stock/linea/{id}', 'Stock\LineaController@actualizar')->name('actualizar_linea');
Route::delete('stock/linea/{id}', 'Stock\LineaController@eliminar')->name('eliminar_linea');

/*
 * Precios
 */

Route::get('stock/precio', 'Stock\PrecioController@index')->name('precio');
Route::get('stock/listar_precio/{formato?}', 'Stock\PrecioController@listar')->name('listar_precio');
Route::get('stock/precio/crear', 'Stock\PrecioController@crear')->name('crear_precio');
Route::post('stock/precio', 'Stock\PrecioController@guardar')->name('guardar_precio');
Route::get('stock/precio/{id}/editar', 'Stock\PrecioController@editar')->name('editar_precio');
Route::put('stock/precio/{id}', 'Stock\PrecioController@actualizar')->name('actualizar_precio');
Route::delete('stock/precio/{id}', 'Stock\PrecioController@eliminar')->name('eliminar_precio');
Route::get('stock/asignaprecio/{id}/{talle?}', 'Stock\PrecioController@asignaPrecio')->name('asigna_precio');
Route::get('stock/asignapreciocliente/{articulo_id}/{cliente_id}', 'Stock\PrecioController@asignaPrecioPorCliente')->name('asigna_precio_cliente');
Route::get('stock/precio/crearimportacionprecio', 'Stock\PrecioController@crearImportacion')->name('crear_importacion_precio');
Route::post('stock/importarprecio', 'Stock\PrecioController@importar')->name('importar_precio');
Route::post('stock/precio/importar-precio/preview', 'Stock\PrecioController@previewImportacion')->name('precio_import_preview');
Route::post('stock/precio/limpiafiltro', 'Stock\PrecioController@limpiafiltro')->name('precio.limpiafiltro');
Route::get('stock/precio/actualizar-por-categoria', 'Stock\PrecioController@actualizarPorCategoria')->name('precio_actualizar_categoria');
Route::post('stock/precio/actualizar-por-categoria/preview', 'Stock\PrecioController@previewActualizacionCategoria')->name('precio_actualizar_categoria_preview');
Route::post('stock/precio/actualizar-por-categoria', 'Stock\PrecioController@aplicarActualizacionCategoria')->name('precio_actualizar_categoria_aplicar');
Route::get('stock/precio/consulta-por-articulo', 'Stock\PrecioController@consultaPreciosArticulo')->name('consulta_precios_articulo');

/*
 * Tienda nube
 */

Route::get('stock/crearimportaciontiendanube', 'Stock\TiendaNubeController@crearImportacion')->name('crear_importacion_tiendanube');
Route::post('stock/importartiendanube', 'Stock\TiendaNubeController@importar')->name('importar_tiendanube');
Route::get('ventas/crearimportacionfacturastiendanube', 'Ventas\FacturanteController@crearImportacion')->name('crear_importacion_facturas_tiendanube');
Route::post('ventas/listarfacturastiendanube', 'Ventas\FacturanteController@listarComprobanteFull')->name('listar_facturas_tiendanube');
Route::post('ventas/generarfacturastiendanube', 'Ventas\FacturanteController@generarFacturasTiendaNube')->name('generar_facturas_tiendanube');

/*
 * Unidades de medida
 */

Route::get('stock/unidadmedida', 'Stock\UnidadmedidaController@index')->name('unidadmedida');
Route::get('stock/unidadmedida/crear', 'Stock\UnidadmedidaController@crear')->name('crear_unidadmedida');
Route::post('stock/unidadmedida', 'Stock\UnidadmedidaController@guardar')->name('guardar_unidadmedida');
Route::get('stock/unidadmedida/{id}/editar', 'Stock\UnidadmedidaController@editar')->name('editar_unidadmedida');
Route::put('stock/unidadmedida/{id}', 'Stock\UnidadmedidaController@actualizar')->name('actualizar_unidadmedida');
Route::delete('stock/unidadmedida/{id}', 'Stock\UnidadmedidaController@eliminar')->name('eliminar_unidadmedida');

/*
 * Uso de articulos
 */

Route::get('stock/usoarticulo', 'Stock\UsoarticuloController@index')->name('usoarticulo');
Route::get('stock/usoarticulo/crear', 'Stock\UsoarticuloController@crear')->name('crear_usoarticulo');
Route::post('stock/usoarticulo', 'Stock\UsoarticuloController@guardar')->name('guardar_usoarticulo');
Route::get('stock/usoarticulo/{id}/editar', 'Stock\UsoarticuloController@editar')->name('editar_usoarticulo');
Route::put('stock/usoarticulo/{id}', 'Stock\UsoarticuloController@actualizar')->name('actualizar_usoarticulo');
Route::delete('stock/usoarticulo/{id}', 'Stock\UsoarticuloController@eliminar')->name('eliminar_usoarticulo');

/*
 * Punteras
 */

Route::get('stock/puntera', 'Stock\PunteraController@index')->name('puntera');
Route::get('stock/puntera/crear', 'Stock\PunteraController@crear')->name('crear_puntera');
Route::post('stock/puntera', 'Stock\PunteraController@guardar')->name('guardar_puntera');
Route::get('stock/puntera/{id}/editar', 'Stock\PunteraController@editar')->name('editar_puntera');
Route::put('stock/puntera/{id}', 'Stock\PunteraController@actualizar')->name('actualizar_puntera');
Route::delete('stock/puntera/{id}', 'Stock\PunteraController@eliminar')->name('eliminar_puntera');

/*
 * Contrafuertes
 */

Route::get('stock/contrafuerte', 'Stock\ContrafuerteController@index')->name('contrafuerte');
Route::get('stock/contrafuerte/crear', 'Stock\ContrafuerteController@crear')->name('crear_contrafuerte');
Route::post('stock/contrafuerte', 'Stock\ContrafuerteController@guardar')->name('guardar_contrafuerte');
Route::get('stock/contrafuerte/{id}/editar', 'Stock\ContrafuerteController@editar')->name('editar_contrafuerte');
Route::put('stock/contrafuerte/{id}', 'Stock\ContrafuerteController@actualizar')->name('actualizar_contrafuerte');
Route::delete('stock/contrafuerte/{id}', 'Stock\ContrafuerteController@eliminar')->name('eliminar_contrafuerte');

/*
 * Plantillas a la vista
 */

Route::get('stock/plvista', 'Stock\PlvistaController@index')->name('plvista');
Route::get('stock/plvista/crear', 'Stock\PlvistaController@crear')->name('crear_plvista');
Route::post('stock/plvista', 'Stock\PlvistaController@guardar')->name('guardar_plvista');
Route::get('stock/plvista/{id}/editar', 'Stock\PlvistaController@editar')->name('editar_plvista');
Route::put('stock/plvista/{id}', 'Stock\PlvistaController@actualizar')->name('actualizar_plvista');
Route::delete('stock/plvista/{id}', 'Stock\PlvistaController@eliminar')->name('eliminar_plvista');

/*
 * Caja
 */

Route::get('stock/caja', 'Stock\CajaController@index')->name('caja');
Route::get('stock/caja/crear', 'Stock\CajaController@crear')->name('crear_caja');
Route::post('stock/caja', 'Stock\CajaController@guardar')->name('guardar_caja');
Route::get('stock/caja/{id}/editar', 'Stock\CajaController@editar')->name('editar_caja');
Route::put('stock/caja/{id}', 'Stock\CajaController@actualizar')->name('actualizar_caja');
Route::delete('stock/caja/{id}', 'Stock\CajaController@eliminar')->name('eliminar_caja');

/*
 * Lotes de stock
 */

Route::get('stock/lote', 'Stock\LoteController@index')->name('lote');
Route::get('stock/lote/crear', 'Stock\LoteController@crear')->name('crear_lote');
Route::post('stock/lote', 'Stock\LoteController@guardar')->name('guardar_lote');
Route::get('stock/lote/{id}/editar', 'Stock\LoteController@editar')->name('editar_lote');
Route::put('stock/lote/{id}', 'Stock\LoteController@actualizar')->name('actualizar_lote');
Route::delete('stock/lote/{id}', 'Stock\LoteController@eliminar')->name('eliminar_lote');

// Reportes de stock

// Catalogo de productos
Route::get('stock/catalogo', 'Stock\CombinacionController@catalogo')->name('catalogo');
Route::post('stock/crearCatalogo', 'Stock\CombinacionController@crearCatalogo')->name('crear_catalogo');

// Combinaciones
Route::get('stock/repcombinacion', 'Stock\RepCombinacionController@index')->name('rep_combinacion');
Route::post('stock/crearrepcombinacion', 'Stock\RepCombinacionController@crearReporteCombinacion')->name('crear_repcombinacion');

// Stock OT
Route::get('stock/repstockot', 'Stock\RepStockOtController@index')->name('rep_stockot');
Route::post('stock/crearrepstockot', 'Stock\RepStockOtController@crearReporteStockOt')->name('crear_repstockot');

// Stock Listas de Precio
Route::get('stock/replistaprecio', 'Stock\RepListaPrecioController@index')->name('rep_listaprecio');
Route::post('stock/crearreplistaprecio', 'Stock\RepListaPrecioController@crearReporteListaPrecio')->name('crear_replistaprecio');

/*
 * Impuestos
 */

Route::get('configuracion/impuesto', 'Configuracion\ImpuestoController@index')->name('impuesto');
Route::get('configuracion/impuesto/crear', 'Configuracion\ImpuestoController@crear')->name('crear_impuesto');
Route::post('configuracion/impuesto', 'Configuracion\ImpuestoController@guardar')->name('guardar_impuesto');
Route::get('configuracion/impuesto/{id}/editar', 'Configuracion\ImpuestoController@editar')->name('editar_impuesto');
Route::put('configuracion/impuesto/{id}', 'Configuracion\ImpuestoController@actualizar')->name('actualizar_impuesto');
Route::delete('configuracion/impuesto/{id}', 'Configuracion\ImpuestoController@eliminar')->name('eliminar_impuesto');

Route::get('contable/libro-iva-digital', 'Contable\LibroIvaDigitalController@index')->name('libro_iva_digital');
Route::get('contable/libro-iva-digital/exportar', 'Contable\LibroIvaDigitalController@exportar')->name('exportar_libro_iva_digital');
Route::get('contable/libro-iva-digital/exportar-iva-simple', 'Contable\LibroIvaDigitalController@exportarIvaSimple')->name('exportar_iva_simple_libro_iva_digital');

/*
 * Empresas
 */

Route::get('configuracion/empresa', 'Configuracion\EmpresaController@index')->name('empresa');
Route::get('configuracion/empresa/crear', 'Configuracion\EmpresaController@crear')->name('crear_empresa');
Route::post('configuracion/empresa', 'Configuracion\EmpresaController@guardar')->name('guardar_empresa');
Route::get('configuracion/empresa/{id}/editar', 'Configuracion\EmpresaController@editar')->name('editar_empresa');
Route::put('configuracion/empresa/{id}', 'Configuracion\EmpresaController@actualizar')->name('actualizar_empresa');
Route::delete('configuracion/empresa/{id}', 'Configuracion\EmpresaController@eliminar')->name('eliminar_empresa');

/*
 * Avisos configurables por módulo
 */
Route::get('configuracion/modulo-aviso', 'Configuracion\ModuloAvisoController@index')->name('consultar_modulo_aviso');
Route::get('configuracion/modulo-aviso/{id}/editar', 'Configuracion\ModuloAvisoController@editar')->name('editar_modulo_aviso');
Route::put('configuracion/modulo-aviso/{id}', 'Configuracion\ModuloAvisoController@actualizar')->name('actualizar_modulo_aviso');

/*
 * Arbol de aprobacion
 */

Route::get('configuracion/arbolaprobacion', 'Configuracion\ArbolaprobacionController@index')->name('consulta_arbolaprobacion');
Route::get('configuracion/arbolaprobacion/crear', 'Configuracion\ArbolaprobacionController@crear')->name('crea_arbolaprobacion');
Route::post('configuracion/arbolaprobacion', 'Configuracion\ArbolaprobacionController@guardar')->name('guarda_arbolaprobacion');
Route::get('configuracion/arbolaprobacion/{id}/editar', 'Configuracion\ArbolaprobacionController@editar')->name('edita_arbolaprobacion');
Route::put('configuracion/arbolaprobacion/{id}', 'Configuracion\ArbolaprobacionController@actualizar')->name('actualiza_arbolaprobacion');
Route::delete('configuracion/arbolaprobacion/{id}', 'Configuracion\ArbolaprobacionController@eliminar')->name('elimina_arbolaprobacion');

Route::get('arbolaprobacion/aprobar/{tipocomprobante}/{comprobante_id}/{hash}', 'Configuracion\ArbolaprobacionController@aprobar');
Route::post('arbolaprobacion/aprobar-requisicion', 'Configuracion\ArbolaprobacionController@confirmarAprobacionRequisicion')->name('aprobar_requisicion_externo');
Route::post('arbolaprobacion/aprobar-requisicion-sala', 'Configuracion\ArbolaprobacionController@confirmarAprobacionRequisicionSala')->name('aprobar_requisicion_sala_externo');
Route::post('arbolaprobacion/aprobar-ordencompra', 'Configuracion\ArbolaprobacionController@confirmarAprobacionOrdencompra')->name('aprobar_ordencompra_externo');
Route::get('arbolaprobacion/buscarechazo/{tipocomprobante}/{comprobante_id}/{hash}', 'Configuracion\ArbolaprobacionController@buscaRechazo')->name('busca_rechazo');
Route::put('arbolaprobacion/rechazar', 'Configuracion\ArbolaprobacionController@rechazar')->name('rechazar');

Route::get('arbolaprobacion/leer_movimiento_aprobacion/{tipocomprobante}/{ordenventa_id}', 'Configuracion\ArbolaprobacionController@leerMovimientoAprobacion')->name('lee_movimiento_aprobacion');
/*
 * Rubros contables
 */

Route::get('contable/rubrocontable', 'Contable\RubrocontableController@index')->name('rubrocontable');
Route::get('contable/rubrocontable/crear', 'Contable\RubrocontableController@crear')->name('crear_rubrocontable');
Route::post('contable/rubrocontable', 'Contable\RubrocontableController@guardar')->name('guardar_rubrocontable');
Route::get('contable/rubrocontable/{id}/editar', 'Contable\RubrocontableController@editar')->name('editar_rubrocontable');
Route::put('contable/rubrocontable/{id}', 'Contable\RubrocontableController@actualizar')->name('actualizar_rubrocontable');
Route::delete('contable/rubrocontable/{id}', 'Contable\RubrocontableController@eliminar')->name('eliminar_rubrocontable');

/*
 * Centros de costo
 */

Route::get('contable/centrocosto', 'Contable\CentrocostoController@index')->name('centrocosto');
Route::get('contable/centrocosto/crear', 'Contable\CentrocostoController@crear')->name('crear_centrocosto');
Route::post('contable/centrocosto', 'Contable\CentrocostoController@guardar')->name('guardar_centrocosto');
Route::get('contable/centrocosto/{id}/editar', 'Contable\CentrocostoController@editar')->name('editar_centrocosto');
Route::post('contable/centrocosto/consultacentrocosto', 'Contable\CentrocostoController@consultaCentrocosto')->name('consulta_centrocosto');
Route::get('contable/centrocosto/resolvercentrocosto', 'Contable\CentrocostoController@resolverCentrocosto')->name('resolver_centrocosto');
Route::put('contable/centrocosto/{id}', 'Contable\CentrocostoController@actualizar')->name('actualizar_centrocosto');
Route::delete('contable/centrocosto/{id}', 'Contable\CentrocostoController@eliminar')->name('eliminar_centrocosto');

Route::get('contable/bien-uso', 'Contable\BienUsoController@index')->name('bien_uso');
Route::get('contable/listabienuso/{formato?}/{busqueda?}', 'Contable\BienUsoController@listar')->name('lista_bien_uso');
Route::get('contable/bien-uso/crear', 'Contable\BienUsoController@crear')->name('crear_bien_uso');
Route::post('contable/bien-uso', 'Contable\BienUsoController@guardar')->name('guardar_bien_uso');
Route::get('contable/bien-uso/{id}/editar', 'Contable\BienUsoController@editar')->name('editar_bien_uso')->middleware('modo.consulta');
Route::put('contable/bien-uso/{id}', 'Contable\BienUsoController@actualizar')->name('actualizar_bien_uso')->middleware('modo.consulta');
Route::delete('contable/bien-uso/{id}', 'Contable\BienUsoController@eliminar')->name('eliminar_bien_uso');

/*
 * Cuentas contables
 */

Route::get('contable/cuentacontable', 'Contable\CuentacontableController@index')->name('cuentacontable');
Route::get('contable/cuentacontable/crear', 'Contable\CuentacontableController@crear')->name('crear_cuentacontable');
Route::post('contable/cuentacontable', 'Contable\CuentacontableController@guardar')->name('guardar_cuentacontable');
Route::get('contable/cuentacontable/{id}/editar', 'Contable\CuentacontableController@editar')->name('editar_cuentacontable');
Route::put('contable/cuentacontable/{id}', 'Contable\CuentacontableController@actualizar')->name('actualizar_cuentacontable');
Route::get('contable/cuentacontable/{id}/eliminar', 'Contable\CuentacontableController@eliminar')->name('eliminar_cuentacontable');
Route::post('contable/cuentacontable/guardarorden', 'Contable\CuentacontableController@guardarOrden')->name('guardar_orden_contable');

// Rutas de consulta de cuentas contables
Route::post('contable/cuentacontable/consultacuentacontable', 'Contable\CuentacontableController@consultaCuentaContable')->name('consulta_cuentacontable');
Route::get('contable/cuentacontable/leercuentacontableporcodigo/{empresa_id}/{codigo}', 'Contable\CuentacontableController@leerCuentaContablePorCodigo')->name('leer_cuentacontable_por_codigo');
Route::get('contable/cuentacontable/leercuentacontablecentrocosto/{cuentacontable_id}', 'Contable\CuentacontableController@leerCuentaContableCentroCosto')->name('leer_cuentacontable_centrocosto');

/*
 * Tipos de asiento
 */

Route::get('contable/tipoasiento', 'Contable\TipoasientoController@index')->name('tipoasiento');
Route::get('contable/tipoasiento/crear', 'Contable\TipoasientoController@crear')->name('crear_tipoasiento');
Route::post('contable/tipoasiento', 'Contable\TipoasientoController@guardar')->name('guardar_tipoasiento');
Route::get('contable/tipoasiento/{id}/editar', 'Contable\TipoasientoController@editar')->name('editar_tipoasiento');
Route::put('contable/tipoasiento/{id}', 'Contable\TipoasientoController@actualizar')->name('actualizar_tipoasiento');
Route::delete('contable/tipoasiento/{id}', 'Contable\TipoasientoController@eliminar')->name('eliminar_tipoasiento');

/*
 * Asientos contables
 */

Route::get('contable/asiento', 'Contable\AsientoController@index')->name('asiento');
Route::get('contable/asiento/crear', 'Contable\AsientoController@crear')->name('crear_asiento');
Route::post('contable/asiento', 'Contable\AsientoController@guardar')->name('guardar_asiento');
Route::get('contable/asiento/{id}/editar', 'Contable\AsientoController@editar')->name('editar_asiento');
Route::put('contable/actualizarasiento/{id}', 'Contable\AsientoController@actualizar')->name('actualizar_asiento');
Route::delete('contable/asiento/{id}', 'Contable\AsientoController@eliminar')->name('eliminar_asiento');
Route::get('contable/listaasiento/{formato?}/{busqueda?}', 'Contable\AsientoController@listar')->name('lista_asiento');
Route::get('contable/asiento/{id}/imprimir-pdf', 'Contable\AsientoController@imprimirPdf')->name('imprimir_pdf_asiento');
Route::post('contable/copiar_asiento', 'Contable\AsientoController@copiarAsiento')->name('copiar_asiento');
Route::post('contable/revertir_asiento', 'Contable\AsientoController@revertirAsiento')->name('revertir_asiento');

Route::get('contable/mayor-concepto', 'Contable\MayorConceptoController@index')->name('mayor_concepto');
Route::post('contable/mayor-concepto/consultar', 'Contable\MayorConceptoController@consultar')->name('mayor_concepto_consultar');
Route::get('contable/listar-mayor-concepto/{formato}', 'Contable\MayorConceptoController@exportar')->name('listar_mayor_concepto');

Route::get('contable/sicore', 'Contable\SicoreReporteController@index')->name('sicore');
Route::get('contable/exportar-sicore', 'Contable\SicoreReporteController@exportar')->name('exportar_sicore');

Route::get('contable/efe-mensual', 'Contable\EfeMensualController@index')->name('efe_mensual');
Route::get('contable/listar-efe-mensual/{formato}', 'Contable\EfeMensualController@exportar')->name('listar_efe_mensual');

Route::get('contable/mayor-plano-cuenta', 'Contable\MayorPlanoCuentaController@index')->name('mayor_plano_cuenta');
Route::get('contable/listar-mayor-plano-cuenta/{formato}', 'Contable\MayorPlanoCuentaController@exportar')->name('listar_mayor_plano_cuenta');

Route::get('contable/cuentas-automaticas', 'Contable\ContabilidadCuentaAutomaticaController@index')->name('cuentas_automaticas_contables');
Route::put('contable/cuentas-automaticas', 'Contable\ContabilidadCuentaAutomaticaController@actualizar')->name('actualizar_cuentas_automaticas_contables');

Route::get('contable/conciliacion-bancaria', 'Contable\ConciliacionBancariaController@index')->name('conciliacion_bancaria');
Route::get('contable/conciliacion-bancaria/api/enganche-cuentacaja', 'Contable\ConciliacionBancariaController@apiEngancheCuentacaja')->name('conciliacion_bancaria_api_enganche');
Route::get('contable/conciliacion-bancaria/api/cuentacaja-por-codigo/{codigo}', 'Contable\ConciliacionBancariaController@apiCuentacajaPorCodigo')->name('conciliacion_bancaria_api_cuentacaja_por_codigo');
Route::get('contable/exportar-conciliacion-bancaria/{formato}', 'Contable\ConciliacionBancariaController@exportar')->name('exportar_conciliacion_bancaria');

Route::get('contable/cierre-rendiciones-estacionamiento', 'Contable\CierreRendicionEstacionamientoController@index')->name('cierre_rendicion_estacionamiento_contable');
Route::get('contable/cierre-rendiciones-estacionamiento/conciliacion-flash', 'Contable\CierreRendicionEstacionamientoController@conciliacionFlash')->name('cierre_rendicion_estacionamiento_conciliacion_flash');
Route::get('contable/listar-cierre-rendiciones-estacionamiento/{formato?}/{busqueda?}', 'Contable\CierreRendicionEstacionamientoController@listar')->name('listar_cierre_rendicion_estacionamiento_contable');
Route::post('contable/cierre-rendiciones-estacionamiento/api/preview-asiento', 'Contable\CierreRendicionEstacionamientoController@apiPreviewAsiento')->name('api_cierre_rendicion_estacionamiento_preview');
Route::post('contable/cierre-rendiciones-estacionamiento/api/preview-cierre-rango', 'Contable\CierreRendicionEstacionamientoController@apiPreviewCierreRango')->name('api_cierre_rendicion_estacionamiento_preview_rango');
Route::post('contable/cierre-rendiciones-estacionamiento/api/ejecutar-cierre', 'Contable\CierreRendicionEstacionamientoController@apiEjecutarCierre')->name('api_cierre_rendicion_estacionamiento_ejecutar');
Route::post('contable/cierre-rendiciones-estacionamiento/api/ejecutar-cierre-rango', 'Contable\CierreRendicionEstacionamientoController@apiEjecutarCierreRango')->name('api_cierre_rendicion_estacionamiento_ejecutar_rango');
Route::post('contable/cierre-rendiciones-estacionamiento/api/ejecutar-cierre-jornada', 'Contable\CierreRendicionEstacionamientoController@apiEjecutarCierreJornada')->name('api_cierre_rendicion_estacionamiento_ejecutar_jornada');
Route::post('contable/cierre-rendiciones-estacionamiento/api/anular-cierre', 'Contable\CierreRendicionEstacionamientoController@apiAnularCierre')->name('api_cierre_rendicion_estacionamiento_anular');

Route::get('contable/cierre-rendiciones-bingo', 'Contable\CierreRendicionBingoController@index')->name('cierre_rendicion_bingo_contable');
Route::get('contable/listar-cierre-rendiciones-bingo/{formato?}/{busqueda?}', 'Contable\CierreRendicionBingoController@listar')->name('listar_cierre_rendicion_bingo_contable');
Route::post('contable/cierre-rendiciones-bingo/api/preview-asiento', 'Contable\CierreRendicionBingoController@apiPreviewAsiento')->name('api_cierre_rendicion_bingo_preview');
Route::post('contable/cierre-rendiciones-bingo/api/ejecutar-cierre', 'Contable\CierreRendicionBingoController@apiEjecutarCierre')->name('api_cierre_rendicion_bingo_ejecutar');
Route::post('contable/cierre-rendiciones-bingo/api/anular-cierre', 'Contable\CierreRendicionBingoController@apiAnularCierre')->name('api_cierre_rendicion_bingo_anular');

Route::get('contable/cierre-rendiciones-maquinavending', 'Contable\CierreRendicionMaquinavendingController@index')->name('cierre_rendicion_maquinavending_contable');
Route::get('contable/listar-cierre-rendiciones-maquinavending/{formato?}/{busqueda?}', 'Contable\CierreRendicionMaquinavendingController@listar')->name('listar_cierre_rendicion_maquinavending_contable');
Route::post('contable/cierre-rendiciones-maquinavending/api/preview-asiento', 'Contable\CierreRendicionMaquinavendingController@apiPreviewAsiento')->name('api_cierre_rendicion_maquinavending_preview');
Route::post('contable/cierre-rendiciones-maquinavending/api/preview-cierre-rango', 'Contable\CierreRendicionMaquinavendingController@apiPreviewCierreRango')->name('api_cierre_rendicion_maquinavending_preview_rango');
Route::post('contable/cierre-rendiciones-maquinavending/api/ejecutar-cierre', 'Contable\CierreRendicionMaquinavendingController@apiEjecutarCierre')->name('api_cierre_rendicion_maquinavending_ejecutar');
Route::post('contable/cierre-rendiciones-maquinavending/api/ejecutar-cierre-rango', 'Contable\CierreRendicionMaquinavendingController@apiEjecutarCierreRango')->name('api_cierre_rendicion_maquinavending_ejecutar_rango');
Route::post('contable/cierre-rendiciones-maquinavending/api/ejecutar-cierre-jornada', 'Contable\CierreRendicionMaquinavendingController@apiEjecutarCierreJornada')->name('api_cierre_rendicion_maquinavending_ejecutar_jornada');
Route::post('contable/cierre-rendiciones-maquinavending/api/anular-cierre', 'Contable\CierreRendicionMaquinavendingController@apiAnularCierre')->name('api_cierre_rendicion_maquinavending_anular');

Route::get('contable/cierre-periodo', 'Contable\PeriodoCierreContableController@index')->name('cierre_periodo_contable');
Route::post('contable/cierre-periodo/cerrar', 'Contable\PeriodoCierreContableController@cerrar')->name('ejecutar_cierre_periodo_contable');
Route::post('contable/cierre-periodo/borrar-ultimo', 'Contable\PeriodoCierreContableController@borrarUltimo')->name('borrar_ultimo_cierre_periodo_contable');

Route::get('contable/apertura-periodo', 'Contable\AperturaPeriodoContableController@index')->name('apertura_periodo_contable');
Route::post('contable/apertura-periodo/solicitar', 'Contable\AperturaPeriodoContableController@solicitar')->name('solicitar_apertura_periodo_contable');
Route::post('contable/apertura-periodo/{id}/aprobar', 'Contable\AperturaPeriodoContableController@aprobar')->name('aprobar_apertura_periodo_contable');
Route::get('contable/apertura-periodo/{id}/habilitar', 'Contable\AperturaPeriodoContableController@habilitarDesdeAviso')->name('habilitar_apertura_periodo_contable_desde_aviso');
Route::post('contable/apertura-periodo/{id}/rechazar', 'Contable\AperturaPeriodoContableController@rechazar')->name('rechazar_apertura_periodo_contable');
Route::post('contable/apertura-periodo/{id}/revocar', 'Contable\AperturaPeriodoContableController@revocar')->name('revocar_apertura_periodo_contable');

/*
* Aprobación de asientos contables (cuentas no autorizadas)
*/
Route::get('contable/aprobacion-asientos', 'Contable\AsientoAprobacionController@index')->name('aprobacion_asientos');
Route::get('contable/aprobacion-asientos/{id}', 'Contable\AsientoAprobacionController@ver')->name('ver_aprobacion_asiento');
Route::post('contable/aprobacion-asientos/{id}/aprobar', 'Contable\AsientoAprobacionController@aprobar')->name('aprobar_asiento_pendiente');
Route::post('contable/aprobacion-asientos/{id}/rechazar', 'Contable\AsientoAprobacionController@rechazar')->name('rechazar_asiento_pendiente');
Route::get('contable/configuracion-asiento', 'Contable\ConfiguracionAsientoContableController@index')->name('configuracion_asiento_contable');
Route::put('contable/configuracion-asiento', 'Contable\ConfiguracionAsientoContableController@actualizar')->name('actualizar_configuracion_asiento_contable');
Route::post('contable/asiento/validar-cuentas-usuario', 'Contable\AsientoController@validarCuentasUsuario')->name('validar_cuentas_asiento_usuario');

Route::get('contable/asiento/publico/{token}/aprobar', 'Contable\AsientoAprobacionController@aprobarPublico')->name('asiento_aprobar_publico');
Route::match(['get', 'post'], 'contable/asiento/publico/{token}/rechazar', 'Contable\AsientoAprobacionController@rechazarPublico')->name('asiento_rechazar_publico');
Route::get('contable/asiento/publico/{token}/ver', 'Contable\AsientoAprobacionController@verPublico')->name('asiento_ver_publico');

/*
* Cuentas contables por usuario
*/

Route::get('contable/usuario_cuentacontable', 'Contable\Usuario_CuentacontableController@index')->name('usuario_cuentacontable');
Route::get('contable/usuario_cuentacontable/{id}/editar', 'Contable\Usuario_CuentacontableController@editar')->name('editar_usuario_cuentacontable');
Route::put('contable/actualizar_usuario_cuentacontable', 'Contable\Usuario_CuentacontableController@actualizar')->name('actualizar_usuario_cuentacontable');
Route::delete('contable/usuario_cuentacontable/{id}', 'Contable\Usuario_CuentacontableController@eliminar')->name('eliminar_usuario_cuentacontable');

/*
 * Productos
 */

Route::get('stock/producto/{id}', 'Stock\ArticuloFerliController@consultaProducto')->name('consultar_producto');

Route::get('stock/products', 'Stock\ArticuloFerliController@index')->name('products.index');
Route::get('stock/products/list', 'Stock\ArticuloFerliController@list')->name('products.list');
Route::get('stock/product/{sku}/{codigo}', 'Stock\ArticuloFerliController@download')->name('product.download');
Route::get('stock/products/create', 'Stock\ArticuloFerliController@create')->name('product.create');
Route::put('stock/product/save', 'Stock\ArticuloFerliController@save')->name('product.save');
Route::get('stock/product/edit/{id}/{tipo?}/{filtros?}', 'Stock\ArticuloFerliController@edit')->name('product.edit');
Route::get('stock/product/datos-tecnicos/edit/{id}', 'Stock\ArticuloFerliController@edit')->name('product.edittecnica');
Route::put('stock/product/update/{id}/{filtros?}', 'Stock\ArticuloFerliController@actualizar')->name('product.update');
Route::delete('stock/product/delete/{id}', 'Stock\ArticuloFerliController@delete')->name('product.delete');
Route::post('stock/product/limpiafiltro', 'Stock\ArticuloFerliController@limpiafiltro')->name('product.limpiafiltro');
Route::post('stock/product/consultaarticulo', 'Stock\ArticuloFerliController@consultaArticulo')->name('consulta_articulo_ferli');

// Articulos
Route::get('stock/articulo', 'Stock\ArticuloController@index')->name('articulo');
Route::get('stock/articulo/crear', 'Stock\ArticuloController@crear')->name('crear_articulo');
Route::post('stock/articulo/sincronizar-anita', 'Stock\ArticuloController@sincronizarDesdeAnita')->name('sincronizar_articulo_anita');
Route::post('stock/articulo', 'Stock\ArticuloController@guardar')->name('guardar_articulo');
Route::get('stock/articulo/{articulo_id}/precio-proveedor/{proveedor_id}', 'Stock\ArticuloController@precioProveedorArticulo')->name('precio_proveedor_articulo');
Route::get('stock/articulo/resolver-proveedor/{proveedor_id}', 'Stock\ArticuloController@resolverArticuloProveedor')->name('resolver_articulo_proveedor');
Route::get('stock/articulo/{id}/editar', 'Stock\ArticuloController@editar')->name('editar_articulo')->middleware('modo.consulta');
Route::get('stock/articulo/{id}/partes-unicas', 'Stock\ArticuloParteUnicaController@index')->name('articulo_partes_unicas');
Route::post('stock/articulo/{id}/partes-unicas', 'Stock\ArticuloParteUnicaController@guardar')->name('crear_articulo_parte_unica');
Route::delete('stock/articulo/parte-unica/{id}', 'Stock\ArticuloParteUnicaController@eliminar')->name('eliminar_articulo_parte_unica');
Route::put('stock/articulo/{id}', 'Stock\ArticuloController@actualizar')->name('actualizar_articulo')->middleware('modo.consulta');
Route::delete('stock/articulo/{id}', 'Stock\ArticuloController@eliminar')->name('eliminar_articulo');
Route::get('stock/download_articulo/{sku}', 'Stock\ArticuloController@download')->name('download_articulo');
Route::get('stock/articulo/{id}/consultar-npu-etiqueta', 'Stock\ArticuloController@consultarNpuEtiqueta')->name('consultar_npu_etiqueta_articulo');
Route::get('stock/listar_etiqueta_articulo/{id}', 'Stock\ArticuloController@download')->name('listar_etiqueta_articulo');

Route::get('stock/leer_historia_articulo/{articulo_id}', 'Stock\ArticuloController@leerHistoriaArticulo')->name('leer_historia_articulo');
Route::get('stock/leerunarticulo/{articulo_id}', 'Stock\ArticuloController@leeUnArticulo')->name('leer_un_articulo');
Route::get('stock/leerunarticuloporsku/{sku}', 'Stock\ArticuloController@leeUnArticuloPorSku')->name('leer_un_articulo_por_sku');

Route::post('stock/articulo/consultaarticulo', 'Stock\ArticuloController@consultaArticulo')->name('consulta_articulo');
Route::get('stock/articulo/api/saldos-deposito', 'Stock\ArticuloController@apiSaldosDeposito')->name('articulo_saldos_deposito');
Route::get('stock/listaarticulo/{formato?}/{busqueda?}', 'Stock\ArticuloController@listar')->name('lista_articulo');

Route::get('stock/formula-articulo', 'Stock\FormulaArticuloController@index')->name('consultar_formula_articulo');
Route::get('stock/formula-articulo/crear', 'Stock\FormulaArticuloController@crear')->name('crear_formula_articulo');
Route::post('stock/formula-articulo', 'Stock\FormulaArticuloController@guardar')->name('guardar_formula_articulo');
Route::get('stock/formula-articulo/buscar', 'Stock\FormulaArticuloController@buscarJson')->name('buscar_formula_articulo');
Route::get('stock/formula-articulo/costos-ultima-compra', 'Stock\FormulaArticuloController@costosUltimaCompra')->name('costos_ultima_compra_formula_articulo');
Route::get('stock/formula-articulo/{id}/costo-total', 'Stock\FormulaArticuloController@costoTotal')->name('costo_total_formula_articulo');
Route::get('stock/formula-articulo/resolver-por-articulo/{articulo_id}', 'Stock\FormulaArticuloController@resolverPorArticulo')->name('resolver_formula_articulo_por_articulo');
Route::post('stock/formula-articulo/sincronizar-anita', 'Stock\FormulaArticuloController@sincronizarDesdeAnita')->name('sincronizar_formula_articulo_anita');
Route::post('stock/formula-articulo/vincular-articulos-por-codigo', 'Stock\FormulaArticuloController@vincularArticulosPorCodigo')->name('vincular_formula_articulo_por_codigo');
Route::get('stock/formula-articulo/{id}/editar', 'Stock\FormulaArticuloController@editar')->name('editar_formula_articulo');
Route::put('stock/formula-articulo/{id}', 'Stock\FormulaArticuloController@actualizar')->name('actualizar_formula_articulo');
Route::delete('stock/formula-articulo/{id}', 'Stock\FormulaArticuloController@eliminar')->name('eliminar_formula_articulo');
Route::get('stock/lista-formula-articulo/{formato?}/{busqueda?}', 'Stock\FormulaArticuloController@listar')->name('listar_formula_articulo');
Route::get('stock/formula-articulo/{id}/archivo/{archivo}', 'Stock\FormulaArticuloController@descargarArchivo')->name('formula_articulo_archivo');
Route::get('stock/leer_historia_formula_articulo/{formula_articulo_id}', 'Stock\FormulaArticuloController@leerHistoria')->name('leer_historia_formula_articulo');
Route::get('stock/formula-articulo/articulos-compra-por-insumo/{articulo_id}', 'Stock\FormulaArticuloController@articulosCompraPorInsumo')->name('articulos_compra_por_insumo_formula');
Route::get('stock/formula-articulo/{id}/articulos-asociados', 'Stock\FormulaArticuloController@articulosAsociados')->name('articulos_asociados_formula_articulo');
Route::get('stock/formula-articulo/{id}/modal', 'Stock\FormulaArticuloController@modal')->name('formula_articulo_modal');

Route::get('stock/replicar_cuentacontable_articulo/{empresa_id}/{tipoimputacion}/{cuentacontable_id}', 'Stock\ArticuloController@replicarCuentaContableArticulo')->name('replicar_cuentacontable_articulo');

// Actualiza estado articulo desde programas externos
Route::get('stock/actualizaestadoarticulo/{estadoarticulo}/{articulo_id}', 'Stock\ArticuloController@actualizaEstadoArticulo')->name('actualiza_estado_articulo');

Route::get('stock/leercombinaciones/{id}', 'Stock\CombinacionController@leerCombinaciones')->name('leer_combinaciones');
Route::get('stock/leercombinacionesactivas/{id}', 'Stock\CombinacionController@leerCombinacionesActivas')->name('leer_combinaciones_activas');
Route::get('stock/leermodulos/{id}/{modulo?}', 'Stock\LineaController@leerModulos')->name('leer_modulos');
Route::get('stock/leertalles/{id}', 'Stock\ModuloController@leerTalles')->name('leer_talles');

Route::put('stock/product/contaduria/update/{id}', 'Stock\ArticuloController@updateContaduria')->name('product.contaduria.update');
Route::put('stock/product/tecnica/update/{id}', 'Stock\ArticuloController@updateTecnica')->name('product.tecnica.update');

Route::get('stock/combinacion/list', 'Stock\CombinacionController@list')->name('combinacion.list');
Route::get('stock/combinacion/index/{id?}', 'Stock\CombinacionController@index')->name('combinacion.index');

Route::post('stock/combinacion/updateState', 'Stock\CombinacionController@updateState')->name('combinacion.updateState');
Route::post('stock/combinacion/updateStateAll', 'Stock\CombinacionController@updateStateAll')->name('combinacion.updateStateAll');
Route::get('stock/combinacion/edit/{id}/{tipo?}', 'Stock\CombinacionController@edit')->name('combinacion.edit');
Route::put('stock/combinacion/update/{id}', 'Stock\CombinacionController@update')->name('combinacion.update');
Route::put('stock/combinacion/updateTecnica', 'Stock\CombinacionController@updateTecnica')->name('combinacion.tecnica.update');
Route::get('stock/combinacion/create/{id}', 'Stock\CombinacionController@create')->name('combinacion.create');
Route::put('stock/combinacion/save', 'Stock\CombinacionController@save')->name('combinacion.save');
Route::delete('stock/combinacion/delete/{id}', 'Stock\CombinacionController@delete')->name('eliminar_combinacion');
Route::get('stock/combinacion/product/{sku}', 'Stock\CombinacionController@create')->name('combinacion.product');

/*
 * Movimientos de stock
 */

Route::get('stock/movimientostock', 'Stock\MovimientoStockController@index')->name('movimientostock');
Route::get('stock/movimientostock/transferencia/{id}/consultar', 'Stock\MovimientoStockController@consultarTransferencia')->name('consultar_transferencia_movimientostock');
Route::get('stock/movimientostock/transferencia/{id}/com-pdf', 'Stock\MovimientoStockController@imprimirTransferenciaCom')->name('transferencia_movimientostock_com_pdf');
Route::get('stock/movimientostock/crear', 'Stock\MovimientoStockController@crear')->name('crear_movimientostock');
Route::post('stock/movimientostock', 'Stock\MovimientoStockController@guardar')->name('guardar_movimientostock');
Route::get('stock/movimientostock/{id}/com-pdf', 'Stock\MovimientoStockController@imprimirCom')->name('movimientostock_com_pdf');
Route::get('stock/movimientostock/{id}/editar', 'Stock\MovimientoStockController@editar')->name('editar_movimientostock');
Route::match(['get', 'post'], 'stock/movimientostock/preview-asiento', 'Stock\MovimientoStockController@previewAsientoContable')->name('preview_asiento_movimientostock_nuevo');
Route::match(['get', 'post'], 'stock/movimientostock/preview-conversion-formula', 'Stock\MovimientoStockController@previewConversionFormula')->name('preview_conversion_formula_movimientostock');
Route::get('stock/movimientostock/api/saldo-articulo', 'Stock\MovimientoStockController@saldoArticuloDeposito')->name('movimientostock_saldo_articulo');
Route::get('stock/movimientostock/api/precio-linea', 'Stock\MovimientoStockController@precioLineaArticulo')->name('movimientostock_precio_linea');
Route::get('stock/movimientostock/api/resolver-npu-baja', 'Stock\MovimientoStockController@resolverNpuBaja')->name('movimientostock_resolver_npu_baja');
Route::post('stock/movimientostock/consulta-npu-baja', 'Stock\MovimientoStockController@consultaNpuBaja')->name('movimientostock_consulta_npu_baja');
Route::match(['get', 'post'], 'stock/movimientostock/{id}/preview-asiento', 'Stock\MovimientoStockController@previewAsientoContable')->name('preview_asiento_movimientostock');
Route::put('stock/movimientostock/{id}', 'Stock\MovimientoStockController@actualizar')->name('actualizar_movimientostock');
Route::delete('stock/movimientostock/{id}', 'Stock\MovimientoStockController@eliminar')->name('eliminar_movimientostock');
Route::post('stock/movimientostock/{id}/revertir', 'Stock\MovimientoStockController@revertirMovimiento')->name('revertir_movimientostock');
Route::post('stock/movimientostock/transferencia/{id}/revertir', 'Stock\MovimientoStockController@revertirTransferencia')->name('revertir_transferencia_movimientostock');
Route::get('stock/listamovimientostock/{formato?}/{busqueda?}', 'Stock\MovimientoStockController@listar')->name('lista_movimientostock');
Route::get('stock/listarmovimientostock/{id}', 'Stock\MovimientoStockController@listarMovimientoStock')->name('listar_movimientostock');

/*
 * Tipos de transacciones de stock
 */
Route::get('stock/tipotransaccion_stock', 'Stock\Tipotransaccion_StockController@index')->name('tipotransaccion_stock');
Route::get('stock/tipotransaccion_stock/crear', 'Stock\Tipotransaccion_StockController@crear')->name('crear_tipotransaccion_stock');
Route::post('stock/tipotransaccion_stock', 'Stock\Tipotransaccion_StockController@guardar')->name('guardar_tipotransaccion_stock');
Route::get('stock/tipotransaccion_stock/{id}/editar', 'Stock\Tipotransaccion_StockController@editar')->name('editar_tipotransaccion_stock');
Route::put('stock/tipotransaccion_stock/{id}', 'Stock\Tipotransaccion_StockController@actualizar')->name('actualizar_tipotransaccion_stock');
Route::delete('stock/tipotransaccion_stock/{id}', 'Stock\Tipotransaccion_StockController@eliminar')->name('eliminar_tipotransaccion_stock');
Route::get('stock/leertipotransaccion_stock/{id}', 'Stock\Tipotransaccion_StockController@leer')->name('leer_tipotransaccion_stock');
Route::post('stock/tipotransaccion_stock/consultatipotransaccion', 'Stock\Tipotransaccion_StockController@consultaTipotransaccionStock')->name('consulta_tipotransaccion_stock');
Route::get('stock/tipotransaccion_stock/leer/{abreviatura}', 'Stock\Tipotransaccion_StockController@leeUnTipotransaccionPorAbreviatura')->name('leer_tipotransaccion_stock_abreviatura');

/*
 * Transferencia ágil de mercadería (móvil / tablet)
 */
Route::get('stock/transferencia-mercaderia', 'Stock\TransferenciaMercaderiaController@index')->name('transferencia_mercaderia');
Route::get('stock/transferencia-mercaderia/pendientes', 'Stock\TransferenciaMercaderiaController@pendientes')->name('transferencia_mercaderia_pendientes');
Route::get('stock/transferencia-mercaderia/destinatarios', 'Stock\TransferenciaMercaderiaController@destinatarios')->name('transferencia_mercaderia_destinatarios');
Route::get('stock/transferencia-mercaderia/validar-destinatario', 'Stock\TransferenciaMercaderiaController@validarDestinatario')->name('transferencia_mercaderia_validar_destinatario');
Route::post('stock/transferencia-mercaderia/preferencias', 'Stock\TransferenciaMercaderiaController@preferencias')->name('transferencia_mercaderia_preferencias');
Route::get('stock/transferencia-mercaderia/inventario', 'Stock\TransferenciaMercaderiaController@inventario')->name('transferencia_mercaderia_inventario');
Route::get('stock/transferencia-mercaderia/saldo-articulo', 'Stock\TransferenciaMercaderiaController@saldoArticulo')->name('transferencia_mercaderia_saldo_articulo');
Route::get('stock/transferencia-mercaderia/validar-linea-contable', 'Stock\TransferenciaMercaderiaController@validarLineaContable')->name('transferencia_mercaderia_validar_linea_contable');
Route::post('stock/transferencia-mercaderia', 'Stock\TransferenciaMercaderiaController@guardar')->name('transferencia_mercaderia_guardar');
Route::post('stock/transferencia-mercaderia/{id}/aprobar', 'Stock\TransferenciaMercaderiaController@aprobar')->name('transferencia_mercaderia_aprobar');
Route::post('stock/transferencia-mercaderia/{id}/rechazar', 'Stock\TransferenciaMercaderiaController@rechazar')->name('transferencia_mercaderia_rechazar');
Route::get('stock/transferencia-mercaderia/publico/{token}/aprobar', 'Stock\TransferenciaMercaderiaController@aprobarPublico')->name('transferencia_mercaderia_aprobar_publico');
Route::match(['get', 'post'], 'stock/transferencia-mercaderia/publico/{token}/rechazar', 'Stock\TransferenciaMercaderiaController@rechazarPublico')->name('transferencia_mercaderia_rechazar_publico');
Route::get('stock/transferencia-mercaderia/publico/{token}/ver', 'Stock\TransferenciaMercaderiaController@verPublico')->name('transferencia_mercaderia_ver_publico');
Route::get('stock/reporte-movimientos-bien-uso', 'Stock\BienUsoMovimientoReporteController@index')->name('reporte_movimientos_bien_uso');
Route::get('stock/listar-reporte-movimientos-bien-uso/{formato?}', 'Stock\BienUsoMovimientoReporteController@exportar')->name('listar_reporte_movimientos_bien_uso');
Route::get('stock/reporte-baja-npu', 'Stock\ParteUnicaBajaReporteController@index')->name('reporte_baja_npu');
Route::get('stock/listar-reporte-baja-npu/{formato?}', 'Stock\ParteUnicaBajaReporteController@exportar')->name('listar_reporte_baja_npu');
Route::get('stock/reporte-transferencias-pendientes', 'Stock\TransferenciaPendienteReporteController@index')->name('reporte_transferencias_pendientes');
Route::get('stock/listar-reporte-transferencias-pendientes/{formato?}', 'Stock\TransferenciaPendienteReporteController@exportar')->name('listar_reporte_transferencias_pendientes');
Route::get('stock/informes-de-stock/existencias-por-deposito', 'Stock\ExistenciasDepositoReporteController@index')->name('reporte_existencias_deposito');
Route::get('stock/listar-reporte-existencias-deposito/{formato?}', 'Stock\ExistenciasDepositoReporteController@exportar')->name('listar_reporte_existencias_deposito');

/*
 * Préstamos de materiales
 */
Route::get('stock/prestamo', 'Stock\PrestamoController@index')->name('prestamo');
Route::get('stock/prestamo/crear', 'Stock\PrestamoController@crear')->name('crear_prestamo');
Route::post('stock/prestamo', 'Stock\PrestamoController@guardar')->name('guardar_prestamo');
Route::get('stock/prestamo/{id}/editar', 'Stock\PrestamoController@editar')->name('editar_prestamo');
Route::put('stock/prestamo/{id}', 'Stock\PrestamoController@actualizar')->name('actualizar_prestamo');
Route::delete('stock/prestamo/{id}', 'Stock\PrestamoController@eliminar')->name('eliminar_prestamo');
Route::get('stock/prestamo/{id}/ver', 'Stock\PrestamoController@ver')->name('ver_prestamo');
Route::post('stock/prestamo/{id}/confirmar-envio', 'Stock\PrestamoController@confirmarEnvio')->name('confirmar_envio_prestamo');
Route::post('stock/prestamo/{id}/aprobar', 'Stock\PrestamoController@aprobar')->name('aprobar_prestamo');
Route::post('stock/prestamo/{id}/rechazar', 'Stock\PrestamoController@rechazar')->name('rechazar_prestamo');
Route::post('stock/prestamo/{id}/devolver', 'Stock\PrestamoController@devolver')->name('devolver_prestamo');
Route::post('stock/prestamo/{id}/cancelar', 'Stock\PrestamoController@cancelar')->name('cancelar_prestamo');
Route::post('stock/prestamo/{id}/reenviar-correo', 'Stock\PrestamoController@reenviarCorreo')->name('reenviar_correo_prestamo');
Route::get('stock/prestamo/api/saldo-articulo', 'Stock\PrestamoController@saldoArticulo')->name('prestamo_saldo_articulo');

/*
 * Recepción y devolución a proveedores
 */
Route::get('stock/recepcion-proveedor', 'Stock\RecepcionProveedorController@index')->name('recepcion_proveedor');
Route::get('stock/recepcion-proveedor/crear', 'Stock\RecepcionProveedorController@crear')->name('crear_recepcion_proveedor');
Route::post('stock/recepcion-proveedor', 'Stock\RecepcionProveedorController@guardar')->name('guardar_recepcion_proveedor');
Route::get('stock/recepcion-proveedor/consulta-por-articulo', 'Stock\RecepcionProveedorArticuloConsultaController@index')->name('recepcion_proveedor_consulta_articulo')->middleware('modo.consulta');
Route::get('stock/listar-recepcion-proveedor-articulo/{formato?}', 'Stock\RecepcionProveedorArticuloConsultaController@listar')->name('lista_recepcion_proveedor_articulo')->middleware('modo.consulta');
Route::get('stock/recepcion-proveedor/{id}/editar', 'Stock\RecepcionProveedorController@editar')->name('editar_recepcion_proveedor')->middleware('modo.consulta');
Route::put('stock/recepcion-proveedor/{id}', 'Stock\RecepcionProveedorController@actualizar')->name('actualizar_recepcion_proveedor');
Route::post('stock/recepcion-proveedor/{id}/confirmar', 'Stock\RecepcionProveedorController@confirmar')->name('confirmar_recepcion_proveedor');
Route::post('stock/recepcion-proveedor/api/preview-articulo-proveedor', 'Stock\RecepcionProveedorController@apiPreviewArticuloProveedor')->name('recepcion_proveedor_preview_articulo_proveedor');
Route::get('stock/recepcion-proveedor/api/precarga-oc', 'Stock\RecepcionProveedorController@apiPrecargaOc')->name('recepcion_proveedor_precarga_oc');
Route::get('stock/recepcion-proveedor/api/buscar-oc-pendientes', 'Stock\RecepcionProveedorController@apiBuscarOcPendientes')->name('recepcion_proveedor_buscar_oc_pendientes');
Route::get('stock/listarecepcionproveedor/{formato?}/{busqueda?}', 'Stock\RecepcionProveedorController@listar')->name('lista_recepcion_proveedor');
Route::get('stock/recepcion-proveedor/{id}/com-pdf', 'Stock\RecepcionProveedorController@imprimirCom')->name('recepcion_proveedor_com_pdf');
Route::get('stock/recepcion-proveedor/{id}/devolucion', 'Stock\RecepcionProveedorController@crearDevolucion')->name('crear_devolucion_recepcion_proveedor');
Route::post('stock/recepcion-proveedor/{id}/devolucion', 'Stock\RecepcionProveedorController@guardarDevolucion')->name('guardar_devolucion_recepcion_proveedor');
Route::post('stock/recepcion-proveedor/{id}/anular', 'Stock\RecepcionProveedorController@anular')->name('anular_recepcion_proveedor');
Route::delete('stock/recepcion-proveedor/{id}', 'Stock\RecepcionProveedorController@eliminar')->name('eliminar_recepcion_proveedor');
Route::post('stock/recepcion-proveedor/ocr-preview', 'Stock\RecepcionProveedorController@procesarOcrPreview')->name('recepcion_proveedor_ocr_preview');
Route::post('stock/recepcion-proveedor/{id}/ocr', 'Stock\RecepcionProveedorController@subirOcr')->name('recepcion_proveedor_ocr');
Route::get('stock/recepcion-proveedor/{id}/archivo/{archivo}', 'Stock\RecepcionProveedorController@descargarArchivo')->name('recepcion_proveedor_archivo');
Route::get('stock/recepcion-proveedor/publico/{token}/ver', 'Stock\RecepcionProveedorController@verPublico')->name('recepcion_proveedor_ver_publico');
Route::get('stock/recepcion-proveedor/publico/{token}/com-pdf', 'Stock\RecepcionProveedorController@imprimirComPublico')->name('recepcion_proveedor_com_pdf_publico');
Route::get('configuracion/recepcion-proveedor', 'Configuracion\ConfiguracionRecepcionProveedorController@index')->name('configuracion_recepcion_proveedor');
Route::put('configuracion/recepcion-proveedor', 'Configuracion\ConfiguracionRecepcionProveedorController@actualizar')->name('actualizar_configuracion_recepcion_proveedor');
Route::post('configuracion/recepcion-proveedor/tolerancias', 'Configuracion\ConfiguracionRecepcionProveedorController@guardarTolerancias')->name('guardar_tolerancias_recepcion_proveedor');

/*
 * Recuento de inventario por depósito
 */
Route::get('stock/recuento', 'Stock\RecuentoController@index')->name('recuento');
Route::get('stock/listarecuento/{formato?}/{busqueda?}', 'Stock\RecuentoController@listar')->name('lista_recuento');
Route::get('stock/recuento/crear', 'Stock\RecuentoController@crear')->name('crear_recuento');
Route::post('stock/recuento', 'Stock\RecuentoController@guardar')->name('guardar_recuento');
Route::get('stock/recuento/{id}/editar', 'Stock\RecuentoController@editar')->name('editar_recuento');
Route::put('stock/recuento/{id}', 'Stock\RecuentoController@actualizar')->name('actualizar_recuento');
Route::delete('stock/recuento/{id}', 'Stock\RecuentoController@eliminar')->name('eliminar_recuento');
Route::get('stock/recuento/{id}/ver', 'Stock\RecuentoController@ver')->name('ver_recuento');
Route::post('stock/recuento/{id}/suspender', 'Stock\RecuentoController@suspender')->name('suspender_recuento');
Route::post('stock/recuento/{id}/reactivar', 'Stock\RecuentoController@reactivar')->name('reactivar_recuento');
Route::post('stock/recuento/{id}/anular', 'Stock\RecuentoController@anular')->name('anular_recuento');
Route::post('stock/recuento/{id}/cerrar-parcial', 'Stock\RecuentoController@cerrarParcial')->name('cerrar_recuento_parcial');
Route::post('stock/recuento/{id}/cerrar-total', 'Stock\RecuentoController@cerrarTotal')->name('cerrar_recuento_total');
Route::post('stock/recuento/{id}/anular-cierre', 'Stock\RecuentoController@anularCierre')->name('anular_cierre_recuento');
Route::get('stock/recuento/{id}/pdf', 'Stock\RecuentoController@pdf')->name('imprimir_pdf_recuento');
Route::get('stock/recuento/{id}/excel', 'Stock\RecuentoController@excel')->name('exportar_excel_recuento');
Route::get('stock/recuento/api/saldo-articulo', 'Stock\RecuentoController@saldoArticulo')->name('recuento_saldo_articulo');
Route::get('stock/recuento/movimientos-articulo', 'Stock\RecuentoMovimientosArticuloController@index')->name('recuento_movimientos_articulo')->middleware('modo.consulta');
Route::get('stock/listarecuento-movimientos-articulo/{formato?}', 'Stock\RecuentoMovimientosArticuloController@listar')->name('lista_recuento_movimientos_articulo')->middleware('modo.consulta');
Route::post('stock/recuento/api/aleatorio', 'Stock\RecuentoController@aleatorio')->name('recuento_aleatorio');
Route::post('stock/recuento/api/importar-preview', 'Stock\RecuentoController@importarPreview')->name('importar_recuento_preview');
Route::get('stock/recuento/{id}/importar', 'Stock\RecuentoController@importarForm')->name('importar_recuento_form');
Route::post('stock/recuento/{id}/importar', 'Stock\RecuentoController@importar')->name('importar_recuento');
Route::get('stock/recuento/{id}/archivo/{nombre}', 'Stock\RecuentoController@descargarArchivo')->name('descargar_archivo_recuento');

/*
 * Endpoints públicos por token (sin login) que reciben los administradores
 * destinatarios del préstamo en su correo para aprobar / rechazar / ver.
 */
Route::get('stock/prestamo/publico/{token}/aprobar', 'Stock\PrestamoController@aprobarPublico')->name('prestamo_aprobar_publico');
Route::match(['get', 'post'], 'stock/prestamo/publico/{token}/rechazar', 'Stock\PrestamoController@rechazarPublico')->name('prestamo_rechazar_publico');
Route::get('stock/prestamo/publico/{token}/ver', 'Stock\PrestamoController@verPublico')->name('prestamo_ver_publico');

/*
 * Configuración de Préstamos
 */
Route::get('stock/configuracion-prestamo', 'Stock\ConfiguracionPrestamoController@index')->name('configuracion_prestamo');
Route::put('stock/configuracion-prestamo', 'Stock\ConfiguracionPrestamoController@actualizar')->name('actualizar_configuracion_prestamo');

/*
 * Administradores de depósito
 */
Route::get('stock/deposito-administrador', 'Stock\DepositoAdministradorController@index')->name('deposito_administrador');
Route::get('stock/deposito-administrador/crear', 'Stock\DepositoAdministradorController@crear')->name('crear_deposito_administrador');
Route::post('stock/deposito-administrador', 'Stock\DepositoAdministradorController@guardar')->name('guardar_deposito_administrador');
Route::get('stock/deposito-administrador/{id}/editar', 'Stock\DepositoAdministradorController@editar')->name('editar_deposito_administrador');
Route::put('stock/deposito-administrador/{id}', 'Stock\DepositoAdministradorController@actualizar')->name('actualizar_deposito_administrador');
Route::delete('stock/deposito-administrador/{id}', 'Stock\DepositoAdministradorController@eliminar')->name('eliminar_deposito_administrador');

// Modulo de ventas
// Reportes de ventas

// Percepciones de IIBB
Route::get('ventas/reppercepcioniibb', 'Ventas\ReppercepcioniibbController@index')->name('listar_percepcioniibb');
Route::post('ventas/crearreppercepcioniibb', 'Ventas\ReppercepcioniibbController@crearReporteControlPercepcionesIIBB')->name('crear_reppercepcioniibb');

// Pedidos
Route::get('ventas/reppedido', 'Ventas\PedidoController@indexReportePedido')->name('rep_pedido');
Route::post('ventas/crearreppedido', 'Ventas\PedidoController@crearReportePedido')->name('crear_reppedido');

// Kilos Pedidos
Route::get('ventas/repkilopedido', 'Ventas\PedidoController@indexReporteKiloPedido')->name('rep_kilopedido');
Route::get('ventas/listar-repkilopedido/{formato}', 'Ventas\PedidoController@listarReporteKiloPedido')->name('listar_rep_kilopedido');
Route::post('ventas/crearrepkilopedido', 'Ventas\PedidoController@crearReporteKiloPedido')->name('crear_rep_kilopedido');
Route::get('ventas/repkilocategoria', 'Ventas\PedidoController@indexReporteKiloCategoria')->name('rep_kilocategoria');
Route::get('ventas/listar-repkilocategoria/{formato}', 'Ventas\PedidoController@listarReporteKiloCategoria')->name('listar_rep_kilocategoria');
Route::get('ventas/iva-ventas', 'Ventas\IvaVentasReporteController@index')->name('iva_ventas');
Route::get('ventas/listar-iva-ventas/{formato}', 'Ventas\IvaVentasReporteController@exportar')->name('listar_iva_ventas');
Route::get('ventas/cot-electronico', 'Ventas\CotElectronicoController@index')->name('cot_electronico');
Route::post('ventas/cot-electronico/probar-conexion', 'Ventas\CotElectronicoController@probarConexion')->name('cot_electronico_probar_conexion');
Route::get('ventas/listar-cot-electronico/{formato?}', 'Ventas\CotElectronicoController@exportar')->name('listar_cot_electronico');
Route::get('ventas/listar-cot-electronico-sesion/{id}/{formato?}', 'Ventas\CotElectronicoController@exportarSesion')->name('listar_cot_electronico_sesion')->where('id', '[0-9]+');

// Totales de Pedidos
Route::get('ventas/reptotalpedido', 'Ventas\PedidoController@indexReporteTotalPedido')->name('rep_totalpedido');
Route::post('ventas/crearreptotalpedido', 'Ventas\PedidoController@crearReporteTotalPedido')->name('crear_reptotalpedido');

// General de Pedidos
Route::get('ventas/repgeneralpedido', 'Ventas\PedidoController@indexReporteGeneralPedido')->name('rep_generalpedido');
Route::post('ventas/crearrepgeneralpedido', 'Ventas\PedidoController@crearReporteGeneralPedido')->name('crear_repgeneralpedido');

// Consumo de materiales
Route::get('ventas/repconsumomaterial', 'Ventas\PedidoController@indexReporteConsumoMaterial')->name('rep_consumomaterial');
Route::post('ventas/crearrepconsumomaterial', 'Ventas\PedidoController@crearReporteConsumoMaterial')->name('crear_repconsumomaterial');

// Etiquetas de OT
Route::get('ventas/repetiquetaot', 'Ventas\OrdentrabajoController@indexEtiqueta')->name('repetiquetaot');
Route::post('ventas/crearetiquetaot', 'Ventas\OrdentrabajoController@crearEtiquetaOt')->name('crear_repetiquetaot');
Route::get('ventas/generazpl', 'Ventas\OrdentrabajoController@generaZPL')->name('genera_zpl');
Route::get('ventas/generaetiquetaprueba', 'Ventas\OrdentrabajoController@generaEtiquetaPruebaOt')->name('generaetiquetaprueba');
Route::post('ventas/crearetiquetapruebaot', 'Ventas\OrdentrabajoController@crearEtiquetaPruebaOt')->name('crear_repetiquetapruebaot');

// Emision de OT
Route::get('ventas/repemisionot', 'Ventas\OrdentrabajoController@indexEmisionOT')->name('repemisionot');
Route::post('ventas/crearemisionot', 'Ventas\OrdentrabajoController@crearEmisionOt')->name('crearemisionot');

// Clientes
Route::get('ventas/repcliente', 'Ventas\ClienteController@indexReporteCliente')->name('rep_cliente');
Route::post('ventas/crearrepcliente', 'Ventas\ClienteController@crearReporteCliente')->name('crear_repcliente');

/*
 * Vendedores
 */

Route::get('ventas/vendedor', 'Ventas\VendedorController@index')->name('vendedor');
Route::get('ventas/vendedor/crear', 'Ventas\VendedorController@crear')->name('crear_vendedor');
Route::post('ventas/vendedor', 'Ventas\VendedorController@guardar')->name('guardar_vendedor');
Route::get('ventas/vendedor/{id}/editar', 'Ventas\VendedorController@editar')->name('editar_vendedor');
Route::put('ventas/vendedor/{id}', 'Ventas\VendedorController@actualizar')->name('actualizar_vendedor');
Route::delete('ventas/vendedor/{id}', 'Ventas\VendedorController@eliminar')->name('eliminar_vendedor');

Route::get('ventas/listavendedor/{formato?}/{busqueda?}', 'Ventas\VendedorController@listar')->name('lista_vendedor');
Route::post('ventas/vendedor/consultavendedor', 'Ventas\VendedorController@consultavendedor')->name('consulta_vendedor');
Route::get('ventas/leervendedor/{vendedor_id}', 'Ventas\VendedorController@leeUnVendedor')->name('leer_vendedor');
Route::get('ventas/vendedor/{id}/editarremoto', 'Ventas\VendedorController@editarRemoto')->name('editar_vendedor_remoto');

/*
 * Zonas de venta
 */

Route::get('ventas/zonavta', 'Ventas\ZonavtaController@index')->name('zonavta');
Route::get('ventas/zonavta/crear', 'Ventas\ZonavtaController@crear')->name('crear_zonavta');
Route::post('ventas/zonavta', 'Ventas\ZonavtaController@guardar')->name('guardar_zonavta');
Route::get('ventas/zonavta/{id}/editar', 'Ventas\ZonavtaController@editar')->name('editar_zonavta');
Route::put('ventas/zonavta/{id}', 'Ventas\ZonavtaController@actualizar')->name('actualizar_zonavta');
Route::delete('ventas/zonavta/{id}', 'Ventas\ZonavtaController@eliminar')->name('eliminar_zonavta');

Route::get('ventas/listazonavta/{formato?}/{busqueda?}', 'Ventas\ZonavtaController@listar')->name('lista_zonavta');
Route::post('ventas/zonavta/consultazonavta', 'Ventas\ZonavtaController@consultazonavta')->name('consulta_zonavta');
Route::get('ventas/leerzonavta/{zonavta_id}', 'Ventas\ZonavtaController@leeUnaZonavta')->name('leer_zonavta');
Route::get('ventas/leerzonavtaporid/{zonavta_id}', 'Ventas\ZonavtaController@leeUnaZonavtaPorId')->name('leer_zonavta_por_id');

/*
 * Subzonas de venta
 */

Route::get('ventas/subzonavta', 'Ventas\SubzonavtaController@index')->name('subzonavta');
Route::get('ventas/subzonavta/crear', 'Ventas\SubzonavtaController@crear')->name('crear_subzonavta');
Route::post('ventas/subzonavta', 'Ventas\SubzonavtaController@guardar')->name('guardar_subzonavta');
Route::get('ventas/subzonavta/{id}/editar', 'Ventas\SubzonavtaController@editar')->name('editar_subzonavta');
Route::put('ventas/subzonavta/{id}', 'Ventas\SubzonavtaController@actualizar')->name('actualizar_subzonavta');
Route::delete('ventas/subzonavta/{id}', 'Ventas\SubzonavtaController@eliminar')->name('eliminar_subzonavta');

/*
 * Condiciones de venta
 */

Route::get('ventas/condicionventa', 'Ventas\CondicionventaController@index')->name('condicionventa');
Route::get('ventas/condicionventa/crear', 'Ventas\CondicionventaController@crear')->name('crear_condicionventa');
Route::post('ventas/condicionventa', 'Ventas\CondicionventaController@guardar')->name('guardar_condicionventa');
Route::get('ventas/condicionventa/{id}/editar', 'Ventas\CondicionventaController@editar')->name('editar_condicionventa');
Route::put('ventas/condicionventa/{id}', 'Ventas\CondicionventaController@actualizar')->name('actualizar_condicionventa');
Route::delete('ventas/condicionventa/{id}', 'Ventas\CondicionventaController@eliminar')->name('eliminar_condicionventa');

/*
 * Transportes
 */

Route::get('ventas/transporte', 'Ventas\TransporteController@index')->name('transporte');
Route::get('ventas/transporte/crear', 'Ventas\TransporteController@crear')->name('crear_transporte');
Route::post('ventas/transporte', 'Ventas\TransporteController@guardar')->name('guardar_transporte');
Route::get('ventas/transporte/{id}/editar', 'Ventas\TransporteController@editar')->name('editar_transporte');
Route::put('ventas/transporte/{id}', 'Ventas\TransporteController@actualizar')->name('actualizar_transporte');
Route::delete('ventas/transporte/{id}', 'Ventas\TransporteController@eliminar')->name('eliminar_transporte');

Route::post('ventas/transporte/consultatransporte', 'Ventas\TransporteController@consultaTransporte')->name('consulta_transporte');
Route::get('ventas/leertransporte/{transporte_id}', 'Ventas\TransporteController@leeTransporte')->name('leer_transporte');

/*
 * Motivos de cierre de pedido
 */

Route::get('ventas/motivocierrepedido', 'Ventas\MotivocierrepedidoController@index')->name('motivocierrepedido');
Route::get('ventas/motivocierrepedido/crear', 'Ventas\MotivocierrepedidoController@crear')->name('crear_motivocierrepedido');
Route::post('ventas/motivocierrepedido', 'Ventas\MotivocierrepedidoController@guardar')->name('guardar_motivocierrepedido');
Route::get('ventas/motivocierrepedido/{id}/editar', 'Ventas\MotivocierrepedidoController@editar')->name('editar_motivocierrepedido');
Route::put('ventas/motivocierrepedido/{id}', 'Ventas\MotivocierrepedidoController@actualizar')->name('actualizar_motivocierrepedido');
Route::delete('ventas/motivocierrepedido/{id}', 'Ventas\MotivocierrepedidoController@eliminar')->name('eliminar_motivocierrepedido');

/*
 * Tipos de empresa (clientes / ventas)
 */

Route::get('ventas/tipoempresa-cliente', 'Ventas\TipoempresaClienteController@index')->name('tipoempresa_cliente');
Route::get('ventas/tipoempresa-cliente/crear', 'Ventas\TipoempresaClienteController@crear')->name('tipoempresa_cliente_crear');
Route::post('ventas/tipoempresa-cliente', 'Ventas\TipoempresaClienteController@guardar')->name('tipoempresa_cliente_guardar');
Route::get('ventas/tipoempresa-cliente/{id}/editar', 'Ventas\TipoempresaClienteController@editar')->name('tipoempresa_cliente_editar');
Route::put('ventas/tipoempresa-cliente/{id}', 'Ventas\TipoempresaClienteController@actualizar')->name('tipoempresa_cliente_actualizar');
Route::delete('ventas/tipoempresa-cliente/{id}', 'Ventas\TipoempresaClienteController@eliminar')->name('tipoempresa_cliente_eliminar');

/*
 * Tipos suspension de clientes
 */

Route::get('ventas/tiposuspensioncliente', 'Ventas\TiposuspensionclienteController@index')->name('tiposuspensioncliente');
Route::get('ventas/tiposuspensioncliente/crear', 'Ventas\TiposuspensionclienteController@crear')->name('crear_tiposuspensioncliente');
Route::post('ventas/tiposuspensioncliente', 'Ventas\TiposuspensionclienteController@guardar')->name('guardar_tiposuspensioncliente');
Route::get('ventas/tiposuspensioncliente/{id}/editar', 'Ventas\TiposuspensionclienteController@editar')->name('editar_tiposuspensioncliente');
Route::put('ventas/tiposuspensioncliente/{id}', 'Ventas\TiposuspensionclienteController@actualizar')->name('actualizar_tiposuspensioncliente');
Route::delete('ventas/tiposuspensioncliente/{id}', 'Ventas\TiposuspensionclienteController@eliminar')->name('eliminar_tiposuspensioncliente');

/*
 * Incoterms
 */

Route::get('ventas/incoterm', 'Ventas\IncotermController@index')->name('incoterm');
Route::get('ventas/incoterm/crear', 'Ventas\IncotermController@crear')->name('crear_incoterm');
Route::post('ventas/incoterm', 'Ventas\IncotermController@guardar')->name('guardar_incoterm');
Route::get('ventas/incoterm/{id}/editar', 'Ventas\IncotermController@editar')->name('editar_incoterm');
Route::put('ventas/incoterm/{id}', 'Ventas\IncotermController@actualizar')->name('actualizar_incoterm');
Route::delete('ventas/incoterm/{id}', 'Ventas\IncotermController@eliminar')->name('eliminar_incoterm');

/*
 * Forma de pago
 */

Route::get('ventas/formapago', 'Ventas\FormapagoController@index')->name('formapago');
Route::get('ventas/formapago/crear', 'Ventas\FormapagoController@crear')->name('crear_formapago');
Route::post('ventas/formapago', 'Ventas\FormapagoController@guardar')->name('guardar_formapago');
Route::get('ventas/formapago/{id}/editar', 'Ventas\FormapagoController@editar')->name('editar_formapago');
Route::put('ventas/formapago/{id}', 'Ventas\FormapagoController@actualizar')->name('actualizar_formapago');
Route::delete('ventas/formapago/{id}', 'Ventas\FormapagoController@eliminar')->name('eliminar_formapago');

/*
 * Tipos de transacciones de ventas
 */

Route::get('ventas/tipotransaccion', 'Ventas\TipotransaccionController@index')->name('tipotransaccion');
Route::get('ventas/tipotransaccion/arca-tipos-cbte', 'Ventas\TipotransaccionController@tiposCbteArca')->name('tipotransaccion_arca_tipos_cbte');
Route::get('ventas/tipotransaccion/crear', 'Ventas\TipotransaccionController@crear')->name('crear_tipotransaccion');
Route::post('ventas/tipotransaccion', 'Ventas\TipotransaccionController@guardar')->name('guardar_tipotransaccion');
Route::get('ventas/tipotransaccion/{id}/editar', 'Ventas\TipotransaccionController@editar')->name('editar_tipotransaccion');
Route::put('ventas/tipotransaccion/{id}', 'Ventas\TipotransaccionController@actualizar')->name('actualizar_tipotransaccion');
Route::delete('ventas/tipotransaccion/{id}', 'Ventas\TipotransaccionController@eliminar')->name('eliminar_tipotransaccion');

/*
 * Puntos de venta
 */

/*
 * Configuración punto de venta gastronomía
 */
Route::get('ventas/configuracion-puntoventa-gastronomia', 'Ventas\ConfiguracionPuntoventaGastronomiaController@index')->name('consultar_configuracion_puntoventa_gastronomia');
Route::get('ventas/configuracion-puntoventa-gastronomia/crear', 'Ventas\ConfiguracionPuntoventaGastronomiaController@crear')->name('crear_configuracion_puntoventa_gastronomia');
Route::post('ventas/configuracion-puntoventa-gastronomia', 'Ventas\ConfiguracionPuntoventaGastronomiaController@guardar')->name('guardar_configuracion_puntoventa_gastronomia');
Route::get('ventas/configuracion-puntoventa-gastronomia/{id}/editar', 'Ventas\ConfiguracionPuntoventaGastronomiaController@editar')->name('editar_configuracion_puntoventa_gastronomia');
Route::put('ventas/configuracion-puntoventa-gastronomia/{id}', 'Ventas\ConfiguracionPuntoventaGastronomiaController@actualizar')->name('actualizar_configuracion_puntoventa_gastronomia');
Route::delete('ventas/configuracion-puntoventa-gastronomia/{id}', 'Ventas\ConfiguracionPuntoventaGastronomiaController@eliminar')->name('eliminar_configuracion_puntoventa_gastronomia');
Route::get('ventas/configuracion-puntoventa-gastronomia/api/selects-por-empresa/{empresaId}', 'Ventas\ConfiguracionPuntoventaGastronomiaController@apiSelectsPorEmpresa')->name('configuracion_puntoventa_gastronomia_api_selects');

/*
 * Gastronomía — ABM y proceso
 */
Route::get('ventas/mesa-gastronomia', 'Ventas\MesaGastronomiaController@index')->name('consultar_mesa_gastronomia');
Route::get('ventas/mesa-gastronomia/crear', 'Ventas\MesaGastronomiaController@crear')->name('crear_mesa_gastronomia');
Route::post('ventas/mesa-gastronomia', 'Ventas\MesaGastronomiaController@guardar')->name('guardar_mesa_gastronomia');
Route::post('ventas/mesa-gastronomia/sincronizar-anita', 'Ventas\MesaGastronomiaController@sincronizarDesdeAnita')->name('sincronizar_mesa_gastronomia_anita');
Route::get('ventas/mesa-gastronomia/{id}/editar', 'Ventas\MesaGastronomiaController@editar')->name('editar_mesa_gastronomia');
Route::put('ventas/mesa-gastronomia/{id}', 'Ventas\MesaGastronomiaController@actualizar')->name('actualizar_mesa_gastronomia');
Route::delete('ventas/mesa-gastronomia/{id}', 'Ventas\MesaGastronomiaController@eliminar')->name('eliminar_mesa_gastronomia');

Route::get('ventas/ubicaciones-gastronomia', 'Ventas\UbicacionGastronomiaController@index')->name('consultar_ubicaciones_gastronomia');
Route::get('ventas/ubicaciones-gastronomia/crear', 'Ventas\UbicacionGastronomiaController@crear')->name('crear_ubicaciones_gastronomia');
Route::post('ventas/ubicaciones-gastronomia', 'Ventas\UbicacionGastronomiaController@guardar')->name('guardar_ubicaciones_gastronomia');
Route::get('ventas/ubicaciones-gastronomia/{id}/editar', 'Ventas\UbicacionGastronomiaController@editar')->name('editar_ubicaciones_gastronomia');
Route::put('ventas/ubicaciones-gastronomia/{id}', 'Ventas\UbicacionGastronomiaController@actualizar')->name('actualizar_ubicaciones_gastronomia');
Route::delete('ventas/ubicaciones-gastronomia/{id}', 'Ventas\UbicacionGastronomiaController@eliminar')->name('eliminar_ubicaciones_gastronomia');

Route::get('ventas/descuento-gastronomia', 'Ventas\DescuentoGastronomiaController@index')->name('consultar_descuento_gastronomia');
Route::get('ventas/descuento-gastronomia/crear', 'Ventas\DescuentoGastronomiaController@crear')->name('crear_descuento_gastronomia');
Route::post('ventas/descuento-gastronomia', 'Ventas\DescuentoGastronomiaController@guardar')->name('guardar_descuento_gastronomia');
Route::post('ventas/descuento-gastronomia/consultadescuento', 'Ventas\DescuentoGastronomiaController@consultaDescuento')->name('consulta_descuento_gastronomia');
Route::get('ventas/descuento-gastronomia/leer/{codigo}', 'Ventas\DescuentoGastronomiaController@leeUnDescuentoPorCodigo')->name('leer_descuento_gastronomia');
Route::post('ventas/descuento-gastronomia/sincronizar-anita', 'Ventas\DescuentoGastronomiaController@sincronizarDesdeAnita')->name('sincronizar_descuento_gastronomia_anita');
Route::get('ventas/descuento-gastronomia/{id}/editar', 'Ventas\DescuentoGastronomiaController@editar')->name('editar_descuento_gastronomia');
Route::put('ventas/descuento-gastronomia/{id}', 'Ventas\DescuentoGastronomiaController@actualizar')->name('actualizar_descuento_gastronomia');
Route::delete('ventas/descuento-gastronomia/{id}', 'Ventas\DescuentoGastronomiaController@eliminar')->name('eliminar_descuento_gastronomia');

Route::get('ventas/area-comanda-gastronomia', 'Ventas\AreaComandaGastronomiaController@index')->name('consultar_area_comanda_gastronomia');
Route::get('ventas/area-comanda-gastronomia/crear', 'Ventas\AreaComandaGastronomiaController@crear')->name('crear_area_comanda_gastronomia');
Route::post('ventas/area-comanda-gastronomia', 'Ventas\AreaComandaGastronomiaController@guardar')->name('guardar_area_comanda_gastronomia');
Route::get('ventas/area-comanda-gastronomia/{id}/editar', 'Ventas\AreaComandaGastronomiaController@editar')->name('editar_area_comanda_gastronomia');
Route::put('ventas/area-comanda-gastronomia/{id}', 'Ventas\AreaComandaGastronomiaController@actualizar')->name('actualizar_area_comanda_gastronomia');
Route::delete('ventas/area-comanda-gastronomia/{id}', 'Ventas\AreaComandaGastronomiaController@eliminar')->name('eliminar_area_comanda_gastronomia');

Route::get('ventas/totem-waitry-gastronomia', 'Ventas\TotemWaitryGastronomiaController@index')->name('consultar_totem_waitry_gastronomia');
Route::get('ventas/totem-waitry-gastronomia/crear', 'Ventas\TotemWaitryGastronomiaController@crear')->name('crear_totem_waitry_gastronomia');
Route::post('ventas/totem-waitry-gastronomia', 'Ventas\TotemWaitryGastronomiaController@guardar')->name('guardar_totem_waitry_gastronomia');
Route::get('ventas/totem-waitry-gastronomia/api/ubicaciones-por-empresa/{empresaId}', 'Ventas\TotemWaitryGastronomiaController@ubicacionesPorEmpresa')->name('totem_waitry_gastronomia_ubicaciones_por_empresa');
Route::get('ventas/totem-waitry-gastronomia/{id}/editar', 'Ventas\TotemWaitryGastronomiaController@editar')->name('editar_totem_waitry_gastronomia');
Route::put('ventas/totem-waitry-gastronomia/{id}', 'Ventas\TotemWaitryGastronomiaController@actualizar')->name('actualizar_totem_waitry_gastronomia');
Route::delete('ventas/totem-waitry-gastronomia/{id}', 'Ventas\TotemWaitryGastronomiaController@eliminar')->name('eliminar_totem_waitry_gastronomia');

Route::get('ventas/gastronomia/maquinas-vending', 'Ventas\MaquinavendingController@index')->name('consultar_maquinavending_gastronomia');
Route::get('ventas/gastronomia/maquinas-vending/crear', 'Ventas\MaquinavendingController@crear')->name('crear_maquinavending_gastronomia');
Route::post('ventas/gastronomia/maquinas-vending', 'Ventas\MaquinavendingController@guardar')->name('guardar_maquinavending_gastronomia');
Route::post('ventas/gastronomia/maquinas-vending/sincronizar-anita', 'Ventas\MaquinavendingController@sincronizarDesdeAnita')->name('sincronizar_maquinavending_gastronomia_anita');
Route::get('ventas/gastronomia/maquinas-vending/api/selects-por-empresa/{empresaId}', 'Ventas\MaquinavendingController@selectsPorEmpresa')->name('maquinavending_gastronomia_selects_por_empresa');
Route::get('ventas/gastronomia/maquinas-vending/{id}/editar', 'Ventas\MaquinavendingController@editar')->name('editar_maquinavending_gastronomia');
Route::put('ventas/gastronomia/maquinas-vending/{id}', 'Ventas\MaquinavendingController@actualizar')->name('actualizar_maquinavending_gastronomia');
Route::delete('ventas/gastronomia/maquinas-vending/{id}', 'Ventas\MaquinavendingController@eliminar')->name('eliminar_maquinavending_gastronomia');

Route::get('ventas/gastronomia/viandas/tipos-menu', 'Ventas\ViandaTipoMenuController@index')->name('consultar_vianda_tipo_menu_gastronomia');
Route::get('ventas/gastronomia/viandas/tipos-menu/crear', 'Ventas\ViandaTipoMenuController@crear')->name('crear_vianda_tipo_menu_gastronomia');
Route::post('ventas/gastronomia/viandas/tipos-menu', 'Ventas\ViandaTipoMenuController@guardar')->name('guardar_vianda_tipo_menu_gastronomia');
Route::get('ventas/gastronomia/viandas/tipos-menu/{id}/editar', 'Ventas\ViandaTipoMenuController@editar')->name('editar_vianda_tipo_menu_gastronomia');
Route::put('ventas/gastronomia/viandas/tipos-menu/{id}', 'Ventas\ViandaTipoMenuController@actualizar')->name('actualizar_vianda_tipo_menu_gastronomia');
Route::delete('ventas/gastronomia/viandas/tipos-menu/{id}', 'Ventas\ViandaTipoMenuController@eliminar')->name('eliminar_vianda_tipo_menu_gastronomia');

Route::get('ventas/gastronomia/viandas/usuarios', 'Ventas\ViandaUsuarioController@index')->name('consultar_vianda_usuario_gastronomia');
Route::get('ventas/gastronomia/viandas/usuarios-listado/{formato?}/{busqueda?}', 'Ventas\ViandaUsuarioController@listar')->name('lista_vianda_usuario');
Route::get('ventas/gastronomia/viandas/usuarios/crear', 'Ventas\ViandaUsuarioController@crear')->name('crear_vianda_usuario_gastronomia');
Route::post('ventas/gastronomia/viandas/usuarios', 'Ventas\ViandaUsuarioController@guardar')->name('guardar_vianda_usuario_gastronomia');
Route::get('ventas/gastronomia/viandas/usuarios/{id}/editar', 'Ventas\ViandaUsuarioController@editar')->name('editar_vianda_usuario_gastronomia')->middleware('modo.consulta');
Route::put('ventas/gastronomia/viandas/usuarios/{id}', 'Ventas\ViandaUsuarioController@actualizar')->name('actualizar_vianda_usuario_gastronomia')->middleware('modo.consulta');
Route::delete('ventas/gastronomia/viandas/usuarios/{id}', 'Ventas\ViandaUsuarioController@eliminar')->name('eliminar_vianda_usuario_gastronomia');

Route::get('ventas/gastronomia/viandas/configuracion-terminal', 'Ventas\ConfiguracionTerminalViandaController@index')->name('consultar_configuracion_terminal_vianda');
Route::get('ventas/gastronomia/viandas/configuracion-terminal/crear', 'Ventas\ConfiguracionTerminalViandaController@crear')->name('crear_configuracion_terminal_vianda');
Route::post('ventas/gastronomia/viandas/configuracion-terminal', 'Ventas\ConfiguracionTerminalViandaController@guardar')->name('guardar_configuracion_terminal_vianda');
Route::get('ventas/gastronomia/viandas/configuracion-terminal/api/depositos/{empresaId}', 'Ventas\ConfiguracionTerminalViandaController@apiDepositosPorEmpresa')->name('configuracion_terminal_vianda_api_depositos');
Route::get('ventas/gastronomia/viandas/configuracion-terminal/{id}/editar', 'Ventas\ConfiguracionTerminalViandaController@editar')->name('editar_configuracion_terminal_vianda');
Route::put('ventas/gastronomia/viandas/configuracion-terminal/{id}', 'Ventas\ConfiguracionTerminalViandaController@actualizar')->name('actualizar_configuracion_terminal_vianda');
Route::delete('ventas/gastronomia/viandas/configuracion-terminal/{id}', 'Ventas\ConfiguracionTerminalViandaController@eliminar')->name('eliminar_configuracion_terminal_vianda');

Route::get('ventas/gastronomia/viandas/proceso', 'Ventas\ViandaProcesoController@index')->name('proceso_vianda_gastronomia');
Route::get('ventas/gastronomia/viandas/proceso/api/estado', 'Ventas\ViandaProcesoController@apiEstado')->name('proceso_vianda_api_estado');
Route::post('ventas/gastronomia/viandas/proceso/api/login', 'Ventas\ViandaProcesoController@apiLogin')->name('proceso_vianda_api_login');
Route::post('ventas/gastronomia/viandas/proceso/api/marchar', 'Ventas\ViandaProcesoController@apiMarchar')->name('proceso_vianda_api_marchar');
Route::post('ventas/gastronomia/viandas/proceso/api/reimprimir', 'Ventas\ViandaProcesoController@apiReimprimir')->name('proceso_vianda_api_reimprimir');
Route::post('ventas/gastronomia/viandas/proceso/api/logout', 'Ventas\ViandaProcesoController@apiLogout')->name('proceso_vianda_api_logout');

Route::get('ventas/gastronomia/viandas/reporte', 'Ventas\ViandaReporteController@index')->name('consultar_reporte_vianda_gastronomia');
Route::get('ventas/gastronomia/viandas/listar-reporte/{formato}', 'Ventas\ViandaReporteController@exportar')->name('listar_reporte_vianda_gastronomia');

Route::get('ventas/gastronomia/viandas/dia', 'Ventas\ViandaDiaController@index')->name('viandas_dia_gastronomia')->middleware('modo.consulta');
Route::get('ventas/gastronomia/viandas/dia/{consumoId}/ver', 'Ventas\ViandaDiaController@ver')->name('viandas_dia_ver')->whereNumber('consumoId')->middleware('modo.consulta');
Route::post('ventas/gastronomia/viandas/dia/{consumoId}/reimprimir', 'Ventas\ViandaDiaController@reimprimir')->name('viandas_dia_reimprimir')->whereNumber('consumoId');
Route::post('ventas/gastronomia/viandas/dia/{consumoId}/borrar', 'Ventas\ViandaDiaController@borrar')->name('viandas_dia_borrar')->whereNumber('consumoId');

Route::get('ventas/gastronomia/maquinas-vending/rendiciones', 'Ventas\MaquinavendingRendicionController@index')->name('consultar_maquinavending_rendicion_gastronomia');
Route::get('ventas/listar-maquinavending-rendicion/{formato?}/{busqueda?}', 'Ventas\MaquinavendingRendicionController@listar')->name('lista_maquinavending_rendicion');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/crear', 'Ventas\MaquinavendingRendicionController@crear')->name('crear_maquinavending_rendicion_gastronomia');
Route::post('ventas/gastronomia/maquinas-vending/rendiciones', 'Ventas\MaquinavendingRendicionController@guardar')->name('guardar_maquinavending_rendicion_gastronomia');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/{id}/editar', 'Ventas\MaquinavendingRendicionController@editar')->name('editar_maquinavending_rendicion_gastronomia');
Route::put('ventas/gastronomia/maquinas-vending/rendiciones/{id}', 'Ventas\MaquinavendingRendicionController@actualizar')->name('actualizar_maquinavending_rendicion_gastronomia');
Route::delete('ventas/gastronomia/maquinas-vending/rendiciones/{id}', 'Ventas\MaquinavendingRendicionController@eliminar')->name('eliminar_maquinavending_rendicion_gastronomia');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/{id}/comprobante', 'Ventas\MaquinavendingRendicionController@comprobante')->name('maquinavending_rendicion_comprobante');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/api/empresa/{empresaId}/maquinas', 'Ventas\MaquinavendingRendicionController@apiMaquinasPorEmpresa')->name('maquinavending_rendicion_api_maquinas');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/api/maquina/{maquinavendingId}/articulos', 'Ventas\MaquinavendingRendicionController@apiArticulosMaquina')->name('maquinavending_rendicion_api_articulos');
Route::get('ventas/gastronomia/maquinas-vending/rendiciones/api/cuentas-caja', 'Ventas\MaquinavendingRendicionController@apiCuentasCaja')->name('maquinavending_rendicion_api_cuentas_caja');

Route::get('ventas/mozo-gastronomia', 'Ventas\MozoGastronomiaController@index')->name('consultar_mozo_gastronomia');
Route::get('ventas/mozo-gastronomia/crear', 'Ventas\MozoGastronomiaController@crear')->name('crear_mozo_gastronomia');
Route::get('ventas/mozo-gastronomia/proximo-codigo', 'Ventas\MozoGastronomiaController@proximoCodigo')->name('proximo_codigo_mozo_gastronomia');
Route::post('ventas/mozo-gastronomia', 'Ventas\MozoGastronomiaController@guardar')->name('guardar_mozo_gastronomia');
Route::post('ventas/mozo-gastronomia/consultamozo', 'Ventas\MozoGastronomiaController@consultaMozo')->name('consulta_mozo_gastronomia');
Route::get('ventas/mozo-gastronomia/leer/{codigo}', 'Ventas\MozoGastronomiaController@leeUnMozoPorCodigo')->name('leer_mozo_gastronomia');
Route::post('ventas/mozo-gastronomia/sincronizar-anita', 'Ventas\MozoGastronomiaController@sincronizarDesdeAnita')->name('sincronizar_mozo_gastronomia_anita');
Route::get('ventas/mozo-gastronomia/{id}/editar', 'Ventas\MozoGastronomiaController@editar')->name('editar_mozo_gastronomia');
Route::put('ventas/mozo-gastronomia/{id}', 'Ventas\MozoGastronomiaController@actualizar')->name('actualizar_mozo_gastronomia');
Route::delete('ventas/mozo-gastronomia/{id}', 'Ventas\MozoGastronomiaController@eliminar')->name('eliminar_mozo_gastronomia');

Route::get('ventas/gastronomia/canjes/cliente-vip', 'Ventas\ClienteVipGastronomiaController@index')->name('consultar_cliente_vip_gastronomia');
Route::get('ventas/listaclientevipgastronomia/{formato?}/{busqueda?}', 'Ventas\ClienteVipGastronomiaController@listar')->name('lista_cliente_vip_gastronomia');
Route::get('ventas/gastronomia/canjes/cliente-vip/crear', 'Ventas\ClienteVipGastronomiaController@crear')->name('crear_cliente_vip_gastronomia');
Route::post('ventas/gastronomia/canjes/cliente-vip', 'Ventas\ClienteVipGastronomiaController@guardar')->name('guardar_cliente_vip_gastronomia');
Route::post('ventas/gastronomia/canjes/cliente-vip/sincronizar-anita', 'Ventas\ClienteVipGastronomiaController@sincronizarDesdeAnita')->name('sincronizar_cliente_vip_gastronomia_anita');
Route::get('ventas/gastronomia/canjes/cliente-vip/{id}/editar', 'Ventas\ClienteVipGastronomiaController@editar')->name('editar_cliente_vip_gastronomia');
Route::put('ventas/gastronomia/canjes/cliente-vip/{id}', 'Ventas\ClienteVipGastronomiaController@actualizar')->name('actualizar_cliente_vip_gastronomia');
Route::delete('ventas/gastronomia/canjes/cliente-vip/{id}', 'Ventas\ClienteVipGastronomiaController@eliminar')->name('eliminar_cliente_vip_gastronomia');

Route::get('ventas/gastronomia/canjes/listado-marketing', 'Ventas\CanjeMarketingListadoController@index')->name('canje_marketing_listado')->middleware('modo.consulta');
Route::get('ventas/lista-canje-marketing-gastronomia/{formato}', 'Ventas\CanjeMarketingListadoController@exportar')->name('lista_canje_marketing_gastronomia');

Route::get('ventas/gastronomia/canjes/proceso-facturacion', 'Ventas\CanjeMarketingProcesoFacturacionController@index')->name('canje_marketing_proceso_facturacion');
Route::prefix('ventas/gastronomia/canjes/api')->group(function () {
    Route::get('config', 'Ventas\CanjeMarketingProcesoFacturacionController@apiConfig')->name('canje_marketing_api_config');
    Route::post('autenticar-mozo', 'Ventas\CanjeMarketingProcesoFacturacionController@apiAutenticarMozo')->name('canje_marketing_api_autenticar_mozo');
    Route::get('cuentas-activas', 'Ventas\CanjeMarketingProcesoFacturacionController@apiCuentasActivas')->name('canje_marketing_api_cuentas_activas');
    Route::post('abrir-cuenta', 'Ventas\CanjeMarketingProcesoFacturacionController@apiAbrirCuenta')->name('canje_marketing_api_abrir_cuenta');
    Route::get('cuenta/{id}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiCuentaVer')->name('canje_marketing_api_cuenta_ver');
    Route::patch('cuenta/{id}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiActualizarCuenta')->name('canje_marketing_api_cuenta_actualizar');
    Route::post('cuenta/{id}/cerrar', 'Ventas\CanjeMarketingProcesoFacturacionController@apiCerrarCuenta')->name('canje_marketing_api_cerrar_cuenta');
    Route::post('cuentas-activas/cerrar-todas', 'Ventas\CanjeMarketingProcesoFacturacionController@apiCerrarTodasCuentas')->name('canje_marketing_api_cerrar_todas_cuentas');
    Route::post('cuenta/{id}/linea', 'Ventas\CanjeMarketingProcesoFacturacionController@apiAgregarLinea')->name('canje_marketing_api_agregar_linea');
    Route::patch('cuenta/{cuentaId}/linea/{lineaId}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiActualizarCantidadLinea')->name('canje_marketing_api_actualizar_linea');
    Route::delete('cuenta/{cuentaId}/linea/{lineaId}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiEliminarLinea')->name('canje_marketing_api_eliminar_linea');
    Route::get('articulo-catalogo-por-sku', 'Ventas\CanjeMarketingProcesoFacturacionController@apiArticuloCatalogoPorSku')->name('canje_marketing_api_articulo_sku');
    Route::get('articulos-catalogo', 'Ventas\CanjeMarketingProcesoFacturacionController@apiArticulosCatalogo')->name('canje_marketing_api_articulos_catalogo');
    Route::get('opcionales-articulo/{articuloId}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiOpcionalesArticulo')->name('canje_marketing_api_opcionales');
    Route::post('validar-emision', 'Ventas\CanjeMarketingProcesoFacturacionController@apiValidarEmision')->name('canje_marketing_api_validar_emision');
    Route::post('emitir-factura', 'Ventas\CanjeMarketingProcesoFacturacionController@apiEmitirFactura')->name('canje_marketing_api_emitir_factura');
    Route::get('descuento-prefijado', 'Ventas\CanjeMarketingProcesoFacturacionController@apiDescuentoPrefijado')->name('canje_marketing_api_descuento_prefijado');
    Route::post('consulta-mozo', 'Ventas\CanjeMarketingProcesoFacturacionController@apiConsultaMozo')->name('canje_marketing_api_consulta_mozo');
    Route::get('mozo/leer-codigo/{codigo}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiMozoPorCodigo')->name('canje_marketing_api_mozo_codigo');
    Route::get('mozo/leer-id/{id}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiMozoPorId')->name('canje_marketing_api_mozo_id');
    Route::post('consulta-cliente-vip', 'Ventas\CanjeMarketingProcesoFacturacionController@apiConsultaClienteVip')->name('canje_marketing_api_consulta_cliente_vip');
    Route::get('cliente-vip/leer/{codigo}', 'Ventas\CanjeMarketingProcesoFacturacionController@apiClienteVipPorCodigo')->name('canje_marketing_api_cliente_vip_codigo');
    Route::post('cliente-vip/wigos', 'Ventas\CanjeMarketingProcesoFacturacionController@apiClienteVipWigos')->name('canje_marketing_api_cliente_vip_wigos');
});

Route::get('ventas/turno-gastronomia', 'Ventas\TurnoGastronomiaController@index')->name('consultar_turno_gastronomia');
Route::get('ventas/turno-gastronomia/crear', 'Ventas\TurnoGastronomiaController@crear')->name('crear_turno_gastronomia');
Route::post('ventas/turno-gastronomia', 'Ventas\TurnoGastronomiaController@guardar')->name('guardar_turno_gastronomia');
Route::get('ventas/turno-gastronomia/{id}/editar', 'Ventas\TurnoGastronomiaController@editar')->name('editar_turno_gastronomia');
Route::put('ventas/turno-gastronomia/{id}', 'Ventas\TurnoGastronomiaController@actualizar')->name('actualizar_turno_gastronomia');
Route::delete('ventas/turno-gastronomia/{id}', 'Ventas\TurnoGastronomiaController@eliminar')->name('eliminar_turno_gastronomia');

Route::get('ventas/categoria-fidelidad-gastronomia', 'Ventas\CategoriafidelidadGastronomiaController@index')->name('consultar_categoria_fidelidad_gastronomia');
Route::get('ventas/categoria-fidelidad-gastronomia/crear', 'Ventas\CategoriafidelidadGastronomiaController@crear')->name('crear_categoria_fidelidad_gastronomia');
Route::post('ventas/categoria-fidelidad-gastronomia', 'Ventas\CategoriafidelidadGastronomiaController@guardar')->name('guardar_categoria_fidelidad_gastronomia');
Route::post('ventas/categoria-fidelidad-gastronomia/sincronizar-anita', 'Ventas\CategoriafidelidadGastronomiaController@sincronizarDesdeAnita')->name('sincronizar_categoria_fidelidad_gastronomia_anita');
Route::get('ventas/categoria-fidelidad-gastronomia/{id}/editar', 'Ventas\CategoriafidelidadGastronomiaController@editar')->name('editar_categoria_fidelidad_gastronomia');
Route::put('ventas/categoria-fidelidad-gastronomia/{id}', 'Ventas\CategoriafidelidadGastronomiaController@actualizar')->name('actualizar_categoria_fidelidad_gastronomia');
Route::delete('ventas/categoria-fidelidad-gastronomia/{id}', 'Ventas\CategoriafidelidadGastronomiaController@eliminar')->name('eliminar_categoria_fidelidad_gastronomia');

Route::get('ventas/gastronomia/proceso-facturacion', 'Ventas\GastronomiaProcesoFacturacionController@index')->name('gastronomia_proceso_facturacion');
Route::get('ventas/gastronomia/api/config', 'Ventas\GastronomiaProcesoFacturacionController@apiConfig')->name('gastronomia_api_config');
Route::get('ventas/gastronomia/api/turno-estado', 'Ventas\GastronomiaProcesoFacturacionController@apiTurnoEstado')->name('gastronomia_api_turno_estado');
Route::post('ventas/gastronomia/api/cierre-parcial-turno', 'Ventas\GastronomiaProcesoFacturacionController@apiCierreParcialTurno')->name('gastronomia_api_cierre_parcial_turno');
Route::post('ventas/gastronomia/api/cerrar-turno', 'Ventas\GastronomiaProcesoFacturacionController@apiCerrarTurno')->name('gastronomia_api_cerrar_turno');
Route::post('ventas/gastronomia/api/preferencia-modo-seleccion', 'Ventas\GastronomiaProcesoFacturacionController@apiGuardarPreferenciaModoSeleccion')->name('gastronomia_api_preferencia_modo_seleccion');
Route::get('ventas/gastronomia/api/waitry-ordenes-pendientes', 'Ventas\GastronomiaProcesoFacturacionController@apiWaitryOrdenesPendientes')->name('gastronomia_api_waitry_ordenes_pendientes');
Route::post('ventas/gastronomia/api/waitry-importar-orden', 'Ventas\GastronomiaProcesoFacturacionController@apiWaitryImportarOrden')->name('gastronomia_api_waitry_importar_orden');
Route::get('ventas/gastronomia/api/mesas', 'Ventas\GastronomiaProcesoFacturacionController@apiMesas')->name('gastronomia_api_mesas');
Route::get('ventas/gastronomia/api/cuentas-activas', 'Ventas\GastronomiaProcesoFacturacionController@apiCuentasActivas')->name('gastronomia_api_cuentas_activas');
Route::get('ventas/gastronomia/api/cuenta/{id}', 'Ventas\GastronomiaProcesoFacturacionController@apiCuentaVer')->name('gastronomia_api_cuenta_ver');
Route::post('ventas/gastronomia/api/abrir-mesa', 'Ventas\GastronomiaProcesoFacturacionController@apiAbrirMesa')->name('gastronomia_api_abrir_mesa');
Route::post('ventas/gastronomia/api/abrir-cuenta', 'Ventas\GastronomiaProcesoFacturacionController@apiAbrirCuenta')->name('gastronomia_api_abrir_cuenta');
Route::patch('ventas/gastronomia/api/cuenta/{id}', 'Ventas\GastronomiaProcesoFacturacionController@apiActualizarCuenta')->name('gastronomia_api_actualizar_cuenta');
Route::post('ventas/gastronomia/api/cuenta/{id}/linea', 'Ventas\GastronomiaProcesoFacturacionController@apiAgregarLinea')->name('gastronomia_api_agregar_linea');
Route::delete('ventas/gastronomia/api/cuenta/{cuentaId}/linea/{lineaId}', 'Ventas\GastronomiaProcesoFacturacionController@apiEliminarLinea')->name('gastronomia_api_eliminar_linea');
Route::patch('ventas/gastronomia/api/cuenta/{cuentaId}/linea/{lineaId}', 'Ventas\GastronomiaProcesoFacturacionController@apiActualizarCantidadLinea')->name('gastronomia_api_actualizar_cantidad_linea');
Route::get('ventas/gastronomia/api/articulo-catalogo-por-sku', 'Ventas\GastronomiaProcesoFacturacionController@apiArticuloCatalogoPorSku')->name('gastronomia_api_articulo_catalogo_por_sku');
Route::post('ventas/gastronomia/api/cuenta/{id}/cerrar', 'Ventas\GastronomiaProcesoFacturacionController@apiCerrarCuenta')->name('gastronomia_api_cerrar_cuenta');
Route::get('ventas/gastronomia/api/articulos-catalogo', 'Ventas\GastronomiaProcesoFacturacionController@apiArticulosCatalogo')->name('gastronomia_api_articulos_catalogo');
Route::get('ventas/gastronomia/api/opcionales-articulo/{articuloId}', 'Ventas\GastronomiaProcesoFacturacionController@apiOpcionalesArticulo')->name('gastronomia_api_opcionales_articulo');
Route::get('ventas/gastronomia/api/mozos', 'Ventas\GastronomiaProcesoFacturacionController@apiMozos')->name('gastronomia_api_mozos');
Route::get('ventas/gastronomia/api/descuentos-gastronomia', 'Ventas\GastronomiaProcesoFacturacionController@apiDescuentos')->name('gastronomia_api_descuentos');
Route::get('ventas/gastronomia/api/monedas', 'Ventas\GastronomiaProcesoFacturacionController@apiMonedas')->name('gastronomia_api_monedas');
Route::get('ventas/gastronomia/api/usos-cuentacaja', 'Ventas\GastronomiaProcesoFacturacionController@apiUsosCuentacaja')->name('gastronomia_api_usos_cuentacaja');
Route::get('ventas/gastronomia/api/cuentas-caja', 'Ventas\GastronomiaProcesoFacturacionController@apiCuentasCaja')->name('gastronomia_api_cuentas_caja');
Route::get('ventas/gastronomia/api/cuentacaja-por-codigo/{codigo}', 'Ventas\GastronomiaProcesoFacturacionController@apiCuentacajaPorCodigo')->name('gastronomia_api_cuentacaja_por_codigo');
Route::get('ventas/gastronomia/api/cotizacion', 'Ventas\GastronomiaProcesoFacturacionController@apiCotizacion')->name('gastronomia_api_cotizacion');
Route::post('ventas/gastronomia/api/validar-emision', 'Ventas\GastronomiaProcesoFacturacionController@apiValidarEmision')->name('gastronomia_api_validar_emision');
Route::post('ventas/gastronomia/api/validar-ticket-tarjeta', 'Ventas\GastronomiaProcesoFacturacionController@apiValidarTicketTarjeta')->name('gastronomia_api_validar_ticket_tarjeta');
Route::post('ventas/gastronomia/api/validar-ticket-canje-premio', 'Ventas\GastronomiaProcesoFacturacionController@apiValidarTicketCanjePremio')->name('gastronomia_api_validar_ticket_canje_premio');
Route::post('ventas/gastronomia/api/aplicar-ticket-canje-premio', 'Ventas\GastronomiaProcesoFacturacionController@apiAplicarTicketCanjePremio')->name('gastronomia_api_aplicar_ticket_canje_premio');
Route::post('ventas/gastronomia/api/validar-canje-fidelidad', 'Ventas\GastronomiaProcesoFacturacionController@apiValidarCanjeFidelidad')->name('gastronomia_api_validar_canje_fidelidad');
Route::post('ventas/gastronomia/api/aplicar-canje-fidelidad', 'Ventas\GastronomiaProcesoFacturacionController@apiAplicarCanjeFidelidad')->name('gastronomia_api_aplicar_canje_fidelidad');
Route::get('ventas/gastronomia/api/canjes-premio-turno', 'Ventas\GastronomiaProcesoFacturacionController@apiListarCanjesPremioTurno')->name('gastronomia_api_canjes_premio_turno');
Route::get('ventas/gastronomia/api/tickets-tarjeta-turno', 'Ventas\GastronomiaProcesoFacturacionController@apiListarTicketsTarjetaTurno')->name('gastronomia_api_tickets_tarjeta_turno');
Route::get('ventas/gastronomia/api/invitaciones-turno', 'Ventas\GastronomiaProcesoFacturacionController@apiListarInvitacionesTurno')->name('gastronomia_api_invitaciones_turno');
Route::get('ventas/gastronomia/api/diagnostico-emision', 'Ventas\GastronomiaProcesoFacturacionController@apiDiagnosticoEmision')->name('gastronomia_api_diagnostico_emision');
Route::get('ventas/gastronomia/api/diagnostico-ticket', 'Ventas\GastronomiaProcesoFacturacionController@apiDiagnosticoTicket')->name('gastronomia_api_diagnostico_ticket');
Route::post('ventas/gastronomia/api/emitir-factura', 'Ventas\GastronomiaProcesoFacturacionController@apiEmitirFactura')->name('gastronomia_api_emitir_factura');

Route::get('ventas/gastronomia/cierres-turno', 'Ventas\CierreTurnoGastronomiaController@index')->name('gastronomia_cierres_turno')->middleware('modo.consulta');
Route::get('ventas/lista-gastronomia-cierres-turno/{formato}', 'Ventas\CierreTurnoGastronomiaController@exportar')->name('listar_gastronomia_cierres_turno');
Route::get('ventas/gastronomia/cierres-turno/parcial/{id}/comprobante', 'Ventas\CierreTurnoGastronomiaController@comprobanteParcial')->name('gastronomia_cierre_turno_comprobante_parcial');
Route::get('ventas/gastronomia/cierres-turno/cierre/{id}/comprobante', 'Ventas\CierreTurnoGastronomiaController@comprobanteCierre')->name('gastronomia_cierre_turno_comprobante_cierre');
Route::get('ventas/gastronomia/cierres-turno/cierre/{id}/ver', 'Ventas\CierreTurnoGastronomiaController@verCierre')->name('gastronomia_cierre_turno_ver')->middleware('modo.consulta');
Route::get('ventas/gastronomia/cierres-turno/api/comprobantes', 'Ventas\CierreTurnoGastronomiaController@apiComprobantes')->name('gastronomia_cierres_turno_api_comprobantes');
Route::get('ventas/gastronomia/cierres-turno/api/canjes-premio', 'Ventas\CierreTurnoGastronomiaController@apiCanjesPremio')->name('gastronomia_cierres_turno_api_canjes_premio');
Route::get('ventas/gastronomia/cierres-turno/api/canjes-fidelidad', 'Ventas\CierreTurnoGastronomiaController@apiCanjesFidelidad')->name('gastronomia_cierres_turno_api_canjes_fidelidad');
Route::get('ventas/gastronomia/cierres-turno/api/tickets-tarjeta', 'Ventas\CierreTurnoGastronomiaController@apiTicketsTarjeta')->name('gastronomia_cierres_turno_api_tickets_tarjeta');
Route::get('ventas/gastronomia/cierres-turno/api/arqueo-cierre', 'Ventas\CierreTurnoGastronomiaController@apiArqueoCierre')->name('gastronomia_cierres_turno_api_arqueo_cierre');
Route::post('ventas/gastronomia/cierres-turno/api/corregir-arqueo-cierre', 'Ventas\CierreTurnoGastronomiaController@apiCorregirArqueoCierre')->name('gastronomia_cierres_turno_api_corregir_arqueo_cierre');

Route::get('ventas/gastronomia/habilitacion-turno', 'Ventas\HabilitacionTurnoGastronomiaController@index')->name('gastronomia_habilitacion_turno');
Route::get('ventas/gastronomia/habilitacion-turno/api/estado', 'Ventas\HabilitacionTurnoGastronomiaController@apiEstado')->name('gastronomia_habilitacion_turno_api_estado');
Route::post('ventas/gastronomia/habilitacion-turno/api/habilitar', 'Ventas\HabilitacionTurnoGastronomiaController@apiHabilitar')->name('gastronomia_habilitacion_turno_api_habilitar');
Route::post('ventas/gastronomia/habilitacion-turno/api/actualizar-monto-habilitacion', 'Ventas\HabilitacionTurnoGastronomiaController@apiActualizarMontoHabilitacion')->name('gastronomia_habilitacion_turno_api_actualizar_monto_habilitacion');
Route::post('ventas/gastronomia/habilitacion-turno/api/cierre-parcial', 'Ventas\HabilitacionTurnoGastronomiaController@apiCierreParcial')->name('gastronomia_habilitacion_turno_api_cierre_parcial');
Route::post('ventas/gastronomia/habilitacion-turno/api/cerrar', 'Ventas\HabilitacionTurnoGastronomiaController@apiCerrar')->name('gastronomia_habilitacion_turno_api_cerrar');
Route::post('ventas/gastronomia/habilitacion-turno/api/anular-cierre', 'Ventas\HabilitacionTurnoGastronomiaController@apiAnularCierre')->name('gastronomia_habilitacion_turno_api_anular_cierre');
Route::get('ventas/gastronomia/habilitacion-turno/api/conciliacion-turno', 'Ventas\HabilitacionTurnoGastronomiaController@apiConciliacionTurno')->name('gastronomia_habilitacion_turno_api_conciliacion_turno');
Route::get('ventas/gastronomia/habilitacion-turno/api/conciliacion-medio', 'Ventas\HabilitacionTurnoGastronomiaController@apiConciliacionMedio')->name('gastronomia_habilitacion_turno_api_conciliacion_medio');
Route::get('ventas/gastronomia/habilitacion-turno/api/conciliacion-notas-credito', 'Ventas\HabilitacionTurnoGastronomiaController@apiConciliacionNotasCredito')->name('gastronomia_habilitacion_turno_api_conciliacion_notas_credito');
Route::get('ventas/gastronomia/habilitacion-turno/api/conciliacion-invitaciones', 'Ventas\HabilitacionTurnoGastronomiaController@apiConciliacionInvitaciones')->name('gastronomia_habilitacion_turno_api_conciliacion_invitaciones');
Route::get('ventas/gastronomia/habilitacion-turno/informe-mozo-pdf', 'Ventas\HabilitacionTurnoGastronomiaController@informeMozoPdf')->name('gastronomia_habilitacion_turno_informe_mozo_pdf');

Route::get('ventas/gastronomia/jornada', 'Ventas\JornadaGastronomiaController@index')->name('gastronomia_jornada');
Route::get('ventas/gastronomia/jornada/api/estado/{empresaId}', 'Ventas\JornadaGastronomiaController@apiEstado')->name('gastronomia_jornada_api_estado');
Route::get('ventas/gastronomia/jornada/api/preview-cierre-totem/{empresaId}', 'Ventas\JornadaGastronomiaController@apiPreviewCierreTotem')->name('gastronomia_jornada_api_preview_cierre_totem');
Route::post('ventas/gastronomia/jornada/api/abrir', 'Ventas\JornadaGastronomiaController@apiAbrir')->name('gastronomia_jornada_api_abrir');
Route::post('ventas/gastronomia/jornada/api/cerrar', 'Ventas\JornadaGastronomiaController@apiCerrar')->name('gastronomia_jornada_api_cerrar');
Route::post('ventas/gastronomia/jornada/api/eliminar', 'Ventas\JornadaGastronomiaController@apiEliminar')->name('gastronomia_jornada_api_eliminar');
Route::post('ventas/gastronomia/jornada/api/anular-cierre', 'Ventas\JornadaGastronomiaController@apiAnularCierre')->name('gastronomia_jornada_api_anular_cierre');
Route::get('ventas/gastronomia/jornada/{jornadaId}/comprobante-cierre-totem', 'Ventas\JornadaGastronomiaController@comprobanteCierreTotem')->name('gastronomia_jornada_comprobante_cierre_totem');
Route::get('ventas/gastronomia/jornada/api/informe-z/{jornadaId}', 'Ventas\JornadaGastronomiaController@apiInformeZDatos')->name('gastronomia_jornada_api_informe_z_datos');
Route::post('ventas/gastronomia/jornada/api/informe-z', 'Ventas\JornadaGastronomiaController@apiInformeZGuardar')->name('gastronomia_jornada_api_informe_z_guardar');
Route::post('ventas/gastronomia/jornada/api/informe-z-borrador', 'Ventas\JornadaGastronomiaController@apiInformeZBorradorGuardar')->name('gastronomia_jornada_api_informe_z_borrador_guardar');

Route::get('ventas/gastronomia/saneamiento-turno', 'Ventas\GastronomiaSaneamientoTurnoController@index')->name('gastronomia_saneamiento_turno');
Route::get('ventas/gastronomia/saneamiento-turno/api/diagnostico', 'Ventas\GastronomiaSaneamientoTurnoController@apiDiagnostico')->name('gastronomia_saneamiento_turno_api_diagnostico');
Route::post('ventas/gastronomia/saneamiento-turno/api/extender-cierre', 'Ventas\GastronomiaSaneamientoTurnoController@apiExtenderCierre')->name('gastronomia_saneamiento_turno_api_extender_cierre');
Route::post('ventas/gastronomia/saneamiento-turno/api/crear-retroactivo', 'Ventas\GastronomiaSaneamientoTurnoController@apiCrearRetroactivo')->name('gastronomia_saneamiento_turno_api_crear_retroactivo');
Route::post('ventas/gastronomia/saneamiento-turno/api/cerrar-turno-remoto', 'Ventas\GastronomiaSaneamientoTurnoController@apiCerrarTurnoRemoto')->name('gastronomia_saneamiento_turno_api_cerrar_turno_remoto');
Route::post('ventas/gastronomia/saneamiento-turno/api/recalcular-totales', 'Ventas\GastronomiaSaneamientoTurnoController@apiRecalcularTotales')->name('gastronomia_saneamiento_turno_api_recalcular_totales');
Route::post('ventas/gastronomia/saneamiento-turno/api/cerrar-cuentas-pendientes', 'Ventas\GastronomiaSaneamientoTurnoController@apiCerrarCuentasPendientes')->name('gastronomia_saneamiento_turno_api_cerrar_cuentas');
Route::get('ventas/gastronomia/saneamiento-turno/informe-pdf', 'Ventas\GastronomiaSaneamientoTurnoController@informePdf')->name('gastronomia_saneamiento_turno_informe_pdf');

Route::get('ventas/gastronomia/cierre-turno-central', 'Ventas\CierreTurnoCentralGastronomiaController@index')->name('gastronomia_cierre_turno_central')->middleware('modo.consulta');
Route::get('ventas/gastronomia/cierre-turno-central/api/turnos', 'Ventas\CierreTurnoCentralGastronomiaController@apiListarTurnos')->name('gastronomia_cierre_turno_central_api_turnos');
Route::get('ventas/gastronomia/cierre-turno-central/api/estado-turno', 'Ventas\CierreTurnoCentralGastronomiaController@apiEstadoTurno')->name('gastronomia_cierre_turno_central_api_estado_turno');
Route::get('ventas/gastronomia/cierre-turno-central/api/conciliacion-turno', 'Ventas\CierreTurnoCentralGastronomiaController@apiConciliacionTurno')->name('gastronomia_cierre_turno_central_api_conciliacion_turno');
Route::get('ventas/gastronomia/cierre-turno-central/api/conciliacion-medio', 'Ventas\CierreTurnoCentralGastronomiaController@apiConciliacionMedio')->name('gastronomia_cierre_turno_central_api_conciliacion_medio');
Route::get('ventas/gastronomia/cierre-turno-central/api/conciliacion-notas-credito', 'Ventas\CierreTurnoCentralGastronomiaController@apiConciliacionNotasCredito')->name('gastronomia_cierre_turno_central_api_conciliacion_notas_credito');
Route::get('ventas/gastronomia/cierre-turno-central/api/conciliacion-invitaciones', 'Ventas\CierreTurnoCentralGastronomiaController@apiConciliacionInvitaciones')->name('gastronomia_cierre_turno_central_api_conciliacion_invitaciones');
Route::post('ventas/gastronomia/cierre-turno-central/api/cerrar', 'Ventas\CierreTurnoCentralGastronomiaController@apiCerrar')->name('gastronomia_cierre_turno_central_api_cerrar');

Route::get('ventas/gastronomia/facturas-dia', 'Ventas\GastronomiaFacturasDiaController@index')->name('gastronomia_facturas_dia')->middleware('modo.consulta');
Route::get('ventas/lista-gastronomia-facturas-dia/{formato}', 'Ventas\GastronomiaFacturasDiaController@exportar')->name('listar_gastronomia_facturas_dia');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/ver', 'Ventas\GastronomiaFacturasDiaController@ver')->name('gastronomia_facturas_dia_ver')->middleware('modo.consulta');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/tickets-tarjeta', 'Ventas\GastronomiaFacturasDiaController@apiTicketsTarjeta')->name('gastronomia_facturas_dia_tickets_tarjeta');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/canjes-premio', 'Ventas\GastronomiaFacturasDiaController@apiTicketsCanjePremio')->name('gastronomia_facturas_dia_canjes_premio');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/canjes-fidelidad', 'Ventas\GastronomiaFacturasDiaController@apiCanjesFidelidad')->name('gastronomia_facturas_dia_canjes_fidelidad');
Route::post('ventas/gastronomia/facturas-dia/{ventaId}/reimprimir-ticket', 'Ventas\GastronomiaFacturasDiaController@reimprimirTicket')->name('gastronomia_facturas_dia_reimprimir_ticket');
Route::post('ventas/gastronomia/facturas-dia/{ventaId}/generar-nota-credito', 'Ventas\GastronomiaFacturasDiaController@generarNotaCredito')->name('gastronomia_facturas_dia_generar_nota_credito');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/medios-pago', 'Ventas\GastronomiaFacturasDiaController@apiMediosPagoCambio')->name('gastronomia_facturas_dia_medios_pago');
Route::get('ventas/gastronomia/facturas-dia/{ventaId}/cuentacaja-por-codigo/{codigo}', 'Ventas\GastronomiaFacturasDiaController@apiCuentacajaPorCodigo')->name('gastronomia_facturas_dia_cuentacaja_por_codigo');
Route::put('ventas/gastronomia/facturas-dia/{ventaId}/medios-pago', 'Ventas\GastronomiaFacturasDiaController@actualizarMediosPago')->name('gastronomia_facturas_dia_actualizar_medios_pago');

Route::get('ventas/gastronomia/informe-gerente', 'Ventas\GastronomiaInformeGerenteController@index')->name('gastronomia_informe_gerente')->middleware('modo.consulta');
Route::get('ventas/gastronomia/articulos-vendidos', 'Ventas\GastronomiaArticulosVendidosController@index')->name('gastronomia_articulos_vendidos')->middleware('modo.consulta');
Route::get('ventas/gastronomia/insumos-tipoarticulo-reporte', 'Ventas\GastronomiaInsumosTipoarticuloReporteController@index')->name('gastronomia_insumos_tipoarticulo_reporte')->middleware('modo.consulta');
Route::get('ventas/listar-gastronomia-insumos-tipoarticulo/{formato}', 'Ventas\GastronomiaInsumosTipoarticuloReporteController@exportar')->name('listar_gastronomia_insumos_tipoarticulo');
Route::get('ventas/gastronomia/descuento-reporte', 'Ventas\GastronomiaDescuentoReporteController@index')->name('gastronomia_descuento_reporte')->middleware('modo.consulta');
Route::post('ventas/gastronomia/descuento-reporte/consulta-facturas', 'Ventas\GastronomiaDescuentoReporteController@consultaFacturasBloque')->name('gastronomia_descuento_reporte_consulta_facturas');
Route::post('ventas/gastronomia/descuento-reporte/consulta-mozo', 'Ventas\GastronomiaDescuentoReporteController@consultaMozo')->name('gastronomia_descuento_reporte_consulta_mozo');
Route::get('ventas/gastronomia/descuento-reporte/leer-mozo/{codigo}', 'Ventas\GastronomiaDescuentoReporteController@leerMozoPorCodigo')->name('gastronomia_descuento_reporte_leer_mozo');
Route::post('ventas/gastronomia/descuento-reporte/consulta-clientevip', 'Ventas\GastronomiaDescuentoReporteController@consultaClienteVip')->name('gastronomia_descuento_reporte_consulta_clientevip');
Route::get('ventas/gastronomia/descuento-reporte/leer-clientevip/{codigo}', 'Ventas\GastronomiaDescuentoReporteController@leerClienteVipPorCodigo')->name('gastronomia_descuento_reporte_leer_clientevip');
Route::get('ventas/listar-gastronomia-descuento-reporte/{formato}', 'Ventas\GastronomiaDescuentoReporteController@exportar')->name('listar_gastronomia_descuento_reporte');
Route::get('ventas/gastronomia/ventas-articulos-reporte', 'Ventas\GastronomiaVentasArticulosReporteController@index')->name('gastronomia_ventas_articulos_reporte')->middleware('modo.consulta');
Route::get('ventas/listar-gastronomia-ventas-articulos-reporte/{formato}', 'Ventas\GastronomiaVentasArticulosReporteController@exportar')->name('listar_gastronomia_ventas_articulos_reporte');
Route::get('ventas/lista-gastronomia-articulos-vendidos/{formato}', 'Ventas\GastronomiaArticulosVendidosController@exportar')->name('listar_gastronomia_articulos_vendidos');
Route::get('ventas/gastronomia/articulos-vendidos/api/{articuloId}/facturas', 'Ventas\GastronomiaArticulosVendidosController@apiFacturas')->name('gastronomia_articulos_vendidos_api_facturas');
Route::get('ventas/gastronomia/articulos-vendidos/api/{articuloId}/movimientos', 'Ventas\GastronomiaArticulosVendidosController@apiMovimientos')->name('gastronomia_articulos_vendidos_api_movimientos');

Route::get('ventas/arca-caea', 'Ventas\ArcaCaeaController@index')->name('arca_caea');
Route::get('ventas/arca-caea/{id}', 'Ventas\ArcaCaeaController@show')->name('arca_caea_ver');
Route::post('ventas/arca-caea/solicitar', 'Ventas\ArcaCaeaController@solicitar')->name('arca_caea_solicitar');
Route::post('ventas/arca-caea/{id}/reintentar', 'Ventas\ArcaCaeaController@reintentar')->name('arca_caea_reintentar');
Route::post('ventas/arca-caea/{id}/informar', 'Ventas\ArcaCaeaController@informar')->name('arca_caea_informar');
Route::post('ventas/arca-caea/{id}/actualizar-resumen', 'Ventas\ArcaCaeaController@actualizarResumen')->name('arca_caea_actualizar_resumen');
Route::post('ventas/arca-caea/{id}/grabar-anita', 'Ventas\ArcaCaeaController@grabarAnita')->name('arca_caea_grabar_anita');

Route::get('ventas/puntoventa', 'Ventas\PuntoventaController@index')->name('puntoventa');
Route::get('ventas/puntoventa/arca-puntos-venta', 'Ventas\PuntoventaController@puntosVentaArca')->name('puntoventa_arca_puntos_venta');
Route::post('ventas/puntoventa/sincronizar-anita', 'Ventas\PuntoventaController@sincronizarDesdeAnita')->name('sincronizar_puntoventa_anita');
Route::get('ventas/puntoventa/crear', 'Ventas\PuntoventaController@crear')->name('crear_puntoventa');
Route::post('ventas/puntoventa', 'Ventas\PuntoventaController@guardar')->name('guardar_puntoventa');
Route::get('ventas/puntoventa/{id}/editar', 'Ventas\PuntoventaController@editar')->name('editar_puntoventa');
Route::put('ventas/puntoventa/{id}', 'Ventas\PuntoventaController@actualizar')->name('actualizar_puntoventa');
Route::delete('ventas/puntoventa/{id}', 'Ventas\PuntoventaController@eliminar')->name('eliminar_puntoventa');

// Llamada desde jquery
Route::get('ventas/chequeapuntoventa/{id}', 'Ventas\PuntoventaController@chequeapuntoventa')->name('chequea_puntoventa');
Route::get('ventas/leeunpuntoventa/{id}', 'Ventas\PuntoventaController@leeUnPuntoventa')->name('lee_un_puntoventa');

/*
 * Clientes
 */

Route::get('ventas/cliente', 'Ventas\ClienteController@index')->name('cliente');
Route::get('ventas/cliente/crear/{tipoalta?}', 'Ventas\ClienteController@crear')->name('crear_cliente');
Route::post('ventas/cliente', 'Ventas\ClienteController@guardar')->name('guardar_cliente');
Route::post('ventas/clienteprovisorio', 'Ventas\ClienteController@guardarClienteProvisorio')->name('guardar_cliente_provisorio');
Route::get('ventas/cliente/{id}/editar', 'Ventas\ClienteController@editar')->name('editar_cliente');
Route::post('ventas/cliente/verificar-documento', 'Ventas\ClienteController@verificarDocumentoAlta')->name('verificar_cliente_documento_alta');
Route::post('ventas/cliente/{id}/validar-arca-padron', 'Ventas\ClienteController@validarArcaPadron')->name('validar_cliente_arca_padron');
Route::post('ventas/cliente/{id}/validar-arca-apoc', 'Ventas\ClienteController@validarArcaApoc')->name('validar_cliente_arca_apoc');
Route::post('ventas/cliente/{id}/validar-padron-operacion', 'Ventas\ClienteController@validarPadronOperacion')->name('validar_cliente_padron_operacion');
Route::post('ventas/cliente/{id}/validar-apoc-operacion', 'Ventas\ClienteController@validarApocOperacion')->name('validar_cliente_apoc_operacion');
Route::get('ventas/cliente/{cliente_id}/suitecrm-cuenta', 'Ventas\ClienteSuitecrmCuentaController@show')->name('cliente_suitecrm_cuenta');
Route::post('ventas/cliente/{cliente_id}/suitecrm-cuenta/sincronizar', 'Ventas\ClienteSuitecrmCuentaController@sincronizar')->name('sincronizar_cliente_suitecrm_cuenta');
Route::get('ventas/cliente/{cliente_id}/suitecrm-notas', 'Ventas\ClienteSuitecrmNotaController@index')->name('cliente_suitecrm_notas');
Route::post('ventas/cliente/{cliente_id}/suitecrm-notas', 'Ventas\ClienteSuitecrmNotaController@store')->name('guardar_cliente_suitecrm_nota');
Route::put('ventas/cliente/{cliente_id}/suitecrm-notas/{nota_id}', 'Ventas\ClienteSuitecrmNotaController@update')->name('actualizar_cliente_suitecrm_nota');
Route::delete('ventas/cliente/{cliente_id}/suitecrm-notas/{nota_id}', 'Ventas\ClienteSuitecrmNotaController@destroy')->name('eliminar_cliente_suitecrm_nota');
Route::put('ventas/cliente/{id}', 'Ventas\ClienteController@actualizar')->name('actualizar_cliente');
Route::delete('ventas/cliente/{id}', 'Ventas\ClienteController@eliminar')->name('eliminar_cliente');
Route::get('ventas/leercliente_entrega/{cliente_id}', 'Ventas\ClienteController@leerCliente_Entrega')->name('leer_cliente_entrega');
Route::get('ventas/leercliente/{cliente_id}', 'Ventas\ClienteController@leerCliente')->name('leer_cliente');

Route::get('ventas/listacliente/{formato?}/{busqueda?}', 'Ventas\ClienteController@listar')->name('lista_cliente');
Route::post('ventas/consultacliente', 'Ventas\ClienteController@consultaCliente')->name('consultar_cliente');
Route::get('ventas/leeruncliente/{cliente_id}', 'Ventas\ClienteController@leeUnCliente')->name('leer_un_cliente');
Route::get('ventas/leerunclienteporcodigo/{codigo}', 'Ventas\ClienteController@leeUnClientePorCodigo')->name('leer_un_cliente_por_codigo');

Route::get('ventas/cliente/crearremoto/{id}', 'Ventas\ClienteController@crearRemoto')->name('crear_cliente_remoto');
Route::get('ventas/cliente/emitenc/{cliente_id}', 'Ventas\ClienteController@emiteNc')->name('emite_nc');

Route::get('ventas/cliente/listacuentacorriente/{id}', 'Ventas\ClienteController@listarCuentaCorriente')->name('listar_cuentacorriente_cliente');
Route::get('ventas/cliente/consultadeuda/{cliente_id}/{empresa_id}/{venta_id?}', 'Ventas\ClienteController@consultarDeuda')->name('consultar_deuda_cliente');
Route::get('ventas/cliente/editacuentacorriente/{id}', 'Ventas\ClienteController@editarCuentaCorriente')->name('editar_cuentacorriente_cliente');
Route::get('ventas/cliente/leercuentacorrienteaplicacion/{id}', 'Ventas\ClienteController@leerCuentaCorrienteAplicacion')->name('leer_cuentacorriente_aplicacion');
/*
 * Pedidos
 */

Route::get('ventas/pedido', 'Ventas\PedidoController@indexp')->name('pedido');
// Route::get('ventas/pedidop', 'Ventas\PedidoController@indexp')->name('pedidop');
Route::get('ventas/pedido/crear', 'Ventas\PedidoController@crear')->name('crear_pedido');
Route::post('ventas/pedido', 'Ventas\PedidoController@guardar')->name('guardar_pedido');
Route::get('ventas/pedido/{id}/editar', 'Ventas\PedidoController@editar')->name('editar_pedido')->middleware('modo.consulta');
Route::put('ventas/pedido/{id}', 'Ventas\PedidoController@actualizar')->name('actualizar_pedido')->middleware('modo.consulta');
Route::delete('ventas/pedido/{id}', 'Ventas\PedidoController@eliminar')->name('eliminar_pedido');
Route::get('ventas/listarpedido/{id}/{cliente_id?}', 'Ventas\PedidoController@listarPedido')->name('listar_pedido');
Route::get('ventas/listarpedidopdf/{id}/{cliente_id?}', 'Ventas\PedidoController@listarPedidoPdf')->name('listar_pedido_pdf');
Route::get('ventas/listarprefactura/{id}/{itemid}/{descuentolinea?}', 'Ventas\PedidoController@listarPreFactura')->name('listar_prefactura');
Route::get('ventas/anularitempedido/{id}/{codigoot}/{motivocierrepedido_id}/{cliente_id?}', 'Ventas\PedidoController@anularItemPedido')->name('anular_item_pedido');
Route::get('ventas/pedido/cerrar', 'Ventas\PedidoController@cerrarPedido')->name('cerrar_pedido');
Route::post('ventas/pedido/ejecutacierre', 'Ventas\PedidoController@ejecutaCierre')->name('ejecuta_cierre_pedido');
Route::post('ventas/pedido/limpiafiltro', 'Ventas\PedidoController@limpiafiltro')->name('pedido.limpiafiltro');
Route::get('ventas/listapedido/{formato?}/{busqueda?}', 'Ventas\PedidoController@listar')->name('lista_pedido');
Route::get('ventas/estadoitempedido/{pedido_articulo_id?}', 'Ventas\PedidoController@estadoItemPedido')->name('estado_item_pedido');
Route::post('ventas/calculafacturaporpedido', 'Ventas\FacturacionController@calculaFacturaPorPedido')->name('calcula_factura_por_pedido');
Route::post('ventas/facturarporpedido', 'Ventas\FacturacionController@facturarPorPedido')->name('facturar_por_pedido');

// Actualiza pedido desde otras aplicaciones fuera del ABM
Route::get('ventas/actualizasolopedido/{estadopedido}/{pedido_id}', 'Ventas\PedidoController@actualizaSoloPedido')->name('actualiza_solo_pedido');
Route::get('ventas/leerhistoriaitempedido/{pedido_articulo_id}', 'Ventas\PedidoController@leerHistoriaItemPedido')->name('leer_historia_item_pedido');

/*
 * Ordenes de trabajo
 */

Route::get('ventas/ordenestrabajo', 'Ventas\OrdentrabajoController@indexp')->name('ordentrabajo');
// Route::get('ventas/ordenestrabajop', 'Ventas\OrdentrabajoController@indexp')->name('ordentrabajop');
Route::get('ventas/consultaordenestrabajo', 'Ventas\OrdentrabajoController@indexp')->name('consulta_ordentrabajo');
Route::get('ventas/ordenestrabajo/crear', 'Ventas\OrdentrabajoController@crear')->name('crear_ordentrabajo');
Route::get('ventas/ordenestrabajo/{id}/editar', 'Ventas\OrdentrabajoController@editar')->name('editar_ordentrabajo');
Route::put('ventas/ordenestrabajo/{id}', 'Ventas\OrdentrabajoController@actualizar')->name('actualizar_ordentrabajo');
Route::delete('ventas/ordenestrabajo/{id}', 'Ventas\OrdentrabajoController@eliminar')->name('eliminar_ordentrabajo');
Route::post('ventas/pedido/consultapendientesot', 'Ventas\PedidoController@consultarPendienteOt')->name('consultar_pendiente_ot');
Route::post('ventas/ordenestrabajo/generar', 'Ventas\OrdentrabajoController@generar')->name('generar_ordentrabajo');
Route::get('ventas/guardaordenestrabajo/{origen}/{ids}/{checkotstock}/{ordentrabajo_stock_codigo}/{deposito_id}/{leyenda?}',
    'Ventas\OrdentrabajoController@guardar')->name('guardar_ordentrabajo');
Route::get('ventas/listaordenestrabajo/{id}', 'Ventas\OrdentrabajoController@listar')->name('listar_ordentrabajo');
Route::get('ventas/estadoot/{id}/{pedido_combinacion_id?}', 'Ventas\OrdentrabajoController@estadoOt')->name('estado_ot');
Route::get('ventas/controlaordentrabajostock/{id}/{articulo_id}/{combinacion_id}', 'Ventas\OrdentrabajoController@controlaOtStock')->name('controla_ordetrabajo_stock');
Route::post('ventas/ordenestrabajo/limpiafiltro', 'Ventas\OrdentrabajoController@limpiafiltro')->name('ordentrabajo.limpiafiltro');
Route::get('ventas/listaordentrabajo/{formato?}/{busqueda?}', 'Ventas\OrdentrabajoController@lista')->name('lista_ordentrabajo');
Route::post('ventas/ordenestrabajo/borrarOt', 'Ventas\OrdentrabajoController@borrarOt')->name('borrar_ot');

/*
 * Comprobantes de venta
 */

Route::get('ventas/factura', 'Ventas\FacturacionController@index')->name('factura');
Route::get('ventas/factura/crear', 'Ventas\FacturacionController@crear')->name('crear_factura');
Route::post('ventas/factura/preferencias', 'Ventas\FacturacionController@preferencias')->name('factura_preferencias');
Route::post('ventas/factura', 'Ventas\FacturacionController@guardar')->name('guardar_factura');
Route::get('ventas/factura/{id}/editar', 'Ventas\FacturacionController@editar')->name('editar_factura');
Route::put('ventas/factura/grabacomprobante', 'Ventas\FacturacionController@grabaComprobante')->name('grabar_comprobante');
Route::put('ventas/factura/{id}', 'Ventas\FacturacionController@actualizar')->name('actualizar_factura');
Route::delete('ventas/factura/{id}', 'Ventas\FacturacionController@eliminar')->name('eliminar_factura');
Route::get('ventas/listafactura/{formato?}/{busqueda?}', 'Ventas\FacturacionController@listar')->name('listar_factura');
Route::get('ventas/listaunafactura/{id}', 'Ventas\FacturacionController@listaUnaFactura')->name('lista_una_factura');
Route::get('ventas/factura/generanotadecredito/{id}', 'Ventas\FacturacionController@generaNotaDeCredito')->name('generar_notadecredito');
Route::post('ventas/calcula_factura_general', 'Ventas\FacturacionController@calculaFacturaGeneral')->name('calcula_factura_general');

/* PRODUCCION */

/*
 * Tareas
 */

Route::get('produccion/tarea', 'Produccion\TareaController@index')->name('tarea');
Route::get('produccion/tarea/crear', 'Produccion\TareaController@crear')->name('crear_tarea');
Route::post('produccion/tarea', 'Produccion\TareaController@guardar')->name('guardar_tarea');
Route::get('produccion/tarea/{id}/editar', 'Produccion\TareaController@editar')->name('editar_tarea');
Route::put('produccion/tarea/{id}', 'Produccion\TareaController@actualizar')->name('actualizar_tarea');
Route::delete('produccion/tarea/{id}', 'Produccion\TareaController@eliminar')->name('eliminar_tarea');

/*
 * Empleados
 */

Route::get('produccion/empleado', 'Produccion\EmpleadoController@index')->name('empleado');
Route::get('produccion/empleado/crear', 'Produccion\EmpleadoController@crear')->name('crear_empleado');
Route::post('produccion/empleado', 'Produccion\EmpleadoController@guardar')->name('guardar_empleado');
Route::get('produccion/empleado/{id}/editar', 'Produccion\EmpleadoController@editar')->name('editar_empleado');
Route::put('produccion/empleado/{id}', 'Produccion\EmpleadoController@actualizar')->name('actualizar_empleado');
Route::delete('produccion/empleado/{id}', 'Produccion\EmpleadoController@eliminar')->name('eliminar_empleado');

/*
 * Operaciones
 */

Route::get('produccion/operacion', 'Produccion\OperacionController@index')->name('operacion');
Route::get('produccion/operacion/crear', 'Produccion\OperacionController@crear')->name('crear_operacion');
Route::post('produccion/operacion', 'Produccion\OperacionController@guardar')->name('guardar_operacion');
Route::get('produccion/operacion/{id}/editar', 'Produccion\OperacionController@editar')->name('editar_operacion');
Route::put('produccion/operacion/{id}', 'Produccion\OperacionController@actualizar')->name('actualizar_operacion');
Route::delete('produccion/operacion/{id}', 'Produccion\OperacionController@eliminar')->name('eliminar_operacion');

/*
 * Movimientos de OT
 */

Route::get('produccion/movimientoordentrabajo', 'Produccion\MovimientoOrdentrabajoController@index')->name('movimientoordentrabajo');
Route::get('produccion/movimientoordentrabajo/crear', 'Produccion\MovimientoOrdentrabajoController@crear')->name('crear_movimientoordentrabajo');
Route::post('produccion/movimientoordentrabajo', 'Produccion\MovimientoOrdentrabajoController@guardar')->name('guardar_movimientoordentrabajo');
Route::get('produccion/movimientoordentrabajo/{id}/editar', 'Produccion\MovimientoOrdentrabajoController@editar')->name('editar_movimientoordentrabajo');
Route::put('produccion/movimientoordentrabajo/{id}', 'Produccion\MovimientoOrdentrabajoController@actualizar')->name('actualizar_movimientoordentrabajo');
Route::delete('produccion/movimientoordentrabajo/{id}', 'Produccion\MovimientoOrdentrabajoController@eliminar')->name('eliminar_movimientoordentrabajo');
Route::get('produccion/consultamovimientoordentrabajo/{id}', 'Produccion\MovimientoOrdentrabajoController@index')->name('consultamovimientoordentrabajo');

// Llamadas desde movimientos de OT
Route::get('produccion/controlsecuencia/{ots}/{operacion}/{tarea}', 'Produccion\MovimientoOrdentrabajoController@controlSecuencia')->name('control_secuencia');
Route::get('produccion/ctrlsecuencia/{ots}/{operacion}/{tarea}/{pedido}', 'Produccion\MovimientoOrdentrabajoController@ctrlSecuencia')->name('ctrl_secuencia');

// Llamadas desde consulta de OT
Route::post('produccion/empacarTarea', 'Produccion\MovimientoOrdentrabajoController@empacarTarea')->name('empacar_tarea');
Route::post('ventas/actualizarpedido', 'Ventas\PedidoController@actualizaItemPedido')->name('actualizar_pedido_desdeoc');
Route::get('produccion/leerTareas/{ot_id}', 'Produccion\MovimientoOrdentrabajoController@leerTareas')->name('leer_tareas');
Route::post('ventas/facturarItemOt', 'Ventas\FacturacionController@facturarItemOt')->name('facturar_item_ot');

// Reportes de produccion

// Estado de OT
Route::get('produccion/repestadoot', 'Produccion\RepEstadoOtController@index')->name('rep_estadoot');
Route::post('produccion/crearrepestadoot', 'Produccion\RepEstadoOtController@crearReporteEstadoOt')->name('crear_repestadoot');

// Total de pares
Route::get('produccion/reptotalpares', 'Produccion\RepTotalParesController@index')->name('rep_totalpares');
Route::post('produccion/crearreptotlapares', 'Produccion\RepTotalParesController@crearReporteTotalPares')->name('crear_reptotalpares');

// Liquidacion de tareas
Route::get('produccion/repliquidaciontarea', 'Produccion\RepLiquidacionTareaController@index')->name('rep_liquidaciontarea');
Route::post('produccion/crearrepliquidaciotarea', 'Produccion\RepLiquidacionTareaController@crearReporteLiquidacionTarea')->name('crear_repliquidaciontarea');

// Consumo de OT
Route::get('produccion/repconsumoot', 'Produccion\RepConsumoOtController@index')->name('rep_consumoot');
Route::post('produccion/crearrepconsumoot', 'Produccion\RepConsumoOtController@crearReporteConsumoOt')->name('crear_repconsumoot');

// Consumo de Cajas
Route::get('produccion/repconsumocaja', 'Produccion\RepConsumoCajaController@index')->name('rep_consumocaja');
Route::post('produccion/crearrepconsumocaja', 'Produccion\RepConsumoCajaController@crearReporteConsumoCaja')->name('crear_repconsumocaja');

// Programacion de Armado
Route::get('produccion/repprogarmado', 'Produccion\RepProgArmadoController@index')->name('rep_progarmado');
Route::post('produccion/crearrepprogarmado', 'Produccion\RepProgArmadoController@crearReporteProgArmado')->name('crear_repprogarmado');

// Graficos
Route::get('graficos/velas', 'Graficos\GraficosController@index')->name('grafico_vela');
Route::get('graficos/reporte', 'Graficos\GraficosController@indexReporteLecturas')->name('reporte_lecturas');
Route::post('graficos/crearreplecturas', 'Graficos\GraficosController@crearReporteLecturas')->name('crear_replecturas');
Route::get('graficos/leerDatosLecturas/{fecha}/{dias}', 'Graficos\GraficosController@leerDatosLecturas')->name('leer_datos_lecturas');
Route::get('graficos/reporteindicadores', 'Graficos\GraficosController@indexReporteIndicadores')->name('reporte_indicadores');
Route::post('graficos/crearindicadores', 'Graficos\GraficosController@crearReporteIndicadores')->name('crear_repindicadores');
Route::get('graficos/ordenes', 'Graficos\GraficosController@indexGeneraOrdenes')->name('ordenes');
Route::post('graficos/generaordenes', 'Graficos\GraficosController@generaOrdenes')->name('genera_ordenes');

// Modulo de caja

/*
 * Cuentas de caja
 */

Route::get('caja/cuentacaja', 'Caja\CuentacajaController@index')->name('cuentacaja');
Route::get('caja/listacuentacaja/{formato?}/{busqueda?}', 'Caja\CuentacajaController@listar')->name('lista_cuentacaja');
Route::get('caja/cuentacaja/crear', 'Caja\CuentacajaController@crear')->name('crear_cuentacaja');
Route::post('caja/cuentacaja', 'Caja\CuentacajaController@guardar')->name('guardar_cuentacaja');
Route::get('caja/cuentacaja/{id}/editar', 'Caja\CuentacajaController@editar')->name('editar_cuentacaja')->middleware('modo.consulta');
Route::put('caja/cuentacaja/{id}', 'Caja\CuentacajaController@actualizar')->name('actualizar_cuentacaja')->middleware('modo.consulta');
Route::delete('caja/cuentacaja/{id}', 'Caja\CuentacajaController@eliminar')->name('eliminar_cuentacaja');

// Rutas de consulta de cuentas de caja
Route::post('caja/cuentacaja/consultacuentacaja', 'Caja\CuentacajaController@consultaCuentaCaja')->name('consulta_cuentacaja');
Route::get('caja/cuentacaja/leercuentacajaporcodigo/{codigo}', 'Caja\CuentacajaController@leerCuentaCajaPorCodigo')->name('leer_cuentacaja_por_codigo');

/*
 * Chequeras
 */

Route::get('caja/chequera', 'Caja\ChequeraController@index')->name('chequera');
Route::get('caja/chequera/crear', 'Caja\ChequeraController@crear')->name('crear_chequera');
Route::post('caja/chequera', 'Caja\ChequeraController@guardar')->name('guardar_chequera');
Route::get('caja/chequera/{id}/editar', 'Caja\ChequeraController@editar')->name('editar_chequera');
Route::put('caja/chequera/{id}', 'Caja\ChequeraController@actualizar')->name('actualizar_chequera');
Route::delete('caja/chequera/{id}', 'Caja\ChequeraController@eliminar')->name('eliminar_chequera');

/*
 * Conceptos de gastos
 */

Route::get('caja/conceptogasto', 'Caja\ConceptogastoController@index')->name('conceptogasto');
Route::get('caja/conceptogasto/crear', 'Caja\ConceptogastoController@crear')->name('crear_conceptogasto');
Route::post('caja/conceptogasto', 'Caja\ConceptogastoController@guardar')->name('guardar_conceptogasto');
Route::get('caja/conceptogasto/{id}/editar', 'Caja\ConceptogastoController@editar')->name('editar_conceptogasto');
Route::put('caja/actualizar_conceptogasto/{id}', 'Caja\ConceptogastoController@actualizar')->name('actualizar_conceptogasto');
Route::delete('caja/conceptogasto/{id}', 'Caja\ConceptogastoController@eliminar')->name('eliminar_conceptogasto');

Route::post('caja/conceptogasto/consultaconceptogasto', 'Caja\ConceptogastoController@consultaConceptogasto')->name('consulta_conceptogasto');
Route::get('caja/leerconceptogasto/{conceptogasto_id}', 'Caja\ConceptogastoController@leeconceptogasto')->name('leer_conceptogasto');

/*
 * Estado de cheques para el banco
 */

Route::get('caja/estadocheque_banco', 'Caja\Estadocheque_BancoController@index')->name('estadocheque_banco');
Route::get('caja/estadocheque_banco/crear', 'Caja\Estadocheque_BancoController@crear')->name('crear_estadocheque_banco');
Route::post('caja/estadocheque_banco', 'Caja\Estadocheque_BancoController@guardar')->name('guardar_estadocheque_banco');
Route::get('caja/estadocheque_banco/{id}/editar', 'Caja\Estadocheque_BancoController@editar')->name('editar_estadocheque_banco');
Route::put('caja/estadocheque_banco/{id}', 'Caja\Estadocheque_BancoController@actualizar')->name('actualizar_estadocheque_banco');
Route::delete('caja/estadocheque_banco/{id}', 'Caja\Estadocheque_BancoController@eliminar')->name('eliminar_estadocheque_banco');

/*
 * Cheques
 */

Route::get('caja/cheque', 'Caja\ChequeController@index')->name('cheque');
Route::get('caja/cheque/crear', 'Caja\ChequeController@crear')->name('crear_cheque');
Route::post('caja/cheque', 'Caja\ChequeController@guardar')->name('guardar_cheque');
Route::get('caja/Cheque/{id}/editar', 'Caja\ChequeController@editar')->name('editar_cheque');
Route::put('caja/cheque/{id}', 'Caja\ChequeController@actualizar')->name('actualizar_cheque');
Route::delete('caja/cheque/{id}', 'Caja\ChequeController@eliminar')->name('eliminar_cheque');

/*
 * Origen de vouchers
 */

Route::get('caja/origenvoucher', 'Caja\OrigenvoucherController@index')->name('origenvoucher');
Route::get('caja/origenvoucher/crear', 'Caja\OrigenvoucherController@crear')->name('crear_origenvoucher');
Route::post('caja/origenvoucher', 'Caja\OrigenvoucherController@guardar')->name('guardar_origenvoucher');
Route::get('caja/origenvoucher/{id}/editar', 'Caja\OrigenvoucherController@editar')->name('editar_origenvoucher');
Route::put('caja/origenvoucher/{id}', 'Caja\OrigenvoucherController@actualizar')->name('actualizar_origenvoucher');
Route::delete('caja/origenvoucher/{id}', 'Caja\OrigenvoucherController@eliminar')->name('eliminar_origenvoucher');

/*
 * Talonarios de vouchers
 */

Route::get('caja/talonariovoucher', 'Caja\TalonariovoucherController@index')->name('talonariovoucher');
Route::get('caja/talonariovoucher/crear', 'Caja\TalonariovoucherController@crear')->name('crear_talonariovoucher');
Route::post('caja/talonariovoucher', 'Caja\TalonariovoucherController@guardar')->name('guardar_talonariovoucher');
Route::get('caja/talonariovoucher/{id}/editar', 'Caja\TalonariovoucherController@editar')->name('editar_talonariovoucher');
Route::put('caja/talonariovoucher/{id}', 'Caja\TalonariovoucherController@actualizar')->name('actualizar_talonariovoucher');
Route::delete('caja/talonariovoucher/{id}', 'Caja\TalonariovoucherController@eliminar')->name('eliminar_talonariovoucher');

/*
 * Talonarios de rendiciones
 */

Route::get('caja/talonariorendicion', 'Caja\TalonariorendicionController@index')->name('talonariorendicion');
Route::get('caja/talonariorendicion/crear', 'Caja\TalonariorendicionController@crear')->name('crear_talonariorendicion');
Route::post('caja/talonariorendicion', 'Caja\TalonariorendicionController@guardar')->name('guardar_talonariorendicion');
Route::get('caja/talonariorendicion/{id}/editar', 'Caja\TalonariorendicionController@editar')->name('editar_talonariorendicion');
Route::put('caja/talonariorendicion/{id}', 'Caja\TalonariorendicionController@actualizar')->name('actualizar_talonariorendicion');
Route::delete('caja/talonariorendicion/{id}', 'Caja\TalonariorendicionController@eliminar')->name('eliminar_talonariorendicion');

/*
 * Bancos
 */

Route::get('caja/banco', 'Caja\BancoController@index')->name('banco');
Route::get('caja/banco/crear', 'Caja\BancoController@crear')->name('crear_banco');
Route::post('caja/banco', 'Caja\BancoController@guardar')->name('guardar_banco');
Route::get('caja/banco/{id}/editar', 'Caja\BancoController@editar')->name('editar_banco');
Route::put('caja/banco/{id}', 'Caja\BancoController@actualizar')->name('actualizar_banco');
Route::delete('caja/banco/{id}', 'Caja\BancoController@eliminar')->name('eliminar_banco');

Route::post('caja/banco/consultabanco', 'Caja\BancoController@consultaBanco')->name('consulta_banco');
Route::get('caja/leerbanco/{banco_id}', 'Caja\BancoController@leebanco')->name('leer_banco');
Route::get('caja/leerbancoporcodigo/{codigobanco}', 'Caja\BancoController@leerBancoPorCodigo')->name('leer_banco_por_codigo');

/*
 * Tipo de cuenta de caja
 */

Route::get('caja/tipocuentacaja', 'Caja\TipocuentacajaController@index')->name('tipocuentacaja');
Route::get('caja/tipocuentacaja/crear', 'Caja\TipocuentacajaController@crear')->name('crear_tipocuentacaja');
Route::post('caja/tipocuentacaja', 'Caja\TipocuentacajaController@guardar')->name('guardar_tipocuentacaja');
Route::get('caja/tipocuentacaja/{id}/editar', 'Caja\TipocuentacajaController@editar')->name('editar_tipocuentacaja');
Route::put('caja/tipocuentacaja/{id}', 'Caja\TipocuentacajaController@actualizar')->name('actualizar_tipocuentacaja');
Route::delete('caja/tipocuentacaja/{id}', 'Caja\TipocuentacajaController@eliminar')->name('eliminar_tipocuentacaja');

/*
 * Uso de medio de pago
 */
Route::get('caja/usocuentacaja', 'Caja\UsocuentacajaController@index')->name('consultar_usocuentacaja');
Route::get('caja/usocuentacaja/crear', 'Caja\UsocuentacajaController@crear')->name('crear_usocuentacaja');
Route::post('caja/usocuentacaja', 'Caja\UsocuentacajaController@guardar')->name('guardar_usocuentacaja');
Route::get('caja/usocuentacaja/{id}/editar', 'Caja\UsocuentacajaController@editar')->name('editar_usocuentacaja');
Route::put('caja/usocuentacaja/{id}', 'Caja\UsocuentacajaController@actualizar')->name('actualizar_usocuentacaja');
Route::delete('caja/usocuentacaja/{id}', 'Caja\UsocuentacajaController@eliminar')->name('eliminar_usocuentacaja');

/*
 * Estacionamiento — categorías (solo entorno AGG)
 */
Route::middleware('estacionamiento.habilitado')->group(function () {
    Route::get('caja/estacionamiento/categoria-automovil', 'Caja\Estacionamiento\CategoriaAutomovilController@index')->name('estacionamiento_categoria_automovil');
    Route::get('caja/lista-estacionamiento-categoria-automovil/{formato?}/{busqueda?}', 'Caja\Estacionamiento\CategoriaAutomovilController@listar')->name('lista_estacionamiento_categoria_automovil');
    Route::get('caja/estacionamiento/categoria-automovil/crear', 'Caja\Estacionamiento\CategoriaAutomovilController@crear')->name('crear_estacionamiento_categoria_automovil');
    Route::post('caja/estacionamiento/categoria-automovil', 'Caja\Estacionamiento\CategoriaAutomovilController@guardar')->name('guardar_estacionamiento_categoria_automovil');
    Route::get('caja/estacionamiento/categoria-automovil/{id}/editar', 'Caja\Estacionamiento\CategoriaAutomovilController@editar')->name('editar_estacionamiento_categoria_automovil');
    Route::put('caja/estacionamiento/categoria-automovil/{id}', 'Caja\Estacionamiento\CategoriaAutomovilController@actualizar')->name('actualizar_estacionamiento_categoria_automovil');
    Route::delete('caja/estacionamiento/categoria-automovil/{id}', 'Caja\Estacionamiento\CategoriaAutomovilController@eliminar')->name('eliminar_estacionamiento_categoria_automovil');

    Route::get('caja/estacionamiento/item', 'Caja\Estacionamiento\ItemEstacionamientoController@index')->name('estacionamiento_item');
    Route::get('caja/lista-estacionamiento-item/{formato?}/{busqueda?}', 'Caja\Estacionamiento\ItemEstacionamientoController@listar')->name('lista_estacionamiento_item');
    Route::get('caja/estacionamiento/item/crear', 'Caja\Estacionamiento\ItemEstacionamientoController@crear')->name('crear_estacionamiento_item');
    Route::post('caja/estacionamiento/item', 'Caja\Estacionamiento\ItemEstacionamientoController@guardar')->name('guardar_estacionamiento_item');
    Route::get('caja/estacionamiento/item/{id}/editar', 'Caja\Estacionamiento\ItemEstacionamientoController@editar')->name('editar_estacionamiento_item');
    Route::put('caja/estacionamiento/item/{id}', 'Caja\Estacionamiento\ItemEstacionamientoController@actualizar')->name('actualizar_estacionamiento_item');
    Route::delete('caja/estacionamiento/item/{id}', 'Caja\Estacionamiento\ItemEstacionamientoController@eliminar')->name('eliminar_estacionamiento_item');

    Route::get('caja/estacionamiento/lista-precio', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@index')->name('estacionamiento_lista_precio');
    Route::get('caja/lista-estacionamiento-lista-precio/{formato?}/{busqueda?}', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@listar')->name('lista_estacionamiento_lista_precio');
    Route::get('caja/estacionamiento/lista-precio/crear', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@crear')->name('crear_estacionamiento_lista_precio');
    Route::get('caja/estacionamiento/lista-precio/validar-cabecera', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@validarCabecera')->name('estacionamiento_lista_precio_validar_cabecera');
    Route::post('caja/estacionamiento/lista-precio', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@guardar')->name('guardar_estacionamiento_lista_precio');
    Route::get('caja/estacionamiento/lista-precio/{id}/editar', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@editar')->name('editar_estacionamiento_lista_precio');
    Route::put('caja/estacionamiento/lista-precio/{id}', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@actualizar')->name('actualizar_estacionamiento_lista_precio');
    Route::delete('caja/estacionamiento/lista-precio/{id}', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@eliminar')->name('eliminar_estacionamiento_lista_precio');
    Route::get('caja/estacionamiento/lista-precio/items-empresa/{empresaId}', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@itemsPorEmpresa')->name('estacionamiento_lista_precio_items_empresa');
    Route::get('caja/estacionamiento/lista-precio/{id}/historia-item/{itemId}', 'Caja\Estacionamiento\ListaPrecioEstacionamientoController@historiaItem')->name('estacionamiento_lista_precio_historia_item');

    Route::get('caja/estacionamiento/jornada', 'Caja\Estacionamiento\JornadaEstacionamientoController@index')->name('estacionamiento_jornada');
    Route::get('caja/estacionamiento/jornada/api/estado/{empresaId}', 'Caja\Estacionamiento\JornadaEstacionamientoController@apiEstado')->name('estacionamiento_jornada_api_estado');
    Route::post('caja/estacionamiento/jornada/api/abrir', 'Caja\Estacionamiento\JornadaEstacionamientoController@apiAbrir')->name('estacionamiento_jornada_api_abrir');
    Route::post('caja/estacionamiento/jornada/api/cerrar', 'Caja\Estacionamiento\JornadaEstacionamientoController@apiCerrar')->name('estacionamiento_jornada_api_cerrar');
    Route::post('caja/estacionamiento/jornada/api/eliminar', 'Caja\Estacionamiento\JornadaEstacionamientoController@apiEliminar')->name('estacionamiento_jornada_api_eliminar');
    Route::post('caja/estacionamiento/jornada/api/anular-cierre', 'Caja\Estacionamiento\JornadaEstacionamientoController@apiAnularCierre')->name('estacionamiento_jornada_api_anular_cierre');
    Route::get('caja/estacionamiento/jornada/{jornadaId}/comprobante-totales-z', 'Caja\Estacionamiento\JornadaEstacionamientoController@comprobanteTotalesZ')->name('estacionamiento_jornada_comprobante_totales_z');

    Route::get('caja/estacionamiento/turno', 'Caja\Estacionamiento\TurnoEstacionamientoController@index')->name('estacionamiento_turno');
    Route::get('caja/estacionamiento/turno/crear', 'Caja\Estacionamiento\TurnoEstacionamientoController@crear')->name('crear_estacionamiento_turno');
    Route::post('caja/estacionamiento/turno', 'Caja\Estacionamiento\TurnoEstacionamientoController@guardar')->name('guardar_estacionamiento_turno');
    Route::get('caja/estacionamiento/turno/{id}/editar', 'Caja\Estacionamiento\TurnoEstacionamientoController@editar')->name('editar_estacionamiento_turno');
    Route::put('caja/estacionamiento/turno/{id}', 'Caja\Estacionamiento\TurnoEstacionamientoController@actualizar')->name('actualizar_estacionamiento_turno');
    Route::delete('caja/estacionamiento/turno/{id}', 'Caja\Estacionamiento\TurnoEstacionamientoController@eliminar')->name('eliminar_estacionamiento_turno');

    Route::get('caja/estacionamiento/descuento', 'Caja\Estacionamiento\DescuentoEstacionamientoController@index')->name('estacionamiento_descuento');
    Route::get('caja/estacionamiento/descuento/crear', 'Caja\Estacionamiento\DescuentoEstacionamientoController@crear')->name('crear_estacionamiento_descuento');
    Route::post('caja/estacionamiento/descuento', 'Caja\Estacionamiento\DescuentoEstacionamientoController@guardar')->name('guardar_estacionamiento_descuento');
    Route::get('caja/estacionamiento/descuento/{id}/editar', 'Caja\Estacionamiento\DescuentoEstacionamientoController@editar')->name('editar_estacionamiento_descuento');
    Route::put('caja/estacionamiento/descuento/{id}', 'Caja\Estacionamiento\DescuentoEstacionamientoController@actualizar')->name('actualizar_estacionamiento_descuento');
    Route::delete('caja/estacionamiento/descuento/{id}', 'Caja\Estacionamiento\DescuentoEstacionamientoController@eliminar')->name('eliminar_estacionamiento_descuento');

    Route::get('caja/estacionamiento/configuracion-puntoventa', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@index')->name('consultar_configuracion_puntoventa_estacionamiento');
    Route::get('caja/estacionamiento/configuracion-puntoventa/crear', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@crear')->name('crear_configuracion_puntoventa_estacionamiento');
    Route::post('caja/estacionamiento/configuracion-puntoventa', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@guardar')->name('guardar_configuracion_puntoventa_estacionamiento');
    Route::get('caja/estacionamiento/configuracion-puntoventa/{id}/editar', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@editar')->name('editar_configuracion_puntoventa_estacionamiento');
    Route::put('caja/estacionamiento/configuracion-puntoventa/{id}', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@actualizar')->name('actualizar_configuracion_puntoventa_estacionamiento');
    Route::delete('caja/estacionamiento/configuracion-puntoventa/{id}', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@eliminar')->name('eliminar_configuracion_puntoventa_estacionamiento');
    Route::get('caja/estacionamiento/configuracion-puntoventa/api/selects-por-empresa/{empresaId}', 'Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoController@apiSelectsPorEmpresa')->name('configuracion_puntoventa_estacionamiento_api_selects');

    Route::get('caja/estacionamiento/habilitacion-turno', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@index')->name('estacionamiento_habilitacion_turno');
    Route::get('caja/estacionamiento/habilitacion-turno/api/estado', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiEstado')->name('estacionamiento_habilitacion_turno_api_estado');
    Route::post('caja/estacionamiento/habilitacion-turno/api/habilitar', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiHabilitar')->name('estacionamiento_habilitacion_turno_api_habilitar');
    Route::post('caja/estacionamiento/habilitacion-turno/api/actualizar-monto-habilitacion', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiActualizarMontoHabilitacion')->name('estacionamiento_habilitacion_turno_api_actualizar_monto_habilitacion');
    Route::post('caja/estacionamiento/habilitacion-turno/api/cierre-parcial', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiCierreParcial')->name('estacionamiento_habilitacion_turno_api_cierre_parcial');
    Route::post('caja/estacionamiento/habilitacion-turno/api/cerrar', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiCerrar')->name('estacionamiento_habilitacion_turno_api_cerrar');
    Route::post('caja/estacionamiento/habilitacion-turno/api/anular-cierre', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiAnularCierre')->name('estacionamiento_habilitacion_turno_api_anular_cierre');
    Route::get('caja/estacionamiento/habilitacion-turno/api/conciliacion-turno', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiConciliacionTurno')->name('estacionamiento_habilitacion_turno_api_conciliacion_turno');
    Route::get('caja/estacionamiento/habilitacion-turno/api/conciliacion-medio', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiConciliacionMedio')->name('estacionamiento_habilitacion_turno_api_conciliacion_medio');
    Route::get('caja/estacionamiento/habilitacion-turno/api/conciliacion-notas-credito', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiConciliacionNotasCredito')->name('estacionamiento_habilitacion_turno_api_conciliacion_notas_credito');
    Route::get('caja/estacionamiento/habilitacion-turno/api/conciliacion-invitaciones', 'Caja\Estacionamiento\HabilitacionTurnoEstacionamientoController@apiConciliacionInvitaciones')->name('estacionamiento_habilitacion_turno_api_conciliacion_invitaciones');

    Route::get('caja/estacionamiento/saneamiento-turno', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@index')->name('estacionamiento_saneamiento_turno');
    Route::get('caja/estacionamiento/saneamiento-turno/api/diagnostico', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiDiagnostico')->name('estacionamiento_saneamiento_turno_api_diagnostico');
    Route::post('caja/estacionamiento/saneamiento-turno/api/extender-cierre', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiExtenderCierre')->name('estacionamiento_saneamiento_turno_api_extender_cierre');
    Route::post('caja/estacionamiento/saneamiento-turno/api/crear-retroactivo', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiCrearRetroactivo')->name('estacionamiento_saneamiento_turno_api_crear_retroactivo');
    Route::post('caja/estacionamiento/saneamiento-turno/api/cerrar-turno-remoto', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiCerrarTurnoRemoto')->name('estacionamiento_saneamiento_turno_api_cerrar_turno_remoto');
    Route::post('caja/estacionamiento/saneamiento-turno/api/recalcular-totales', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiRecalcularTotales')->name('estacionamiento_saneamiento_turno_api_recalcular_totales');
    Route::post('caja/estacionamiento/saneamiento-turno/api/cerrar-cuentas-pendientes', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiCerrarCuentasPendientes')->name('estacionamiento_saneamiento_turno_api_cerrar_cuentas');
    Route::post('caja/estacionamiento/saneamiento-turno/api/anular-tickets-pendientes', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@apiAnularTicketsPendientes')->name('estacionamiento_saneamiento_turno_api_anular_tickets');
    Route::get('caja/estacionamiento/saneamiento-turno/informe-pdf', 'Caja\Estacionamiento\EstacionamientoSaneamientoTurnoController@informePdf')->name('estacionamiento_saneamiento_turno_informe_pdf');

    Route::get('caja/estacionamiento/cierres-turno', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@index')->name('estacionamiento_cierres_turno')->middleware('modo.consulta');
    Route::get('caja/lista-estacionamiento-cierres-turno/{formato}', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@exportar')->name('listar_estacionamiento_cierres_turno');
    Route::get('caja/estacionamiento/cierres-turno/parcial/{id}/comprobante', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@comprobanteParcial')->name('estacionamiento_cierre_turno_comprobante_parcial');
    Route::get('caja/estacionamiento/cierres-turno/cierre/{id}/comprobante', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@comprobanteCierre')->name('estacionamiento_cierre_turno_comprobante_cierre');
    Route::get('caja/estacionamiento/cierres-turno/cierre/{id}/ver', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@verCierre')->name('estacionamiento_cierre_turno_ver')->middleware('modo.consulta');
    Route::get('caja/estacionamiento/cierres-turno/api/comprobantes', 'Caja\Estacionamiento\CierreTurnoEstacionamientoController@apiComprobantes')->name('estacionamiento_cierres_turno_api_comprobantes');

    Route::get('caja/estacionamiento/facturas-dia', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@index')->name('estacionamiento_facturas_dia')->middleware('modo.consulta');
    Route::get('caja/lista-estacionamiento-facturas-dia/{formato}', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@exportar')->name('listar_estacionamiento_facturas_dia');
    Route::get('caja/estacionamiento/facturas-dia/{ventaId}/ver', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@ver')->name('estacionamiento_facturas_dia_ver')->middleware('modo.consulta');
    Route::post('caja/estacionamiento/facturas-dia/{ventaId}/generar-nota-credito', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@generarNotaCredito')->name('estacionamiento_facturas_dia_generar_nota_credito');
    Route::post('caja/estacionamiento/facturas-dia/{ventaId}/reimprimir-ticket', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@reimprimirTicket')->name('estacionamiento_facturas_dia_reimprimir_ticket');
    Route::get('caja/estacionamiento/facturas-dia/{ventaId}/medios-pago', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@apiMediosPagoCambio')->name('estacionamiento_facturas_dia_medios_pago');
    Route::get('caja/estacionamiento/facturas-dia/{ventaId}/cuentacaja-por-codigo/{codigo}', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@apiCuentacajaPorCodigo')->name('estacionamiento_facturas_dia_cuentacaja_por_codigo');
    Route::put('caja/estacionamiento/facturas-dia/{ventaId}/medios-pago', 'Caja\Estacionamiento\EstacionamientoFacturasDiaController@actualizarMediosPago')->name('estacionamiento_facturas_dia_actualizar_medios_pago');

    Route::get('caja/estacionamiento/proceso-facturacion', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@index')->name('estacionamiento_proceso_facturacion');
    Route::get('caja/estacionamiento/descuento/leer/{codigo}', 'Caja\Estacionamiento\DescuentoEstacionamientoController@leeUnDescuentoPorCodigo')->name('leer_descuento_estacionamiento');
    Route::post('caja/estacionamiento/descuento/consultadescuento', 'Caja\Estacionamiento\DescuentoEstacionamientoController@consultaDescuento')->name('consulta_descuento_estacionamiento');

    Route::prefix('caja/estacionamiento/api')->group(function () {
        Route::get('config', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiConfig')->name('estacionamiento_api_config');
        Route::get('cuenta-activa', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCuentaActiva')->name('estacionamiento_api_cuenta_activa');
        Route::get('categorias', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCategorias')->name('estacionamiento_api_categorias');
        Route::get('items-catalogo', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiItemsCatalogo')->name('estacionamiento_api_items_catalogo');
        Route::get('item/{id}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiItemPorId')->name('estacionamiento_api_item');
        Route::get('descuentos', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiDescuentos')->name('estacionamiento_api_descuentos');
        Route::get('descuento-por-codigo/{codigo}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiDescuentoPorCodigo')->name('estacionamiento_api_descuento_por_codigo');
        Route::get('cuentas-caja', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCuentasCaja')->name('estacionamiento_api_cuentas_caja');
        Route::get('cuentacaja-por-codigo/{codigo}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCuentacajaPorCodigo')->name('estacionamiento_api_cuentacaja_por_codigo');
        Route::get('monedas', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiMonedas')->name('estacionamiento_api_monedas');
        Route::get('cotizacion', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCotizacion')->name('estacionamiento_api_cotizacion');
        Route::patch('cuenta/{id}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiActualizarCuenta')->name('estacionamiento_api_actualizar_cuenta');
        Route::post('cuenta/{id}/linea', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiAgregarLinea')->name('estacionamiento_api_agregar_linea');
        Route::patch('cuenta/{cuentaId}/linea/{lineaId}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiActualizarCantidadLinea')->name('estacionamiento_api_actualizar_cantidad_linea');
        Route::delete('cuenta/{cuentaId}/linea/{lineaId}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiEliminarLinea')->name('estacionamiento_api_eliminar_linea');
        Route::post('validar-emision', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiValidarEmision')->name('estacionamiento_api_validar_emision');
        Route::post('emitir-factura', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiEmitirFactura')->name('estacionamiento_api_emitir_factura');
        Route::post('cerrar-cuenta/{id}', 'Caja\Estacionamiento\EstacionamientoProcesoFacturacionController@apiCerrarCuenta')->name('estacionamiento_api_cerrar_cuenta');
    });
});

/*
 * Bingo — cartones, conceptos de rendición (módulo Caja)
 */
Route::middleware('bingo.habilitado')->group(function () {
    Route::get('caja/bingo/carton', 'Caja\Bingo\BingoCartonController@index')->name('bingo_carton');
    Route::get('caja/lista-bingo-carton/{formato?}/{busqueda?}', 'Caja\Bingo\BingoCartonController@listar')->name('lista_bingo_carton');
    Route::get('caja/bingo/carton/crear', 'Caja\Bingo\BingoCartonController@crear')->name('crear_bingo_carton');
    Route::post('caja/bingo/carton', 'Caja\Bingo\BingoCartonController@guardar')->name('guardar_bingo_carton');
    Route::get('caja/bingo/carton/{id}/editar', 'Caja\Bingo\BingoCartonController@editar')->name('editar_bingo_carton');
    Route::put('caja/bingo/carton/{id}', 'Caja\Bingo\BingoCartonController@actualizar')->name('actualizar_bingo_carton');
    Route::delete('caja/bingo/carton/{id}', 'Caja\Bingo\BingoCartonController@eliminar')->name('eliminar_bingo_carton');

    Route::get('caja/flash', 'Caja\Flash\FlashCajaController@index')->name('flash_caja');
    Route::get('caja/lista-flash-caja/{formato?}/{busqueda?}', 'Caja\Flash\FlashCajaController@listar')->name('lista_flash_caja');
    Route::get('caja/flash/reporte-historico', 'Caja\Flash\FlashCajaController@reporteHistorico')->name('flash_caja_reporte_historico');
    Route::get('caja/flash/listar-reporte-historico/{formato?}', 'Caja\Flash\FlashCajaController@exportarReporteHistorico')->name('listar_flash_caja_reporte_historico');
    Route::get('caja/flash/crear', 'Caja\Flash\FlashCajaController@crear')->name('crear_flash_caja');
    Route::post('caja/flash', 'Caja\Flash\FlashCajaController@guardar')->name('guardar_flash_caja');
    Route::post('caja/flash/api/calcular', 'Caja\Flash\FlashCajaController@apiCalcular')->name('flash_caja_api_calcular');
    Route::get('caja/flash/{id}/editar', 'Caja\Flash\FlashCajaController@editar')->name('editar_flash_caja');
    Route::put('caja/flash/{id}', 'Caja\Flash\FlashCajaController@actualizar')->name('actualizar_flash_caja');
    Route::delete('caja/flash/{id}', 'Caja\Flash\FlashCajaController@eliminar')->name('eliminar_flash_caja');
    Route::get('caja/flash/{id}/reporte/{formato?}', 'Caja\Flash\FlashCajaController@reporte')->name('flash_caja_reporte');

    Route::get('caja/bingo/concepto-rendicion', 'Caja\Bingo\BingoConceptoRendicionController@index')->name('bingo_concepto_rendicion');
    Route::get('caja/lista-bingo-concepto-rendicion/{formato?}/{busqueda?}', 'Caja\Bingo\BingoConceptoRendicionController@listar')->name('lista_bingo_concepto_rendicion');
    Route::get('caja/bingo/concepto-rendicion/crear', 'Caja\Bingo\BingoConceptoRendicionController@crear')->name('crear_bingo_concepto_rendicion');
    Route::post('caja/bingo/concepto-rendicion', 'Caja\Bingo\BingoConceptoRendicionController@guardar')->name('guardar_bingo_concepto_rendicion');
    Route::get('caja/bingo/concepto-rendicion/{id}/editar', 'Caja\Bingo\BingoConceptoRendicionController@editar')->name('editar_bingo_concepto_rendicion');
    Route::put('caja/bingo/concepto-rendicion/{id}', 'Caja\Bingo\BingoConceptoRendicionController@actualizar')->name('actualizar_bingo_concepto_rendicion');
    Route::delete('caja/bingo/concepto-rendicion/{id}', 'Caja\Bingo\BingoConceptoRendicionController@eliminar')->name('eliminar_bingo_concepto_rendicion');

    Route::get('caja/bingo/jornada', 'Caja\Bingo\JornadaBingoController@index')->name('bingo_jornada');
    Route::get('caja/bingo/jornada/api/estado/{empresaId}', 'Caja\Bingo\JornadaBingoController@apiEstado')->name('bingo_jornada_api_estado');
    Route::post('caja/bingo/jornada/api/abrir', 'Caja\Bingo\JornadaBingoController@apiAbrir')->name('bingo_jornada_api_abrir');
    Route::post('caja/bingo/jornada/api/cerrar', 'Caja\Bingo\JornadaBingoController@apiCerrar')->name('bingo_jornada_api_cerrar');
    Route::post('caja/bingo/jornada/api/eliminar', 'Caja\Bingo\JornadaBingoController@apiEliminar')->name('bingo_jornada_api_eliminar');
    Route::post('caja/bingo/jornada/api/anular-cierre', 'Caja\Bingo\JornadaBingoController@apiAnularCierre')->name('bingo_jornada_api_anular_cierre');

    Route::get('caja/bingo/turno', 'Caja\Bingo\TurnoBingoController@index')->name('bingo_turno');
    Route::get('caja/bingo/turno/crear', 'Caja\Bingo\TurnoBingoController@crear')->name('crear_bingo_turno');
    Route::post('caja/bingo/turno', 'Caja\Bingo\TurnoBingoController@guardar')->name('guardar_bingo_turno');
    Route::get('caja/bingo/turno/{id}/editar', 'Caja\Bingo\TurnoBingoController@editar')->name('editar_bingo_turno');
    Route::put('caja/bingo/turno/{id}', 'Caja\Bingo\TurnoBingoController@actualizar')->name('actualizar_bingo_turno');
    Route::delete('caja/bingo/turno/{id}', 'Caja\Bingo\TurnoBingoController@eliminar')->name('eliminar_bingo_turno');

    Route::get('caja/bingo/configuracion-puntoventa', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@index')->name('bingo_configuracion_puntoventa');
    Route::get('caja/bingo/configuracion-puntoventa/crear', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@crear')->name('crear_bingo_configuracion_puntoventa');
    Route::post('caja/bingo/configuracion-puntoventa', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@guardar')->name('guardar_bingo_configuracion_puntoventa');
    Route::get('caja/bingo/configuracion-puntoventa/{id}/editar', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@editar')->name('editar_bingo_configuracion_puntoventa');
    Route::put('caja/bingo/configuracion-puntoventa/{id}', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@actualizar')->name('actualizar_bingo_configuracion_puntoventa');
    Route::delete('caja/bingo/configuracion-puntoventa/{id}', 'Caja\Bingo\ConfiguracionPuntoventaBingoController@eliminar')->name('eliminar_bingo_configuracion_puntoventa');

    Route::get('caja/bingo/habilitacion-turno', 'Caja\Bingo\HabilitacionTurnoBingoController@index')->name('bingo_habilitacion_turno');
    Route::get('caja/bingo/habilitacion-turno/api/estado', 'Caja\Bingo\HabilitacionTurnoBingoController@apiEstado')->name('bingo_habilitacion_turno_api_estado');
    Route::post('caja/bingo/habilitacion-turno/api/habilitar', 'Caja\Bingo\HabilitacionTurnoBingoController@apiHabilitar')->name('bingo_habilitacion_turno_api_habilitar');
    Route::post('caja/bingo/habilitacion-turno/api/cierre-parcial', 'Caja\Bingo\HabilitacionTurnoBingoController@apiCierreParcial')->name('bingo_habilitacion_turno_api_cierre_parcial');
    Route::post('caja/bingo/habilitacion-turno/api/cerrar', 'Caja\Bingo\HabilitacionTurnoBingoController@apiCerrar')->name('bingo_habilitacion_turno_api_cerrar');

    Route::get('caja/bingo/rendicion/cargar', 'Caja\Bingo\RendicionBingoTerminalController@cargar')->name('bingo_rendicion_cargar');
    Route::get('caja/bingo/rendicion/editar/{turno}', 'Caja\Bingo\RendicionBingoTerminalController@editar')->name('bingo_rendicion_editar');
    Route::post('caja/bingo/rendicion/api/calcular', 'Caja\Bingo\RendicionBingoTerminalController@apiCalcular')->name('bingo_rendicion_api_calcular');
    Route::post('caja/bingo/rendicion/api/guardar', 'Caja\Bingo\RendicionBingoTerminalController@apiGuardar')->name('bingo_rendicion_api_guardar');

    Route::get('caja/bingo/cierres-turno', 'Caja\Bingo\CierreTurnoBingoController@index')->name('bingo_cierres_turno')->middleware('modo.consulta');
    Route::get('caja/bingo/cierres-turno/cierre/{id}/comprobante', 'Caja\Bingo\CierreTurnoBingoController@comprobanteCierre')->name('bingo_cierre_turno_comprobante_cierre');
    Route::get('caja/bingo/cierres-turno/parcial/{id}/comprobante', 'Caja\Bingo\CierreTurnoBingoController@comprobanteParcial')->name('bingo_cierre_turno_comprobante_parcial');
});

/*
 * Voucher
 */

Route::get('caja/voucher', 'Caja\VoucherController@index')->name('voucher');
Route::get('caja/voucher/crear', 'Caja\VoucherController@crear')->name('crear_voucher');
Route::post('caja/voucher', 'Caja\VoucherController@guardar')->name('guardar_voucher');
Route::get('caja/voucher/{id}/editar', 'Caja\VoucherController@editar')->name('editar_voucher');
Route::put('caja/voucher/{id}', 'Caja\VoucherController@actualizar')->name('actualizar_voucher');
Route::delete('caja/voucher/{id}', 'Caja\VoucherController@eliminar')->name('eliminar_voucher');
Route::get('caja/listavoucher/{formato?}/{busqueda?}', 'Caja\VoucherController@listar')->name('lista_voucher');
Route::get('caja/listarvoucher/{id}', 'Caja\VoucherController@listarVoucher')->name('listar_voucher');
/*
 * Rendicion de receptivo
 */

Route::get('caja/rendicionreceptivo', 'Caja\RendicionreceptivoController@index')->name('rendicionreceptivo');
Route::get('caja/rendicionreceptivo/crear/{caja?}', 'Caja\RendicionreceptivoController@crear')->name('crear_rendicionreceptivo');
Route::post('caja/rendicionreceptivo', 'Caja\RendicionreceptivoController@guardar')->name('guardar_rendicionreceptivo');
Route::get('caja/rendicionreceptivo/{id}/{origen?}/editar', 'Caja\RendicionreceptivoController@editar')->name('editar_rendicionreceptivo');
Route::put('caja/actualizarrendicionreceptivo/{id}', 'Caja\RendicionreceptivoController@actualizar')->name('actualizar_rendicionreceptivo');
Route::delete('caja/rendicionreceptivo/{id}/{origen?}', 'Caja\RendicionreceptivoController@eliminar')->name('eliminar_rendicionreceptivo');
Route::get('caja/listarendicionreceptivo/{formato?}/{busqueda?}', 'Caja\RendicionreceptivoController@listar')->name('lista_rendicionreceptivo');

Route::post('caja/rendicionreceptivo/leegastoanterior', 'Caja\RendicionreceptivoController@leeGastoAnterior')->name('leer_gasto_anterior');
Route::post('caja/rendicionreceptivo/leevoucher', 'Caja\RendicionreceptivoController@leeVoucher')->name('leer_voucher');
/*
 * Rendición gastronomía caja
 */
Route::get('caja/rendiciongastronomia', 'Caja\RendicionGastronomiaController@index')->name('rendiciongastronomia');
Route::get('caja/listarendiciongastronomia/{formato?}/{busqueda?}', 'Caja\RendicionGastronomiaController@listar')->name('listar_rendiciongastronomia');
Route::get('caja/rendiciongastronomia/crear/{caja?}', 'Caja\RendicionGastronomiaController@crear')->name('crear_rendiciongastronomia');
Route::post('caja/rendiciongastronomia', 'Caja\RendicionGastronomiaController@guardar')->name('guardar_rendiciongastronomia');
Route::get('caja/rendiciongastronomia/{id}/imprimir', 'Caja\RendicionGastronomiaController@imprimir')->name('imprimir_rendicion_gastronomia');
Route::get('caja/rendiciongastronomia/{id}/editar', 'Caja\RendicionGastronomiaController@editar')->name('editar_rendiciongastronomia');
Route::put('caja/rendiciongastronomia/{id}', 'Caja\RendicionGastronomiaController@actualizar')->name('actualizar_rendiciongastronomia');
Route::delete('caja/rendiciongastronomia/{id}', 'Caja\RendicionGastronomiaController@eliminar')->name('eliminar_rendiciongastronomia');
Route::post('caja/rendiciongastronomia/api/datos-turno', 'Caja\RendicionGastronomiaController@apiDatosTurno')->name('api_rendicion_gastronomia_datos_turno');
Route::post('caja/rendiciongastronomia/api/datos-jornada', 'Caja\RendicionGastronomiaController@apiDatosJornada')->name('api_rendicion_gastronomia_datos_jornada');
Route::get('caja/rendiciongastronomia/api/jornada/{numero}', 'Caja\RendicionGastronomiaController@apiJornadaPorNumero')->name('api_rendicion_gastronomia_jornada_numero');
Route::get('caja/rendiciongastronomia/api/proponer-codigo', 'Caja\RendicionGastronomiaController@apiProponerCodigo')->name('api_rendicion_gastronomia_proponer_codigo');
Route::post('caja/rendiciongastronomia/api/consulta-cierre', 'Caja\RendicionGastronomiaController@apiConsultaCierre')->name('api_rendicion_gastronomia_consulta_cierre');
Route::get('caja/rendiciongastronomia/api/turno/{numero}', 'Caja\RendicionGastronomiaController@apiTurnoPorNumero')->name('api_rendicion_gastronomia_turno_numero');
Route::get('caja/rendicionmaquinavending', 'Caja\RendicionMaquinavendingController@index')->name('rendicionmaquinavending');
Route::get('caja/listarrendicionmaquinavending/{formato?}/{busqueda?}', 'Caja\RendicionMaquinavendingController@listar')->name('listar_rendicionmaquinavending');
Route::get('caja/rendicionmaquinavending/crear/{caja?}', 'Caja\RendicionMaquinavendingController@crear')->name('crear_rendicionmaquinavending');
Route::post('caja/rendicionmaquinavending', 'Caja\RendicionMaquinavendingController@guardar')->name('guardar_rendicionmaquinavending');
Route::get('caja/rendicionmaquinavending/{id}/imprimir', 'Caja\RendicionMaquinavendingController@imprimir')->name('imprimir_rendicion_maquinavending');
Route::get('caja/rendicionmaquinavending/{id}/editar', 'Caja\RendicionMaquinavendingController@editar')->name('editar_rendicionmaquinavending');
Route::put('caja/rendicionmaquinavending/{id}', 'Caja\RendicionMaquinavendingController@actualizar')->name('actualizar_rendicionmaquinavending');
Route::delete('caja/rendicionmaquinavending/{id}', 'Caja\RendicionMaquinavendingController@eliminar')->name('eliminar_rendicionmaquinavending');
Route::post('caja/rendicionmaquinavending/api/consulta-rendicion', 'Caja\RendicionMaquinavendingController@apiConsultaRendicionVentas')->name('api_rendicion_maquinavending_consulta_rendicion');
Route::post('caja/rendicionmaquinavending/api/datos-rendicion', 'Caja\RendicionMaquinavendingController@apiDatosRendicionVentas')->name('api_rendicion_maquinavending_datos_rendicion');
Route::get('caja/rendicionbingo', 'Caja\RendicionBingoController@index')->name('rendicionbingo');
Route::get('caja/listarendicionbingo/{formato?}/{busqueda?}', 'Caja\RendicionBingoController@listar')->name('listar_rendicionbingo');
Route::get('caja/rendicionbingo/crear/{caja?}', 'Caja\RendicionBingoController@crear')->name('crear_rendicionbingo');
Route::post('caja/rendicionbingo', 'Caja\RendicionBingoController@guardar')->name('guardar_rendicionbingo');
Route::get('caja/rendicionbingo/{id}/imprimir', 'Caja\RendicionBingoController@imprimir')->name('imprimir_rendicion_bingo');
Route::delete('caja/rendicionbingo/{id}', 'Caja\RendicionBingoController@eliminar')->name('eliminar_rendicionbingo');
Route::post('caja/rendicionbingo/api/consulta-cierre', 'Caja\RendicionBingoController@apiConsultaCierre')->name('api_rendicion_bingo_consulta_cierre');
Route::post('caja/rendicionbingo/api/datos-turno', 'Caja\RendicionBingoController@apiDatosTurno')->name('api_rendicion_bingo_datos_turno');
Route::redirect('caja/bingo/rendicion', '/caja/rendicionbingo', 301);
Route::get('caja/rendicionestacionamiento', 'Caja\RendicionEstacionamientoController@index')->name('rendicionestacionamiento');
Route::get('caja/listarendicionestacionamiento/{formato?}/{busqueda?}', 'Caja\RendicionEstacionamientoController@listar')->name('listar_rendicionestacionamiento');
Route::get('caja/rendicionestacionamiento/crear/{caja?}', 'Caja\RendicionEstacionamientoController@crear')->name('crear_rendicionestacionamiento');
Route::post('caja/rendicionestacionamiento', 'Caja\RendicionEstacionamientoController@guardar')->name('guardar_rendicionestacionamiento');
Route::get('caja/rendicionestacionamiento/{id}/imprimir', 'Caja\RendicionEstacionamientoController@imprimir')->name('imprimir_rendicion_estacionamiento');
Route::get('caja/rendicionestacionamiento/{id}/editar', 'Caja\RendicionEstacionamientoController@editar')->name('editar_rendicionestacionamiento');
Route::put('caja/rendicionestacionamiento/{id}', 'Caja\RendicionEstacionamientoController@actualizar')->name('actualizar_rendicionestacionamiento');
Route::delete('caja/rendicionestacionamiento/{id}', 'Caja\RendicionEstacionamientoController@eliminar')->name('eliminar_rendicionestacionamiento');
Route::post('caja/rendicionestacionamiento/api/datos-turno', 'Caja\RendicionEstacionamientoController@apiDatosTurno')->name('api_rendicion_estacionamiento_datos_turno');
Route::post('caja/rendicionestacionamiento/api/datos-jornada', 'Caja\RendicionEstacionamientoController@apiDatosJornada')->name('api_rendicion_estacionamiento_datos_jornada');
Route::get('caja/rendicionestacionamiento/api/jornada/{numero}', 'Caja\RendicionEstacionamientoController@apiJornadaPorNumero')->name('api_rendicion_estacionamiento_jornada_numero');
Route::get('caja/rendicionestacionamiento/api/proponer-codigo', 'Caja\RendicionEstacionamientoController@apiProponerCodigo')->name('api_rendicion_estacionamiento_proponer_codigo');
Route::post('caja/rendicionestacionamiento/api/consulta-cierre', 'Caja\RendicionEstacionamientoController@apiConsultaCierre')->name('api_rendicion_estacionamiento_consulta_cierre');
Route::get('caja/rendicionestacionamiento/api/turno/{numero}', 'Caja\RendicionEstacionamientoController@apiTurnoPorNumero')->name('api_rendicion_estacionamiento_turno_numero');
Route::get('caja/waitry-cierre-jornada', 'Caja\WaitryCierreJornadaController@index')->name('waitry_cierre_jornada');
Route::get('caja/listarwaitrycierrejornada/{formato?}', 'Caja\WaitryCierreJornadaController@listar')->name('listar_waitry_cierre_jornada');
Route::get('caja/waitry-cierre-jornada/api/proceso/analizar', 'Caja\WaitryCierreJornadaController@apiProcesoAnalizar')->name('waitry_cierre_jornada_api_proceso_analizar');
Route::post('caja/waitry-cierre-jornada/api/proceso/recalcular', 'Caja\WaitryCierreJornadaController@apiProcesoRecalcular')->name('waitry_cierre_jornada_api_proceso_recalcular');
Route::get('caja/waitry-cierre-jornada/api/proceso/preview-factura', 'Caja\WaitryCierreJornadaController@apiProcesoPreviewFactura')->name('waitry_cierre_jornada_api_proceso_preview_factura');
Route::get('caja/waitry-cierre-jornada/api/proceso/preview-lotes-factura', 'Caja\WaitryCierreJornadaController@apiProcesoPreviewLotesFactura')->name('waitry_cierre_jornada_api_proceso_preview_lotes_factura');
Route::post('caja/waitry-cierre-jornada/api/proceso/emitir-factura', 'Caja\WaitryCierreJornadaController@apiProcesoEmitirFactura')->name('waitry_cierre_jornada_api_proceso_emitir_factura');
Route::post('caja/waitry-cierre-jornada/api/proceso/ejecutar-automatico', 'Caja\WaitryCierreJornadaController@apiProcesoEjecutarAutomatico')->name('waitry_cierre_jornada_api_proceso_ejecutar_automatico');
Route::post('caja/waitry-cierre-jornada/api/proceso/grabar-asientos', 'Caja\WaitryCierreJornadaController@apiProcesoGrabarAsientos')->name('waitry_cierre_jornada_api_proceso_grabar_asientos');
Route::post('caja/waitry-cierre-jornada/api/proceso/revertir', 'Caja\WaitryCierreJornadaController@apiProcesoRevertir')->name('waitry_cierre_jornada_api_proceso_revertir');
Route::get('caja/waitry-cierre-jornada/api/proceso/opciones-emitir', 'Caja\WaitryCierreJornadaController@apiProcesoOpcionesEmitir')->name('waitry_cierre_jornada_api_proceso_opciones_emitir');
Route::get('caja/waitry-cierre-jornada/api/proceso/movimientos/{grupo}', 'Caja\WaitryCierreJornadaController@apiProcesoMovimientosGrupo')->name('waitry_cierre_jornada_api_proceso_movimientos');
Route::get('caja/waitry-cierre-jornada/api/proceso/cuadro-detalle/{fila}/{medio}', 'Caja\WaitryCierreJornadaController@apiProcesoCuadroDetalle')->name('waitry_cierre_jornada_api_proceso_cuadro_detalle');
Route::get('caja/waitry-cierre-jornada/api/proceso/config/{empresaId}', 'Caja\WaitryCierreJornadaController@apiProcesoConfig')->name('waitry_cierre_jornada_api_proceso_config');
Route::post('caja/waitry-cierre-jornada/api/proceso/config/{empresaId}', 'Caja\WaitryCierreJornadaController@apiProcesoGuardarConfig')->name('waitry_cierre_jornada_api_proceso_config_guardar');
/*
 * Tipos de transacciones de caja
 */

Route::get('caja/tipotransaccion_caja', 'Caja\Tipotransaccion_CajaController@index')->name('tipotransaccion_caja');
Route::get('caja/tipotransaccion_caja/crear', 'Caja\Tipotransaccion_CajaController@crear')->name('crear_tipotransaccion_caja');
Route::post('caja/tipotransaccion_caja', 'Caja\Tipotransaccion_CajaController@guardar')->name('guardar_tipotransaccion_caja');
Route::get('caja/tipotransaccion_caja/{id}/editar', 'Caja\Tipotransaccion_CajaController@editar')->name('editar_tipotransaccion_caja');
Route::put('caja/tipotransaccion_caja/{id}', 'Caja\Tipotransaccion_CajaController@actualizar')->name('actualizar_tipotransaccion_caja');
Route::delete('caja/tipotransaccion_caja/{id}', 'Caja\Tipotransaccion_CajaController@eliminar')->name('eliminar_tipotransaccion_caja');

Route::get('caja/leertipotransaccion_caja/{id}', 'Caja\Tipotransaccion_CajaController@leeTipotransaccion_caja')->name('leer_tipotransaccion_caja');
/*
 * Cajas
 */

Route::get('caja/caja', 'Caja\CajaController@index')->name('consulta_caja');
Route::get('caja/caja/crear', 'Caja\CajaController@crear')->name('crea_caja');
Route::post('caja/caja', 'Caja\CajaController@guardar')->name('guarda_caja');
Route::get('caja/caja/{id}/editar', 'Caja\CajaController@editar')->name('edita_caja');
Route::put('caja/caja/{id}', 'Caja\CajaController@actualizar')->name('actualiza_caja');
Route::delete('caja/caja/{id}', 'Caja\CajaController@eliminar')->name('elimina_caja');

/*
 * Asignacion de Cajas
 */

Route::get('caja/cajaasignacion', 'Caja\CajaAsignacionController@index')->name('consulta_cajaasignacion');
Route::get('caja/cajasignacion/crear', 'Caja\CajaAsignacionController@crear')->name('crea_cajaasignacion');
Route::post('caja/cajasignacion', 'Caja\CajaAsignacionController@guardar')->name('guarda_cajaasignacion');
Route::get('caja/cajasignacion/{id}/editar', 'Caja\CajaAsignacionController@editar')->name('edita_cajaasignacion');
Route::put('caja/cajasignacion/{id}', 'Caja\CajaAsignacionController@actualizar')->name('actualiza_cajaasignacion');
Route::delete('caja/cajasignacion/{id}', 'Caja\CajaAsignacionController@eliminar')->name('elimina_cajaasignacion');

/*
 * Movimiento de Cajas
 */

Route::get('caja/movimientocaja', 'Caja\MovimientoCajaController@index')->name('consulta_movimiento_caja');

/*
 * Ingresos y Egresos de Caja
 */

Route::get('caja/ingresoegreso', 'Caja\IngresoEgresoController@index')->name('ingresoegreso');
Route::get('caja/ingresoegreso/crear/{caja?}', 'Caja\IngresoEgresoController@crear')->name('crear_ingresoegreso');
Route::post('caja/ingresoegreso', 'Caja\IngresoEgresoController@guardar')->name('guardar_ingresoegreso');
Route::get('caja/ingresoegreso/{id}/{origen?}/editar', 'Caja\IngresoEgresoController@editar')->name('editar_ingresoegreso');
Route::put('caja/actualizaringresoegreso/{id}', 'Caja\IngresoEgresoController@actualizar')->name('actualizar_ingresoegreso');
Route::delete('caja/ingresoegreso/{id}/{origen?}', 'Caja\IngresoEgresoController@eliminar')->name('eliminar_ingresoegreso');
Route::get('caja/listaingresoegreso/{formato?}/{busqueda?}', 'Caja\IngresoEgresoController@listar')->name('lista_ingresoegreso');
Route::post('caja/copiar_ingresoegreso', 'Caja\IngresoEgresoController@copiarIngresoEgreso')->name('copiar_ingresoegreso');
Route::post('caja/revertir_ingresoegreso', 'Caja\IngresoEgresoController@revertirIngresoEgreso')->name('revertir_ingresoegreso');
Route::post('caja/generaasientocontable_ingresoegreso', 'Caja\IngresoEgresoController@generaAsientoContable')->name('generaasientocontable_ingresoegreso');
Route::post('caja/ingresoegreso/comprobante-iva/preview-asiento', 'Caja\IngresoEgresoController@previewAsientoComprobanteIva')->name('ingresoegreso_comprobante_iva_preview_asiento');
Route::post('caja/ingresoegreso/comprobante-iva/pdf-ia-preview', 'Caja\IngresoEgresoController@previewPdfComprobanteIva')->name('ingresoegreso_comprobante_iva_pdf_ia_preview');
Route::post('caja/ingresoegreso/comprobante-iva/validar-totales', 'Caja\IngresoEgresoController@validarTotalesComprobantesIva')->name('ingresoegreso_comprobante_iva_validar_totales');
Route::post('caja/ingresoegreso/comprobante-iva/validar-duplicado', 'Caja\IngresoEgresoController@validarDuplicadoComprobanteIva')->name('ingresoegreso_comprobante_iva_validar_duplicado');
Route::post('caja/ingresoegreso/buscar-cheque', 'Caja\IngresoEgresoController@buscarCheque')->name('ingresoegreso_buscar_cheque');

/*
 * Cobranzas
 */

Route::get('caja/cobranza', 'Caja\CobranzaController@index')->name('cobranza');
Route::get('caja/cobranza/crear/{venta_id?}/{caja?}', 'Caja\CobranzaController@crear')->name('crear_cobranza');
Route::post('caja/cobranza', 'Caja\CobranzaController@guardar')->name('guardar_cobranza');
Route::get('caja/cobranza/{id}/{origen?}/editar', 'Caja\CobranzaController@editar')->name('editar_cobranza');
Route::put('caja/actualizarcobranza/{id}', 'Caja\CobranzaController@actualizar')->name('actualizar_cobranza');
Route::delete('caja/cobranza/{id}/{origen?}', 'Caja\CobranzaController@eliminar')->name('eliminar_cobranza');
Route::get('caja/listacobranza/{formato?}/{busqueda?}', 'Caja\CobranzaController@listar')->name('lista_cobranza');
Route::post('caja/generaasientocontable_cobranza', 'Caja\CobranzaController@generaAsientoContable')->name('generaasientocontable_cobranza');

Route::get('caja/leer_historia_cobranza/{cobranza_id}', 'Caja\CobranzaController@leerHistoriaCobranza')->name('leer_historia_cobranza');
Route::get('caja/listar_una_cobranza/{id}', 'Caja\CobranzaController@listarUnaCobranza')->name('listar_una_cobranza');

/*
 * Interface con Interbanking
 */

Route::get('caja/interbanking', 'Caja\InterbankingController@index')->name('interbanking');
// Movimientos Interbanking (JSON): GET .../caja/interbanking/movimientos?empresa_id=&account_number=&bank_number=011&movement_type=dia — ver PHPDoc en InterbankingController::movimientos y config/interbanking.php
Route::get('caja/interbanking/movimientos', 'Caja\InterbankingController@movimientos')->name('interbanking_movimientos');
Route::get('caja/interbanking/transferencias', 'Caja\InterbankingController@transferencias')->name('interbanking_transferencias');
Route::post('caja/interbanking/transferencias/detalle', 'Caja\InterbankingController@detalleTransferenciaApi')->name('interbanking_transferencia_detalle_api');
Route::post('caja/interbanking/transferencias-persistidas/sincronizar', 'Caja\InterbankingTransferenciaHistoricoController@sincronizar')->name('interbanking_transferencias_sincronizar');
Route::get('caja/interbanking/transferencias-persistidas/{id}/comprobante', 'Caja\InterbankingTransferenciaHistoricoController@comprobante')->name('interbanking_transferencia_comprobante');
Route::get('caja/interbanking/transferencias-persistidas/{id}/detalle', 'Caja\InterbankingTransferenciaHistoricoController@detalle')->name('interbanking_transferencia_detalle');
Route::get('caja/interbanking/transferencias-persistidas/{formato}', 'Caja\InterbankingTransferenciaHistoricoController@exportar')->name('lista_interbanking_transferencias_historicas');
Route::get('caja/interbanking/transferencias-persistidas', 'Caja\InterbankingTransferenciaHistoricoController@index')->name('interbanking_transferencias_persistidas');
Route::post('caja/interbanking/movimientos-persistidos/sincronizar', 'Caja\InterbankingMovimientoHistoricoController@sincronizar')->name('interbanking_movimientos_sincronizar');
Route::get('caja/interbanking/movimientos-persistidos/{formato}', 'Caja\InterbankingMovimientoHistoricoController@exportar')->name('lista_interbanking_movimientos_historicos');
Route::get('caja/interbanking/movimientos-persistidos', 'Caja\InterbankingMovimientoHistoricoController@index')->name('interbanking_movimientos_persistidos');
Route::get('caja/interbanking/saldos-historicos/{formato}', 'Caja\InterbankingSaldoHistoricoController@exportar')->name('lista_interbanking_saldos_historicos');
Route::get('caja/interbanking/saldos-historicos', 'Caja\InterbankingSaldoHistoricoController@index')->name('interbanking_saldos_historicos');

// Modulo de compras

/*
 * Condiciones de pago
 */

Route::get('compras/condicionpago', 'Compras\CondicionpagoController@index')->name('condicionpago');
Route::get('compras/condicionpago/crear', 'Compras\CondicionpagoController@crear')->name('crear_condicionpago');
Route::post('compras/condicionpago', 'Compras\CondicionpagoController@guardar')->name('guardar_condicionpago');
Route::get('compras/condicionpago/{id}/editar', 'Compras\CondicionpagoController@editar')->name('editar_condicionpago');
Route::put('compras/condicionpago/{id}', 'Compras\CondicionpagoController@actualizar')->name('actualizar_condicionpago');
Route::delete('compras/condicionpago/{id}', 'Compras\CondicionpagoController@eliminar')->name('eliminar_condicionpago');

/*
 * Condiciones de compra
 */

Route::get('compras/condicioncompra', 'Compras\CondicioncompraController@index')->name('condicioncompra');
Route::get('compras/condicioncompra/crear', 'Compras\CondicioncompraController@crear')->name('crear_condicioncompra');
Route::post('compras/condicioncompra', 'Compras\CondicioncompraController@guardar')->name('guardar_condicioncompra');
Route::get('compras/condicioncompra/{id}/editar', 'Compras\CondicioncompraController@editar')->name('editar_condicioncompra');
Route::put('compras/condicioncompra/{id}', 'Compras\CondicioncompraController@actualizar')->name('actualizar_condicioncompra');
Route::delete('compras/condicioncompra/{id}', 'Compras\CondicioncompraController@eliminar')->name('eliminar_condicioncompra');

/*
 * Condiciones de entrega
 */

Route::get('compras/condicionentrega', 'Compras\CondicionentregaController@index')->name('condicionentrega');
Route::get('compras/condicionentrega/crear', 'Compras\CondicionentregaController@crear')->name('crear_condicionentrega');
Route::post('compras/condicionentrega', 'Compras\CondicionentregaController@guardar')->name('guardar_condicionentrega');
Route::get('compras/condicionentrega/{id}/editar', 'Compras\CondicionentregaController@editar')->name('editar_condicionentrega');
Route::put('compras/condicionentrega/{id}', 'Compras\CondicionentregaController@actualizar')->name('actualizar_condicionentrega');
Route::delete('compras/condicionentrega/{id}', 'Compras\CondicionentregaController@eliminar')->name('eliminar_condicionentrega');

/*
 * Tipos de empresa
 */

Route::get('compras/tipoempresa', 'Compras\TipoempresaController@index')->name('tipoempresa');
Route::get('compras/tipoempresa/crear', 'Compras\TipoempresaController@crear')->name('crear_tipoempresa');
Route::post('compras/tipoempresa', 'Compras\TipoempresaController@guardar')->name('guardar_tipoempresa');
Route::get('compras/tipoempresa/{id}/editar', 'Compras\TipoempresaController@editar')->name('editar_tipoempresa');
Route::put('compras/tipoempresa/{id}', 'Compras\TipoempresaController@actualizar')->name('actualizar_tipoempresa');
Route::delete('compras/tipoempresa/{id}', 'Compras\TipoempresaController@eliminar')->name('eliminar_tipoempresa');

/*
 * Tipos de servicio de proveedor
 */

Route::get('compras/tiposervicio_proveedor', 'Compras\Tiposervicio_ProveedorController@index')->name('tiposervicio_proveedor');
Route::get('compras/tiposervicio_proveedor/crear', 'Compras\Tiposervicio_ProveedorController@crear')->name('crear_tiposervicio_proveedor');
Route::post('compras/tiposervicio_proveedor', 'Compras\Tiposervicio_ProveedorController@guardar')->name('guardar_tiposervicio_proveedor');
Route::get('compras/tiposervicio_proveedor/{id}/editar', 'Compras\Tiposervicio_ProveedorController@editar')->name('editar_tiposervicio_proveedor');
Route::put('compras/tiposervicio_proveedor/{id}', 'Compras\Tiposervicio_ProveedorController@actualizar')->name('actualizar_tiposervicio_proveedor');
Route::delete('compras/tiposervicio_proveedor/{id}', 'Compras\Tiposervicio_ProveedorController@eliminar')->name('eliminar_tiposervicio_proveedor');

/*
* Retenciones de ganancia
*/

Route::get('compras/retencionganancia', 'Compras\RetenciongananciaController@index')->name('retencionganancia');
Route::get('compras/retencionganancia/crear', 'Compras\RetenciongananciaController@crear')->name('crear_retencionganancia');
Route::post('compras/retencionganancia', 'Compras\RetenciongananciaController@guardar')->name('guardar_retencionganancia');
Route::get('compras/retencionganancia/{id}/editar', 'Compras\RetenciongananciaController@editar')->name('editar_retencionganancia');
Route::put('compras/retencionganancia/{id}', 'Compras\RetenciongananciaController@actualizar')->name('actualizar_retencionganancia');
Route::delete('compras/retencionganancia/{id}', 'Compras\RetenciongananciaController@eliminar')->name('eliminar_retencionganancia');

/*
 * Retenciones de iva
 */

Route::get('compras/retencioniva', 'Compras\RetencionivaController@index')->name('retencioniva');
Route::get('compras/retencioniva/crear', 'Compras\RetencionivaController@crear')->name('crear_retencioniva');
Route::post('compras/retencioniva', 'Compras\RetencionivaController@guardar')->name('guardar_retencioniva');
Route::get('compras/retencioniva/{id}/editar', 'Compras\RetencionivaController@editar')->name('editar_retencioniva');
Route::put('compras/retencioniva/{id}', 'Compras\RetencionivaController@actualizar')->name('actualizar_retencioniva');
Route::delete('compras/retencioniva/{id}', 'Compras\RetencionivaController@eliminar')->name('eliminar_retencioniva');

/*
 * Retenciones de suss
 */

Route::get('compras/retencionsuss', 'Compras\RetencionsussController@index')->name('retencionsuss');
Route::get('compras/retencionsuss/crear', 'Compras\RetencionsussController@crear')->name('crear_retencionsuss');
Route::post('compras/retencionsuss', 'Compras\RetencionsussController@guardar')->name('guardar_retencionsuss');
Route::get('compras/retencionsuss/{id}/editar', 'Compras\RetencionsussController@editar')->name('editar_retencionsuss');
Route::put('compras/retencionsuss/{id}', 'Compras\RetencionsussController@actualizar')->name('actualizar_retencionsuss');
Route::delete('compras/retencionsuss/{id}', 'Compras\RetencionsussController@eliminar')->name('eliminar_retencionsuss');

/*
 * Retenciones de IIBB
 */

Route::get('compras/retencionIIBB', 'Compras\RetencionIIBBController@index')->name('retencionIIBB');
Route::get('compras/retencionIIBB/crear', 'Compras\RetencionIIBBController@crear')->name('crear_retencionIIBB');
Route::post('compras/retencionIIBB', 'Compras\RetencionIIBBController@guardar')->name('guardar_retencionIIBB');
Route::get('compras/retencionIIBB/{id}/editar', 'Compras\RetencionIIBBController@editar')->name('editar_retencionIIBB');
Route::put('compras/retencionIIBB/{id}', 'Compras\RetencionIIBBController@actualizar')->name('actualizar_retencionIIBB');
Route::delete('compras/retencionIIBB/{id}', 'Compras\RetencionIIBBController@eliminar')->name('eliminar_retencionIIBB');

/*
 * Tipo de suspension de proveedores
 */

Route::get('compras/tiposuspensionproveedor', 'Compras\TiposuspensionproveedorController@index')->name('tiposuspensionproveedor');
Route::get('compras/tiposuspensionproveedor/crear', 'Compras\TiposuspensionproveedorController@crear')->name('crear_tiposuspensionproveedor');
Route::post('compras/tiposuspensionproveedor', 'Compras\TiposuspensionproveedorController@guardar')->name('guardar_tiposuspensionproveedor');
Route::get('compras/tiposuspensionproveedor/{id}/editar', 'Compras\TiposuspensionproveedorController@editar')->name('editar_tiposuspensionproveedor');
Route::put('compras/tiposuspensionproveedor/{id}', 'Compras\TiposuspensionproveedorController@actualizar')->name('actualizar_tiposuspensionproveedor');
Route::delete('compras/tiposuspensionproveedor/{id}', 'Compras\TiposuspensionproveedorController@eliminar')->name('eliminar_tiposuspensionproveedor');

/*
 * Sector legajo compra
 */

Route::get('compras/sector_legajocompra', 'Compras\SectorLegajocompraController@index')->name('consultar_sector_legajocompra');
Route::get('compras/sector_legajocompra/crear', 'Compras\SectorLegajocompraController@crear')->name('crear_sector_legajocompra');
Route::post('compras/sector_legajocompra', 'Compras\SectorLegajocompraController@guardar')->name('guardar_sector_legajocompra');
Route::get('compras/sector_legajocompra/{id}/editar', 'Compras\SectorLegajocompraController@editar')->name('editar_sector_legajocompra');
Route::put('compras/sector_legajocompra/{id}', 'Compras\SectorLegajocompraController@actualizar')->name('actualizar_sector_legajocompra');
Route::delete('compras/sector_legajocompra/{id}', 'Compras\SectorLegajocompraController@eliminar')->name('eliminar_sector_legajocompra');

/*
 * Columnas de iva compras
 */

Route::get('compras/columna_ivacompra', 'Compras\Columna_IvacompraController@index')->name('columna_ivacompra');
Route::get('compras/columna_ivacompra/crear', 'Compras\Columna_IvacompraController@crear')->name('crear_columna_ivacompra');
Route::post('compras/columna_ivacompra', 'Compras\Columna_IvacompraController@guardar')->name('guardar_columna_ivacompra');
Route::get('compras/columna_ivacompra/{id}/editar', 'Compras\Columna_IvacompraController@editar')->name('editar_columna_ivacompra');
Route::put('compras/columna_ivacompra/{id}', 'Compras\Columna_IvacompraController@actualizar')->name('actualizar_columna_ivacompra');
Route::delete('compras/columna_ivacompra/{id}', 'Compras\Columna_IvacompraController@eliminar')->name('eliminar_columna_ivacompra');

/*
 * Conceptos de iva compras
 */

Route::get('compras/concepto_ivacompra', 'Compras\Concepto_IvacompraController@index')->name('concepto_ivacompra');
Route::get('compras/concepto_ivacompra/crear', 'Compras\Concepto_IvacompraController@crear')->name('crear_concepto_ivacompra');
Route::post('compras/concepto_ivacompra', 'Compras\Concepto_IvacompraController@guardar')->name('guardar_concepto_ivacompra');
Route::get('compras/concepto_ivacompra/{id}/editar', 'Compras\Concepto_IvacompraController@editar')->name('editar_concepto_ivacompra');
Route::put('compras/concepto_ivacompra/{id}', 'Compras\Concepto_IvacompraController@actualizar')->name('actualizar_concepto_ivacompra');
Route::delete('compras/concepto_ivacompra/{id}', 'Compras\Concepto_IvacompraController@eliminar')->name('eliminar_concepto_ivacompra');

/*
 * Tipo de transaccion de compras
 */

Route::get('compras/tipotransaccion_compra', 'Compras\Tipotransaccion_CompraController@index')->name('tipotransaccion_compra');
Route::get('compras/tipotransaccion_compra/crear', 'Compras\Tipotransaccion_CompraController@crear')->name('crear_tipotransaccion_compra');
Route::post('compras/tipotransaccion_compra', 'Compras\Tipotransaccion_CompraController@guardar')->name('guardar_tipotransaccion_compra');
Route::get('compras/tipotransaccion_compra/{id}/editar', 'Compras\Tipotransaccion_CompraController@editar')->name('editar_tipotransaccion_compra');
Route::put('compras/tipotransaccion_compra/{id}', 'Compras\Tipotransaccion_CompraController@actualizar')->name('actualizar_tipotransaccion_compra');
Route::delete('compras/tipotransaccion_compra/{id}', 'Compras\Tipotransaccion_CompraController@eliminar')->name('eliminar_tipotransaccion_compra');

/*
 * Proveedores
 */

Route::get('compras/proveedor', 'Compras\ProveedorController@index')->name('proveedor');
Route::get('compras/proveedor/crear/{tipoalta?}', 'Compras\ProveedorController@crear')->name('crear_proveedor');
Route::post('compras/proveedor', 'Compras\ProveedorController@guardar')->name('guardar_proveedor');
Route::post('compras/proveedorprovisorio', 'Compras\ProveedorController@guardarClienteProvisorio')->name('guardar_proveedor_provisorio');
Route::get('compras/proveedor/{id}/editar', 'Compras\ProveedorController@editar')->name('editar_proveedor');
Route::post('compras/proveedor/{id}/validar-arca-padron', 'Compras\ProveedorController@validarArcaPadron')->name('validar_proveedor_arca_padron');
Route::post('compras/proveedor/{id}/validar-arca-apoc', 'Compras\ProveedorController@validarArcaApoc')->name('validar_proveedor_arca_apoc');
Route::put('compras/proveedor/{id}', 'Compras\ProveedorController@actualizar')->name('actualizar_proveedor');
Route::delete('compras/proveedor/{id}', 'Compras\ProveedorController@eliminar')->name('eliminar_proveedor');

Route::post('compras/proveedor/consultaproveedor', 'Compras\ProveedorController@consultaProveedor')->name('consulta_proveedor');
Route::get('compras/leerproveedor/{proveedor_id}', 'Compras\ProveedorController@leeProveedor')->name('leer_proveedor');
Route::get('compras/leerproveedorporcodigo/{codigo}', 'Compras\ProveedorController@leeProveedorPorCodigo')->name('leer_proveedor_por_codigo');
Route::get('compras/listaproveedor/{formato?}/{busqueda?}', 'Compras\ProveedorController@listar')->name('lista_proveedor');

Route::get('compras/listarcuentacorrienteproveedor/{id}', 'Compras\ProveedorController@listarCuentaCorriente')->name('listar_cuentacorriente_proveedor');
Route::get('compras/proveedor/editacuentacorriente/{id}', 'Compras\ProveedorController@editarCuentaCorriente')->name('editar_cuentacorriente_proveedor');
Route::get('compras/listarencuestaproveedor/{id}', 'Compras\ProveedorController@listarEncuesta')->name('listar_encuesta_proveedor');
Route::get('compras/listarrequisicionproveedor/{id}', 'Compras\ProveedorController@listarRequisicion')->name('listar_requisicion_proveedor');
Route::get('compras/listar_ordencompra_proveedor/{id}', 'Compras\ProveedorController@listarOrdencompra')->name('listar_ordencompra_proveedor');

Route::get('compras/genera_proveedor_encuesta/{proveedor_id}/{encuesta_id}/{origen}/{hash}', 'Compras\ProveedorController@generarEncuesta')->name('generar_proveedor_encuesta');
Route::post('compras/guardar_proveedor_encuesta', 'Compras\ProveedorController@guardarEncuesta')->name('guardar_proveedor_encuesta');

/*
 * Cuenta corriente de proveedores
 */

Route::get('compras/proveedor/leercuentacorrienteaplicacion/{id}', 'Compras\ProveedorController@leerCuentaCorrienteAplicacion')->name('leer_cuentacorriente_aplicacion_proveedor');

/*
 * Precarga de comprobantes de proveedores
 */

Route::get('compras/precarga_comprobante_proveedor', 'Compras\Precarga_Comprobante_ProveedorController@index')->name('precarga_comprobante_proveedor');
Route::post('compras/precarga_comprobante_proveedor/pdf-ia/preview', 'Compras\Precarga_Comprobante_ProveedorController@previewPdfIa')->name('precarga_comprobante_proveedor_pdf_ia_preview');
Route::post('compras/precarga_comprobante_proveedor/pdf-ia/resolver-oc', 'Compras\Precarga_Comprobante_ProveedorController@resolverOcPdfIa')->name('precarga_comprobante_proveedor_pdf_ia_resolver_oc');
Route::post('compras/precarga_comprobante_proveedor/pdf-ia/confirmar', 'Compras\Precarga_Comprobante_ProveedorController@confirmarPdfIa')->name('precarga_comprobante_proveedor_pdf_ia_confirmar');
Route::get('compras/precarga_comprobante_proveedor/{id}/factura-pdf', 'Compras\Precarga_Comprobante_ProveedorController@verFacturaPdf')->name('precarga_comprobante_proveedor_factura_pdf');
Route::post('compras/precarga_comprobante_proveedor/{id}/generar-comprobante', 'Compras\Comprobante_ProveedorController@generarDesdePrecarga')->name('generar_comprobante_desde_precarga');
Route::get('compras/precarga_comprobante_proveedor/crear', 'Compras\Precarga_Comprobante_ProveedorController@crear')->name('crear_precarga_comprobante_proveedor');
Route::post('compras/precarga_comprobante_proveedor', 'Compras\Precarga_Comprobante_ProveedorController@guardar')->name('guardar_precarga_comprobante_proveedor');
Route::get('compras/precarga_comprobante_proveedor/{id}/editar', 'Compras\Precarga_Comprobante_ProveedorController@editar')->name('editar_precarga_comprobante_proveedor');
Route::put('compras/precarga_comprobante_proveedor/{id}', 'Compras\Precarga_Comprobante_ProveedorController@actualizar')->name('actualizar_precarga_comprobante_proveedor');
Route::delete('compras/precarga_comprobante_proveedor/{id}', 'Compras\Precarga_Comprobante_ProveedorController@eliminar')->name('eliminar_precarga_comprobante_proveedor');

Route::get('compras/lista_precarga_comprobante_proveedor/{formato?}/{busqueda?}', 'Compras\Precarga_Comprobante_ProveedorController@listar')->name('lista_precarga_comprobante_proveedor');

Route::get('compras/comprobante-proveedor', 'Compras\Comprobante_ProveedorController@index')->name('comprobante_proveedor');
Route::get('compras/comprobante-proveedor/opciones-carga', 'Compras\Comprobante_ProveedorController@opcionesCarga')->name('comprobante_proveedor_opciones_carga');
Route::get('compras/comprobante-proveedor/resolver-oc', 'Compras\Comprobante_ProveedorController@resolverOrdencompraParaAlta')->name('comprobante_proveedor_resolver_oc');
Route::get('compras/lista_comprobante_proveedor/{formato?}/{busqueda?}', 'Compras\Comprobante_ProveedorController@listar')->name('lista_comprobante_proveedor');
Route::get('compras/comprobante-proveedor/crear', 'Compras\Comprobante_ProveedorController@crear')->name('crear_comprobante_proveedor');
Route::post('compras/comprobante-proveedor', 'Compras\Comprobante_ProveedorController@guardar')->name('guardar_comprobante_proveedor');
Route::get('compras/comprobante-proveedor/{id}/editar', 'Compras\Comprobante_ProveedorController@editar')->name('editar_comprobante_proveedor');
Route::put('compras/comprobante-proveedor/{id}', 'Compras\Comprobante_ProveedorController@actualizar')->name('actualizar_comprobante_proveedor');
Route::delete('compras/comprobante-proveedor/{id}', 'Compras\Comprobante_ProveedorController@eliminar')->name('eliminar_comprobante_proveedor');
Route::match(['get', 'post'], 'compras/comprobante-proveedor/preview-asiento', 'Compras\Comprobante_ProveedorController@previewAsientoContable')->name('preview_asiento_comprobante_proveedor_nuevo');
Route::match(['get', 'post'], 'compras/comprobante-proveedor/{id}/preview-asiento', 'Compras\Comprobante_ProveedorController@previewAsientoContable')->name('preview_asiento_comprobante_proveedor');
Route::post('compras/comprobante-proveedor/{id}/contabilizar', 'Compras\Comprobante_ProveedorController@contabilizar')->name('contabilizar_comprobante_proveedor');
Route::post('compras/comprobante-proveedor/validar-proveedor-arca', 'Compras\Comprobante_ProveedorController@validarProveedorArcaPadron')->name('comprobante_proveedor_validar_proveedor_arca');
Route::post('compras/comprobante-proveedor/validar-proveedor-arca-apoc', 'Compras\Comprobante_ProveedorController@validarProveedorArcaApoc')->name('comprobante_proveedor_validar_proveedor_arca_apoc');
Route::get('compras/comprobante-proveedor/{id}/factura-pdf', 'Compras\Comprobante_ProveedorController@verFacturaPdf')->name('comprobante_proveedor_factura_pdf');
Route::get('compras/comprobante-proveedor/{id}/archivo/{archivo}', 'Compras\Comprobante_ProveedorController@descargarArchivo')->name('comprobante_proveedor_archivo');

/*
 * Tabla de encuestas
 */

Route::get('compras/encuesta', 'Compras\EncuestaController@index')->name('consultar_encuesta');
Route::get('compras/encuesta/crear', 'Compras\EncuestaController@crear')->name('crear_encuesta');
Route::post('compras/encuesta', 'Compras\EncuestaController@guardar')->name('guardar_encuesta');
Route::get('compras/encuesta/{id}/editar', 'Compras\EncuestaController@editar')->name('editar_encuesta');
Route::put('compras/encuesta/{id}', 'Compras\EncuestaController@actualizar')->name('actualizar_encuesta');
Route::delete('compras/encuesta/{id}', 'Compras\EncuestaController@eliminar')->name('eliminar_encuesta');

/*
 * Requisiciones
 */

Route::post('compras/requisicion/{id}/confirmar', 'Compras\RequisicionController@confirmar')->name('confirmar_requisicion');
Route::delete('compras/requisicion/{id}/provisorio', 'Compras\RequisicionController@eliminarProvisorio')->name('eliminar_requisicion_provisorio');
Route::get('compras/requisicion', 'Compras\RequisicionController@index')->name('consultar_requisicion');
Route::get('compras/requisicion-reporte', 'Compras\RequisicionReporteController@index')->name('reporte_requisicion_compras');
Route::get('compras/listar-requisicion-reporte/{formato?}', 'Compras\RequisicionReporteController@exportar')->name('listar_reporte_requisicion_compras');
Route::get('compras/requisicion/crear', 'Compras\RequisicionController@crear')->name('crear_requisicion');
Route::post('compras/requisicion', 'Compras\RequisicionController@guardar')->name('guardar_requisicion');
Route::get('compras/requisicion/{id}/editar', 'Compras\RequisicionController@editar')->name('editar_requisicion');
Route::get('compras/requisicion/{id}/imprimir-pdf', 'Compras\RequisicionController@imprimirPdf')->name('imprimir_pdf_requisicion');
Route::get('compras/requisicion/{id}/comprobantes-asociados', 'Compras\RequisicionController@comprobantesAsociados')->name('requisicion_comprobantes_asociados');
Route::get('compras/requisicion/{id}/archivo/{archivo}', 'Compras\RequisicionController@descargarArchivo')->name('requisicion_archivo');
Route::get('compras/requisicion/{requisicion}/presupuestos/{presupuesto}/pdf', 'Compras\RequisicionPresupuestoController@pdfPresupuesto')->name('requisicion_presupuesto_pdf');
Route::get('compras/requisicion/{requisicion}/presupuestos/{presupuesto}/imprimir', 'Compras\RequisicionPresupuestoController@formularioImpresionPresupuesto')->name('requisicion_presupuesto_impresion');
Route::get('compras/requisicion/{requisicion}/presupuestos/{presupuesto}/archivo/{archivo}/ver', 'Compras\RequisicionPresupuestoController@verArchivo')->name('requisicion_presupuesto_archivo_ver');
Route::get('compras/requisicion/{requisicion}/presupuestos/{presupuesto}', 'Compras\RequisicionPresupuestoController@show')->name('requisicion_presupuesto_show');
Route::post('compras/requisicion/{requisicion}/presupuestos', 'Compras\RequisicionPresupuestoController@store')->name('requisicion_presupuesto_store');
Route::put('compras/requisicion/{requisicion}/presupuestos/{presupuesto}', 'Compras\RequisicionPresupuestoController@update')->name('requisicion_presupuesto_update');
Route::delete('compras/requisicion/{requisicion}/presupuestos/{presupuesto}', 'Compras\RequisicionPresupuestoController@destroy')->name('requisicion_presupuesto_destroy');
Route::get('compras/requisicion/{requisicion}/presupuestos', 'Compras\RequisicionPresupuestoController@index')->name('requisicion_presupuestos_index');
Route::get('compras/requisicion/{id}/firmantes-retome-arbol', 'Compras\RequisicionController@firmantesRetomeArbol')->name('firmantes_retome_arbol_requisicion');
Route::post('compras/requisicion/{id}/enviar-arbol-aprobacion', 'Compras\RequisicionController@enviarArbolAprobacion')->name('enviar_arbol_requisicion');
Route::post('compras/requisicion/{id}/volver-compras', 'Compras\RequisicionController@volverCompras')->name('volver_compras_requisicion');
Route::put('compras/requisicion/{id}', 'Compras\RequisicionController@actualizar')->name('actualizar_requisicion');
Route::delete('compras/requisicion/{id}', 'Compras\RequisicionController@eliminar')->name('eliminar_requisicion');
Route::get('compras/listarequisicion/{formato?}/{busqueda?}', 'Compras\RequisicionController@listar')->name('listar_requisicion');
Route::get('compras/leer_historia_requisicion/{requisicion_id}', 'Compras\RequisicionController@leerHistoriaRequisicion')->name('lee_historia_requisicion');
Route::get('compras/requisicion/aviso-arbol-grabacion', 'Compras\RequisicionController@avisoArbolGrabacion')->name('requisicion_aviso_arbol_grabacion');
Route::get('compras/requisicion/consulta-listas-precio-articulo', 'Compras\RequisicionController@consultaListasPrecioArticulo')->name('requisicion_consulta_listas_precio_articulo');
Route::get('compras/requisicion/precio-ultima-compra-articulo', 'Compras\RequisicionController@precioUltimaCompraArticulo')->name('requisicion_precio_ultima_compra_articulo');
Route::post('compras/requisicion/calcular-totales', 'Compras\RequisicionController@calcularTotales')->name('requisicion_calcular_totales');
Route::post('compras/requisicion/consulta_partidagasto', 'Compras\RequisicionController@consultaPartidagastoRequisicion')->name('consulta_partidagasto_requisicion');
Route::get('compras/requisicion/leer_partidagasto/{partidagasto_id}', 'Compras\RequisicionController@leerPartidagastoRequisicionPorId')->name('leer_partidagasto_requisicion');
Route::get('compras/requisicion/visualizar/{id}/{hash}', 'Compras\RequisicionController@visualizar')->name('visualizar_requisicion');
Route::get('compras/requisicion/soloconsulta/{id}', 'Compras\RequisicionController@soloConsulta')->name('solo_consulta_requisicion');
Route::get('compras/requisicion/{id}/wizard-multiples-oc', 'Compras\OrdencompraController@wizardMultiplesDesdeRequisicion')->name('requisicion_wizard_multiples_oc');
Route::post('compras/requisicion/{id}/generar-multiples-oc', 'Compras\OrdencompraController@generarMultiplesOcDesdeRequisicion')->name('requisicion_generar_multiples_oc');

/*
 * Cumplimiento de requisiciones de compra (genera transferencia de mercadería)
 */
Route::get('compras/cumplir-requisicion-compra', 'Compras\CumplirRequisicionCompraController@index')->name('cumplir_requisicion_compra');
Route::get('compras/cumplir-requisicion-compra/crear', 'Compras\CumplirRequisicionCompraController@crear')->name('crear_cumplir_requisicion_compra');
Route::get('compras/cumplir-requisicion-compra/consulta', 'Compras\CumplirRequisicionCompraController@consultaRequisicion')->name('consulta_requisicion_compra_cumple');
Route::get('compras/cumplir-requisicion-compra/datos/{id}', 'Compras\CumplirRequisicionCompraController@datosRequisicion')->name('datos_requisicion_compra_cumple');
Route::get('compras/cumplir-requisicion-compra/saldo-articulo', 'Compras\CumplirRequisicionCompraController@saldoArticuloDeposito')->name('cumplir_requisicion_compra_saldo_articulo');
Route::get('compras/cumplir-requisicion-compra/pdf/{token?}', 'Compras\CumplirRequisicionCompraController@imprimirPdf')->name('pdf_cumplir_requisicion_compra');
Route::get('compras/lista-cumplir-requisicion-compra/{formato?}', 'Compras\CumplirRequisicionCompraController@listar')->name('lista_cumplir_requisicion_compra');
Route::post('compras/cumplir-requisicion-compra', 'Compras\CumplirRequisicionCompraController@grabar')->name('grabar_cumplir_requisicion_compra');
Route::get('compras/cumplir-requisicion-compra/{id}/imprimir-pdf', 'Compras\CumplirRequisicionCompraController@imprimirCumplimientoPdf')->name('imprimir_pdf_cumplir_requisicion_compra');
Route::put('compras/cumplir-requisicion-compra/{id}', 'Compras\CumplirRequisicionCompraController@actualizar')->name('actualizar_cumplir_requisicion_compra');
Route::post('compras/cumplir-requisicion-compra/{id}/revertir', 'Compras\CumplirRequisicionCompraController@revertir')->name('revertir_cumplir_requisicion_compra');
Route::get('compras/cumplir-requisicion-compra/{id}', 'Compras\CumplirRequisicionCompraController@consultar')->name('consultar_cumplir_requisicion_compra');

/*
 * Listas de precio proveedor
 */
Route::get('compras/listaprecio_proveedor', 'Compras\Listaprecio_ProveedorController@index')->name('consultar_listaprecio_proveedor');
Route::get('compras/listar_listaprecio_proveedor/{formato?}/{busqueda?}', 'Compras\Listaprecio_ProveedorController@listar')->name('listar_listaprecio_proveedor');
Route::get('compras/listaprecio_proveedor/crear', 'Compras\Listaprecio_ProveedorController@crear')->name('crear_listaprecio_proveedor');
Route::post('compras/listaprecio_proveedor', 'Compras\Listaprecio_ProveedorController@guardar')->name('guardar_listaprecio_proveedor');
Route::get('compras/listaprecio_proveedor/{id}/editar', 'Compras\Listaprecio_ProveedorController@editar')->name('editar_listaprecio_proveedor')->middleware('modo.consulta');
Route::put('compras/listaprecio_proveedor/{id}', 'Compras\Listaprecio_ProveedorController@actualizar')->name('actualizar_listaprecio_proveedor')->middleware('modo.consulta');
Route::delete('compras/listaprecio_proveedor/{id}', 'Compras\Listaprecio_ProveedorController@eliminar')->name('eliminar_listaprecio_proveedor');
Route::post('compras/listaprecio_proveedor/{id}/cambiar_estado', 'Compras\Listaprecio_ProveedorController@cambiarEstado')->name('cambiar_estado_listaprecio_proveedor');
Route::get('compras/leer_historia_listaprecio_proveedor/{listaprecio_proveedor_id}', 'Compras\Listaprecio_ProveedorController@leerHistoria')->name('lee_historia_listaprecio_proveedor');
Route::post('compras/listaprecio_proveedor/{id}/importar_excel', 'Compras\Listaprecio_ProveedorController@importarExcel')->name('importar_excel_listaprecio_proveedor');

/*
 * Ordenes de Compra
 */

Route::get('compras/ordencompra', 'Compras\OrdencompraController@index')->name('consultar_ordencompra');
Route::get('compras/ordencompra/crear', 'Compras\OrdencompraController@crear')->name('crear_ordencompra');
Route::post('compras/ordencompra', 'Compras\OrdencompraController@guardar')->name('guardar_ordencompra');
Route::get('compras/ordencompra/{id}/editar', 'Compras\OrdencompraController@editar')->name('editar_ordencompra')->middleware('modo.consulta');
Route::put('compras/ordencompra/{id}', 'Compras\OrdencompraController@actualizar')->name('actualizar_ordencompra')->middleware('modo.consulta');
Route::delete('compras/ordencompra/{id}', 'Compras\OrdencompraController@eliminar')->name('eliminar_ordencompra');
Route::get('compras/listaordencompra/{formato?}/{busqueda?}', 'Compras\OrdencompraController@listar')->name('listar_ordencompra');
Route::get('compras/ordencompra/requisiciones-aprobadas', 'Compras\OrdencompraController@buscarRequisicionesAprobadas')->name('ordencompra_buscar_requisiciones');
Route::get('compras/ordencompra/plantilla-requisicion', 'Compras\OrdencompraController@plantillaRequisicion')->name('ordencompra_plantilla_requisicion');
Route::get('compras/ordencompra/opciones-precio-linea', 'Compras\OrdencompraController@opcionesPrecioLineaOc')->name('ordencompra_opciones_precio_linea');
Route::get('compras/ordencompra/cotizacion-moneda-fecha', 'Compras\OrdencompraController@cotizacionMonedaFecha')->name('ordencompra_cotizacion_moneda_fecha');
Route::post('compras/ordencompra/calcular-totales', 'Compras\OrdencompraController@calcularTotales')->name('ordencompra_calcular_totales');
Route::post('compras/ordencompra/sugerir-cuotas-condicionpago', 'Compras\OrdencompraController@sugerirCuotasCondicionpago')->name('ordencompra_sugerir_cuotas');
Route::get('compras/ordencompra/{id}/imprimir-pdf', 'Compras\OrdencompraController@imprimirPdf')->name('imprimir_pdf_ordencompra');
Route::get('compras/ordencompra/{id}/datos-envio-proveedor', 'Compras\OrdencompraController@datosEnvioProveedor')->name('ordencompra_datos_envio_proveedor');
Route::post('compras/ordencompra/{id}/enviar-proveedor', 'Compras\OrdencompraController@enviarProveedor')->name('ordencompra_enviar_proveedor');
Route::get('compras/ordencompra/{id}/archivo/{archivo}', 'Compras\OrdencompraController@descargarArchivo')->name('ordencompra_archivo');
Route::get('compras/ordencompra/{id}/historia-legajo', 'Compras\OrdencompraController@leerHistoriaLegajo')->name('ordencompra_historia_legajo');
Route::get('compras/ordencompra/{id}/historia-estados', 'Compras\OrdencompraController@leerHistoriaEstados')->name('ordencompra_historia_estados');
Route::get('compras/ordencompra/{id}/historia-precios', 'Compras\OrdencompraController@leerHistoriaPrecios')->name('ordencompra_historia_precios');
Route::get('compras/ordencompra/{id}/recepciones', 'Compras\OrdencompraController@leerRecepciones')->name('ordencompra_recepciones');
Route::post('compras/ordencompra/{id}/aplicar-precios-recepcion/{recepcion_id}', 'Compras\OrdencompraController@aplicarPreciosRecepcion')->name('ordencompra_aplicar_precios_recepcion');
Route::post('compras/ordencompra/{id}/aplicar-precios-solicitados-recepcion/{recepcion_id}', 'Compras\OrdencompraController@aplicarPreciosSolicitadosRecepcionBorrador')->name('ordencompra_aplicar_precios_solicitados_recepcion');
Route::get('compras/ordencompra/{id}/movimiento-aprobacion', 'Compras\OrdencompraController@leerMovimientoAprobacion')->name('ordencompra_movimiento_aprobacion');
Route::post('compras/ordencompra/{id}/cambiar-estado', 'Compras\OrdencompraController@cambiarEstado')->name('ordencompra_cambiar_estado');
Route::post('compras/ordencompra/{id}/reactivar', 'Compras\OrdencompraController@reactivarSuspendida')->name('ordencompra_reactivar');
Route::post('compras/ordencompra/{id}/revertir-cierre-lineas', 'Compras\OrdencompraController@revertirCierreLineas')->name('ordencompra_revertir_cierre_lineas');
Route::post('compras/ordencompra/{id}/cambiar-sector', 'Compras\OrdencompraController@cambiarSector')->name('ordencompra_cambiar_sector');
Route::get('compras/ordencompra/soloconsulta/{id}', 'Compras\OrdencompraController@soloConsulta')->name('solo_consulta_ordencompra');
Route::get('compras/ordencompra/visualizar/{id}/{hash}', 'Compras\OrdencompraController@visualizar')->name('visualizar_ordencompra');

/*
 * Centro de ayuda (manuales por módulo)
 */
Route::get('ayuda', 'AyudaController@index')->name('ayuda');

/*
 * Manual de usuario — Módulo Compras
 */
Route::get('compras/manual', 'Compras\ManualComprasController@index')->name('manual_compras');
Route::get('compras/manual/descargar-pdf', 'Compras\ManualComprasController@descargarPdf')->name('manual_compras_pdf');
Route::get('compras/manual/descargar-word', 'Compras\ManualComprasController@descargarWord')->name('manual_compras_word');

Route::get('ventas/gastronomia/manual', 'Ventas\ManualGastronomiaController@index')->name('manual_gastronomia');
Route::get('ventas/gastronomia/manual/descargar-pdf', 'Ventas\ManualGastronomiaController@descargarPdf')->name('manual_gastronomia_pdf');
Route::get('ventas/gastronomia/manual/descargar-word', 'Ventas\ManualGastronomiaController@descargarWord')->name('manual_gastronomia_word');

Route::get('ventas/gastronomia/maquinas-vending/manual', 'Ventas\ManualVendingController@index')->name('manual_vending');
Route::get('ventas/gastronomia/maquinas-vending/manual/descargar-pdf', 'Ventas\ManualVendingController@descargarPdf')->name('manual_vending_pdf');
Route::get('ventas/gastronomia/maquinas-vending/manual/descargar-word', 'Ventas\ManualVendingController@descargarWord')->name('manual_vending_word');

Route::get('ventas/gastronomia/canjes/manual', 'Ventas\ManualCanjesMarketingController@index')->name('manual_canjes_marketing');
Route::get('ventas/gastronomia/canjes/manual/descargar-pdf', 'Ventas\ManualCanjesMarketingController@descargarPdf')->name('manual_canjes_marketing_pdf');
Route::get('ventas/gastronomia/canjes/manual/descargar-word', 'Ventas\ManualCanjesMarketingController@descargarWord')->name('manual_canjes_marketing_word');

Route::get('ventas/manual', 'Ventas\ManualVentasController@index')->name('manual_ventas');
Route::get('ventas/manual/descargar-pdf', 'Ventas\ManualVentasController@descargarPdf')->name('manual_ventas_pdf');
Route::get('ventas/manual/descargar-word', 'Ventas\ManualVentasController@descargarWord')->name('manual_ventas_word');

/*
 * Manual de usuario — Recuento de inventario (Stock)
 */
Route::get('stock/recuento/manual', 'Stock\ManualStockController@index')->name('manual_stock');
Route::get('stock/recuento/manual/descargar-pdf', 'Stock\ManualStockController@descargarPdf')->name('manual_stock_pdf');
Route::get('stock/recuento/manual/descargar-word', 'Stock\ManualStockController@descargarWord')->name('manual_stock_word');
Route::get('stock/manual-recepcion-movstock', 'Stock\ManualRecepcionMovstockController@index')->name('manual_recepcion_movstock');
Route::get('stock/manual-recepcion-movstock/descargar-pdf', 'Stock\ManualRecepcionMovstockController@descargarPdf')->name('manual_recepcion_movstock_pdf');
Route::get('stock/manual-recepcion-movstock/descargar-word', 'Stock\ManualRecepcionMovstockController@descargarWord')->name('manual_recepcion_movstock_word');

/* Modulo receptivo */

/*
 * Tipo de servicio terrestre
 */

Route::get('receptivo/tiposervicioterrestre', 'Receptivo\TiposervicioterrestreController@index')->name('tiposervicioterrestre');
Route::get('receptivo/tiposervicioterrestre/crear', 'Receptivo\TiposervicioterrestreController@crear')->name('crear_tiposervicioterrestre');
Route::post('receptivo/tiposervicioterrestre', 'Receptivo\TiposervicioterrestreController@guardar')->name('guardar_tiposervicioterrestre');
Route::get('receptivo/tiposervicioterrestre/{id}/editar', 'Receptivo\TiposervicioterrestreController@editar')->name('editar_tiposervicioterrestre');
Route::put('receptivo/tiposervicioterrestre/{id}', 'Receptivo\TiposervicioterrestreController@actualizar')->name('actualizar_tiposervicioterrestre');
Route::delete('receptivo/tiposervicioterrestre/{id}', 'Receptivo\TiposervicioterrestreController@eliminar')->name('eliminar_tiposervicioterrestre');

/*
 * Servicio terrestre
 */

Route::get('receptivo/servicioterrestre', 'Receptivo\ServicioterrestreController@index')->name('servicioterrestre');
Route::get('receptivo/servicioterrestre/crear', 'Receptivo\ServicioterrestreController@crear')->name('crear_servicioterrestre');
Route::post('receptivo/servicioterrestre', 'Receptivo\ServicioterrestreController@guardar')->name('guardar_servicioterrestre');
Route::get('receptivo/servicioterrestre/{id}/editar', 'Receptivo\ServicioterrestreController@editar')->name('editar_servicioterrestre');
Route::put('receptivo/servicioterrestre/{id}', 'Receptivo\ServicioterrestreController@actualizar')->name('actualizar_servicioterrestre');
Route::delete('receptivo/servicioterrestre/{id}', 'Receptivo\ServicioterrestreController@eliminar')->name('eliminar_servicioterrestre');

Route::post('receptivo/servicioterrestre/consultaservicioterrestre', 'Receptivo\ServicioterrestreController@consultaServicioTerrestre')->name('consulta_servicioterrestre');
Route::get('receptivo/leerservicioterrestre/{codigoservicioterrestre}', 'Receptivo\ServicioterrestreController@leeServicioTerrestre')->name('leer_servicioterrestre');

/*
 * Servicios por proveedor
 */

Route::get('receptivo/proveedor_servicioterrestre', 'Receptivo\Proveedor_ServicioterrestreController@index')->name('proveedor_servicioterrestre');
Route::get('receptivo/proveedor_servicioterrestre/crear', 'Receptivo\Proveedor_ServicioterrestreController@crear')->name('crear_proveedor_servicioterrestre');
Route::post('receptivo/proveedor_servicioterrestre', 'Receptivo\Proveedor_ServicioterrestreController@guardar')->name('guardar_proveedor_servicioterrestre');
Route::get('receptivo/proveedor_servicioterrestre/{id}/editar', 'Receptivo\Proveedor_ServicioterrestreController@editar')->name('editar_proveedor_servicioterrestre');
Route::put('receptivo/proveedor_servicioterrestre/{id}', 'Receptivo\Proveedor_ServicioterrestreController@actualizar')->name('actualizar_proveedor_servicioterrestre');
Route::delete('receptivo/proveedor_servicioterrestre/{id}', 'Receptivo\Proveedor_ServicioterrestreController@eliminar')->name('eliminar_proveedor_servicioterrestre');

Route::get('receptivo/leercostoproveedor_servicioterrestre/{servicioterrestre_id}/{proveedor_id}', 'Receptivo\Proveedor_ServicioterrestreController@leeCosto')->name('leer_costo_proveedor_servicioterrestre');
Route::get('receptivo/leerproveedor_servicioterrestre/{servicioterrestre_id}', 'Receptivo\Proveedor_ServicioterrestreController@leeProveedor')->name('leer_proveedor_servicioterrestre');

/*
 * Idiomas
 */

Route::get('receptivo/idioma', 'Receptivo\IdiomaController@index')->name('idioma');
Route::get('receptivo/idioma/crear', 'Receptivo\IdiomaController@crear')->name('crear_idioma');
Route::post('receptivo/idioma', 'Receptivo\IdiomaController@guardar')->name('guardar_idioma');
Route::get('receptivo/idioma/{id}/editar', 'Receptivo\IdiomaController@editar')->name('editar_idioma');
Route::put('receptivo/idioma/{id}', 'Receptivo\IdiomaController@actualizar')->name('actualizar_idioma');
Route::delete('receptivo/idioma/{id}', 'Receptivo\IdiomaController@eliminar')->name('eliminar_idioma');

/*
 * Moviles
 */

Route::get('receptivo/movil', 'Receptivo\MovilController@index')->name('movil');
Route::get('receptivo/movil/crear', 'Receptivo\MovilController@crear')->name('crear_movil');
Route::post('receptivo/movil', 'Receptivo\MovilController@guardar')->name('guardar_movil');
Route::get('receptivo/movil/{id}/editar', 'Receptivo\MovilController@editar')->name('editar_movil');
Route::put('receptivo/movil/{id}', 'Receptivo\MovilController@actualizar')->name('actualizar_movil');
Route::delete('receptivo/movil/{id}', 'Receptivo\MovilController@eliminar')->name('eliminar_movil');

Route::post('receptivo/movil/consultamovil', 'Receptivo\MovilController@consultaMovil')->name('consulta_movil');
Route::get('receptivo/leermovil/{movil_id}', 'Receptivo\MovilController@leeMovil')->name('leer_movil');

/*
 * Guias
 */

Route::get('receptivo/guia', 'Receptivo\GuiaController@index')->name('guia');
Route::get('receptivo/guia/crear', 'Receptivo\GuiaController@crear')->name('crear_guia');
Route::post('receptivo/guia', 'Receptivo\GuiaController@guardar')->name('guardar_guia');
Route::get('receptivo/guia/{id}/editar', 'Receptivo\GuiaController@editar')->name('editar_guia');
Route::put('receptivo/guia/{id}', 'Receptivo\GuiaController@actualizar')->name('actualizar_guia');
Route::delete('receptivo/guia/{id}', 'Receptivo\GuiaController@eliminar')->name('eliminar_guia');

Route::post('receptivo/guia/consultaguia', 'Receptivo\GuiaController@consultaGuia')->name('consulta_guia');
Route::get('receptivo/leerguia/{guia_id}', 'Receptivo\GuiaController@leeguia')->name('leer_guia');
/*
 * Comisiones por servicio
 */

Route::get('receptivo/comision_servicioterrestre', 'Receptivo\Comision_ServicioterrestreController@index')->name('comision_servicioterrestre');
Route::get('receptivo/comision_servicioterrestre/crear', 'Receptivo\Comision_ServicioterrestreController@crear')->name('crear_comision_servicioterrestre');
Route::post('receptivo/comision_servicioterrestre', 'Receptivo\Comision_ServicioterrestreController@guardar')->name('guardar_comision_servicioterrestre');
Route::get('receptivo/comision_servicioterrestre/{id}/editar', 'Receptivo\Comision_ServicioterrestreController@editar')->name('editar_comision_servicioterrestre');
Route::put('receptivo/comision_servicioterrestre/{id}', 'Receptivo\Comision_ServicioterrestreController@actualizar')->name('actualizar_comision_servicioterrestre');
Route::delete('receptivo/comision_servicioterrestre/{id}', 'Receptivo\Comision_ServicioterrestreController@eliminar')->name('eliminar_comision_servicioterrestre');
Route::get('receptivo/leecomision/{formapago_id}/{tipocomision}/{servicioterrestre_id}', 'Receptivo\Comision_ServicioterrestreController@leeComision')->name('lee_comision');

Route::get('caja/leercomision_servicioterrestre/{servioterrestre_id}/{tipocomision}', 'Receptivo\Comision_ServicioterrestreController@leeComision_Servicioterrestre')->name('leer_comision_servicioterrestre');

/*
 * Reserva
 */

Route::get('receptivo/leereserva/{reserva}', 'Receptivo\ReservaController@leeReserva')->name('lee_reserva');
Route::get('receptivo/leereservaporidservicioterrestre/{reserva}/{servicioterrestre_id}', 'Receptivo\ReservaController@leeReservaPorIdServicioTerrestre')->name('lee_reserva_por_id_servicioterrestre');
Route::post('receptivo/reserva/consultareserva', 'Receptivo\ReservaController@consultaReserva')->name('consulta_reserva');

/*
 * Modulo de tickets
 */

/*
 * Turnos
 */

Route::get('ticket/turno_ticket', 'Ticket\Turno_TicketController@index')->name('consulta_turno_ticket');
Route::get('ticket/turno_ticket/crear', 'Ticket\Turno_TicketController@crear')->name('crea_turno_ticket');
Route::post('ticket/turno_ticket', 'Ticket\Turno_TicketController@guardar')->name('guarda_turno_ticket');
Route::get('ticket/turno_ticket/{id}/editar', 'Ticket\Turno_TicketController@editar')->name('edita_turno_ticket');
Route::put('ticket/turno_ticket/{id}', 'Ticket\Turno_TicketController@actualizar')->name('actualiza_turno_ticket');
Route::delete('ticket/turno_ticket/{id}', 'Ticket\Turno_TicketController@eliminar')->name('elimina_turno_ticket');

/*
 * Areas de destino
 */

Route::get('ticket/areadestino', 'Ticket\AreadestinoController@index')->name('consulta_areadestino');
Route::get('ticket/areadestino/crear', 'Ticket\AreadestinoController@crear')->name('crea_areadestino');
Route::post('ticket/areadestino', 'Ticket\AreadestinoController@guardar')->name('guarda_areadestino');
Route::get('ticket/areadestino/{id}/editar', 'Ticket\AreadestinoController@editar')->name('edita_areadestino');
Route::put('ticket/areadestino/{id}', 'Ticket\AreadestinoController@actualizar')->name('actualiza_areadestino');
Route::delete('ticket/areadestino/{id}', 'Ticket\AreadestinoController@eliminar')->name('elimina_areadestino');

/*
 * Tareas
 */

Route::get('ticket/tarea_ticket', 'Ticket\Tarea_TicketController@index')->name('consulta_tarea_ticket');
Route::get('ticket/tarea_ticket/crear', 'Ticket\Tarea_TicketController@crear')->name('crea_tarea_ticket');
Route::post('ticket/tarea_ticket', 'Ticket\Tarea_TicketController@guardar')->name('guarda_tarea_ticket');
Route::get('ticket/tarea_ticket/{id}/editar', 'Ticket\Tarea_TicketController@editar')->name('edita_tarea_ticket');
Route::put('ticket/tarea_ticket/{id}', 'Ticket\Tarea_TicketController@actualizar')->name('actualiza_tarea_ticket');
Route::delete('ticket/tarea_ticket/{id}', 'Ticket\Tarea_TicketController@eliminar')->name('elimina_tarea_ticket');

Route::post('ticket/consultatarea_ticket', 'Ticket\Tarea_TicketController@consultaTarea_Ticket')->name('consultar_tarea_ticket');
Route::get('ticket/leertarea_ticket/{tarea_ticket_id}', 'Ticket\Tarea_TicketController@leeTarea_Ticket')->name('leer_tarea_ticket');

/*
 * Sectores
 */

Route::get('ticket/sector_ticket', 'Ticket\Sector_TicketController@index')->name('consulta_sector_ticket');
Route::get('ticket/sector_ticket/crear', 'Ticket\Sector_TicketController@crear')->name('crea_sector_ticket');
Route::post('ticket/sector_ticket', 'Ticket\Sector_TicketController@guardar')->name('guarda_sector_ticket');
Route::get('ticket/sector_ticket/{id}/editar', 'Ticket\Sector_TicketController@editar')->name('edita_sector_ticket');
Route::put('ticket/sector_ticket/{id}', 'Ticket\Sector_TicketController@actualizar')->name('actualiza_sector_ticket');
Route::delete('ticket/sector_ticket/{id}', 'Ticket\Sector_TicketController@eliminar')->name('elimina_sector_ticket');

/*
 * Tecnicos
 */

Route::get('ticket/tecnico_ticket', 'Ticket\Tecnico_TicketController@index')->name('consulta_tecnico_ticket');
Route::get('ticket/tecnico_ticket/crear', 'Ticket\Tecnico_TicketController@crear')->name('crea_tecnico_ticket');
Route::post('ticket/tecnico_ticket', 'Ticket\Tecnico_TicketController@guardar')->name('guarda_tecnico_ticket');
Route::get('ticket/tecnico_ticket/{id}/editar', 'Ticket\Tecnico_TicketController@editar')->name('edita_tecnico_ticket');
Route::put('ticket/tecnico_ticket/{id}', 'Ticket\Tecnico_TicketController@actualizar')->name('actualiza_tecnico_ticket');
Route::delete('ticket/tecnico_ticket/{id}', 'Ticket\Tecnico_TicketController@eliminar')->name('elimina_tecnico_ticket');

Route::post('ticket/consultatecnico_ticket', 'Ticket\Tecnico_TicketController@consultaTecnico_Ticket')->name('consultar_tecnico_ticket');
Route::get('ticket/leertecnico_ticket/{tecnico_ticket_id}', 'Ticket\Tecnico_TicketController@leeTecnico_Ticket')->name('leer_tecnico_ticket');

/*
 * Categoria
 */

Route::get('ticket/categoria_ticket', 'Ticket\Categoria_TicketController@index')->name('consulta_categoria_ticket');
Route::get('ticket/categoria_ticket/crear', 'Ticket\Categoria_TicketController@crear')->name('crea_categoria_ticket');
Route::post('ticket/categoria_ticket', 'Ticket\Categoria_TicketController@guardar')->name('guarda_categoria_ticket');
Route::get('ticket/categoria_ticket/{id}/editar', 'Ticket\Categoria_TicketController@editar')->name('edita_categoria_ticket');
Route::put('ticket/categoria_ticket/{id}', 'Ticket\Categoria_TicketController@actualizar')->name('actualiza_categoria_ticket');
Route::delete('ticket/categoria_ticket/{id}', 'Ticket\Categoria_TicketController@eliminar')->name('elimina_categoria_ticket');

Route::post('ticket/consultacategoria_ticket', 'Ticket\Categoria_TicketController@consultaCategoria_Ticket')->name('consultar_categoria_ticket');
Route::get('ticket/leercategoria_ticket/{categoria_ticket_id}', 'Ticket\Categoria_TicketController@leeCategoria_Ticket')->name('leer_categoria_ticket');

Route::post('ticket/consultasubcategoria_ticket', 'Ticket\Categoria_TicketController@consultaSubCategoria_Ticket')->name('consultar_subcategoria_ticket');
Route::get('ticket/leersubcategoria_ticket/{subcategoria_ticket_id}', 'Ticket\Categoria_TicketController@leeSubCategoria_Ticket')->name('leer_subcategoria_ticket');

/*
 * Tickets
 */

Route::get('ticket/ticket', 'Ticket\TicketController@index')->name('consulta_ticket');
Route::get('ticket/ticket/crear', 'Ticket\TicketController@crear')->name('crea_ticket');
Route::post('ticket/ticket', 'Ticket\TicketController@guardar')->name('guarda_ticket');
Route::get('ticket/ticket/{id}/editar', 'Ticket\TicketController@editar')->name('edita_ticket');
Route::put('ticket/ticket/{id}', 'Ticket\TicketController@actualizar')->name('actualiza_ticket');
Route::delete('ticket/ticket/{id}', 'Ticket\TicketController@eliminar')->name('elimina_ticket');
Route::post('ticket/ticket/{ticketId}/tarea/{ticketTareaId}/comentario', 'Ticket\TicketController@guardarComentarioTarea')->name('guarda_comentario_tarea_ticket');
Route::get('ticket/listaticket/{formato?}/{busqueda?}', 'Ticket\TicketController@listar')->name('lista_ticket');

/*
 * Administracion de Tickets
 */

Route::get('ticket/administracion_ticket', 'Ticket\Administracion_TicketController@index')->name('consulta_administracion_ticket');
Route::get('ticket/administracion_ticket/crear', 'Ticket\Administracion_TicketController@crear')->name('crea_administracion_ticket');
Route::post('ticket/administracion_ticket', 'Ticket\Administracion_TicketController@guardar')->name('guarda_administracion_ticket');
Route::get('ticket/administracion_ticket/{id}/editar', 'Ticket\Administracion_TicketController@editar')->name('edita_administracion_ticket');
Route::put('ticket/administracion_ticket/{id}', 'Ticket\Administracion_TicketController@actualizar')->name('actualiza_administracion_ticket');
Route::delete('ticket/administracion_ticket/{id}', 'Ticket\Administracion_TicketController@eliminar')->name('elimina_administracion_ticket');
Route::get('ticket/listaadministracion_ticket/{formato?}/{busqueda?}', 'Ticket\Administracion_TicketController@listar')->name('lista_administracion_ticket');

Route::post('ticket/guardar_ticket_tarea_novedad', 'Ticket\Administracion_TicketController@guardarTicketTareaNovedad')->name('guarda_ticket_tarea_novedad');
Route::get('ticket/leer_ticket_tarea_novedad/{ticket_tarea_id}', 'Ticket\Administracion_TicketController@leerTicketTareaNovedad')->name('lee_ticket_tarea_novedad');
Route::get('ticket/leer_historia_ticket/{ticket_id}', 'Ticket\Administracion_TicketController@leerHistoriaTicket')->name('lee_historia_ticket');
Route::get('ticket/cambiar_tecnico/{ticket_tarea_id}/{tecnico_ticket_id}', 'Ticket\Administracion_TicketController@cambiarTecnico')->name('cambiar_tecnico');
Route::get('ticket/finalizar_tarea/{ticket_tarea_id}/{fechafinalizacion}/{tiempoinsumido}', 'Ticket\Administracion_TicketController@finalizarTarea')->name('finalizar_tarea');
Route::post('ticket/administracion_ticket/limpiafiltro', 'Ticket\Administracion_TicketController@limpiafiltro')->name('administracion_ticket_limpiafiltro');

/*
 * Salas
 */

Route::get('configuracion/sala', 'Configuracion\SalaController@index')->name('consulta_sala');
Route::get('configuracion/sala/crear', 'Configuracion\SalaController@crear')->name('crea_sala');
Route::post('configuracion/sala', 'Configuracion\SalaController@guardar')->name('guarda_sala');
Route::get('configuracion/sala/{id}/editar', 'Configuracion\SalaController@editar')->name('edita_sala');
Route::put('configuracion/sala/{id}', 'Configuracion\SalaController@actualizar')->name('actualiza_sala');
Route::delete('configuracion/sala/{id}', 'Configuracion\SalaController@eliminar')->name('elimina_sala');

/*
 * Modulo de Sala
 */
Route::get('sala/zona-sala', 'Sala\ZonaSalaController@index')->name('consultar_zona_sala');
Route::get('sala/zona-sala/crear', 'Sala\ZonaSalaController@crear')->name('crear_zona_sala');
Route::post('sala/zona-sala', 'Sala\ZonaSalaController@guardar')->name('guardar_zona_sala');
Route::get('sala/zona-sala/{id}/editar', 'Sala\ZonaSalaController@editar')->name('editar_zona_sala');
Route::put('sala/zona-sala/{id}', 'Sala\ZonaSalaController@actualizar')->name('actualizar_zona_sala');
Route::delete('sala/zona-sala/{id}', 'Sala\ZonaSalaController@eliminar')->name('eliminar_zona_sala');

Route::get('sala/prioridad-sala', 'Sala\PrioridadSalaController@index')->name('consultar_prioridad_sala');
Route::get('sala/prioridad-sala/crear', 'Sala\PrioridadSalaController@crear')->name('crear_prioridad_sala');
Route::post('sala/prioridad-sala', 'Sala\PrioridadSalaController@guardar')->name('guardar_prioridad_sala');
Route::get('sala/prioridad-sala/{id}/editar', 'Sala\PrioridadSalaController@editar')->name('editar_prioridad_sala');
Route::put('sala/prioridad-sala/{id}', 'Sala\PrioridadSalaController@actualizar')->name('actualizar_prioridad_sala');
Route::delete('sala/prioridad-sala/{id}', 'Sala\PrioridadSalaController@eliminar')->name('eliminar_prioridad_sala');

Route::get('sala/tecnico-laboratorio', 'Sala\TecnicoLaboratorioController@index')->name('consultar_tecnico_laboratorio');
Route::get('sala/tecnico-laboratorio/crear', 'Sala\TecnicoLaboratorioController@crear')->name('crear_tecnico_laboratorio');
Route::post('sala/tecnico-laboratorio', 'Sala\TecnicoLaboratorioController@guardar')->name('guardar_tecnico_laboratorio');
Route::get('sala/tecnico-laboratorio/{id}/editar', 'Sala\TecnicoLaboratorioController@editar')->name('editar_tecnico_laboratorio');
Route::put('sala/tecnico-laboratorio/{id}', 'Sala\TecnicoLaboratorioController@actualizar')->name('actualizar_tecnico_laboratorio');
Route::delete('sala/tecnico-laboratorio/{id}', 'Sala\TecnicoLaboratorioController@eliminar')->name('eliminar_tecnico_laboratorio');

Route::get('sala/cumplir-requisicion-sala', 'Sala\CumplirRequisicionSalaController@index')->name('cumplir_requisicion_sala');
Route::get('sala/cumplir-requisicion-sala/crear', 'Sala\CumplirRequisicionSalaController@crear')->name('crear_cumplir_requisicion_sala');
Route::get('sala/cumplir-requisicion-sala/consulta', 'Sala\CumplirRequisicionSalaController@consultaRequisicion')->name('consulta_requisicion_sala_cumple');
Route::get('sala/cumplir-requisicion-sala/datos/{id}', 'Sala\CumplirRequisicionSalaController@datosRequisicion')->name('datos_requisicion_sala_cumple');
Route::get('sala/cumplir-requisicion-sala/consulta-npu', 'Sala\CumplirRequisicionSalaController@consultaNpu')->name('consulta_npu_cumple_requisicion_sala');
Route::get('sala/cumplir-requisicion-sala/pdf/{token?}', 'Sala\CumplirRequisicionSalaController@imprimirPdf')->name('pdf_cumplir_requisicion_sala');
Route::post('sala/cumplir-requisicion-sala', 'Sala\CumplirRequisicionSalaController@grabar')->name('grabar_cumplir_requisicion_sala');
Route::get('sala/cumplir-requisicion-sala/{id}/imprimir-pdf', 'Sala\CumplirRequisicionSalaController@imprimirCumplimientoPdf')->name('imprimir_pdf_cumplir_requisicion_sala');
Route::put('sala/cumplir-requisicion-sala/{id}', 'Sala\CumplirRequisicionSalaController@actualizar')->name('actualizar_cumplir_requisicion_sala');
Route::post('sala/cumplir-requisicion-sala/{id}/revertir', 'Sala\CumplirRequisicionSalaController@revertir')->name('revertir_cumplir_requisicion_sala');
Route::get('sala/cumplir-requisicion-sala/{id}', 'Sala\CumplirRequisicionSalaController@consultar')->name('consultar_cumplir_requisicion_sala');

Route::get('sala/requisicion-sala', 'Sala\RequisicionSalaController@index')->name('consultar_requisicion_sala');
Route::get('sala/listarequisicionsala/{formato?}/{busqueda?}', 'Sala\RequisicionSalaController@listar')->name('listar_requisicion_sala');
Route::get('sala/requisicion-sala/crear', 'Sala\RequisicionSalaController@crear')->name('crear_requisicion_sala');
Route::get('sala/requisicion-sala/consulta-npu', 'Sala\RequisicionSalaController@consultaNumeroParteUnica')->name('requisicion_sala_consulta_npu');
Route::get('sala/requisicion-sala/{id}/imprimir-pdf', 'Sala\RequisicionSalaController@imprimirPdf')->name('imprimir_pdf_requisicion_sala');
Route::post('sala/requisicion-sala', 'Sala\RequisicionSalaController@guardar')->name('guardar_requisicion_sala');
Route::get('sala/requisicion-sala/visualizar/{id}/{hash}', 'Sala\RequisicionSalaController@visualizar')->name('visualizar_requisicion_sala')->middleware('modo.consulta');
Route::get('sala/requisicion-sala/{id}/editar', 'Sala\RequisicionSalaController@editar')->name('editar_requisicion_sala')->middleware('modo.consulta');
Route::put('sala/requisicion-sala/{id}', 'Sala\RequisicionSalaController@actualizar')->name('actualizar_requisicion_sala')->middleware('modo.consulta');
Route::delete('sala/requisicion-sala/{id}', 'Sala\RequisicionSalaController@eliminar')->name('eliminar_requisicion_sala');
Route::get('sala/requisicion-sala/{id}/archivo/{archivo}', 'Sala\RequisicionSalaController@descargarArchivo')->name('requisicion_sala_archivo');
Route::post('sala/requisicion-sala/{id}/enviar-arbol-aprobacion', 'Sala\RequisicionSalaController@enviarArbolAprobacion')->name('enviar_arbol_requisicion_sala');
Route::get('sala/leer_historia_requisicion_sala/{id}', 'Sala\RequisicionSalaController@leerHistoria')->name('lee_historia_requisicion_sala');

Route::get('sala/requisicion-sala-reporte', 'Sala\RequisicionSalaReporteController@index')->name('reporte_requisicion_sala');
Route::get('sala/listar-requisicion-sala-reporte/{formato?}', 'Sala\RequisicionSalaReporteController@exportar')->name('listar_reporte_requisicion_sala');

/* Modulo UIF */
/*
 * Actividades
 */

Route::get('uif/actividad_uif', 'Uif\Actividad_UifController@index')->name('consulta_actividad_uif');
Route::get('uif/actividad_uif/crear', 'Uif\Actividad_UifController@crear')->name('crea_actividad_uif');
Route::post('uif/actividad_uif', 'Uif\Actividad_UifController@guardar')->name('guarda_actividad_uif');
Route::get('uif/actividad_uif/{id}/editar', 'Uif\Actividad_UifController@editar')->name('edita_actividad_uif');
Route::put('uif/actividad_uif/{id}', 'Uif\Actividad_UifController@actualizar')->name('actualiza_actividad_uif');
Route::delete('uif/actividad_uif/{id}', 'Uif\Actividad_UifController@eliminar')->name('elimina_actividad_uif');

Route::post('uif/consultaactividad_uif', 'Uif\Actividad_UifController@consultaActividad_Uif')->name('consultar_actividad_uif');
Route::get('uif/leerunaactividad_uif/{actividad_uif_id}', 'Uif\Actividad_UifController@leeUnaActividad_Uif')->name('leer_una_actividad_uif');

/*
 * Paises UIF
 */

Route::get('uif/pais_uif', 'Uif\Pais_UifController@index')->name('consulta_pais_uif');
Route::get('uif/pais_uif/crear', 'Uif\Pais_UifController@crear')->name('crea_pais_uif');
Route::post('uif/pais_uif', 'Uif\Pais_UifController@guardar')->name('guarda_pais_uif');
Route::get('uif/pais_uif/{id}/editar', 'Uif\Pais_UifController@editar')->name('edita_pais_uif');
Route::put('uif/pais_uif/{id}', 'Uif\Pais_UifController@actualizar')->name('actualiza_pais_uif');
Route::delete('uif/pais_uif/{id}', 'Uif\Pais_UifController@eliminar')->name('elimina_pais_uif');

Route::post('uif/consultapais_uif', 'Uif\Pais_UifController@consultaPais_Uif')->name('consultar_pais_uif');
Route::get('uif/leerpais_uif/{pais_uif_id}', 'Uif\Pais_UifController@leePais_Uif')->name('leer_pais_uif');

/*
 * Pep UIF
 */

Route::get('uif/pep_uif', 'Uif\Pep_UifController@index')->name('consulta_pep_uif');
Route::get('uif/pep_uif/crear', 'Uif\Pep_UifController@crear')->name('crea_pep_uif');
Route::post('uif/pep_uif', 'Uif\Pep_UifController@guardar')->name('guarda_pep_uif');
Route::get('uif/pep_uif/{id}/editar', 'Uif\Pep_UifController@editar')->name('edita_pep_uif');
Route::put('uif/pep_uif/{id}', 'Uif\Pep_UifController@actualizar')->name('actualiza_pep_uif');
Route::delete('uif/pep_uif/{id}', 'Uif\Pep_UifController@eliminar')->name('elimina_pep_uif');

/*
 * So UIF
 */

Route::get('uif/so_uif', 'Uif\So_UifController@index')->name('consulta_so_uif');
Route::get('uif/so_uif/crear', 'Uif\So_UifController@crear')->name('crea_so_uif');
Route::post('uif/so_uif', 'Uif\So_UifController@guardar')->name('guarda_so_uif');
Route::get('uif/so_uif/{id}/editar', 'Uif\So_UifController@editar')->name('edita_so_uif');
Route::put('uif/so_uif/{id}', 'Uif\So_UifController@actualizar')->name('actualiza_so_uif');
Route::delete('uif/so_uif/{id}', 'Uif\So_UifController@eliminar')->name('elimina_so_uif');

/*
 * Provincia UIF
 */

Route::get('uif/provincia_uif', 'Uif\Provincia_UifController@index')->name('consulta_provincia_uif');
Route::get('uif/provincia_uif/crear', 'Uif\Provincia_UifController@crear')->name('crea_provincia_uif');
Route::post('uif/provincia_uif', 'Uif\Provincia_UifController@guardar')->name('guarda_provincia_uif');
Route::get('uif/provincia_uif/{id}/editar', 'Uif\Provincia_UifController@editar')->name('edita_provincia_uif');
Route::put('uif/provincia_uif/{id}', 'Uif\Provincia_UifController@actualizar')->name('actualiza_provincia_uif');
Route::delete('uif/provincia_uif/{id}', 'Uif\Provincia_UifController@eliminar')->name('elimina_provincia_uif');

Route::post('uif/consultaprovincia_uif', 'Uif\Provincia_UifController@consultaProvincia_Uif')->name('consultar_provincia_uif');
Route::get('uif/leerprovincia_uif/{provincia_uif_id}', 'Uif\Provincia_UifController@leeProvincia_Uif')->name('leer_provincia_uif');

/*
 * Frecuencia UIF
 */

Route::get('uif/frecuencia_uif', 'Uif\Frecuencia_UifController@index')->name('consulta_frecuencia_uif');
Route::get('uif/frecuencia_uif/crear', 'Uif\Frecuencia_UifController@crear')->name('crea_frecuencia_uif');
Route::post('uif/frecuencia_uif', 'Uif\Frecuencia_UifController@guardar')->name('guarda_frecuencia_uif');
Route::get('uif/frecuencia_uif/{id}/editar', 'Uif\Frecuencia_UifController@editar')->name('edita_frecuencia_uif');
Route::put('uif/frecuencia_uif/{id}', 'Uif\Frecuencia_UifController@actualizar')->name('actualiza_frecuencia_uif');
Route::delete('uif/frecuencia_uif/{id}', 'Uif\Frecuencia_UifController@eliminar')->name('elimina_frecuencia_uif');

/*
 * Juego UIF
 */

Route::get('uif/juego_uif', 'Uif\Juego_UifController@index')->name('consulta_juego_uif');
Route::get('uif/juego_uif/crear', 'Uif\Juego_UifController@crear')->name('crea_juego_uif');
Route::post('uif/juego_uif', 'Uif\Juego_UifController@guardar')->name('guarda_juego_uif');
Route::get('uif/juego_uif/{id}/editar', 'Uif\Juego_UifController@editar')->name('edita_juego_uif');
Route::put('uif/juego_uif/{id}', 'Uif\Juego_UifController@actualizar')->name('actualiza_juego_uif');
Route::delete('uif/juego_uif/{id}', 'Uif\Juego_UifController@eliminar')->name('elimina_juego_uif');

/*
 * Inusualidad UIF
 */

Route::get('uif/inusualidad_uif', 'Uif\Inusualidad_UifController@index')->name('consulta_inusualidad_uif');
Route::get('uif/inusualidad_uif/crear', 'Uif\Inusualidad_UifController@crear')->name('crea_inusualidad_uif');
Route::post('uif/inusualidad_uif', 'Uif\Inusualidad_UifController@guardar')->name('guarda_inusualidad_uif');
Route::get('uif/inusualidad_uif/{id}/editar', 'Uif\Inusualidad_UifController@editar')->name('edita_inusualidad_uif');
Route::put('uif/inusualidad_uif/{id}', 'Uif\Inusualidad_UifController@actualizar')->name('actualiza_inusualidad_uif');
Route::delete('uif/inusualidad_uif/{id}', 'Uif\Inusualidad_UifController@eliminar')->name('elimina_inusualidad_uif');

/*
 * Monto UIF
 */

Route::get('uif/monto_uif', 'Uif\Monto_UifController@index')->name('consulta_monto_uif');
Route::get('uif/monto_uif/crear', 'Uif\Monto_UifController@crear')->name('crea_monto_uif');
Route::post('uif/monto_uif', 'Uif\Monto_UifController@guardar')->name('guarda_monto_uif');
Route::get('uif/monto_uif/{id}/editar', 'Uif\Monto_UifController@editar')->name('edita_monto_uif');
Route::put('uif/monto_uif/{id}', 'Uif\Monto_UifController@actualizar')->name('actualiza_monto_uif');
Route::delete('uif/monto_uif/{id}', 'Uif\Monto_UifController@eliminar')->name('elimina_monto_uif');

/*
 * Factor Riesgo UIF
 */

Route::get('uif/factorriesgo_uif', 'Uif\Factorriesgo_UifController@index')->name('consulta_factorriesgo_uif');
Route::get('uif/factorriesgo_uif/crear', 'Uif\Factorriesgo_UifController@crear')->name('crea_factorriesgo_uif');
Route::post('uif/factorriesgo_uif', 'Uif\Factorriesgo_UifController@guardar')->name('guarda_factorriesgo_uif');
Route::get('uif/factorriesgo_uif/{id}/editar', 'Uif\Factorriesgo_UifController@editar')->name('edita_factorriesgo_uif');
Route::put('uif/factorriesgo_uif/{id}', 'Uif\Factorriesgo_UifController@actualizar')->name('actualiza_factorriesgo_uif');
Route::delete('uif/factorriesgo_uif/{id}', 'Uif\Factorriesgo_UifController@eliminar')->name('elimina_factorriesgo_uif');

/*
 * Puntaje UIF
 */

Route::get('uif/puntaje_uif', 'Uif\Puntaje_UifController@index')->name('consulta_puntaje_uif');
Route::get('uif/puntaje_uif/crear', 'Uif\Puntaje_UifController@crear')->name('crea_puntaje_uif');
Route::post('uif/puntaje_uif', 'Uif\Puntaje_UifController@guardar')->name('guarda_puntaje_uif');
Route::get('uif/puntaje_uif/{id}/editar', 'Uif\Puntaje_UifController@editar')->name('edita_puntaje_uif');
Route::put('uif/puntaje_uif/{id}', 'Uif\Puntaje_UifController@actualizar')->name('actualiza_puntaje_uif');
Route::delete('uif/puntaje_uif/{id}', 'Uif\Puntaje_UifController@eliminar')->name('elimina_puntaje_uif');

/*
 * Localidad UIF
 */

Route::get('uif/localidad_uif', 'Uif\Localidad_UifController@index')->name('consulta_localidad_uif');
Route::get('uif/localidad_uif/crear', 'Uif\Localidad_UifController@crear')->name('crea_localidad_uif');
Route::post('uif/localidad_uif', 'Uif\Localidad_UifController@guardar')->name('guarda_localidad_uif');
Route::get('uif/localidad_uif/{id}/editar', 'Uif\Localidad_UifController@editar')->name('edita_localidad_uif');
Route::put('uif/localidad_uif/{id}', 'Uif\Localidad_UifController@actualizar')->name('actualiza_localidad_uif');
Route::delete('uif/localidad_uif/{id}', 'Uif\Localidad_UifController@eliminar')->name('elimina_localidad_uif');

Route::post('uif/consultalocalidad_uif', 'Uif\Localidad_UifController@consultaLocalidad_Uif')->name('consultar_localidad_uif');
Route::get('uif/leerlocalidad_uif/{localidad_uif_id}', 'Uif\Localidad_UifController@leeLocalidad_Uif')->name('leer_localidad_uif');

Route::get('uif/leerlocalidadesuif/{id}', 'Uif\Localidad_UifController@leerLocalidades')->name('leer_una_localidad_uif');
Route::get('uif/leercodigopostaluif/{id}', 'Uif\Localidad_UifController@leerCodigoPostal')->name('leer_codigo_postal_uif');
/*
 * Profesion UIF
 */

Route::get('uif/profesion_uif', 'Uif\Profesion_UifController@index')->name('consulta_profesion_uif');
Route::get('uif/profesion_uif/crear', 'Uif\Profesion_UifController@crear')->name('crea_profesion_uif');
Route::post('uif/profesion_uif', 'Uif\Profesion_UifController@guardar')->name('guarda_profesion_uif');
Route::get('uif/profesion_uif/{id}/editar', 'Uif\Profesion_UifController@editar')->name('edita_profesion_uif');
Route::put('uif/profesion_uif/{id}', 'Uif\Profesion_UifController@actualizar')->name('actualiza_profesion_uif');
Route::delete('uif/profesion_uif/{id}', 'Uif\Profesion_UifController@eliminar')->name('elimina_profesion_uif');

Route::post('uif/consultaprofesion_uif', 'Uif\Profesion_UifController@consultaProfesion_Uif')->name('consultar_profesion_uif');
Route::get('uif/leerprofesion_uif/{profesion_uif_id}', 'Uif\Profesion_UifController@leeProfesion_Uif')->name('leer_profesion_uif');

/*
 * Nivel Socioeconomico UIF
 */

Route::get('uif/nivelsocioeconomico_uif', 'Uif\Nivelsocioeconomico_UifController@index')->name('consulta_nivelsocioeconomico_uif');
Route::get('uif/nivelsocioeconomico_uif/crear', 'Uif\Nivelsocioeconomico_UifController@crear')->name('crea_nivelsocioeconomico_uif');
Route::post('uif/nivelsocioeconomico_uif', 'Uif\Nivelsocioeconomico_UifController@guardar')->name('guarda_nivelsocioeconomico_uif');
Route::get('uif/nivelsocioeconomico_uif/{id}/editar', 'Uif\Nivelsocioeconomico_UifController@editar')->name('edita_nivelsocioeconomico_uif');
Route::put('uif/nivelsocioeconomico_uif/{id}', 'Uif\Nivelsocioeconomico_UifController@actualizar')->name('actualiza_nivelsocioeconomico_uif');
Route::delete('uif/nivelsocioeconomico_uif/{id}', 'Uif\Nivelsocioeconomico_UifController@eliminar')->name('elimina_nivelsocioeconomico_uif');

/*
 * Estado civil UIF
 */

Route::get('uif/estadocivil_uif', 'Uif\Estadocivil_UifController@index')->name('consulta_estadocivil_uif');
Route::get('uif/estadocivil_uif/crear', 'Uif\Estadocivil_UifController@crear')->name('crea_estadocivil_uif');
Route::post('uif/estadocivil_uif', 'Uif\Estadocivil_UifController@guardar')->name('guarda_estadocivil_uif');
Route::get('uif/estadocivil_uif/{id}/editar', 'Uif\Estadocivil_UifController@editar')->name('edita_estadocivil_uif');
Route::put('uif/estadocivil_uif/{id}', 'Uif\Estadocivil_UifController@actualizar')->name('actualiza_estadocivil_uif');
Route::delete('uif/estadocivil_uif/{id}', 'Uif\Estadocivil_UifController@eliminar')->name('elimina_estadocivil_uif');

/*
 * Clientes UIF
 */

Route::get('uif/cliente_uif', 'Uif\Cliente_UifController@index')->name('consulta_cliente_uif');
Route::get('uif/cliente_uif/crear', 'Uif\Cliente_UifController@crear')->name('crea_cliente_uif');
Route::post('uif/cliente_uif', 'Uif\Cliente_UifController@guardar')->name('guarda_cliente_uif');
Route::get('uif/cliente_uif/{id}/editar', 'Uif\Cliente_UifController@editar')->name('edita_cliente_uif')->middleware('modo.consulta');
Route::get('uif/cliente_uif/{id}/listar-premios/{formato?}', 'Uif\Cliente_UifController@listarPremiosCliente')->name('lista_premios_cliente_uif');
Route::get('uif/cliente_uif/{id}/fotodocumento', 'Uif\Cliente_UifController@mostrarFotodocumento')->name('cliente_uif_fotodocumento');
Route::delete('uif/cliente_uif/{id}/fotodocumento', 'Uif\Cliente_UifController@eliminarFotodocumento')->name('elimina_fotodocumento_cliente_uif');
Route::put('uif/cliente_uif/{id}', 'Uif\Cliente_UifController@actualizar')->name('actualiza_cliente_uif')->middleware('modo.consulta');
Route::delete('uif/cliente_uif/{id}', 'Uif\Cliente_UifController@eliminar')->name('elimina_cliente_uif');

Route::get('uif/listacliente_uif/{formato?}/{busqueda?}', 'Uif\Cliente_UifController@listar')->name('lista_cliente_uif');
Route::post('uif/consultacliente_uif', 'Uif\Cliente_UifController@consultaCliente_Uif')->name('consultar_cliente_uif');
Route::get('uif/leercliente_uif/{cliente_uif_id}', 'Uif\Cliente_UifController@leeCliente_Uif')->name('leer_cliente_uif');
Route::get('uif/calculariesgo_uif/{cliente_uif_id}/{periodo}/{inusualidad_uif_id}', 'Uif\Cliente_UifController@calculaRiesgo')->name('calcula_riesgo_cliente_uif');

Route::get('uif/crearexportaoperacion', 'Uif\Cliente_UifController@crearExportaOperacion')->name('crear_exporta_operacion');
Route::post('uif/generaexportaoperacion', 'Uif\Cliente_UifController@generaExportaOperacion')->name('generar_exporta_operacion');
Route::get('uif/exportaoperacion/{periodo}/{limiteinformeuif}/{empresa_id}', 'Uif\Cliente_UifController@listadoExportaOperacion')->name('listado_exporta_operacion_uif');
Route::get('uif/exportaoperacion/{periodo}/{limiteinformeuif}/{empresa_id}/xml', 'Uif\Cliente_UifController@exportaOperacion')->name('exporta_cliente_uif');
Route::get('uif/exportaoperacion/{periodo}/{limiteinformeuif}/{empresa_id}/excel', 'Uif\Cliente_UifController@exportaOperacionExcel')->name('exporta_cliente_uif_excel');
Route::get('uif/exportaoperacion/{periodo}/{limiteinformeuif}/{empresa_id}/xml-zip', 'Uif\Cliente_UifController@descargarXmlZip')->name('descargar_cliente_uif_xml_zip');

Route::get('uif/conciliacion-wigos', 'Uif\UifConciliacionWigosController@index')->name('conciliacion_wigos_uif');
Route::post('uif/conciliacion-wigos/cargar', 'Uif\UifConciliacionWigosController@cargar')->name('cargar_conciliacion_wigos_uif');
Route::post('uif/conciliacion-wigos/conciliar', 'Uif\UifConciliacionWigosController@conciliar')->name('conciliar_conciliacion_wigos_uif');
Route::get('uif/listar-conciliacion-wigos/{formato}', 'Uif\UifConciliacionWigosController@exportar')->name('listar_conciliacion_wigos_uif');

/*
 * Premios UIF
 */

Route::get('uif/premio_uif', 'Uif\Cliente_Premio_UifController@index')->name('consulta_cliente_premio_uif');
Route::get('uif/premio_uif/crear/{id}', 'Uif\Cliente_Premio_UifController@crear')->name('crea_cliente_premio_uif')->middleware('modo.consulta');
Route::post('uif/premio_uif', 'Uif\Cliente_Premio_UifController@guardar')->name('guarda_cliente_premio_uif')->middleware('modo.consulta');
Route::get('uif/premio_uif/{id}/editar', 'Uif\Cliente_Premio_UifController@editar')->name('edita_cliente_premio_uif')->middleware('modo.consulta');
Route::put('uif/premio_uif/{id}', 'Uif\Cliente_Premio_UifController@actualizar')->name('actualiza_cliente_premio_uif')->middleware('modo.consulta');
Route::delete('uif/premio_uif/{id}', 'Uif\Cliente_Premio_UifController@eliminar')->name('elimina_cliente_premio_uif')->middleware('modo.consulta');
Route::post('uif/elimina_premio_uif', 'Uif\Cliente_Premio_UifController@eliminarExterno')->name('elimina_externo_cliente_premio_uif')->middleware('modo.consulta');

Route::get('uif/premio_uif/lista_un_premio_uif/{id}', 'Uif\Cliente_Premio_UifController@listarUnPremio')->name('lista_un_cliente_premio_uif')->middleware('modo.consulta');
Route::get('uif/premio_uif/mostrar_foto/{id}', 'Uif\Cliente_Premio_UifController@mostrarFoto')->name('muestra_foto_cliente_premio_uif')->middleware('modo.consulta');
Route::get('uif/premio_uif/{formato?}/{busqueda?}', 'Uif\Cliente_Premio_UifController@listar')->name('lista_cliente_premio_uif');

/*
 * Actividades
 */

Route::get('uif/cliente_congelado_uif', 'Uif\Cliente_Congelado_UifController@index')->name('consulta_cliente_congelado_uif');
Route::get('uif/cliente_congelado_uif/crear', 'Uif\Cliente_Congelado_UifController@crear')->name('crea_cliente_congelado_uif');
Route::post('uif/cliente_congelado_uif', 'Uif\Cliente_Congelado_UifController@guardar')->name('guarda_cliente_congelado_uif');
Route::get('uif/cliente_congelado_uif/{id}/editar', 'Uif\Cliente_Congelado_UifController@editar')->name('edita_cliente_congelado_uif');
Route::put('uif/cliente_congelado_uif/{id}', 'Uif\Cliente_Congelado_UifController@actualizar')->name('actualiza_cliente_congelado_uif');
Route::delete('uif/cliente_congelado_uif/{id}', 'Uif\Cliente_Congelado_UifController@eliminar')->name('elimina_cliente_congelado_uif');

Route::post('uif/consultacliente_congelado_uif', 'Uif\Cliente_Congelado_UifController@consultaCliente_Congelado_Uif')->name('consultar_cliente_congelado_uif');
Route::get('uif/leeruncliente_congelado_uif/{cliente_congelado_uif_id}', 'Uif\Cliente_Congelado_UifController@leeUnCliente_Congelado_Uif')->name('leer_un_cliente_congelado_uif');
Route::get('uif/crea_importacion_cliente_congelado_uif', 'Uif\Cliente_Congelado_UifController@crearImportacionCliente_Congelado_Uif')->name('crear_importacion_cliente_congelado_uif');
Route::post('uif/importa_cliente_congelado_uif', 'Uif\Cliente_Congelado_UifController@importarCliente_Congelado_Uif')->name('importar_cliente_congelado_uif');

// Modulo de ordenes de venta
/*
 * Ordenes de venta
 */

Route::get('ordenventa/ordenventa', 'Ordenventa\OrdenventaController@index')->name('consulta_ordenventa');
Route::get('ordenventa/ordenventa/crear', 'Ordenventa\OrdenventaController@crear')->name('crea_ordenventa');
Route::post('ordenventa/ordenventa', 'Ordenventa\OrdenventaController@guardar')->name('guarda_ordenventa');
Route::get('ordenventa/ordenventa/{id}/editar', 'Ordenventa\OrdenventaController@editar')->name('edita_ordenventa');
Route::put('ordenventa/ordenventa/{id}', 'Ordenventa\OrdenventaController@actualizar')->name('actualiza_ordenventa');
Route::post('ordenventa/ordenventa/{id}/reenviar-arbol-aprobacion', 'Ordenventa\OrdenventaController@reenviarArbolAprobacion')->name('reenviar_arbol_aprobacion_ordenventa');
Route::delete('ordenventa/ordenventa/{id}', 'Ordenventa\OrdenventaController@eliminar')->name('elimina_ordenventa');
Route::get('ordenventa/listaordenventa/{formato?}/{busqueda?}', 'Ordenventa\OrdenventaController@listar')->name('lista_ordenventa');

Route::get('ordenventa/leer_historia_ordenventa/{ordenventa_id}', 'Ordenventa\OrdenventaController@leerHistoriaOrdenventa')->name('lee_historia_ordenventa');
Route::get('ordenventa/leer_comprobantes_ordenventa/{ordenventa_id}', 'Ordenventa\OrdenventaController@leerComprobantesOrdenventa')->name('lee_comprobantes_ordenventa');

Route::get('ordenventa/visualizar/{id}/{hash}', 'Ordenventa\OrdenventaController@visualizar');
Route::post('ventas/calculafacturaporordenventa', 'Ventas\FacturacionController@calculaFacturaPorOrdenventa')->name('calcula_factura_por_ordenventa');
Route::post('ventas/facturarordenventa', 'Ventas\FacturacionController@facturarPorOrdenventa')->name('facturar_ordenventa');

// Actualiza solo orden de venta desde programas externos
Route::get('ordenventa/actualizasoloordenventa/{estadoordenventa}/{ordenventa_id}', 'Ordenventa\OrdenventaController@actualizaSoloOrdenventa')->name('actualiza_solo_ordenventa');

/*
 * Conceptos de ordenes de venta
 */

Route::get('ordenventa/concepto_ordenventa', 'Ordenventa\Concepto_OrdenventaController@index')->name('consultar_concepto_ordenventa');
Route::get('ordenventa/concepto_ordenventa/crear', 'Ordenventa\Concepto_OrdenventaController@crear')->name('crear_concepto_ordenventa');
Route::post('ordenventa/concepto_ordenventa', 'Ordenventa\Concepto_OrdenventaController@guardar')->name('guardar_concepto_ordenventa');
Route::get('ordenventa/concepto_ordenventa/{id}/editar', 'Ordenventa\Concepto_OrdenventaController@editar')->name('editar_concepto_ordenventa');
Route::put('ordenventa/concepto_ordenventa/{id}', 'Ordenventa\Concepto_OrdenventaController@actualizar')->name('actualizar_concepto_ordenventa');
Route::delete('ordenventa/concepto_ordenventa/{id}', 'Ordenventa\Concepto_OrdenventaController@eliminar')->name('eliminar_concepto_ordenventa');

// Bierzo

/*
 * Abasto
 */

Route::get('ventas/abasto', 'Ventas\AbastoController@index')->name('consultar_abasto');
Route::get('ventas/abasto/crear', 'Ventas\AbastoController@crear')->name('crear_abasto');
Route::post('ventas/abasto', 'Ventas\AbastoController@guardar')->name('guardar_abasto');
Route::get('ventas/abasto/{id}/editar', 'Ventas\AbastoController@editar')->name('editar_abasto');
Route::put('ventas/abasto/{id}', 'Ventas\AbastoController@actualizar')->name('actualizar_abasto');
Route::delete('ventas/abasto/{id}', 'Ventas\AbastoController@eliminar')->name('eliminar_abasto');

/*
 * Modelos de etiquetas
 */

Route::get('configuracion/modeloetiqueta', 'Configuracion\ModeloetiquetaController@index')->name('consultar_modeloetiqueta');
Route::get('configuracion/modeloetiqueta/crear', 'Configuracion\ModeloetiquetaController@crear')->name('crear_modeloetiqueta');
Route::post('configuracion/modeloetiqueta', 'Configuracion\ModeloetiquetaController@guardar')->name('guardar_modeloetiqueta');
Route::get('configuracion/modeloetiqueta/{id}/editar', 'Configuracion\ModeloetiquetaController@editar')->name('editar_modeloetiqueta');
Route::put('configuracion/modeloetiqueta/{id}', 'Configuracion\ModeloetiquetaController@actualizar')->name('actualizar_modeloetiqueta');
Route::delete('configuracion/modeloetiqueta/{id}', 'Configuracion\ModeloetiquetaController@eliminar')->name('eliminar_modeloetiqueta');

Route::get('configuracion/actualizarestadomodeloetiqueta/{estadomodeloetiqueta}/{modeloetiqueta_id}', 'Configuracion\ModeloetiquetaController@actualizaEstado')->name('actualizar_estado_modeloetiqueta');
Route::get('configuracion/configurarmodeloetiqueta/{programa?}', 'Configuracion\ModeloetiquetaController@configurarModeloetiqueta')->name('configurar_modeloetiqueta');
Route::get('configuracion/setearmodeloetiqueta/{programa}/{modeloetiqueta}', 'Configuracion\ModeloetiquetaController@setearModeloetiqueta')->name('setear_modeloetiqueta');
Route::get('configuracion/buscarmodeloetiqueta/{programa?}', 'Configuracion\ModeloetiquetaController@buscarModeloetiqueta')->name('buscar_modeloetiqueta');

/*
 * Oficina de compras
 */

Route::get('configuracion/oficinacompra', 'Configuracion\OficinacompraController@index')->name('consultar_oficinacompra');
Route::get('configuracion/oficinacompra/crear', 'Configuracion\OficinacompraController@crear')->name('crear_oficinacompra');
Route::post('configuracion/oficinacompra', 'Configuracion\OficinacompraController@guardar')->name('guardar_oficinacompra');
Route::get('configuracion/oficinacompra/{id}/editar', 'Configuracion\OficinacompraController@editar')->name('editar_oficinacompra');
Route::put('configuracion/oficinacompra/{id}', 'Configuracion\OficinacompraController@actualizar')->name('actualizar_oficinacompra');
Route::delete('configuracion/oficinacompra/{id}', 'Configuracion\OficinacompraController@eliminar')->name('eliminar_oficinacompra');

/*
 * Periodicidad de compras
 */

Route::get('configuracion/periodicidadcompra', 'Configuracion\PeriodicidadcompraController@index')->name('consultar_periodicidadcompra');
Route::get('configuracion/periodicidadcompra/crear', 'Configuracion\PeriodicidadcompraController@crear')->name('crear_periodicidadcompra');
Route::post('configuracion/periodicidadcompra', 'Configuracion\PeriodicidadcompraController@guardar')->name('guardar_periodicidadcompra');
Route::get('configuracion/periodicidadcompra/{id}/editar', 'Configuracion\PeriodicidadcompraController@editar')->name('editar_periodicidadcompra');
Route::put('configuracion/periodicidadcompra/{id}', 'Configuracion\PeriodicidadcompraController@actualizar')->name('actualizar_periodicidadcompra');
Route::delete('configuracion/periodicidadcompra/{id}', 'Configuracion\PeriodicidadcompraController@eliminar')->name('eliminar_periodicidadcompra');

/*
 * Coeficiente
 */

Route::get('ventas/coeficiente', 'Ventas\CoeficienteController@index')->name('consultar_coeficiente');
Route::get('ventas/coeficiente/crear', 'Ventas\CoeficienteController@crear')->name('crear_coeficiente');
Route::post('ventas/coeficiente', 'Ventas\CoeficienteController@guardar')->name('guardar_coeficiente');
Route::get('ventas/coeficiente/{id}/editar', 'Ventas\CoeficienteController@editar')->name('editar_coeficiente');
Route::put('ventas/coeficiente/{id}', 'Ventas\CoeficienteController@actualizar')->name('actualizar_coeficiente');
Route::delete('ventas/coeficiente/{id}', 'Ventas\CoeficienteController@eliminar')->name('eliminar_coeficiente');

/*
 * Distribuidor
 */

Route::get('ventas/distribuidor', 'Ventas\DistribuidorController@index')->name('consultar_distribuidor');
Route::get('ventas/distribuidor/crear', 'Ventas\DistribuidorController@crear')->name('crear_distribuidor');
Route::post('ventas/distribuidor', 'Ventas\DistribuidorController@guardar')->name('guardar_distribuidor');
Route::get('ventas/distribuidor/{id}/editar', 'Ventas\DistribuidorController@editar')->name('editar_distribuidor');
Route::put('ventas/distribuidor/{id}', 'Ventas\DistribuidorController@actualizar')->name('actualizar_distribuidor');
Route::delete('ventas/distribuidor/{id}', 'Ventas\DistribuidorController@eliminar')->name('eliminar_distribuidor');
Route::post('ventas/distribuidor/consultadistribuidor', 'Ventas\DistribuidorController@consultaDistribuidor')->name('consulta_distribuidor');
Route::get('ventas/leerdistribuidor/{codigo}', 'Ventas\DistribuidorController@leeUnDistribuidor')->name('leer_distribuidor');

/*
 * Descuento venta
 */

Route::get('ventas/descuentoventa', 'Ventas\DescuentoventaController@index')->name('consultar_descuentoventa');
Route::get('ventas/descuentoventa/crear', 'Ventas\DescuentoventaController@crear')->name('crear_descuentoventa');
Route::post('ventas/descuentoventa', 'Ventas\DescuentoventaController@guardar')->name('guardar_descuentoventa');
Route::get('ventas/descuentoventa/{id}/editar', 'Ventas\DescuentoventaController@editar')->name('editar_descuentoventa');
Route::put('ventas/descuentoventa/{id}', 'Ventas\DescuentoventaController@actualizar')->name('actualizar_descuentoventa');
Route::delete('ventas/descuentoventa/{id}', 'Ventas\DescuentoventaController@eliminar')->name('eliminar_descuentoventa');

Route::get('ventas/leeundescuentoventa/{descuentoventa_id}', 'Ventas\DescuentoventaController@leeUnDescuento')->name('lee_un_descuentoventa');

/*
 * Envase senasa
 */

Route::get('stock/envasesenasa', 'Stock\EnvasesenasaController@index')->name('consultar_envasesenasa');
Route::get('stock/envasesenasa/crear', 'Stock\EnvasesenasaController@crear')->name('crear_envasesenasa');
Route::post('stock/envasesenasa', 'Stock\EnvasesenasaController@guardar')->name('guardar_envasesenasa');
Route::get('stock/envasesenasa/{id}/editar', 'Stock\EnvasesenasaController@editar')->name('editar_envasesenasa');
Route::put('stock/envasesenasa/{id}', 'Stock\EnvasesenasaController@actualizar')->name('actualizar_envasesenasa');
Route::delete('stock/envasesenasa/{id}', 'Stock\EnvasesenasaController@eliminar')->name('eliminar_envasesenasa');

/*
 * Codigos senasa
 */

Route::get('stock/codigosenasa', 'Stock\CodigosenasaController@index')->name('consultar_codigosenasa');
Route::get('stock/codigosenasa/crear', 'Stock\CodigosenasaController@crear')->name('crear_codigosenasa');
Route::post('stock/codigosenasa', 'Stock\CodigosenasaController@guardar')->name('guardar_codigosenasa');
Route::get('stock/codigosenasa/{id}/editar', 'Stock\CodigosenasaController@editar')->name('editar_codigosenasa');
Route::put('stock/codigosenasa/{id}', 'Stock\CodigosenasaController@actualizar')->name('actualizar_codigosenasa');
Route::delete('stock/codigosenasa/{id}', 'Stock\CodigosenasaController@eliminar')->name('eliminar_codigosenasa');

/* Produccion
 * Tipo de produccion
 */

Route::get('produccion/tipoproduccion', 'Produccion\TipoproduccionController@index')->name('consultar_tipoproduccion');
Route::get('produccion/tipoproduccion/crear', 'Produccion\TipoproduccionController@crear')->name('crear_tipoproduccion');
Route::post('produccion/tipoproduccion', 'Produccion\TipoproduccionController@guardar')->name('guardar_tipoproduccion');
Route::get('produccion/tipoproduccion/{id}/editar', 'Produccion\TipoproduccionController@editar')->name('editar_tipoproduccion');
Route::put('produccion/tipoproduccion/{id}', 'Produccion\TipoproduccionController@actualizar')->name('actualizar_tipoproduccion');
Route::delete('produccion/tipoproduccion/{id}', 'Produccion\TipoproduccionController@eliminar')->name('eliminar_tipoproduccion');

/*
 * Sector de sellado
 */

Route::get('produccion/sectorsellado', 'Produccion\SectorselladoController@index')->name('consultar_sectorsellado');
Route::get('produccion/sectorsellado/crear', 'Produccion\SectorselladoController@crear')->name('crear_sectorsellado');
Route::post('produccion/sectorsellado', 'Produccion\SectorselladoController@guardar')->name('guardar_sectorsellado');
Route::get('produccion/sectorsellado/{id}/editar', 'Produccion\SectorselladoController@editar')->name('editar_sectorsellado');
Route::put('produccion/sectorsellado/{id}', 'Produccion\SectorselladoController@actualizar')->name('actualizar_sectorsellado');
Route::delete('produccion/sectorsellado/{id}', 'Produccion\SectorselladoController@eliminar')->name('eliminar_sectorsellado');

/*
 * Sala de produccion
 */

Route::get('produccion/salaproduccion', 'Produccion\SalaproduccionController@index')->name('consultar_salaproduccion');
Route::get('produccion/salaproduccion/crear', 'Produccion\SalaproduccionController@crear')->name('crear_salaproduccion');
Route::post('produccion/salaproduccion', 'Produccion\SalaproduccionController@guardar')->name('guardar_salaproduccion');
Route::get('produccion/salaproduccion/{id}/editar', 'Produccion\SalaproduccionController@editar')->name('editar_salaproduccion');
Route::put('produccion/salaproduccion/{id}', 'Produccion\SalaproduccionController@actualizar')->name('actualizar_salaproduccion');
Route::delete('produccion/salaproduccion/{id}', 'Produccion\SalaproduccionController@eliminar')->name('eliminar_salaproduccion');

// Redodea cajas
Route::get('stock/redondeacaja/{articulo_id}/{unidadmedida}/{caja}/{pieza}/{kilo}/{descuento_id}/{opcion}', 'Stock\ArticuloController@redondeaCaja')->name('redondea_caja');

// --------------------------------------------------
// Modulo de presupuesto

/*
 * Presupuestos
 */

Route::get('presupuesto/presupuesto', 'Presupuesto\PresupuestoController@index')->name('consultar_presupuesto');
Route::get('presupuesto/presupuesto/crear', 'Presupuesto\PresupuestoController@crear')->name('crear_presupuesto');
Route::post('presupuesto/presupuesto', 'Presupuesto\PresupuestoController@guardar')->name('guardar_presupuesto');
Route::get('presupuesto/presupuesto/{id}/editar', 'Presupuesto\PresupuestoController@editar')->name('editar_presupuesto');
Route::put('presupuesto/presupuesto/{id}', 'Presupuesto\PresupuestoController@actualizar')->name('actualizar_presupuesto');
Route::delete('presupuesto/presupuesto/{id}', 'Presupuesto\PresupuestoController@eliminar')->name('eliminar_presupuesto');

Route::get('presupuesto/leerescenario/{escenario_id}', 'Presupuesto\PresupuestoController@leerEscenario')->name('lee_presupuesto_escenario');
/*
 * Capex
 */

Route::get('presupuesto/capex', 'Presupuesto\CapexController@index')->name('consultar_capex');
Route::get('presupuesto/capex/crear', 'Presupuesto\CapexController@crear')->name('crear_capex');
Route::post('presupuesto/capex', 'Presupuesto\CapexController@guardar')->name('guardar_capex');
Route::get('presupuesto/capex/{id}/editar', 'Presupuesto\CapexController@editar')->name('editar_capex')->middleware('modo.consulta');
Route::put('presupuesto/capex/{id}', 'Presupuesto\CapexController@actualizar')->name('actualizar_capex')->middleware('modo.consulta');
Route::delete('presupuesto/capex/{id}', 'Presupuesto\CapexController@eliminar')->name('eliminar_capex');

Route::get('presupuesto/actualizaestadocapex/{estadocapex}/{capex_id}', 'Presupuesto\CapexController@actualizaEstadoCapex')->name('actualiza_solo_capex');
Route::get('presupuesto/leerhistoriacapex/{capex_id}', 'Presupuesto\CapexController@leerHistoriaCapex')->name('lee_historia_capex');
Route::get('presupuesto/leerordencompra/{capex_id}', 'Presupuesto\CapexController@leerOrdenCompra')->name('lee_ordencompra_capex');
Route::get('presupuesto/listarordencompra/{formato}/{capex_id}', 'Presupuesto\CapexController@listarOrdenCompra')->name('lista_ordencompra_capex');
Route::get('presupuesto/listacapex/{formato?}/{busqueda?}', 'Presupuesto\CapexController@listar')->name('lista_capex');
Route::get('presupuesto/leercapexpartidamonto/{capex_partida_id}', 'Presupuesto\CapexController@leerCapexPartidaMonto')->name('lee_capex_partida_monto');

Route::post('presupuesto/consulta_capex', 'Presupuesto\CapexController@consultaCapex')->name('consulta_capex');
Route::post('presupuesto/resolver-capex-codigo', 'Presupuesto\CapexController@resolverCapexPorCodigo')->name('resolver_capex_codigo');
Route::get('presupuesto/leer_capex/{capex_id}', 'Presupuesto\CapexController@leerCapexPorId')->name('leer_capex');

Route::get('presupuesto/capex-reporte', 'Presupuesto\CapexReporteController@index')->name('capex_reporte');
Route::get('presupuesto/listar-capex-reporte/{formato?}', 'Presupuesto\CapexReporteController@listar')->name('listar_capex_reporte');

/*
 * Partidas de gastos
 */

Route::get('presupuesto/partidagasto', 'Presupuesto\PartidagastoController@index')->name('consultar_partidagasto');
Route::get('presupuesto/partidagasto/crear', 'Presupuesto\PartidagastoController@crear')->name('crear_partidagasto');
Route::post('presupuesto/partidagasto', 'Presupuesto\PartidagastoController@guardar')->name('guardar_partidagasto');
Route::get('presupuesto/partidagasto/{id}/editar', 'Presupuesto\PartidagastoController@editar')->name('editar_partidagasto');
Route::put('presupuesto/partidagasto/{id}', 'Presupuesto\PartidagastoController@actualizar')->name('actualizar_partidagasto');
Route::delete('presupuesto/partidagasto/{id}', 'Presupuesto\PartidagastoController@eliminar')->name('eliminar_partidagasto');

Route::get('presupuesto/actualizaestadopartidagasto/{estadopartidagasto}/{partidagasto_id}', 'Presupuesto\PartidagastoController@actualizaEstadoPartidagasto')->name('actualiza_solo_partidagasto');
Route::get('presupuesto/leerhistoriapartidagasto/{partidagasto_id}', 'Presupuesto\PartidagastoController@leerHistoriaPartidagasto')->name('lee_historia_partidagasto');
Route::get('presupuesto/leerordencomprapartidagasto/{partidagasto_id}', 'Presupuesto\PartidagastoController@leerOrdenCompra')->name('lee_ordencompra_partidagasto');
Route::get('presupuesto/listarordencomprapartidagasto/{formato}/{partidagasto_id}', 'Presupuesto\PartidagastoController@listarOrdenCompra')->name('lista_ordencompra_partidagasto');
Route::get('presupuesto/listapartidagasto/{formato?}/{busqueda?}', 'Presupuesto\PartidagastoController@listar')->name('lista_partidagasto');
Route::get('presupuesto/leerpartidagastopartidamonto/{partidagasto_partida_id}', 'Presupuesto\PartidagastoController@leerPartidagastoPartidaMonto')->name('lee_partidagasto_partida_monto');

Route::post('presupuesto/consulta_partidagasto', 'Presupuesto\PartidagastoController@consultaPartidagasto')->name('consulta_partidagasto');
Route::post('presupuesto/resolver-partidagasto-codigo', 'Presupuesto\PartidagastoController@resolverPartidagastoPorCodigo')->name('resolver_partidagasto_codigo');
Route::get('presupuesto/leer_partidagasto/{partidagasto_id}', 'Presupuesto\PartidagastoController@leerPartidagastoPorId')->name('leer_partidagasto');

/*
 * Genera asientos contables del presupuesto de gastos
 */

Route::get('presupuesto/generaasiento', 'Presupuesto\PartidagastoController@indexGeneraAsiento')->name('generar_asientos_partidagasto');
Route::post('presupuesto/crear_generaasiento', 'Presupuesto\PartidagastoController@crearGeneraAsiento')->name('crear_genera_asiento_partidagasto');

/* FRASLE */

/* Produccion */

/*
 * Linea de llenado
 */

Route::get('produccion/lineallenado', 'Produccion\LineallenadoController@index')->name('consultar_lineallenado');
Route::get('produccion/lineallenado/crear', 'Produccion\LineallenadoController@crear')->name('crear_lineallenado');
Route::post('produccion/lineallenado', 'Produccion\LineallenadoController@guardar')->name('guardar_lineallenado');
Route::get('produccion/lineallenado/{id}/editar', 'Produccion\LineallenadoController@editar')->name('editar_lineallenado');
Route::put('produccion/lineallenado/{id}', 'Produccion\LineallenadoController@actualizar')->name('actualizar_lineallenado');
Route::delete('produccion/lineallenado/{id}', 'Produccion\LineallenadoController@eliminar')->name('eliminar_lineallenado');

/*
 * Proviene de bines
 */

Route::get('produccion/provienebin', 'Produccion\ProvienebinController@index')->name('consultar_provienebin');
Route::get('produccion/provienebin/crear', 'Produccion\ProvienebinController@crear')->name('crear_provienebin');
Route::post('produccion/provienebin', 'Produccion\ProvienebinController@guardar')->name('guardar_provienebin');
Route::get('produccion/provienebin/{id}/editar', 'Produccion\ProvienebinController@editar')->name('editar_provienebin');
Route::put('produccion/provienebin/{id}', 'Produccion\ProvienebinController@actualizar')->name('actualizar_provienebin');
Route::delete('produccion/provienebin/{id}', 'Produccion\ProvienebinController@eliminar')->name('eliminar_provienebin');

/*
 * Ordenes de produccion
 */

Route::get('produccion/ordenproduccion', 'Produccion\OrdenproduccionController@index')->name('consultar_ordenproduccion');
Route::get('produccion/ordenproduccion/crear', 'Produccion\OrdenproduccionController@crear')->name('crear_ordenproduccion');
Route::post('produccion/ordenproduccion', 'Produccion\OrdenproduccionController@guardar')->name('guardar_ordenproduccion');
Route::get('produccion/ordenproduccion/{id}/editar', 'Produccion\OrdenproduccionController@editar')->name('editar_ordenproduccion');
Route::put('produccion/ordenproduccion/{id}', 'Produccion\OrdenproduccionController@actualizar')->name('actualizar_ordenproduccion');
Route::delete('produccion/ordenproduccion/{id}', 'Produccion\OrdenproduccionController@eliminar')->name('eliminar_ordenproduccion');

Route::get('produccion/listaordenproduccion/{formato?}/{busqueda?}', 'Produccion\OrdenproduccionController@listar')->name('lista_ordenproduccion');
