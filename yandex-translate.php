<?php


class YandexAPI
{
    public $url = "https://translate.yandex.net/api/v1.5/tr.json";
    public $key;
    public $language_codes = array();
    public $language_directions = array();
    
    function YandexAPI($key)
    {
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
        echo "Translatin $lang: $text\n";
        $transl = $this->call_api('translate',array('lang'=>$lang,'text'=>$text));
        if ( $transl["code"] != 200 )
            return null;
        return $transl["text"][0];
    }
}