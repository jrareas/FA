<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$page_security = 'SA_CREATEMODULES';
$path_to_root="..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root."/includes/packages.inc");

if ($use_popup_windows) {
	$js = get_js_open_window(900, 500);
}
page(_($help_context = "Install/Activate extensions"));

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/admin/db/company_db.inc");
include_once($path_to_root . "/admin/db/maintenance_db.inc");
include_once($path_to_root . "/includes/ui.inc");

simple_page_mode(true);

//---------------------------------------------------------------------------------------------
function local_extension($id)
{
	global $next_extension_id, $Ajax, $path_to_root;

	$exts = get_company_extensions();
	$exts[$next_extension_id++] = array(
			'package' => $id,
			'name' => $id,
			'version' => '-',
			'available' => '',
			'type' => 'extension',
			'path' => 'modules/'.$id,
			'active' => false
	);

	@include_once($path_to_root.'/modules/'.$id.'/hooks.php');
	$hooks_class = 'hooks_'.$id;
	if (class_exists($hooks_class)) {
		$hooks = new $hooks_class;
		$hooks->install_extension(false);
	}
	
	$Ajax->activate('ext_tbl'); // refresh settings display
	if (!update_extensions($exts))
		return false;
	return true;
}

function handle_delete($id)
{
	global $path_to_root;
	
	$extensions = get_company_extensions();
	$ext = $extensions[$id];
	if ($ext['version'] != '-') {
		if (!uninstall_package($ext['package']))
			return false;
	} else {
		@include_once($path_to_root.'/'.$ext['path'].'/hooks.php');
		$hooks_class = 'hooks_'.$ext['package'];
		if (class_exists($hooks_class)) {
			$hooks = new $hooks_class;
			$hooks->uninstall_extension(false);
		}
	}
	unset($extensions[$id]);
	if (update_extensions($extensions)) {
		display_notification(_("Selected extension has been successfully deleted"));
	}
	return true;
}
//
// Helper for formating menu tabs/entries to be displayed in extension table
//
function fmt_titles($defs)
{
		if (!$defs) return '';
		foreach($defs as $def) {
			$str[] = access_string($def['title'], true);
		}
		return implode('<br>', array_values($str));
}
//---------------------------------------------------------------------------------------------
//
// Display list of all extensions - installed and available from repository
//
function display_extensions()
{
	global $installed_extensions;
	
	div_start('ext_tbl');
	start_table(TABLESTYLE);

	$th = array(_("Extension"),_("Modules provided"), _("Options provided"),
		 _("Installed"), _("Available"),  "", "");
	table_header($th);

	$k = 0;
	$mods = get_extensions_list('extension');

	foreach($mods as $pkg_name => $ext)
	{
		$available = @$ext['available'];
		$installed = @$ext['version'];
		$id = @$ext['local_id'];
		$is_mod = $ext['type'] == 'module';

		$entries = fmt_titles(@$ext['entries']);
		$tabs = fmt_titles(@$ext['tabs']);

		alt_table_row_color($k);
//		label_cell(is_array($ext['Descr']) ? $ext['Descr'][0] : $ext['Descr']);
		label_cell($available ? get_package_view_str($pkg_name, $ext['name']) : $ext['name']);
		label_cell($tabs);
		label_cell($entries);

		label_cell($id === null ? _("None") :
			($available && $installed ? $installed : _("Unknown")));
		label_cell($available ? $available : _("Unknown"));

		if (!$available && $ext['type'] == 'extension')	{// third-party plugin
			if (!$installed)
				button_cell('Local'.$ext['package'], _("Install"), _('Install third-party extension.'), 
					ICON_DOWN);
			else
				label_cell('');
		} elseif (check_pkg_upgrade($installed, $available)) // outdated or not installed extension in repo
			button_cell('Update'.$pkg_name, $installed ? _("Update") : _("Install"),
				_('Upload and install latest extension package'), ICON_DOWN);
		else
			label_cell('');

		if ($id !== null) {
			delete_button_cell('Delete'.$id, _('Delete'));
			submit_js_confirm('Delete'.$id, 
				sprintf(_("You are about to remove package \'%s\'.\nDo you want to continue ?"), 
					$ext['name']));
		} else
			label_cell('');

		end_row();
	}

	end_table(1);

	submit_center_first('Refresh', _("Update"), '', null);
	submit_center_last('Add', _("Add third-party extension"), '', false);

	div_end();
}
//---------------------------------------------------------------------------------
//
// Get all installed extensions and display
// with current status stored in company directory.
//
function company_extensions($id)
{
	start_table(TABLESTYLE);
	
	$th = array(_("Extension"),_("Modules provided"), _("Options provided"), _("Active"));
	
	$mods = get_company_extensions();
	$exts = get_company_extensions($id);
	foreach($mods as $key => $ins) {
		foreach($exts as $ext)
			if ($ext['name'] == $ins['name']) {
				$mods[$key]['active'] = @$ext['active'];
				continue 2;
			}
	}
	$mods = array_natsort($mods, null, 'name');
	table_header($th);
	$k = 0;
	foreach($mods as $i => $mod)
	{
		if ($mod['type'] != 'extension') continue;
   		alt_table_row_color($k);
		label_cell($mod['name']);
		$entries = fmt_titles(@$mod['entries']);
		$tabs = fmt_titles(@$mod['tabs']);
		label_cell($tabs);
		label_cell($entries);

		check_cells(null, 'Active'.$i, @$mod['active'] ? 1:0, 
			false, false, "align='center'");
		end_row();
	}

	end_table(1);
	submit_center('Refresh', _('Update'), true, false, 'default');
}

//---------------------------------------------------------------------------------------------
if ($Mode == 'Delete')
{
	handle_delete($selected_id);
	$Mode = 'RESET';
}

if (get_post('Refresh')) {
	$comp = get_post('extset');
	$exts = get_company_extensions($comp);

	$result = true;
	foreach($exts as $i => $ext) {
		if ($ext['package'] && ($ext['active'] ^ check_value('Active'.$i))) {
			if (!$ext['active'])
				$activated = activate_hooks($ext['package'], $comp);
			else
				$activated = hook_invoke($ext['package'], check_value('Active'.$i) ?
				 'activate_extension':'deactivate_extension', $comp, false);
			if ($activated !== null)
				$result &= $activated;
			if ($activated || ($activated === null))
				$exts[$i]['active'] = check_value('Active'.$i);
		}
	}
	write_extensions($exts, get_post('extset'));
	if (get_post('extset') == user_company())
		$installed_extensions = $exts;
	
	if(!$result) {
		display_error(_('Status change for some extensions failed.'));
		$Ajax->activate('ext_tbl'); // refresh settings display
	}else
		display_notification(_('Current active extensions set has been saved.'));
}

if ($id = find_submit('Update', false))
	install_extension($id);

if ($id = find_submit('Local', false))
	local_extension($id);

if ($Mode == 'RESET')
{
	$selected_id = -1;
	unset($_POST);
}

//---------------------------------------------------------------------------------------------
start_form(true);
if (list_updated('extset'))
	$Ajax->activate('_page_body');

$set = get_post('extset', -1);

echo "<center>" . _('Extensions:') . "&nbsp;&nbsp;";
echo extset_list('extset', null, true);
echo "</center><br>";

if ($set == -1) 
	display_extensions();
else 
	company_extensions($set);

//---------------------------------------------------------------------------------------------
end_form();

end_page();
?>