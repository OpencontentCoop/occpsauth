<?php

class OCCpsAuthServerVarParser implements OCCpsAuthServerVarParserInterface
{
	public function parseCpsServerVars()
	{		
		$data = array();
		foreach ($_SERVER as $key => $value) {
			if (strrpos($key, 'shibb_pat_attribute_') === 0){
				$cleanKey = str_replace('shibb_pat_attribute_', '', $key);
				$data[$cleanKey] = $value;
			}
		}

		return $data;
	}
}