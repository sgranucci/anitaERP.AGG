<?php
//header('Access-Control-Allow-Origin: *');
//header('Content-Type: application/json; charset=utf8');

ini_set('memory_limit', '512M');
ini_set('display_errors', '0');

include_once 'include/IFXConnection.php';
$data = json_decode(file_get_contents('php://input'), true);

if (array_key_exists('path_sistema', $data) && trim((string) $data['path_sistema']) !== '') {
	$path_sistema = rtrim((string) $data['path_sistema'], '/');
} elseif (! empty($data['IFX_DB_PATH'])) {
	$path_sistema = dirname(rtrim((string) $data['IFX_DB_PATH'], '/'));
} else {
	$path_sistema = '/usr2/bierzo';
}
$sistema = (array_key_exists('sistema', $data) ? $data['sistema'] : 'ventas');
$proceso = getmypid();
$ts = str_replace('.', '', sprintf('%.6F', microtime(true))).'_'.$proceso;

$_nombre_file = $path_sistema.'/'.$sistema.'/cmd_sql.'.$ts.'.sql';
$_nombre_ret = $path_sistema.'/'.$sistema.'/cmd_sql.'.$ts.'-'.$data['acc'].'.csv';

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
}

$fp1 = fopen($_nombre_file, "w");
fprintf($fp1, "%s", $sql);
fclose($fp1);

$ifxServer = (! empty($data['IFX_SERVER']) ? $data['IFX_SERVER'] : 'bincadmin');
$_cmdd = "export LD_ASSUME_KERNEL=2.4.19;export INFORMIXDIR=/home/informix;export LD_LIBRARY_PATH=:/home/informix/lib:/home/informix/lib/esql:/home/informix_esql/lib;export INFORMIXSERVER=".$ifxServer.";cd ".$path_sistema."/".$sistema.";";

$_cmd = $_cmdd."sql ".$sistema." ".$_nombre_file." 2>&1";

$arr = shell_exec($_cmd);

if ($data['acc'] == "list" || $data['acc'] == "customSql") {
	if (! is_readable($_nombre_ret)) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array(
			'Error' => 'UNLOAD no generó el archivo CSV (revisar permisos, ruta o SQL Informix).',
			'csv_esperado' => $_nombre_ret,
			'sql_file' => $_nombre_file,
			'informix_output' => trim((string) $arr),
		));
		@unlink($_nombre_file);
		exit;
	}
    $dataArr = array();
	$archivo = fopen($_nombre_ret, 'r');
	$camposArr = explode(',', (string) ($data['campos'] ?? ''));
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
			$registroAssoc[trim($value)] = $lineaArr[$key];
		}
		$dataArr[] = $registroAssoc;
	}
	fclose($archivo);
	@unlink($_nombre_ret);
	@unlink($_nombre_file);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($dataArr);
}else{
	$_nombre_retorno = $_nombre_file.".ret";
	$hayErrorRet = false;
	if (is_readable($_nombre_retorno)) {
		$hayErrorRet = filesize($_nombre_retorno) > 0;
		@unlink($_nombre_retorno);
	}
	@unlink($_nombre_file);
	header('Content-Type: text/plain; charset=utf-8');
	if ($hayErrorRet) {
		echo "Error";
	} else {
		echo trim((string) $arr);
	}
}
?>

