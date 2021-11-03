<?php

namespace Server\Events;

class OnAfterCommandExecute
{


	/**
	 * @param mixed $data
	 */
	public function __construct(public mixed $data)
	{
	}

}
