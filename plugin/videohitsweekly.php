<?php defined('_JEXEC') or die;

/**
 * File       video_hits_weekly.php
 * Created    2/3/14 1:20 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

class plgSystemVideohitsweekly extends JPlugin
{
	// Hardcoded minimum of 5 minutes
	protected $interval = 300;

	function plgSystemPseudocron(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$this->app      = JFactory::getApplication();
		$this->db       = JFactory::getDBO();
		$this->plugin   =& JPluginHelper::getPlugin('system', 'videohitsweekly');
		$this->params   = new JParameter($this->plugin->params);
		$this->interval = (int) ($this->params->get('interval', 5) * 60);

		// correct value if value is under the minimum
		if ($this->interval < 300)
		{
			$this->interval = 300;
		}
	}

	function onAfterRoute()
	{
		$this->app = JFactory::getApplication();

		if ($this->app->isSite())
		{
			$now  = JFactory::getDate();
			$now  = $now->toUnix();
			$last = $this->params->get('last_run');
			$diff = $now - $last;

			if ($diff > $this->interval)
			{

				$version = new JVersion();
				define('J_VERSION', $version->getShortVersion());
				jimport('joomla.registry.format');
				$this->db = JFactory::getDbo();
				$this->params->set('last_run', $now);

				// Retrieve saved parameters from database
				$query = ' SELECT params' .
					' FROM #__plugins' .
					' WHERE element = ' . $this->db->Quote('videohitsweekly') . '';
				$this->db->setQuery($query);
				$params = $this->db->loadResult();
				// Check if last_run parameter has been previously saved.
				if (preg_match('/last_run=/', $params))
				{
					// If it has been, update it.
					$params = preg_replace('/last_run=([0-9]*)/', 'last_run=' . $now, $params);
				}
				else
				{
					// Add last_run parameter to databse if it has not been recored before.
					// TODO: Currently adding last_run to beginning of param string due to extra "\n" when using $params .=
					$params = 'last_run=' . $now . "\n" . $params;
				}
				// Update plugin parameters in database
				$query = 'UPDATE #__plugins' .
					' SET params=' . $this->db->Quote($params) .
					' WHERE element = ' . $this->db->Quote('videohitsweekly') .
					' AND folder = ' . $this->db->Quote('system') .
					' AND published >= 1';
				$this->db->setQuery($query);
				$this->db->query();

				$this->createTable();
				$this->getBrightcoveWeeklyHits();
				$this->getYoutubeWeeklyHits();

			}
		}

		return false;
	}

	private function createTable()
	{
		$query = "CREATE TABLE IF NOT EXISTS `jos_weekly_hits` (
					`id`           INT(11)     UNSIGNED NOT NULL AUTO_INCREMENT,
					`itemId`       INT(11)              NOT NULL,
					`hits`         INT(11)              NOT NULL,
					PRIMARY KEY (`id`),
					UNIQUE INDEX (`itemId`)
				)
					ENGINE =MyISAM
					AUTO_INCREMENT =0
					DEFAULT CHARSET =utf8;";
		$this->db->setQuery($query);
		$this->db->query();
	}

	private function getBrightcoveWeeklyHits()
	{
		foreach ($this->getVideoIds() as $id => $videoId)
		{
			$brightcovetoken = htmlspecialchars($this->params->get('brightcovetoken'));
			$providerfield   = htmlspecialchars($this->params->get('providerfield'));
			$videoIdField    = htmlspecialchars($this->params->get('videoidfield'));
			$videoData       = null;

			$serviceUrl = 'http://api.brightcove.com/services/library';

			$parameters = array(
				'command'      => 'find_video_by_id',
				'video_id'     => $videoId,
				'video_fields' => 'playsTrailingWeek',
				'token'        => $brightcovetoken
			);

			$query = http_build_query($parameters);

			//open connection
			$curl = curl_init();

			// Make a POST request to get bearer token
			curl_setopt_array($curl, Array(
				CURLOPT_URL            => $serviceUrl,
				CURLOPT_POSTFIELDS     => $query,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HEADER         => 0
			));

			//execute post
			$response = curl_exec($curl);
			$response = json_decode($response);

			//close connection
			curl_close($curl);

			$query = 'INSERT INTO' . $this->db->nameQuote('#__weekly_hits') .
				'(' . $this->db->nameQuote('itemId') . ',' . $this->db->nameQuote('hits') . ')' .
				' VALUES (' . $this->db->Quote($id) . ',' . $this->db->Quote($response->playsTrailingWeek) . ')' .
				' ON DUPLICATE KEY UPDATE ' .
				$this->db->nameQuote('hits') . '=VALUES(' . $this->db->nameQuote('hits') . ')';
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	private function getYoutubeWeeklyHits()
	{
		$this->accessToken = JPATH_SITE . '/cache/plg_googleoauth/access.token';

		// Fetch parameters via database query
		$this->db = JFactory::getDBO();
		$sql      = 'SELECT ' . $this->db->nameQuote('params') .
			' FROM ' . $this->db->nameQuote('#__plugins') .
			' WHERE ' . $this->db->nameQuote('element') . ' = ' . $this->db->quote('googleoauth');
		$this->db->setQuery($sql);
		$params = $this->db->loadResult();
		$params = parse_ini_string($params);

		foreach ($params as $name => $value)
		{
			$this->{$name} = $value;
		}

		foreach ($this->getVideoIds('youtube') as $id => $videoId)
		{
			if (file_exists($this->accessToken))
			{
				$parameters = array(
					'ids'        => 'channel==UCiAY9AsPrNPa8T4sMP23_vg',
					'start-date' => date('Y-m-d', time() - (7 * 24 * 60 * 60)),
					'end-date'   => date('Y-m-d', time() - (1 * 24 * 60 * 60)),
					'metrics'    => 'views',
					'filters'    => 'video==' . $videoId,
					'key'        => $this->googleApiKey
				);

				$url   = 'https://www.googleapis.com/youtube/analytics/v1/reports?';
				$query = http_build_query($parameters);
				$curl  = curl_init();

				curl_setopt_array($curl, Array(
					CURLOPT_HTTPHEADER     => array('Authorization:  Bearer ' . file_get_contents($this->accessToken)),
					CURLOPT_URL            => $url . $query,
					CURLOPT_RETURNTRANSFER => 1
				));

				$response = curl_exec($curl);
				$response = json_decode($response);

				$hits = $response->rows[0][0];

				$query = 'INSERT INTO' . $this->db->nameQuote('#__weekly_hits') .
					'(' . $this->db->nameQuote('itemId') . ',' . $this->db->nameQuote('hits') . ')' .
					' VALUES (' . $this->db->Quote($id) . ',' . $this->db->Quote($hits) . ')' .
					' ON DUPLICATE KEY UPDATE ' .
					$this->db->nameQuote('hits') . '=VALUES(' . $this->db->nameQuote('hits') . ')';
				$this->db->setQuery($query);
				$this->db->query();
			}
		}
	}

	private function getK2Items()
	{
		$k2categories = htmlspecialchars($this->params->get('k2category'));

		$query = "SELECT id, plugins
		 FROM #__k2_items
		 WHERE catid IN ($k2categories)
		 AND published = 1
		 AND trash = 0";
		$this->db->setQuery($query);
		$items = $this->db->loadAssocList('id');

		return $items;
	}

	private function getVideoIds($provider = 'brightcove')
	{
		$items = $this->getK2Items();

		foreach ($items as $id => $plugins)
		{
			$params = parse_ini_string($plugins['plugins']);

			switch ($params['video_datavideoProvider'])
			{
				case('brightcove'):
					$brightcoveVideoIds[$id] = $params['video_datavideoID'];
					break;
				case('youtube'):
					$youtubeVideoIds[$id] = $params['video_datavideoID'];
					break;
			}
		}

		switch ($provider)
		{
			case('brightcove'):
				return $brightcoveVideoIds;
				break;
			case('youtube'):
				return $youtubeVideoIds;
				break;
		}
	}
}
