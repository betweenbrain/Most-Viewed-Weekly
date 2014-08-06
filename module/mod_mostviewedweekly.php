<?php defined('_JEXEC') or die;

/**
 * File       mostviewedweekly.php
 * Created    2/3/14 1:23 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

require_once __DIR__ . '/helper.php';

$helper = new modMostviewedweeklyHelper($params);
$items = $helper->getItems();

require(JModuleHelper::getLayoutPath('mod_mostviewedweekly'));