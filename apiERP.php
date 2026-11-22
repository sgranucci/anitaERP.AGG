<?php
// Bridge HTTP → Informix (sql). Corre en el servidor Anita con PHP viejo (5.3):
// sin [], sin ??, sin random_bytes.

ini_set('memory_limit', '512M');
ini_set('display_errors', '0');

include_once 'include/IFXConnection.php';
$data = json_decode(file_get_contents('php://input'), true);
if (! is_array($data) || empty($data['acc'])) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('Error' => 'Bridge: payload JSON inválido o sin acc'));
	exit;
}

if (array_key_exists('path_sistema', $data) && trim((string) $data['path_sistema']) !== '') {
	$path_sistema = rtrim((string) $data['path_sistema'], '/');
} elseif (! empty($data['IFX_DB_PATH'])) {
	$path_sistema = dirname(rtrim((string) $data['IFX_DB_PATH'], '/'));
} else {
	$path_sistema = '/usr2/bierzo';
}
$sistema = (array_key_exists('sistema', $data) ? $data['sistema'] : 'ventas');
$dir_trabajo = $path_sistema.'/'.$sistema;

// Nombre único por request: dos requests concurrentes no pueden compartir el .sql
// (con substr(microtime,0,8) el nombre se repetía durante 100 segundos y un request
// ejecutaba el SQL de otro: inserts duplicados, errores cruzados, CSV faltantes).
$_nombre_file = null;
for ($i = 0; $i < 5 && $_nombre_file === null; $i++) {
	$ts = str_replace('.', '', sprintf('%.6F', microtime(true))).'_'.getmypid().'_'.mt_rand(100000, 999999);
	$candidato = $dir_trabajo.'/cmd_sql.'.$ts.'.sql';
	$fp1 = @fopen($candidato, 'x');
	if ($fp1 !== false) {
		$_nombre_file = $candidato;
	}
}
if ($_nombre_file === null) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('Error' => 'Bridge: no se pudo crear el archivo SQL en '.$dir_trabajo.' (disco lleno o permisos)'));
	exit;
}
$_nombre_ret = substr($_nombre_file, 0, -4).'-'.$data['acc'].'.csv';
$_nombre_retorno = $_nombre_file.'.ret';

// Borra los temporales de este request pase lo que pase (error fatal, corte del cliente).
$GLOBALS['_bridge_temporales'] = array($_nombre_file, $_nombre_ret, $_nombre_retorno);
register_shutdown_function('bridge_borrar_temporales');
ignore_user_abort(true);

function bridge_borrar_temporales()
{
	foreach ($GLOBALS['_bridge_temporales'] as $f) {
		if (is_file($f)) {
			@unlink($f);
		}
	}
}

// Restos de versiones anteriores o de procesos matados: 1 de cada 200 requests
// barre cmd_sql.* con más de 2 horas.
if (mt_rand(1, 200) === 1) {
	$dh = @opendir($dir_trabajo);
	if ($dh !== false) {
		$limite = time() - 7200;
		while (($nom = readdir($dh)) !== false) {
			if (strpos($nom, 'cmd_sql.') !== 0) {
				continue;
			}
			$full = $dir_trabajo.'/'.$nom;
			$mt = @filemtime($full);
			if ($mt !== false && $mt < $limite) {
				@unlink($full);
			}
		}
		closedir($dh);
	}
}

if (array_key_exists('DB_NAME', $data)) {
	putenv ("DBPATH="        . $data["IFX_DB_PATH"]);
	putenv ("INFORMIXSERVER=" 	. $data["IFX_SERVER"]);
	$conn2 = new IFXConnection ($data["DB_NAME"]."@".$data["IFX_SERVER"], $config["IFX_DB_USER"], $config["IFX_DB_PASSWORD"]);
}

$data['where'] 		 = (array_key_exists('where', $data) ? $data['where'] : "");
$data['campos'] 	 = (array_key_exists('campos', $data) ? $data['campos'] : "");
$data['tabla'] 		 = (array_key_exists('tabla', $data) ? $data['tabla'] : "");
$data['whereArmado']     = (array_key_exists('whereArmado', $data) ? $data['whereArmado'] : "");
$data['orderBy'] 	 = (array_key_exists('orderBy', $data) ? " ORDER BY ".$data['orderBy'] : "");
$data['groupBy'] 	 = (array_key_exists('groupBy', $data) ? " GROUP BY ".$data['groupBy'] : "");
$data['valores'] 	 = (array_key_exists('valores', $data) ? $data['valores'] : "");

