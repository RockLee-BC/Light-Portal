<?php

/**
 * Block management section template
 *
 * Шаблон раздела управления блоками
 *
 * @return void
 */
function template_manage_blocks()
{
	global $settings;

	show_block_table();

	echo '
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
	<script src="', $settings['default_theme_url'], '/scripts/light_portal/manage_blocks.js"></script>';
}

/**
 * Displaying a table with blocks
 *
 * Отображение таблицы с блоками
 *
 * @return void
 */
function show_block_table()
{
	global $context, $txt, $scripturl, $modSettings;

	if (empty($context['lp_current_blocks'])) {
		echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['lp_blocks'], '</h3>
	</div>
	<div class="information">', $txt['lp_no_items'], '</div>';
	} else {
		foreach ($context['lp_current_blocks'] as $placement => $blocks) {
			$bloсk_group_type = 'default';
			if (!in_array($placement, ['header', 'top', 'left', 'right', 'bottom', 'footer']))
				$bloсk_group_type = 'additional';

			echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<span class="floatright">
				<a href="', $scripturl, '?action=admin;area=lp_blocks;sa=add;', $context['session_var'], '=', $context['session_id'], ';placement=', $placement, '">
					<i class="fas fa-plus" title="' . $txt['lp_blocks_add'] . '"></i>
				</a>
			</span>
			', $txt['lp_block_placement_set'][$placement] ?? $txt['not_applicable'], is_array($blocks) ? (' (' . count($blocks) . ')') : '', '
		</h3>
	</div>
	<table class="lp_', $bloсk_group_type, '_blocks table_grid centertext">';

			if (is_array($blocks)) {
				echo '
		<thead>
			<tr class="title_bar">';

				if (!empty($modSettings['lp_use_block_icons']) && $modSettings['lp_use_block_icons'] != 'none')
					echo '
				<th scope="col" class="icon">
					', $txt['custom_profile_icon'], '
				</th>';

				echo '
				<th scope="col" class="title">
					', $txt['lp_title'], '
				</th>
				<th scope="col" class="type">
					', $txt['lp_block_type'], '
				</th>
				<th scope="col" class="areas">
					', $txt['lp_block_areas'], '
				</th>
				<th scope="col" class="priority">
					', $txt['lp_block_priority'], '
				</th>
				<th scope="col" class="actions">
					', $txt['lp_actions'], '
				</th>
			</tr>
		</thead>
		<tbody data-placement="', $placement, '">';

				foreach ($blocks as $id => $data)
					show_block_entry($id, $data);
			} else {
				echo '
		<tbody data-placement="', $placement, '">
			<tr class="windowbg centertext">
				<td>', $txt['lp_no_items'], '</td>
			</tr>';
			}

			echo '
		</tbody>
	</table>';
		}
	}
}

/**
 * Adding a row with block parameters to the common table
 *
 * Добавление строчки с параметрами блока в общую таблицу
 *
 * @param int $id
 * @param array $data
 * @return void
 */
function show_block_entry($id, $data)
{
	global $modSettings, $context, $language, $txt, $settings, $scripturl;

	if (empty($id) || empty($data))
		return;

	echo '
	<tr id="lp_block_', $id, '" class="windowbg">';

	if (!empty($modSettings['lp_use_block_icons']) && $modSettings['lp_use_block_icons'] != 'none')
		echo '
		<td class="icon">
			', $data['icon'], '
		</td>';

	echo '
		<td class="title">
			', $data['title'][$context['user']['language']] ?? $data['title'][$language] ?? $data['title']['english'], '
		</td>
		<td class="type">
			', $txt['lp_block_types'][$data['type']] ?? $context['lp_missing_block_types'][$data['type']], '
		</td>
		<td class="areas">
			', $data['areas'], '
		</td>
		<td class="priority">
			', $data['priority'], ' <span class="handle ', ($context['lp_fontawesome_enabled'] ? 'fas fa-sort' : 'main_icons select_here'), '" data-key="', $id, '" title="', $txt['lp_action_move'], '"></span>
		</td>
		<td class="actions">';

		if (empty($data['status']))
			echo '
			<span class="toggle_status off" data-id="', $id, '" title="', $txt['lp_action_on'], '"></span>';
		else
			echo '
			<span class="toggle_status on" data-id="', $id, '" title="', $txt['lp_action_off'], '"></span>';

		if ($context['lp_fontawesome_enabled']) {
			echo '
			<span class="fas fa-clone reports" data-id="', $id, '" title="', $txt['lp_action_clone'], '"></span>
			<a href="', $scripturl, '?action=admin;area=lp_blocks;sa=edit;id=', $id, '"><span class="fas fa-tools" title="', $txt['edit'], '"></span></a>
			<span class="fas fa-trash del_block" data-id="', $id, '" title="', $txt['remove'], '"></span>';
		} else {
			echo '
			<span class="main_icons reports" data-id="', $id, '" title="', $txt['lp_action_clone'], '"></span>
			<a href="', $scripturl, '?action=admin;area=lp_blocks;sa=edit;id=', $id, '"><span class="main_icons settings" title="', $txt['edit'], '"></span></a>
			<span class="main_icons unread_button del_block" data-id="', $id, '" title="', $txt['remove'], '"></span>';
		}

		echo '
		</td>
	</tr>';
}

