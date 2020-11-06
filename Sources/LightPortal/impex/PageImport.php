<?php

namespace Bugo\LightPortal\Impex;

use Bugo\LightPortal\Helpers;

/**
 * PageImport.php
 *
 * @package Light Portal
 * @link https://dragomano.ru/mods/light-portal
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2019-2020 Bugo
 * @license https://spdx.org/licenses/GPL-3.0-or-later.html GPL-3.0-or-later
 *
 * @version 1.2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class PageImport extends Import
{

	/**
	 * Page import
	 *
	 * Импорт страниц
	 *
	 * @return void
	 */
	public static function prepare()
	{
		global $context, $txt, $scripturl;

		loadTemplate('LightPortal/ManageImport');

		$context['page_title']      = $txt['lp_portal'] . ' - ' . $txt['lp_pages_import'];
		$context['page_area_title'] = $txt['lp_pages_import'];
		$context['canonical_url']   = $scripturl . '?action=admin;area=lp_pages;sa=import';

		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title'       => LP_NAME,
			'description' => $txt['lp_pages_import_tab_description']
		);

		$context['sub_template'] = 'manage_import';

		self::run();
	}

	/**
	 * Import from an XML file
	 *
	 * Импорт из XML-файла
	 *
	 * @return void
	 */
	protected static function run()
	{
		global $db_temp_cache, $db_cache, $smcFunc, $context;

		if (empty($_FILES['import_file']))
			return;

		// Might take some time.
		@set_time_limit(600);

		// Don't allow the cache to get too full
		$db_temp_cache = $db_cache;
		$db_cache = [];

		$file = $_FILES['import_file'];

		if ($file['type'] !== 'text/xml')
			return;

		$xml = simplexml_load_file($file['tmp_name']);

		if ($xml === false)
			return;

		if (!isset($xml->pages->item[0]['page_id']))
			fatal_lang_error('lp_wrong_import_file', false);

		$items = $titles = $params = $keywords = $comments = [];

		foreach ($xml as $element) {
			foreach ($element->item as $item) {
				$items[] = [
					'page_id'      => $page_id = intval($item['page_id']),
					'author_id'    => intval($item['author_id']),
					'alias'        => (string) $item->alias,
					'description'  => $item->description,
					'content'      => $item->content,
					'type'         => (string) $item->type,
					'permissions'  => intval($item['permissions']),
					'status'       => intval($item['status']),
					'num_views'    => intval($item['num_views']),
					'num_comments' => intval($item['num_comments']),
					'created_at'   => intval($item['created_at']),
					'updated_at'   => intval($item['updated_at'])
				];

				if (!empty($item->titles)) {
					foreach ($item->titles as $title) {
						foreach ($title as $k => $v) {
							$titles[] = [
								'item_id' => $page_id,
								'type'    => 'page',
								'lang'    => $k,
								'title'   => $v
							];
						}
					}
				}

				if (!empty($item->params)) {
					foreach ($item->params as $param) {
						foreach ($param as $k => $v) {
							$params[] = [
								'item_id' => $page_id,
								'type'    => 'page',
								'name'    => $k,
								'value'   => intval($v)
							];
						}
					}
				}

				if (!empty($item->keywords)) {
					foreach (explode(', ', $item->keywords) as $value) {
						$keywords[] = [
							'page_id' => $page_id,
							'value'   => $value
						];
					}
				}

				if (!empty($item->comments)) {
					foreach ($item->comments as $comment) {
						foreach ($comment as $k => $v) {
							$comments[] = [
								'id'         => $v['id'],
								'parent_id'  => $v['parent_id'],
								'page_id'    => $page_id,
								'author_id'  => $v['author_id'],
								'message'    => $v->message,
								'created_at' => $v['created_at']
							];
						}
					}
				}
			}
		}

		if (!empty($items)) {
			$items = array_chunk($items, 100);
			$count = sizeof($items);

			for ($i = 0; $i < $count; $i++) {
				$sql = "REPLACE INTO {db_prefix}lp_pages (`page_id`, `author_id`, `alias`, `description`, `content`, `type`, `permissions`, `status`, `num_views`, `num_comments`, `created_at`, `updated_at`)
					VALUES ";

				$sql .= self::getValues($items[$i]);

				$result = $smcFunc['db_query']('', $sql);
				$context['lp_num_queries']++;
			}
		}

		if (!empty($titles) && !empty($result)) {
			$titles = array_chunk($titles, 100);
			$count = sizeof($titles);

			for ($i = 0; $i < $count; $i++) {
				$sql = "REPLACE INTO {db_prefix}lp_titles (`item_id`, `type`, `lang`, `title`)
					VALUES ";

				$sql .= self::getValues($titles[$i]);

				$result = $smcFunc['db_query']('', $sql);
				$context['lp_num_queries']++;
			}
		}

		if (!empty($params) && !empty($result)) {
			$params = array_chunk($params, 100);
			$count = sizeof($params);

			for ($i = 0; $i < $count; $i++) {
				$sql = "REPLACE INTO {db_prefix}lp_params (`item_id`, `type`, `name`, `value`)
					VALUES ";

				$sql .= self::getValues($params[$i]);

				$result = $smcFunc['db_query']('', $sql);
				$context['lp_num_queries']++;
			}
		}

		if (!empty($keywords) && !empty($result)) {
			$keywords = array_chunk($keywords, 100);
			$count = sizeof($keywords);

			for ($i = 0; $i < $count; $i++) {
				$sql = "REPLACE INTO {db_prefix}lp_tags (`page_id`, `value`)
					VALUES ";

				$sql .= self::getValues($keywords[$i]);

				$result = $smcFunc['db_query']('', $sql);
				$context['lp_num_queries']++;
			}
		}

		if (!empty($comments) && !empty($result)) {
			$comments = array_chunk($comments, 100);
			$count = sizeof($comments);

			for ($i = 0; $i < $count; $i++) {
				$sql = "REPLACE INTO {db_prefix}lp_comments (`id`, `parent_id`, `page_id`, `author_id`, `message`, `created_at`)
					VALUES ";

				$sql .= self::getValues($comments[$i]);

				$result = $smcFunc['db_query']('', $sql);
				$context['lp_num_queries']++;
			}
		}

		if (empty($result))
			fatal_lang_error('lp_import_failed', false);

		// Restore the cache
		$db_cache = $db_temp_cache;

		Helpers::cache()->flush();
	}
}
