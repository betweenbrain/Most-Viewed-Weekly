<?php defined('_JEXEC') or die;

/**
 * File       default.php
 * Created    2/3/14 1:24 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

include_once(JPATH_SITE . '/components/com_k2/helpers/route.php');

$options = array('weekly' => 'Weekly', 'forever' => 'All TIme');
$criteria = JRequest::getVar('hits');

if (count($items)): ?>
	<div id="looper<?php echo $module->id; ?>" data-looper="go" data-interval="false" class="looper side slide featured video-slider<?php echo $moduleclass_sfx ?>">
		<?php if (count($items) > 3) : ?>
			<div class="nav">
				<form action="" method="post">
					<select name="hits" onchange="this.form.submit()">
						<?php foreach ($options as $value => $text) : ?>
							<option value="<?php echo $value ?>" <?php if ($criteria === $value)
							{
								echo 'selected="selected"';
							} ?>><?php echo $text ?></option>
						<?php endforeach ?>
					</select>
				</form>
				<a data-looper="prev" class="prev" href="#looper<?php echo $module->id; ?>">Previous</a>
				<a data-looper="next" class="next" href="#looper<?php echo $module->id; ?>">Next</a>
			</div>
		<?php endif ?>
		<ol class="looper-inner">
			<?php
			$last = count($items) - 1;
			$isItem = false;
			foreach ($items as $key => $item): ?>
			<?php // Trim title if longer than 50
			if (strlen($item->title) >= 60)
			{
				// Trim to 35 chars
				$item->shortTitle = substr($item->title, 0, 52);
				// Trim non-alphanumeric and spaces off end
				$item->shortTitle = preg_replace('/[^a-z0-9]+$/i', '', $item->shortTitle);
				// Trim string back to nearest space
				$item->shortTitle = preg_replace('/[^\s]+$/i', '', $item->shortTitle);
				// Trim off space left over and add elipses
				$item->shortTitle = preg_replace('/[^a-z0-9]+$/i', '', $item->shortTitle) . '&hellip;';
			}
			else
			{
				$item->shortTitle = $item->title;
			}?>
			<?php
			/**
			 * Parse K2 plugins data for each field
			 */
			$plugins = parse_ini_string($item->plugins);
			$item->videoImage = $plugins['universal_fieldsitemImage'];
			$item->videoDuration = $plugins['video_datavideoDuration'];
			?>
			<?php
			/**
			 * Get item link
			 */
			$item->link = K2HelperRoute::getItemRoute($item->id . ':' . urlencode($item->alias), $item->catid);
			$item->link = urldecode(JRoute::_($item->link));
			?>
			<?php switch ($key)
			{
				case 0 :
					$class  = 'featured';
					$isItem = true;
					break;

				case (($key > 0) && ($key < 3)) :
					$class  = 'last';
					$isItem = false;
					break;

				case ($key >= 3) :
					switch (fmod($key, 6))
					{
						case 3 :
							$class  = 'first';
							$isItem = true;
							break;
						case 4 :
							$class  = 'middle';
							$isItem = false;
							break;
						case 5 :
							$class  = 'last';
							$isItem = false;
							break;
						case 0 :
							$class  = 'first';
							$isItem = false;
							break;
						case 1 :
							$class  = 'middle';
							$isItem = false;
							break;
						case 2 :
							$class  = 'last';
							$isItem = false;
							break;
					}
					break;
			} ?>
			<?php // Change image file name based on being featured
			if ($class == 'featured')
			{
				$item->videoImage = str_replace('_280', '_902', $item->videoImage);
			}
			else
			{
				$item->videoImage = str_replace('_902', '_280', $item->videoImage);
			}
			?>
			<?php if (($key > 0) && ($isItem))
			{
				echo '</div>';
			}
			if ($isItem) : ?>
			<div class="item">
				<?php endif ?>
				<li class="<?php echo $class ?>">
					<a class="blockContainer" href="<?php echo $item->link; ?>" title="<?php echo $item->title ?>">
						<div class="itemDescription">
							<p class="itemTitle">
								<span class="order"><?php echo($key + 1) ?></span>
								<span class="title"><?php echo $item->shortTitle ?>
									<?php if ($item->videoDuration) : ?>
										<span class="duration"><?php echo $item->videoDuration ?></span>
									<?php endif ?>
								</span>
							</p>
						</div>
						<img src="<?php echo $item->videoImage; ?>" title="<?php echo $item->title; ?>" />
					</a>
				</li>
				<?php if (($key == $last) && (!$isItem))
				{
					echo '</div>';
				} ?>
				<?php endforeach; ?>
		</ol>
	</div>
<?php endif;