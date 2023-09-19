<?php
// Configuración
if (!is_file('config.php')) {
	exit();
}

// Configuración
require_once('config.php');

// Inicio
require_once(DIR_SYSTEM . 'startup.php');

// Cargador Automático
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\\Catalog', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

require_once(DIR_SYSTEM . 'vendor.php');

// Registro
$registro = new \Opencart\System\Engine\Registry();
$registro->set('autoloader', $autoloader);

// Configuración
$configuracion = new \Opencart\System\Engine\Config();
$registro->set('configuracion', $configuracion);

// Cargar la configuración predeterminada
$configuracion->addPath(DIR_CONFIG);
$configuracion->load('default');
$configuracion->load('catalogo');
$configuracion->set('aplicacion', 'Catálogo');

// Establecer la zona horaria predeterminada
date_default_timezone_set($configuracion->get('date_timezone'));

// Tienda
$configuracion->set('config_store_id', 0);

// Registro
$registro->set('registro', new \Opencart\System\Library\Log($configuracion->get('error_filename')));
$log = $registro->get('registro');

// Controlador de Errores
set_error_handler(function (string $codigo, string $mensaje, string $archivo, string $linea) use ($log, $configuracion) {
	// Error suprimido con @
	if (@error_reporting() === 0) {
		return false;
	}

	switch ($codigo) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Aviso';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Advertencia';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Error Fatal';
			break;
		default:
			$error = 'Desconocido';
			break;
	}

	if ($configuracion->get('error_log')) {
		$log->write('PHP ' . $error . ':  ' . $mensaje . ' en ' . $archivo . ' en la línea ' . $linea);
	}

	if ($configuracion->get('error_display')) {
		echo '<b>' . $error . '</b>: ' . $mensaje . ' en <b>' . $archivo . '</b> en la línea <b>' . $linea . '</b>';
	} else {
		header('Location: ' . $configuracion->get('error_page'));
		exit();
	}

	return true;
});

// Controlador de Excepciones
set_exception_handler(function (\Throwable $e) use ($log, $configuracion) {
	if ($configuracion->get('error_log')) {
		$log->write(get_class($e) . ':  ' . $e->getMessage() . ' en ' . $e->getFile() . ' en la línea ' . $e->getLine());
	}

	if ($configuracion->get('error_display')) {
		echo '<b>' . get_class($e) . '</b>: ' . $e->getMessage() . ' en <b>' . $e->getFile() . '</b> en la línea <b>' . $e->getLine() . '</b>';
	} else {
		header('Location: ' . $configuracion->get('error_page'));
		exit();
	}
});

// Evento
$evento = new \Opencart\System\Engine\Event($registro);
$registro->set('evento', $evento);

// Registro de Evento
if ($configuracion->has('action_event')) {
	foreach ($configuracion->get('action_event') as $clave => $valor) {
		foreach ($valor as $prioridad => $accion) {
			$evento->register($clave, new \Opencart\System\Engine\Action($accion), $prioridad);
		}
	}
}

// Cargador
$cargador = new \Opencart\System\Engine\Loader($registro);
$registro->set('cargador', $cargador);

// Solicitud
$solicitud = new \Opencart\System\Library\Request();
$registro->set('solicitud', $solicitud);

// Respuesta
$respuesta = new \Opencart\System\Library\Response();
$registro->set('respuesta', $respuesta);

// Base de Datos
if ($configuracion->get('db_autostart')) {
	$base_datos = new \Opencart\System\Library\DB($configuracion->get('db_engine'), $configuracion->get('db_hostname'), $configuracion->get('db_username'), $configuracion->get('db_password'), $configuracion->get('db_database'), $configuracion->get('db_port'));
	$registro->set('base_datos', $base_datos);

	// Sincronizar las zonas horarias de PHP y DB
	$base_datos->query("SET `time_zone` = '" . $base_datos->escape(date('P')) . "'");
}

// Sesión
if ($configuracion->get('session_autostart')) {
	$sesion = new \Opencart\System\Library\Session($configuracion->get('session_engine'), $registro);
	$registro->set('sesion', $sesion);

	if (isset($solicitud->cookie[$configuracion->get('session_name')])) {
		$id_sesion = $solicitud->cookie[$configuracion->get('session_name')];
	} else {
		$id_sesion = '';
	}

	$sesion->start($id_sesion);

	// Requerir una mayor seguridad para las cookies de sesión
	$opciones = [
		'expires'  => 0,
		'path'     => $configuracion->get('session_path'),
		'domain'   => $configuracion->get('session_domain'),
		'secure'   => $solicitud->server['HTTPS'],
		'httponly' => false,
		'SameSite' => $configuracion->get('session_samesite')
	];

	setcookie($configuracion->get('session_name'), $sesion->getId(), $opciones);
}

// Caché
$registro->set('cache', new \Opencart\System\Library\Cache($configuracion->get('cache_engine'), $configuracion->get('cache_expire')));

// Plantilla
$plantilla = new \Opencart\System\Library\Template($configuracion->get('template_engine'));
$registro->set('plantilla', $plantilla);
$plantilla->addPath(DIR_TEMPLATE);

// Idioma
$idioma = new \Opencart\System\Library\Language($configuracion->get('language_code'));
$registro->set('idioma', $idioma);
$idioma->addPath(DIR_LANGUAGE);
$cargador->load->language($configuracion->get('language_code'));

// URL
$registro->set('url', new \Opencart\System\Library\Url($configuracion->get('site_url')));

// Acciones Previas
foreach ($configuracion->get('action_pre_action') as $accion_previa) {
	$cargador->controller($accion_previa);
}

// Despacho
$cargador->controller('cron/cron');

// Salida
$respuesta->output();
