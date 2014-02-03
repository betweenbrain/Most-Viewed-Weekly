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

		$this->plugin =& JPluginHelper::getPlugin('system', 'videohitsweekly');
		$this->params = new JParameter($this->plugin->params);

		$this->interval = (int) ($this->params->get('interval', 5) * 60);

		// correct value if value is under the minimum
		if ($this->interval < 300)
		{
			$this->interval = 300;
		}
	}

	function onAfterRoute()
	{
		$app = JFactory::getApplication();

		if ($app->isSite())
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
				$db = JFactory::getDbo();
				$this->params->set('last_run', $now);

				if (J_VERSION >= 1.6)
				{
					$handler = JRegistryFormat::getInstance('json');
					$params  = new JObject();
					$params->set('interval', $this->params->get('interval', 5));
					$params->set('last_run', $now);
					$params = $handler->objectToString($params, array());
					// Update plugin parameters in database
					$query = 'UPDATE #__extensions' .
						' SET params=' . $db->Quote($params) .
						' WHERE element = ' . $db->Quote('videohitsweekly') .
						' AND folder = ' . $db->Quote('system') .
						' AND enabled >= 1' .
						' AND type =' . $db->Quote('plugin') .
						' AND state >= 0';
					$db->setQuery($query);
					$db->query();
					/**
					 * Do 1.6+ only stuff here
					 *
					 * */
				}
				else
				{
					// Retrieve saved parameters from database
					$query = ' SELECT params' .
						' FROM #__plugins' .
						' WHERE element = ' . $db->Quote('videohitsweekly') . '';
					$db->setQuery($query);
					$params = $db->loadResult();
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
						' SET params=' . $db->Quote($params) .
						' WHERE element = ' . $db->Quote('videohitsweekly') .
						' AND folder = ' . $db->Quote('system') .
						' AND published >= 1';
					$db->setQuery($query);
					$db->query();
					/**
					 * Do 1.5 only stuff here
					 *
					 * */
				}

				// Do stuff for all versions here
				die('Pseudocron executed!');
			}
		}

		return false;
	}
}