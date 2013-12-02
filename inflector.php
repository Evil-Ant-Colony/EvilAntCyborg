<?php

class Inflector
{
    public $rules;
    
    function Inflector($rules=array())
    {
        $this->rules = $rules;
    }
    
    function context_sensitive_rule($word,$context_pre,$context_post)
    {
        return $word;
    }
    
    function inflect($v,$context_pre="",$context_post="")
    {
        $v = $this->context_sensitive_rule($v,$context_pre,$context_post);
        foreach( $this->rules as $patt => $replace )
        {
            if ( preg_match("{^$patt$}i",$v) )
            {
                return preg_replace("{^$patt$}i",$replace,$v);
            }
        }
        return $v;
    }
};

// infinitive to 3rd person singular
$english_verb = new Inflector( array(
        'be'=>'is',
        'have'=>'has',
        'say'=>'says',
        'do' => 'does',
        'don\'t' => 'doesn\'t',
        '(.*[bcdfghjklmnpqrstvwxyz]o)'=>'\1es',
        '(.*(z|s|ch|sh|j|zh))' =>'\1es',
        '(.*[bcdfghjklmnpqrstvwxyz])y' =>'\1ies',
        '(.*)' => '\1s',
    )
);

$english_genitive = new Inflector( array(
    '(.*s)'=>"\\1'",
    '(.*)' => "\\1's",
));

// you <-> me
class PronounSwapper extends Inflector
{
    function PronounSwapper($me,$you)
    {
        global $english_genitive;
        $my = $english_genitive->inflect($me);
        //$your = $english_genitive->inflect($you);
        parent::__construct(array(
            'you' => $you,
            'your' => 'its',
            'yours' => 'its',
            'yourself' => 'itself',
            'am'=>"is",
            'I\'m'=>"$me is",
            'I' => $me,
            'me' => $me,
            'my' => $my,
            'mine' => $my,
            'myself' => $me,
        ));
    }
    
    
    function context_sensitive_rule($word,$context_pre,$context_post) 
    {
        if ( strtolower($word) == 'are' && 
                ( strtolower($context_pre) == 'you' || 
                    strtolower($context_post) == 'you' ) 
            )
            return "is";
        
        return $word;
    }
}
