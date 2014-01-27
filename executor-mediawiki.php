<?php
require_once("bot-driver.php");

function parse_wikitext(&$wikitext)
{
    $wikiarr = str_split($wikitext);
    return  parse_wikitext_start($wikiarr);
}

function parse_wikitext_start(&$wikiarr)
{
    while(count($wikiarr)>0)
    {
        $c = array_shift($wikiarr);
        if ( $c == '{' )
            parse_wikitext_skip_template($wikiarr);
        else if ( !ctype_space($c) )
        {
            array_unshift($wikiarr,$c);
            return parse_wikitext_sentence($wikiarr);
        }
    }
}

function parse_wikitext_sentence(&$wikiarr)
{
    $text = "";
    while(count($wikiarr)>0)
    {
        $c = array_shift($wikiarr);
        if ( $c == '[' )
            $text .= parse_wikitext_link($wikiarr);
        else if ( $c == '{' )
            parse_wikitext_skip_template($wikiarr);
        else if ( $c == '.' )
        {
            $c = array_shift($wikiarr);
            if ( ctype_alpha($c) )
                $text .= ".$c";
            else
                return $text;
        }
        else
            $text .= $c;
    }
}

function parse_wikitext_link(&$wikiarr)
{
    $c = array_shift($wikiarr);
    $text = "";
    if ( $c == '[' )
    {
        while(count($wikiarr)>0)
        {
            $c = array_shift($wikiarr);
            if ( $c == '|' )
            {
                $text = "";
                break;
            }
            else if ( $c == ']' )
            {
                array_shift($wikiarr);
                return $text;
            }
            else if ( $text == 'File:' ||  $text == 'Image:' )
            {
                parse_wikitext_skip_template($wikiarr,2,'[',']');
                return "";
            }
            else
                $text .= $c;
        }
        while(count($wikiarr)>0)
        {
            $c = array_shift($wikiarr);
            if ( $c == ']' )
            {
                array_shift($wikiarr);
                return $text;
            }
            else
                $text .= $c;
        }
    }
    else
    {
        while($c != " " && count($wikiarr)>0)
        {
            $c = array_shift($wikiarr);
        }
        while(count($wikiarr)>0)
        {
            $c = array_shift($wikiarr);
            if ( $c == ']' )
                return $text;
            $text .= $c;
        }
    }
    return $text;
}

function parse_wikitext_skip_template(&$wikiarr,$n=1,$open='{',$close='}')
{
    while($n > 0 && count($wikiarr)>0)
    {
        $c = array_shift($wikiarr);
        if ( $c == $open )
            $n++;
        else if ( $c == $close )
            $n--;
    }
}

/// \note read api tos and set user agent befor calling this
function mediawiki_describe($title,$api_url)
{
    $title= urlencode($title);
    $url="$api_url?format=json&action=query&titles=$title&redirects&prop=revisions&rvprop=content&rvsection=0";
    $reply=json_decode(file_get_contents($url),true);

    //print_r($reply);
    $p = @array_values($reply["query"]["pages"]);
    $wikitext = @$p[0]["revisions"][0]["*"];

    $wikitext = preg_replace("(\n[*:;])","",$wikitext);
    $wikitext = preg_replace("(''+)","",$wikitext);
    $wikitext = preg_replace("(<([a-z]+)[^>]*/>)is","",$wikitext);
    $wikitext = preg_replace("(<([a-z]+)[^>]*>.*?</\\1>)is","",$wikitext);
    $wikitext = preg_replace("(\\s+)"," ",$wikitext);
    $wikitext = strip_tags($wikitext);
    $wikitext = html_entity_decode($wikitext);
    $wikitext = parse_wikitext($wikitext);
    $wikitext = preg_replace("(^\\s+)","",$wikitext);
    
    return $wikitext;
}

function elide_string($string,$length)
{
    $lines = explode("\n",wordwrap($string,$length));
    $text = $lines[0];
    if ( count($lines) > 1 )
        $text .= "...";
    return $text;
}



class Executor_Wiki extends CommandExecutor
{
	public $api_url;
	
	
	function Executor_Wiki($trigger,$service,$api_url)
	{
		parent::__construct($trigger,null,"$trigger Term...","Search the term on $service");
		$this->api_url = $api_url;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
        $text = mediawiki_describe($cmd->param_string(),$this->api_url);
        if ( $text == "" )
            $bot->say($cmd->channel, "I don't know anything about ".$cmd->param_string());
        else
            $bot->say($cmd->channel, elide_string($text,400));
	}
}


class Executor_Wiki_Opensearch extends CommandExecutor
{
	public $api_url;
	
	
	function Executor_Wiki_Opensearch($trigger,$service,$api_url)
	{
		parent::__construct($trigger,null,"$trigger Term...","Search the term on $service");
		$this->api_url = $api_url;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$url=$this->api_url."?action=opensearch&format=xml&limit=1&search=".urlencode($cmd->param_string());
		$reply = new SimpleXMLElement(file_get_contents($url));
		if ( isset($reply->Section->Item->Description) )
			$bot->say($cmd->channel, elide_string($reply->Section->Item->Description,400));
		else
		{
			echo "$url\n";
			print_r($reply);
			$bot->say($cmd->channel, "I don't know anything about ".$cmd->param_string());
		}
	}
}
