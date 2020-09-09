<?php

namespace Bugo\LightPortal\Addons\News;

use Bugo\LightPortal\Helpers;

/**
 * News
 *
 * @package Light Portal
 * @link https://dragomano.ru/mods/light-portal
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2019-2020 Bugo
 * @license https://spdx.org/licenses/GPL-3.0-or-later.html GPL-3.0-or-later
 *
 * @version 1.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class News
{
	/**
	 * Specify an icon (from the FontAwesome Free collection)
	 *
	 * Указываем иконку (из коллекции FontAwesome Free)
	 *
	 * @var string
	 */
	public static $addon_icon = 'far fa-newspaper';

	/**
	 * Get the news list of the forum
	 *
	 * Получаем список новостей форума
	 *
	 * @return string
	 */
	public static function getData()
	{
		global $boarddir;

		require_once($boarddir . '/SSI.php');
		setupThemeContext();

		return ssi_news('return');
	}

	/**
	 * Form the content block
	 *
	 * Формируем контент блока
	 *
	 * @param string $content
	 * @param string $type
	 * @param int $block_id
	 * @param int $cache_time
	 * @return void
	 */
	public static function prepareContent(&$content, $type, $block_id, $cache_time)
	{
		global $txt;

		if ($type !== 'news')
			return;

		$news = Helpers::getFromCache('news_addon_b' . $block_id, 'getData', __CLASS__, $cache_time);

		ob_start();
		echo $news ?: $txt['lp_news_addon_no_items'];
		$content = ob_get_clean();
	}
}
