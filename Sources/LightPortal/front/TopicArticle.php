<?php

namespace Bugo\LightPortal\Front;

use Bugo\LightPortal\Helpers;
use Bugo\LightPortal\Subs;

/**
 * TopicArticle.php
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

class TopicArticle extends Article
{
	/**
	 * Get topics from selected boards
	 *
	 * Получаем темы из выбранных разделов
	 *
	 * @param int $start
	 * @param int $limit
	 * @return array
	 */
	public static function getData(int $start, int $limit): array
	{
		global $modSettings, $user_info, $smcFunc, $scripturl, $txt;

		$selected_boards = !empty($modSettings['lp_frontpage_boards']) ? explode(',', $modSettings['lp_frontpage_boards']) : [];

		if (empty($selected_boards))
			return [];

		if (($topics = Helpers::cache()->get('articles_u' . $user_info['id'] . '_' . $start . '_' . $limit, LP_CACHE_TIME)) === null) {
			$custom_columns = [];
			$custom_tables  = [];
			$custom_wheres  = [];

			$custom_parameters = [
				'current_member'    => $user_info['id'],
				'is_approved'       => 1,
				'id_poll'           => 0,
				'id_redirect_topic' => 0,
				'attachment_type'   => 0,
				'selected_boards'   => $selected_boards,
				'start'             => $start,
				'limit'             => $limit
			];

			$custom_sorting = [
				't.id_last_msg DESC',
				'mf.poster_time DESC',
				'mf.poster_time',
			];

			Subs::runAddons('frontTopics', array(&$custom_columns, &$custom_tables, &$custom_wheres, &$custom_parameters, &$custom_sorting));

			$request = $smcFunc['db_query']('', '
				SELECT
					t.id_topic, t.id_board, t.num_views, t.num_replies, t.is_sticky, t.id_first_msg, t.id_member_started, mf.subject, mf.body, mf.smileys_enabled, COALESCE(mem.real_name, mf.poster_name) AS poster_name, mf.poster_time, mf.id_member, ml.id_msg, ml.id_member AS last_poster_id, ml.poster_name AS last_poster_name, ml.body AS last_body, ml.poster_time AS last_msg_time, b.name, ' . (!empty($modSettings['lp_show_images_in_articles']) ? '(
						SELECT id_attach
						FROM {db_prefix}attachments
						WHERE id_msg = t.id_first_msg
							AND width <> 0
							AND height <> 0
							AND approved = {int:is_approved}
							AND attachment_type = {int:attachment_type}
						ORDER BY id_attach
						LIMIT 1
					) AS id_attach, ' : '') . ($user_info['is_guest'] ? '0' : 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1') . ' AS new_from, ml.id_msg_modified' . (!empty($custom_columns) ? ',
					' . implode(', ', $custom_columns) : '') . '
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS ml ON (t.id_last_msg = ml.id_msg)
					INNER JOIN {db_prefix}messages AS mf ON (t.id_first_msg = mf.id_msg)
					INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mf.id_member = mem.id_member)' . ($user_info['is_guest'] ? '' : '
					LEFT JOIN {db_prefix}log_topics AS lt ON (t.id_topic = lt.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (t.id_board = lmr.id_board AND lmr.id_member = {int:current_member})') . (!empty($custom_tables) ? '
					' . implode("\n\t\t\t\t\t", $custom_tables) : '') . '
				WHERE t.approved = {int:is_approved}
					AND t.id_poll = {int:id_poll}
					AND t.id_redirect_topic = {int:id_redirect_topic}
					AND t.id_board IN ({array_int:selected_boards})
					AND {query_wanna_see_board}' . (!empty($custom_wheres) ? '
					' . implode("\n\t\t\t\t\t", $custom_wheres) : '') . '
				ORDER BY ' . (!empty($modSettings['lp_frontpage_order_by_num_replies']) ? 't.num_replies DESC, ' : '') . $custom_sorting[$modSettings['lp_frontpage_article_sorting'] ?? 0] . '
				LIMIT {int:start}, {int:limit}',
				$custom_parameters
			);

			$topics = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				if (!isset($topics[$row['id_topic']])) {
					Helpers::cleanBbcode($row['subject']);
					censorText($row['subject']);
					censorText($row['body']);
					censorText($row['last_body']);

					$image = null;
					if (!empty($row['id_attach']))
						$image = $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image';

					if (!empty($modSettings['lp_show_images_in_articles']) && empty($image)) {
						$body = parse_bbc($row['body'], false);
						$first_post_image = preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $body, $value);
						$image = $first_post_image ? array_pop($value) : null;
					}

					$row['body'] = preg_replace('~\[spoiler.*].*?\[\/spoiler]~Usi', $txt['spoiler'] ?? '', $row['body']);
					$row['body'] = preg_replace('~\[code.*].*?\[\/code]~Usi', $txt['code'], $row['body']);
					$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_first_msg']), array('<br>' => ' ')), '<blockquote><cite>');

					$row['last_body'] = preg_replace('~\[spoiler.*].*?\[\/spoiler]~Usi', $txt['spoiler'] ?? '', $row['last_body']);
					$row['last_body'] = preg_replace('~\[code.*].*?\[\/code]~Usi', $txt['code'], $row['last_body']);
					$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['smileys_enabled'], $row['id_msg']), array('<br>' => ' ')), '<blockquote><cite>');

					$topics[$row['id_topic']] = array(
						'id'          => $row['id_topic'],
						'id_msg'      => $row['id_first_msg'],
						'author_id'   => $author_id = empty($row['num_replies']) ? $row['id_member'] : $row['last_poster_id'],
						'author_link' => $scripturl . '?action=profile;u=' . $author_id,
						'author_name' => empty($row['num_replies']) ? $row['poster_name'] : $row['last_poster_name'],
						'date'        => empty($modSettings['lp_frontpage_article_sorting']) && !empty($row['last_msg_time']) ? $row['last_msg_time'] : $row['poster_time'],
						'subject'     => $row['subject'],
						'teaser'      => Helpers::getTeaser(empty($row['num_replies']) ? $row['body'] : $row['last_body']),
						'link'        => $scripturl . '?topic=' . $row['id_topic'] . ($row['new_from'] > $row['id_msg_modified'] ? '.0' : '.new;topicseen#new'),
						'board_link'  => $scripturl . '?board=' . $row['id_board'] . '.0',
						'board_name'  => $row['name'],
						'is_sticky'   => !empty($row['is_sticky']),
						'is_new'      => $row['new_from'] <= $row['id_msg_modified'] && $row['last_poster_id'] != $user_info['id'],
						'num_views'   => $row['num_views'],
						'num_replies' => $row['num_replies'],
						'css_class'   => $row['is_sticky'] ? ' sticky' : '',
						'image'       => $image,
						'can_edit'    => $user_info['is_admin'] || ($row['id_member'] == $user_info['id'] && !empty($user_info['id']))
					);

					$topics[$row['id_topic']]['msg_link'] = $topics[$row['id_topic']]['link'];

					if (!empty($topics[$row['id_topic']]['num_replies']))
						$topics[$row['id_topic']]['msg_link'] = $scripturl . '?msg=' . $row['id_msg'];
				}

				Subs::runAddons('frontTopicsOutput', array(&$topics, $row));
			}

			$smcFunc['db_free_result']($request);
			$smcFunc['lp_num_queries']++;

			Helpers::cache()->put('articles_u' . $user_info['id'] . '_' . $start . '_' . $limit, $topics, LP_CACHE_TIME);
		}

		return $topics;
	}

	/**
	 * Get count of active topics from selected boards
	 *
	 * Получаем количество активных тем из выбранных разделов
	 *
	 * @return int
	 */
	public static function getTotal(): int
	{
		global $modSettings, $user_info, $smcFunc;

		$selected_boards = !empty($modSettings['lp_frontpage_boards']) ? explode(',', $modSettings['lp_frontpage_boards']) : [];

		if (empty($selected_boards))
			return 0;

		if (($num_topics = Helpers::cache()->get('articles_u' . $user_info['id'] . '_total', LP_CACHE_TIME)) === null) {
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(t.id_topic)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				WHERE t.approved = {int:is_approved}
					AND t.id_poll = {int:id_poll}
					AND t.id_redirect_topic = {int:id_redirect_topic}
					AND t.id_board IN ({array_int:selected_boards})
					AND {query_wanna_see_board}',
				array(
					'is_approved'       => 1,
					'id_poll'           => 0,
					'id_redirect_topic' => 0,
					'selected_boards'   => $selected_boards
				)
			);

			[$num_topics] = $smcFunc['db_fetch_row']($request);

			$smcFunc['db_free_result']($request);
			$smcFunc['lp_num_queries']++;

			Helpers::cache()->put('articles_u' . $user_info['id'] . '_total', (int) $num_topics, LP_CACHE_TIME);
		}

		return (int) $num_topics;
	}
}
