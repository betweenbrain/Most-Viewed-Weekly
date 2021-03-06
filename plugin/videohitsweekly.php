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

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDBO();
	}

	function onAfterRoute()
	{
		if ($this->app->isSite())
		{
			$now      = JFactory::getDate()->toUnix();
			$interval = (int) ($this->params->get('interval', 5) * 60);
			$last     = $this->params->get('last_run');

			if (($now - $last) > $interval)
			{
				$this->updateLastRun($now);

				if ($this->params->get('brightcove'))
				{
					$this->getBrightcoveWeeklyHits();
				}
				if ($this->params->get('youtube'))
				{
					$this->getYoutubeWeeklyHits();
				}
			}

			return;
		}

		$this->createTable();
	}

	/**
	 * Updates the last_run parameter stored for this plugin
	 *
	 * @param $now
	 */
	private function updateLastRun($now)
	{
		$query = ' SELECT params' .
			' FROM #__plugins' .
			' WHERE element = ' . $this->db->Quote('videohitsweekly') . '';
		$this->db->setQuery($query);

		$params             = parse_ini_string($this->db->loadResult());
		$params['last_run'] = $now;
		$paramsIni          = null;

		foreach ($params as $key => $value)
		{
			$paramsIni .= $key . '=' . $value . "\n";
		}

		$query = 'UPDATE #__plugins' .
			' SET params=' . $this->db->Quote($paramsIni) .
			' WHERE element = ' . $this->db->Quote('videohitsweekly') . '';
		$this->db->setQuery($query);
		$this->db->query();
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

	private function insertHit($id, $value)
	{
		$query = 'INSERT INTO' . $this->db->nameQuote('#__weekly_hits') .
			'(' . $this->db->nameQuote('itemId') . ',' . $this->db->nameQuote('hits') . ')' .
			' VALUES (' . $this->db->Quote($id) . ',' . $this->db->Quote($value) . ')' .
			' ON DUPLICATE KEY UPDATE ' .
			$this->db->nameQuote('hits') . '=VALUES(' . $this->db->nameQuote('hits') . ')';
		$this->db->setQuery($query);
		$this->db->query();
	}

	private function getBrightcoveWeeklyHits()
	{
		$brightcovetoken = htmlspecialchars($this->params->get('brightcovetoken'));
		$serviceUrl      = 'http://api.brightcove.com/services/library';

		foreach ($this->getVideoIds() as $id => $videoId)
		{
			$parameters = array(
				'command'      => 'find_video_by_id',
				'video_id'     => $videoId,
				'video_fields' => 'playsTrailingWeek',
				'token'        => $brightcovetoken
			);

			$curlOptions = array(
				CURLOPT_URL            => $serviceUrl,
				CURLOPT_POSTFIELDS     => http_build_query($parameters),
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HEADER         => 0
			);

			$response = $this->makeRequest($curlOptions);

			$this->insertHit($id, $response->playsTrailingWeek);
		}
	}

	private function makeRequest($curlOpt)
	{
		//open connection
		$curl = curl_init();

		// Make a POST request to get bearer token
		curl_setopt_array($curl, $curlOpt);

		//execute post
		$response = curl_exec($curl);

		//close connection
		curl_close($curl);

		return json_decode($response);
	}

	private function getYoutubeWeeklyHits()
	{
		$this->accessToken = JPATH_SITE . '/cache/plg_googleoauth/access.token';
		$youtubeChannel    = htmlspecialchars($this->params->get('youtubeChannel'));
		$url               = 'https://www.googleapis.com/youtube/analytics/v1/reports?';

		$googleoauthplugin =& JPluginHelper::getPlugin('system', 'googleoauth');
		$googleoauthparams = new JParameter($googleoauthplugin->params);
		$googleApiKey      = $googleoauthparams->get('googleapikey');

		if (file_exists($this->accessToken))
		{
			foreach ($this->getVideoIds('youtube') as $id => $videoId)
			{
				$parameters = array(
					'ids'        => 'channel==' . $youtubeChannel,
					'start-date' => date('Y-m-d', time() - (7 * 24 * 60 * 60)),
					'end-date'   => date('Y-m-d', time() - (1 * 24 * 60 * 60)),
					'metrics'    => 'views',
					'filters'    => 'video==' . $videoId,
					'key'        => $googleApiKey
				);

				$curlOptions = array(
					CURLOPT_HTTPHEADER     => array('Authorization:  Bearer ' . file_get_contents($this->accessToken)),
					CURLOPT_URL            => $url . http_build_query($parameters),
					CURLOPT_RETURNTRANSFER => 1
				);

				$response = $this->makeRequest($curlOptions);

				$this->insertHit($id, $response->rows[0][0]);

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
