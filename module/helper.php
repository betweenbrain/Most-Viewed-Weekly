<?php defined('_JEXEC') or die;

/**
 * File       helper.php
 * Created    2/3/14 1:24 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

jimport('joomla.application.router');

class modMostviewedweeklyHelper
{
	public function __construct($params)
	{
		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDbo();
	}

	public function getItems()
	{
		$query = 'SELECT k2.id as id, k2.title as title, k2.plugins as plugins, weekly.hits as hits ' .
			' FROM ' . $this->db->nameQuote('#__k2_items') . ' as k2' .
			' LEFT JOIN ' . $this->db->nameQuote('#__weekly_hits') . ' as weekly' .
			' ON weekly.itemId = k2.id' .
			' WHERE k2.published = 1' .
			' AND k2.trash = 0' .
			' ORDER BY hits DESC' .
			' LIMIT 5';

		$this->db->setQuery($query);
		$items = $this->db->loadObjectList();

		return $items;
	}
}