/**
 * The page for adding blocks
 *
 * Страница добавления блоков
 *
 * @return void
 */
function template_block_add()
{
	global $txt, $context, $settings;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['lp_blocks'], '</h3>
	</div>
	<div class="information">', $txt['lp_blocks_add_instruction'], '</div>
	<div id="lp_blocks">
		<form name="block_add_form" action="', $context['canonical_url'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="row">';

	asort($txt['lp_block_types']);
	foreach ($txt['lp_block_types'] as $type => $title) {
		echo '
				<div class="col-xs-12 col-sm-6 col-md-4 col-lg-3">
					<div class="item roundframe" data-type="', $type, '">
						<i class="', $txt['lp_' . $type . '_icon'], '"></i>
						<strong>', $title, '</strong>
						<hr>
						<p>', $txt['lp_block_types_descriptions'][$type], '</p>
					</div>
				</div>';
	}

	echo '
			</div>
			<input type="hidden" name="add_block">
			<input type="hidden" name="placement" value="', $context['current_block']['placement'], '">
		</form>
		<script src="', $settings['default_theme_url'], '/scripts/light_portal/post_block.js"></script>
	</div>';
}

/**
 * Block creation/editing template
 *
 * Шаблон создания/редактирования блока
 *
 * @return void
 */
function template_block_post()
{
	global $context, $txt, $settings;

	if (isset($context['preview_content']) && empty($context['post_errors'])) {
		if (!empty($context['lp_block']['title_style']))
			$context['preview_title'] = '<span style="' . $context['lp_block']['title_style'] . '">' . $context['preview_title'] . '</span>';

		echo sprintf($context['lp_all_title_classes'][$context['lp_block']['title_class']], $context['preview_title']);

		$style = '';
		if (!empty($context['lp_block']['content_style']))
			$style = ' style="' . $context['lp_block']['content_style'] . '"';

		echo '
	<div class="preview block_', $context['lp_block']['type'], '">
		', sprintf($context['lp_all_content_classes'][$context['lp_block']['content_class'] ?: '_'], $context['preview_content'], $style), '
	</div>';
	} else {
		echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['lp_block_types'][$context['lp_block']['type']], '</h3>
	</div>
	<div class="information">
		', $txt['lp_block_types_descriptions'][$context['lp_block']['type']], '
	</div>';
	}

	if (!empty($context['post_errors'])) {
		echo '
	<div class="errorbox">
		<ul>';

		foreach ($context['post_errors'] as $error) {
			echo '
			<li>', $error, '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	echo '
	<form id="postblock" action="', $context['canonical_url'], '" method="post" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this);">
		<div class="roundframe">
			<div class="lp_tabs">
				<input id="tab1" type="radio" name="tabs" checked>
				<label for="tab1" class="bg odd">', $txt['lp_tab_content'], '</label>
				<input id="tab2" type="radio" name="tabs">
				<label for="tab2" class="bg odd">', $txt['lp_tab_access_placement'], '</label>
				<input id="tab3" type="radio" name="tabs">
				<label for="tab3" class="bg odd">', $txt['lp_tab_appearance'], '</label>';

	if ($context['lp_block_tab_tuning']) {
		echo '
				<input id="tab4" type="radio" name="tabs">
				<label for="tab4" class="bg odd">', $txt['lp_tab_tuning'], '</label>';
	}

	echo '
				<section id="content-tab1" class="bg even">
					', template_post_tab();

	if (!empty($context['lp_block']['options']['content']) && $context['lp_block']['type'] === 'bbc') {
		echo '
					<div>', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '</div>';
	}

	echo '
				</section>
				<section id="content-tab2" class="bg even">
					', template_post_tab('access_placement'), '
				</section>
				<section id="content-tab3" class="bg even">
					', template_post_tab('appearance'), '
				</section>';

	if ($context['lp_block_tab_tuning']) {
		echo '
				<section id="content-tab4" class="bg even">
					', template_post_tab('tuning'), '
				</section>';
	}

	echo '
			</div>
			<br class="clear">
			<div class="centertext">
				<input type="hidden" name="block_id" value="', $context['lp_block']['id'], '">
				<input type="hidden" name="add_block" value="', $context['lp_block']['type'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

	if (!empty($context['lp_block']['id'])) {
		echo '
				<button type="submit" class="button active" name="remove" style="float: left">', $txt['remove'], '</button>';
	}

	echo '
				<button type="submit" class="button" name="preview">', $txt['preview'], '</button>
				<button type="submit" class="button" name="save">', $txt['save'], '</button>
			</div>
		</div>
	</form>
	<script src="', $settings['default_theme_url'], '/scripts/light_portal/post_block.js"></script>';
}

/**
 * Show a table with possible areas for displaying the block
 *
 * Отображаем табличку с возможными областями для вывода блока
 *
 * @return void
 */
function template_show_areas_info()
{
	global $txt, $context;

	echo $txt['lp_block_areas_subtext'] . '<br>';

	echo '
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th>', $txt['lp_block_areas_area_th'], '</th>
				<th>', $txt['lp_block_areas_display_th'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['lp_possible_areas'] as $area => $where_to_display) {
		echo '
			<tr class="windowbg">
				<td class="righttext"><strong>', $area, '</strong></td>
				<td class="lefttext">', $where_to_display, '</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}
