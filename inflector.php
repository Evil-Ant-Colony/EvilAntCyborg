<?php

class Inflector
{
    public $rules;
    
    function Inflector($rules=array())
    {
        $this->rules = $rules;
    }
    
    function inflect($v)
    {
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
        '(.*[bcdfghjklmnpqrstvwxyz]o)'=>'\1es',
        '(.*(z|s|ch|sh|j|zh))' =>'\1es',
        '(.*y)' =>'\1ies',
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
    function PronounSwapper($me)
    {
        global $english_genitive;
        $my = $english_genitive->inflect($me);
        parent::__construct(array(
            'you' => 'it',
            'your' => 'its',
            'yours' => 'its',
            'yourself' => 'itself',
            'I' => $me,
            'me' => $me,
            'my' => $my,
            'mine' => $my,
            'myself' => $me,
        ));
    }
}
