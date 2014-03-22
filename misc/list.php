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

class MelanoListNode
{
	public $next;
	public $prev;
	public $value;
	
	function __construct($value,MelanoListNode $prev=null,MelanoListNode $next=null)
	{
		$this->value = $value;
		$this->prev = $prev;
		$this->next = $next;
	}
}

class MelanoListIterator implements Iterator
{
	protected $node;
	protected $list;
	protected $reverse;
	
	function __construct(MelanoList $list, MelanoListNode $node = null,$reverse=false)
	{
		$this->list = $list;
		$this->node = $node;
		$this->reverse = $reverse;
	}
// accessors
	function get_list()
	{
		return $this->list;
	}
	
	function node()
	{
		return $this->node;
	}
	
	function value()
	{
		if ( $this->node )
			return $this->node->value;
		return null;
	}
	
	function is_reverse()
	{
		return $this->reverse;
	}

// PHP iterator
	function current()
	{
		return $this->value();
	}
	function key()
	{
		return $this->current();
	}
	function next()
	{
		if ( $this->node )
			$this->node = $this->reverse ? $this->node->prev : $this->node->next;
	}
	function prev()
	{
		if ( $this->node )
			$this->node = $this->reverse ? $this->node->next : $this->node->prev;
	}
	function rewind()
	{
		$b = $this->is_reverse() ? $this->list->rbegin() : $this->list->begin();
		$this->node = $b->node;
	}
	function valid()
	{
		return  $this->list != null && $this->node != null;
	}
}

/**
 * \brief Doubly linked list
 *
 * Allows:
 * * forward and reverse iterations, 
 * * insertion and removal of elements in the middle, 
 * * constant time count()
 * * C++-style begin/front operations
 */
class MelanoList implements IteratorAggregate, Countable
{
	private $first, $last, $count=0;
	
	function __construct($elements=array())
	{
		foreach($elements as $e)
			$this->push_back($e);
	}
	
	/// O(1)
	function count()
	{
		return $this->count;
	}
	
	function is_empty()
	{
		return $this->count == 0;
	}
	
	function clear()
	{
		$this->first = $this->last = null;
		$this->count = 0;
	}
	
	function begin()
	{
		return new MelanoListIterator($this,$this->first,false);
	}
	
	function rbegin()
	{
		return new MelanoListIterator($this,$this->last,true);
	}
	
	/**
	 * \brief Insert  a value before the given iterator
	 * \return iterator to the new element
	 */
	function insert_before(MelanoListIterator $it, $value)
	{
		if ( !$this->first )
			$node = $this->last = $this->first = new MelanoListNode($value,null,null);
		else if ( $it->valid() && $it->get_list() == $this )
		{
			$prev = $it->node()->prev;
			$node = new MelanoListNode($value,$prev,$it->node());
			if ( $prev )
				$prev->next = $node;
			else
				$this->first = $node;
			$it->node()->prev = $node;
		}
		else
			return null;

		$this->count++;
		return new MelanoListIterator($this,$node,$it->is_reverse());
	}
	
	/**
	 * \brief Insert  a value before the given iterator
	 * \return iterator to the new element
	 */
	function insert_after(MelanoListIterator $it, $value)
	{
		if ( !$this->first )
			$node = $this->last = $this->first = new MelanoListNode($value,null,null);
		else if ( $it->valid() && $it->get_list() == $this )
		{
			$next = $it->node()->next;
			$node = new MelanoListNode($value,$it->node(),$next);
			if ( $next )
				$next->prev = $node;
			else
				$this->last = $node;
			$it->node()->next = $node;
		}
		else
			return null;

		$this->count++;
		return new MelanoListIterator($this,$node,$it->is_reverse());
	}
	
	
	/**
	 * \return iterator to the next element
	 */
	function erase(MelanoListIterator $it)
	{
		if ( !$it->valid() || $it->get_list() != $this )
			return null;
		$this->count --;
		$p = $it->prev;
		$n = $it->next;
		$it->node()->next = $it->node()->prev = null;
		if ( $p ) $p->next = $n;
		if ( $n ) $n->prev = $p;
		return new MelanoListIterator($this,$it->is_reverse()?$p:$n,$it->is_reverse());
	}
	
