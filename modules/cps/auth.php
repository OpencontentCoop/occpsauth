<?php
/** @var eZModule $module */
$module = $Params['Module'];
$ini = eZINI::instance('occpsauth.ini');
$parserClass = $ini->variable('HandlerSettings', 'ServerVarParser');

if (class_exists($parserClass)){
	$parser = new $parserClass();
}else{
	eZDebug::writeError("Missing ini configuration occpsauth.ini[HandlerSettings]ServerVarParser");
	return $module->handleError(  eZError::KERNEL_NOT_AVAILABLE,  false, array(),  array( 'OCCpsAuthError', 1 ) );
}

$handlerClass = $ini->variable('HandlerSettings', 'UserHandler');
if (class_exists($handlerClass)){
	$handler = new $handlerClass();
}else{
	eZDebug::writeError("Missing ini configuration occpsauth.ini[HandlerSettings]UserHandler");
	return $module->handleError(  eZError::KERNEL_NOT_AVAILABLE,  false, array(),  array( 'OCCpsAuthError', 2 ) );
}

try{
	$data = $parser->parseCpsServerVars();
	return $handler->login($data, $module);
}catch(Exception $e){
	eZDebug::writeError($e->getMessage());
	return $module->handleError(  eZError::KERNEL_NOT_FOUND,  false, array(),  array( 'OCCpsAuthError', 2 ) );
}