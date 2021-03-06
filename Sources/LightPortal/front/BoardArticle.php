<?php

namespace Bugo\LightPortal\Front;

use Bugo\LightPortal\Helpers;
use Bugo\LightPortal\Subs;

/**
 * BoardArticle.php
 *
 * @package Light Portal
 * @link https://dragomano.ru/mods/light-portal
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2019-2020 Bugo
 * @license https://spdx.org/licenses/GPL-3.0-or-later.html GPL-3.0-or-later
 *
 * @version 1.3
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class BoardArticle extends Article
{
	/**
	 * Get selected boards
	 *
	 * Получаем выбранные разделы
	 *
	 * @param int $start
	 * @param int $limit
	 * @return array
	 */
	public static function getData(int $start, int $limit): array
	{
		global $modSettings, $user_info, $smcFunc, $context, $scripturl;

		$selected_boards = !empty($modSettings['lp_frontpage_boards']) ? explode(',', $modSettings['lp_frontpage_boards']) : [];

		if (empty($selected_boards))
			return [];

		if (($boards = Helpers::cache()->get('articles_u' . $user_info['id'] . '_' . $start . '_' . $limit, LP_CACHE_TIME)) === null) {
			$custom_columns = [];
			$custom_tables  = [];
			$custom_wheres  = [];

			$custom_parameters = [
				'blank_string'    => '',
				'current_member'  => $user_info['id'],
				'selected_boards' => $selected_boards,
				'start'           => $start,
				'limit'           => $limit
			];

			$custom_sorting = [
				'b.id_last_msg DESC',
				'm.poster_time DESC',
				'm.poster_time',
			];

			Subs::runAddons('frontBoards', array(&$custom_columns, &$custom_tables, &$custom_wheres, &$custom_parameters, &$custom_sorting));

			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name, b.description, b.redirect, CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect, b.num_posts,
					GREATEST(m.poster_time, m.modified_time) AS last_updated, m.id_msg, m.id_topic, c.name AS cat_name,' . ($user_info['is_guest'] ? ' 1 AS is_read, 0 AS new_from' : '
					(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_last_msg THEN 1 ELSE 0 END) AS is_read, COALESCE(lb.id_msg, -1) + 1 AS new_from') . (!empty($modSettings['lp_show_images_in_articles']) ? ', COALESCE(a.id_attach, 0) AS attach_id' : '') . (!empty($custom_columns) ? ',
					' . implode(', ', $custom_columns) : '') . '
				FROM {db_prefix}boards AS b
					INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
					LEFT JOIN {db_prefix}messages AS m ON (b.id_last_msg = m.id_msg)' . ($user_info['is_guest'] ? '' : '
					LEFT JOIN {db_prefix}log_boards AS lb ON (b.id_board = lb.id_board AND lb.id_member = {int:current_member})') . (!empty($modSettings['lp_show_images_in_articles']) ? '
					LEFT JOIN {db_prefix}attachments AS a ON (b.id_last_msg = a.id_msg AND a.id_thumb <> 0 AND a.width > 0 AND a.height > 0)' : '') . (!empty($custom_tables) ? '
					' . implode("\n\t\t\t\t\t", $custom_tables) : '') . '
				WHERE b.id_board IN ({array_int:selected_boards})
					AND {query_see_board}' . (!empty($custom_wheres) ? '
					' . implode("\n\t\t\t\t\t", $custom_wheres) : '') . '
				ORDER BY ' . (!empty($modSettings['lp_frontpage_order_by_num_replies']) ? 'b.num_posts DESC, ' : '') . $custom_sorting[$modSettings['lp_frontpage_article_sorting'] ?? 0] . '
				LIMIT {int:start}, {int:limit}',
				$custom_parameters
			);

			$boards = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$board_name  = parse_bbc($row['name'], false, '', $context['description_allowed_tags']);
				$description = parse_bbc($row['description'], false, '', $context['description_allowed_tags']);
				$cat_name    = parse_bbc($row['cat_name'], false, '', $context['description_allowed_tags']);

				$image = null;
				if (!empty($modSettings['lp_show_images_in_articles'])) {
					$board_image = preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $description, $value);
					$image = $board_image ? array_pop($value) : (!empty($row['attach_id']) ? $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . ';attach=' . $row['attach_id'] . ';image' : null);
				}

				$description = strip_tags($description);

				$boards[$row['id_board']] = array(
					'id'          => $row['id_board'],
					'name'        => $board_name,
					'teaser'      => Helpers::getTeaser($description),
					'category'    => $cat_name,
					'link'        => $row['is_redirect'] ? $row['redirect'] : $scripturl . '?board=' . $row['id_board'] . '.0',
					'is_redirect' => $row['is_redirect'],
					'is_updated'  => empty($row['is_read']),
					'num_posts'   => $row['num_posts'],
					'image'       => $image,
					'can_edit'    => $user_info['is_admin'] || allowedTo('manage_boards')
				);

				if (!empty($row['last_updated'])) {
					$boards[$row['id_board']]['last_post'] = $scripturl . '?topic=' . $row['id_topic'] . '.msg' . ($user_info['is_guest'] ? $row['id_msg'] : $row['new_from']) . (empty($row['is_read']) ? ';boardseen' : '') . '#new';

					$boards[$row['id_board']]['date'] = $row['last_updated'];
				}

				$boards[$row['id_board']]['msg_link'] = $boards[$row['id_board']]['link'];

				if (empty($boards[$row['id_board']]['is_redirect']))
					$boards[$row['id_board']]['msg_link'] = $scripturl . '?msg=' . $row['id_msg'];

				Subs::runAddons('frontBoardsOutput', array(&$boards, $row));
			}

			$smcFunc['db_free_result']($request);
			$smcFunc['lp_num_queries']++;

			Helpers::cache()->put('articles_u' . $user_info['id'] . '_' . $start . '_' . $limit, $boards, LP_CACHE_TIME);
		}

		return $boards;
	}

	/**
	 * Get count of selected boards
	 *
	 * Получаем количество выбранных разделов
	 *
	 * @return int
	 */
	public static function getTotal(): int
	{
		global $modSettings, $user_info, $smcFunc;

		$selected_boards = !empty($modSettings['lp_frontpage_boards']) ? explode(',', $modSettings['lp_frontpage_boards']) : [];

		if (empty($selected_boards))
			return 0;

		if (($num_boards = Helpers::cache()->get('articles_u' . $user_info['id'] . '_total', LP_CACHE_TIME)) === null) {
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(b.id_board)
				FROM {db_prefix}boards AS b
					INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
				WHERE b.id_board IN ({array_int:selected_boards})
					AND {query_see_board}',
				array(
					'selected_boards' => $selected_boards
				)
			);

			[$num_boards] = $smcFunc['db_fetch_row']($request);

			$smcFunc['db_free_result']($request);
			$smcFunc['lp_num_queries']++;

			Helpers::cache()->put('articles_u' . $user_info['id'] . '_total', (int) $num_boards, LP_CACHE_TIME);
		}

		return (int) $num_boards;
	}
}