	function front()
	{
		return $this->first ? $this->first->value : null;
	}
	
	function back()
	{
		return $this->last ? $this->last->value : null;
	}
	
	function push_front($value)
	{
		$this->count++;
		$node = new MelanoListNode($value,null,$this->first);
		
		if ( $this->first )
			$this->first->prev = $node;
			
		$this->first = $node;
		
		if ( !$this->last ) 
			$this->last = $node;
	}
	
	function push_back($value)
	{
		$this->count++;
		$node = new MelanoListNode($value,$this->last,null);
		
		if ( $this->last )
			$this->last->next = $node;
			
		$this->last = $node;
		
		if ( !$this->first )
			$this->first = $node;
	}
	
	function pop_front()
	{
		if ( $this->first )
		{
			$f = $this->first;
			$this->first = $this->first->next;
			$f->next = null;
			if ( $this->first )
				$this->first->prev = null;
			else
				$this->last = null;
			$this->count--;
		}
	}
	
	function pop_back()
	{
		if ( $this->last )
		{
			$l = $this->last;
			$this->last = $this->last->prev;
			$l->prev = null;
			if ( $this->last )
				$this->last->next = null;
			else
				$this->first = null;
			$this->count--;
		}
	}
	
	function getIterator()
	{
		return $this->begin();
	}
}

/**
 * \brief A priority queue which preserves insertion order for elements with the same priority
 *
 * Insert-sorted liked list
 *
 * Complexity:
 * * insertion O(n/2)
 * * extraction O(1)
 */
class StablePriorityQueue implements IteratorAggregate, Countable
{
	private $list;
	public $max_size = 0; ///< Maximum number of elements, if 0 unlimited
	
	function __construct()
	{
		$this->list = new MelanoList;
	}
	
	function count()
	{
		return $this->list->count();
	}
	
	/**
	 * When iterating, the elements are \b StdClass objects with \c value and \c priority attributes
	 */
	function getIterator()
	{
		return $this->list->begin();
	}
	
	function is_empty()
	{
		return $this->list->count() == 0;
	}
	
	function top()
	{
		return $this->is_empty() ? null : $this->list->front()->value;
	}
	
	function top_priority()
	{
		return $this->is_empty() ? null : $this->list->front()->priority;
	}
	
	function bottom_priority()
	{
		return $this->is_empty() ? null : $this->list->back()->priority;
	}
	
	/**
	 * \brief Remove and return the top element
	 * \param $with_priority if true a \b StdClass with \c value and \c priority fields is returned
	 */
	function pop($with_priority=false)
	{
		$t = $this->list->front();
		$this->list->pop_front();
		if ( $with_priority || $t == null )
			return $t;
		return $t->value;
	}
	
	function push($value,$priority)
	{
		$e = new StdClass;
		$e->value = $value;
		$e->priority = $priority;
		if ( ($this->top_priority()+$this->bottom_priority()) / 2 < $priority )
			$this->push_top($e);
		else
			$this->push_bottom($e);
			
		if ( $this->max_size )
			while($this->list->count() > $this->max_size )
				$this->list->pop_back();
		
	}
	
	function clear()
	{
		$this->list->clear();
	}
	
	private function push_bottom($e)
	{
		$it = $this->list->rbegin();
		while($it->valid() && $it->value()->priority < $e->priority )
			$it->next();
			
		if ( !$it->valid() )
			$this->list->push_front($e);
		else
			$this->list->insert_after($it,$e); 
	}
	
	private function push_top($e)
	{
		$it = $this->list->begin();
		while($it->valid() && $it->value()->priority >= $e->priority )
			$it->next();
			
		if ( !$it->valid() )
			$this->list->push_back($e);
		else
			$this->list->insert_before($it,$e); 
	}
}