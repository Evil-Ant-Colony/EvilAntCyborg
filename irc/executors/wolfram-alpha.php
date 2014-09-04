<?php

abstract class WolframAlpha extends CommandExecutor
{
	public $app_id; ///< App ID
	public $base_url = "http://api.wolframalpha.com/v2/query";
	public $units = "metric";

	function __construct($app_id,$trigger,$auth,$synopsis,$help)
	{
		parent::__construct($trigger,$auth,$synopsis,$help);
		$this->app_id = $app_id;
	}
	
	function get_xml($input)
	{
		$url = "{$this->base_url}?appid={$this->app_id}&excludepodid=Input&units={$this->units}&input=".urlencode($input);
		Logger::log("std","<","\x1b[1mGET\x1b[0m $url",4);
		return simplexml_load_file($url);
	}

}

class Executor_WolframAlpha_Text  extends WolframAlpha
{
	
	function __construct($app_id,$trigger="evaluate",$auth='admin')
	{
		parent::__construct($app_id,$trigger,$auth,"$trigger expression", 
			'Evaluate expression using Wolfram|Alpha');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) == 0 )
			return;
        
		$xml = $this->get_xml($cmd->param_string(false));
		
		$attr = $xml->attributes();
		if ( $attr['error'] == 'true' )
		{
			$bot->say($cmd->channel,"Error: ".$xml->error->msg);
			return;
		}
		
		$result = $xml->xpath('/queryresult/pod[1]/subpod[1]/plaintext');
		if ( $result && is_array($result))
			$bot->say($cmd->channel,(string)$result[0]);
		else
			$bot->say($cmd->channel,"Sorry, but I don't know");
	}
	
}

class Executor_WolframAlpha_Plot  extends WolframAlpha
{
	public $empty_message = "Cannot plot that";
	
	function __construct($app_id,$trigger="plot",$auth='admin')
	{
		parent::__construct($app_id,$trigger,$auth,"$trigger expression", 
			'Plot expression using Wolfram|Alpha');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) == 0 )
			return;
        
		$xml = $this->get_xml($cmd->param_string(false));
		
		$attr = $xml->attributes();
		if ( $attr['error'] == 'true' )
		{
			$bot->say($cmd->channel,"Error: ".$xml->error->msg);
			return;
		}
		
		$result = $xml->xpath('/queryresult/pod[1]/subpod[1]/img/@src');
		if ( $result && is_array($result))
			$bot->say($cmd->channel,urldecode((string)$result[0]));
		else
			$bot->say($cmd->channel,$this->empty_message);
	}
	
}