// Defensa: UPDATE/DELETE sin WHERE de clave (incidente 20/nov/2026 ven_impuesto1).
$acc_safe = strtolower(trim($data['acc']));
if (($acc_safe == 'update' || $acc_safe == 'delete')
	&& empty($data['permitir_escritura_masiva'])) {
	$where_safe = trim($data['whereArmado']);
	$tabla_safe = strtolower(trim($data['tabla']));
	$err_where = '';
	if ($where_safe === '') {
		$err_where = 'UPDATE/DELETE Anita sin whereArmado: prohibido';
	} elseif (! preg_match('/\bwhere\b/i', $where_safe)) {
		$err_where = 'UPDATE/DELETE Anita: whereArmado debe incluir WHERE';
	} else {
		$sin_triv = preg_replace('/\bwhere\s+1\s*=\s*1\s*(and\s+)?/i', 'WHERE ', $where_safe);
		$sin_triv = trim($sin_triv);
		if ($sin_triv === '' || preg_match('/^\s*where\s*$/i', $sin_triv)) {
			$err_where = 'UPDATE/DELETE Anita: whereArmado solo WHERE 1=1';
		} elseif (! preg_match('/\b[a-z_][a-z0-9_]*\s*=\s*(\'[^\']*\'|\d+)/i', $sin_triv)) {
			$err_where = 'UPDATE/DELETE Anita: whereArmado sin predicado col=valor';
		}
	}
	$tablas_pk = array('venta','vengrav','vencae','venibr','comprob','compaux','climov','stkmov','subdiario','subhist');
	if ($err_where === '' && in_array($tabla_safe, $tablas_pk)) {
		$ok_pk = preg_match('/\b\w*_tipo\s*=/i', $where_safe)
			&& preg_match('/\b\w*_letra\s*=/i', $where_safe)
			&& preg_match('/\b\w*_sucursal\s*=/i', $where_safe)
			&& preg_match('/\b\w*_(nro|nro_fact)\s*=/i', $where_safe);
		if (! $ok_pk) {
			$err_where = 'UPDATE/DELETE Anita en '.$tabla_safe.': falta tipo+letra+sucursal+nro';
		}
	}
	if ($err_where !== '') {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('Error' => $err_where));
		exit;
	}
}

switch ($data['acc']){
    case 'list':
		$sql = "UNLOAD TO '".$_nombre_ret."' DELIMITER '|' SELECT ".$data['campos']." FROM ".$data['tabla']." ".$data['whereArmado']." ".$data['groupBy']." ".$data['orderBy'];
		break;
	case 'insert':
		$sql = "INSERT INTO ".$data['tabla']." (".$data['campos'].") VALUES (".$data['valores'].")";
	break;
	case 'update':
		$sql = "UPDATE ".$data['tabla']." SET ".$data['valores']." ".$data['whereArmado'];
	break;
	case 'delete':
		$sql = "DELETE FROM ".$data['tabla']." ".$data['whereArmado'];
	break;
    case 'customSql':
	    $sql = "UNLOAD TO '".$_nombre_ret."' DELIMITER '|' ".$data['customSql'];
		break;
	default:
		fclose($fp1);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('Error' => 'Bridge: acc desconocida '.$data['acc']));
		exit;
}

$escrito = fwrite($fp1, $sql);
fclose($fp1);
if ($escrito === false || $escrito < strlen($sql)) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('Error' => 'Bridge: no se pudo escribir el SQL completo (disco lleno)'));
	exit;
}

$ifxServer = (! empty($data['IFX_SERVER']) ? trim($data['IFX_SERVER']) : 'bincadmin');
// sql(1) usa sqlhosts de ESTE host. bi7ncadmin es el alias remoto del ERP (Informix 25596).
if ($ifxServer == 'bi7ncadmin') {
	$ifxServer = 'bincadmin';
}
$_cmdd_pre = "export LD_ASSUME_KERNEL=2.4.19;export INFORMIXDIR=/home/informix;export LD_LIBRARY_PATH=:/home/informix/lib:/home/informix/lib/esql:/home/informix_esql/lib;export INFORMIXSERVER=";
$_cmdd_post = ";cd ".$dir_trabajo.";";

$_cmd = $_cmdd_pre.$ifxServer.$_cmdd_post."sql ".$sistema." ".$_nombre_file." 2>&1";

$arr = shell_exec($_cmd);

if ($data['acc'] == "list" || $data['acc'] == "customSql") {
	if (! is_readable($_nombre_ret) && $ifxServer != 'bincadmin') {
		$outIfx = trim((string) $arr);
		if ($outIfx != '' && (strpos($outIfx, '25596') !== false || strpos($outIfx, 'INFORMIXSERVER') !== false)) {
			$ifxServer = 'bincadmin';
			$_cmd = $_cmdd_pre.$ifxServer.$_cmdd_post."sql ".$sistema." ".$_nombre_file." 2>&1";
			$arr = shell_exec($_cmd);
		}
	}
	if (! is_readable($_nombre_ret)) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array(
			'Error' => 'UNLOAD no generó el archivo CSV (revisar permisos, ruta o SQL Informix).',
			'csv_esperado' => $_nombre_ret,
			'informix_server' => $ifxServer,
			'informix_output' => trim((string) $arr),
		));
		exit;
	}
    $dataArr = array();
	$archivo = fopen($_nombre_ret, 'r');
	$camposArr = explode(',', (string) $data['campos']);
	while ($linea = fgets($archivo)) {
		$linea = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '#', $linea);
		$registroAssoc = array();
		$lineaArr = (explode("|", $linea));
		foreach ($camposArr as $key => $value) {
			$nombreAux = explode(" as ", $value);
			if (count($nombreAux) == 2) {
				$value = $nombreAux[1];
			}else{
				$nombreAux = explode(" AS ", $value);
				if (count($nombreAux) == 2) {
					$value = $nombreAux[1];
				}
			}
			$registroAssoc[trim($value)] = isset($lineaArr[$key]) ? $lineaArr[$key] : '';
		}
		$dataArr[] = $registroAssoc;
	}
	fclose($archivo);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($dataArr);
}else{
	$hayErrorRet = false;
	if (is_readable($_nombre_retorno)) {
		$hayErrorRet = filesize($_nombre_retorno) > 0;
	}
	header('Content-Type: text/plain; charset=utf-8');
	if ($hayErrorRet) {
		echo "Error";
	} else {
		echo trim((string) $arr);
	}
}
?>
