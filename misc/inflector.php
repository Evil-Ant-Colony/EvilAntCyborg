<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2014 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Inflector
{
    public $rules;
    
    function __construct($rules=array())
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
        'can'=>'can',
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
    function __construct($me,$you)
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
