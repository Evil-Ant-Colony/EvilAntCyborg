<?php

class Executor_YandexTranslate extends CommandExecutor
{
    public $url = "https://translate.yandex.net/api/v1.5/tr.json";
    public $key;
    public $language_codes = array();
    public $language_directions = array();
    
    function Executor_YandexTranslate($key)
    {
		parent::__construct("translate",null,'translate [from Language] [into Language] Phrase...',
			'Make the bot translate the given Phrase (using Yandex)');
        $this->key = $key;
        
        $langs=$this->call_api('getLangs',array('ui'=>'en'));
        $this->language_directions = $langs["dirs"];
        foreach($langs["langs"] as $code => $name)
        {
            $this->language_codes[$name] = $code;
        }
    }
    
    
    function call_api($action,$params)
    {
        $url = "{$this->url}/$action?key={$this->key}";
        foreach($params as $k => $v )
            $url .= "&$k=".urlencode($v);
        return json_decode(file_get_contents($url),true);
    }
    
    function translate($lang,$text)
    {
        echo "Translating $lang: $text\n";
        $transl = $this->call_api('translate',array('lang'=>$lang,'text'=>$text));
        if ( $transl["code"] != 200 )
            return null;
        return $transl["text"][0];
    }
    
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) > 2 )
		{
			$lang_from = $lang_to = 'English';
			$lang_from_code = $lang_to_code = 'en';
			
			$direction = array_shift($cmd->params);
			if ( $direction == 'from' )
			{
				$lang_from = ucfirst(array_shift($cmd->params));
				if ( isset($this->language_codes[$lang_from]) )
					$lang_from_code = $this->language_codes[$lang_from];
				else
				{
					$bot->say($cmd->channel,"I'm sorry but I don't speak $lang_from");
					return;
				}
				if ( $cmd->params[0] == 'into' )
					$direction = array_shift($cmd->params);
			}
			
			if ( $direction == 'into' )
			{
				
				$lang_to = ucfirst(array_shift($cmd->params));
				if ( isset($this->language_codes[$lang_to]) )
					$lang_to_code = $this->language_codes[$lang_to];
				else
				{
					$bot->say($cmd->channel,"I'm sorry but I don't speak $lang_to");
					return;
				}
			}
			
			$lang_dir = "$lang_from_code-$lang_to_code";
			
			if ( in_array($lang_dir,$this->language_directions) )
			{
				$translation=$this->translate($lang_dir,implode(' ',$cmd->params));
				if ( $translation != null )
					$bot->say($cmd->channel,$translation);
				else
					$bot->say($cmd->channel,"I'm sorry but I can't translate that...");
			}
			else
			{
				$bot->say($cmd->channel,"I'm sorry but I can't translate $lang_from to $lang_to");
			}
			
		}
	}
}