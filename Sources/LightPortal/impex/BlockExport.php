<?php

namespace Bugo\LightPortal\Impex;

use Bugo\LightPortal\Helpers;

/**
 * BlockExport.php
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

class BlockExport extends Export
{
	/**
	 * The page of export blocks
	 *
	 * Страница экспорта блоков
	 *
	 * @return void
	 */
	public static function main()
	{
		global $context, $txt, $scripturl;

		loadTemplate('LightPortal/ManageExport');

		$context['page_title']      = $txt['lp_portal'] . ' - ' . $txt['lp_blocks_export'];
		$context['page_area_title'] = $txt['lp_blocks_export'];
		$context['canonical_url']   = $scripturl . '?action=admin;area=lp_blocks;sa=export';

		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title'       => LP_NAME,
			'description' => $txt['lp_blocks_export_tab_description']
		);

		self::run();

		$context['lp_current_blocks'] = \Bugo\LightPortal\ManageBlocks::getAll();
		$context['lp_current_blocks'] = array_merge(array_flip(array_keys($txt['lp_block_placement_set'])), $context['lp_current_blocks']);

		$context['sub_template'] = 'manage_export_blocks';
	}

	/**
	 * Creating data in XML format
	 *
	 * Формируем данные в XML-формате
	 *
	 * @return array
	 */
	protected static function getData()
	{
		global $smcFunc;

		if (Helpers::post()->isEmpty('items'))
			return [];

		$request = $smcFunc['db_query']('', '
			SELECT
				b.block_id, b.icon, b.icon_type, b.type, b.content, b.placement, b.priority, b.permissions, b.status, b.areas, b.title_class, b.title_style, b.content_class, b.content_style,
				pt.lang, pt.title, pp.name, pp.value
			FROM {db_prefix}lp_blocks AS b
				LEFT JOIN {db_prefix}lp_titles AS pt ON (b.block_id = pt.item_id AND pt.type = {string:type})
				LEFT JOIN {db_prefix}lp_params AS pp ON (b.block_id = pp.item_id AND pp.type = {string:type})
			WHERE b.block_id IN ({array_int:blocks})',
			array(
				'type'   => 'block',
				'blocks' => Helpers::post('items')
			)
		);

		$items = [];
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			if (!isset($items[$row['block_id']]))
				$items[$row['block_id']] = array(
					'block_id'      => $row['block_id'],
					'icon'          => $row['icon'],
					'icon_type'     => $row['icon_type'],
					'type'          => $row['type'],
					'content'       => $row['content'],
					'placement'     => $row['placement'],
					'priority'      => $row['priority'],
					'permissions'   => $row['permissions'],
					'status'        => $row['status'],
					'areas'         => $row['areas'],
					'title_class'   => $row['title_class'],
					'title_style'   => $row['title_style'],
					'content_class' => $row['content_class'],
					'content_style' => $row['content_style']
				);

			if (!empty($row['lang']))
				$items[$row['block_id']]['titles'][$row['lang']] = $row['title'];

			if (!empty($row['name']))
				$items[$row['block_id']]['params'][$row['name']] = $row['value'];
		}

		$smcFunc['db_free_result']($request);
		$smcFunc['lp_num_queries']++;

		return $items;
	}

	/**
	 * Get filename with XML data
	 *
	 * Получаем имя файла с XML-данными
	 *
	 * @return string
	 */
	protected static function getXmlFile()
	{
		if (empty($items = self::getData()))
			return '';

		$xml = new \DomDocument('1.0', 'utf-8');
		$root = $xml->appendChild($xml->createElement('light_portal'));

		$xml->formatOutput = true;

		$xmlElements = $root->appendChild($xml->createElement('blocks'));
		foreach ($items as $item) {
			$xmlElement = $xmlElements->appendChild($xml->createElement('item'));
			foreach ($item as $key => $val) {
				$xmlName = $xmlElement->appendChild(in_array($key, ['block_id', 'priority', 'permissions', 'status']) ? $xml->createAttribute($key) : $xml->createElement($key));

				if (in_array($key, ['titles', 'params'])) {
					foreach ($item[$key] as $k => $v) {
						$xmlTitle = $xmlName->appendChild($xml->createElement($k));
						$xmlTitle->appendChild($xml->createTextNode($v));
					}
				} elseif ($key == 'content') {
					$xmlName->appendChild($xml->createCDATASection($val));
				} else {
					$xmlName->appendChild($xml->createTextNode($val));
				}
			}
		}

		$file = sys_get_temp_dir() . '/lp_blocks_backup.xml';
		$xml->save($file);

		return $file;
	}
}
