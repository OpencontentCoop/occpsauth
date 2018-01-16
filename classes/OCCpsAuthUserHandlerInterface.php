<?php

interface OCCpsAuthUserHandlerInterface
{
	public function login(array $data, eZModule $module);

	public function logout(eZModule $module);
}