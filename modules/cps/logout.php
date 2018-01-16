<?php
/** @var eZModule $module */
$module = $Params['Module'];
$ini = eZINI::instance('occpsauth.ini');
$parserClass = $ini->variable('HandlerSettings', 'ServerVarParser');

$handlerClass = $ini->variable('HandlerSettings', 'UserHandler');
if (class_exists($handlerClass)){
	$handler = new $handlerClass();
}else{
	eZDebug::writeError("Missing ini configuration occpsauth.ini[HandlerSettings]UserHandler");
	return $module->redirectTo('/');
}

try{
	return $handler->logout($module);	
}catch(Exception $e){
	eZDebug::writeError($e->getMessage());	
}
