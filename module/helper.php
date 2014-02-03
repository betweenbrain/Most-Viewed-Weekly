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
		$query = 'SELECT *
					FROM ' . $this->db->nameQuote('#__video_hits_weekly') . '
					WHERE published = 1
					ORDER BY hits DESC,
					LIMIT 5';

		$this->db->setQuery($query);
		$items = $this->db->loadObjectList();

		return $items;
	}
}