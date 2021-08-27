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
$page_security = 'SA_ITEM';
$path_to_root = "../..";
include($path_to_root . "/includes/session.inc");
include($path_to_root . "/reporting/includes/tcpdf.php");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();

if (isset($_GET['FixedAsset'])) {
  $page_security = 'SA_ASSET';
  $_SESSION['page_title'] = _($help_context = "Fixed Assets");
  $_POST['mb_flag'] = 'F';
  $_POST['fixed_asset']  = 1;
}
else {
  $_SESSION['page_title'] = _($help_context = "Items");
	if (!get_post('fixed_asset'))
		$_POST['fixed_asset']  = 0;
}


page($_SESSION['page_title'], @$_REQUEST['popup'], false, "", $js);

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");

include_once($path_to_root . "/inventory/includes/inventory_db.inc");
include_once($path_to_root . "/fixed_assets/includes/fixed_assets_db.inc");

$user_comp = user_company();
$new_item = get_post('stock_id')=='' || get_post('cancel') || get_post('clone'); 
//------------------------------------------------------------------------------------
function set_edit($stock_id)
{
	$_POST = array_merge($_POST, get_item($stock_id));

	$_POST['depreciation_rate'] = number_format2($_POST['depreciation_rate'], 1);
	$_POST['depreciation_factor'] = number_format2($_POST['depreciation_factor'], 1);
	$_POST['depreciation_start'] = sql2date($_POST['depreciation_start']);
	$_POST['depreciation_date'] = sql2date($_POST['depreciation_date']);
	$_POST['del_image'] = 0;
}

if (isset($_GET['stock_id']))
{
	$_POST['stock_id'] = $_GET['stock_id'];
}
$stock_id = get_post('stock_id');
if (list_updated('stock_id')) {
	$_POST['NewStockID'] = $stock_id = get_post('stock_id');
    clear_data();
	$Ajax->activate('details');
	$Ajax->activate('controls');
}

if (get_post('cancel')) {
	$_POST['NewStockID'] = $stock_id = $_POST['stock_id'] = '';
    clear_data();
	set_focus('stock_id');
	$Ajax->activate('_page_body');
}
if (list_updated('category_id') || list_updated('mb_flag') || list_updated('fa_class_id') || list_updated('depreciation_method')) {
	$Ajax->activate('details');
}
$upload_file = "";

function clear_data()
{
	unset($_POST['long_description']);
	unset($_POST['description']);
	unset($_POST['category_id']);
	unset($_POST['tax_type_id']);
	unset($_POST['units']);
	unset($_POST['mb_flag']);
	unset($_POST['NewStockID']);
	unset($_POST['dimension_id']);
	unset($_POST['dimension2_id']);
	unset($_POST['no_sale']);
	unset($_POST['no_purchase']);
	unset($_POST['depreciation_method']);
	unset($_POST['depreciation_rate']);
	unset($_POST['depreciation_factor']);
	unset($_POST['depreciation_start']);
}

//------------------------------------------------------------------------------------

if (isset($_POST['addupdate'])) 
{

	$input_error = 0;
	if ($upload_file == 'No')
		$input_error = 1;
	if (strlen($_POST['description']) == 0) 
	{
		$input_error = 1;
		display_error( _('The item name must be entered.'));
		set_focus('description');
	} 
	elseif (strlen($_POST['NewStockID']) == 0) 
	{
		$input_error = 1;
		display_error( _('The item code cannot be empty'));
		set_focus('NewStockID');
	}
	elseif (strstr($_POST['NewStockID'], " ") || strstr($_POST['NewStockID'],"'") || 
		strstr($_POST['NewStockID'], "+") || strstr($_POST['NewStockID'], "\"") || 
		strstr($_POST['NewStockID'], "&") || strstr($_POST['NewStockID'], "\t")) 
	{
		$input_error = 1;
		display_error( _('The item code cannot contain any of the following characters -  & + OR a space OR quotes'));
		set_focus('NewStockID');

	}
	elseif ($new_item && db_num_rows(get_item_kit($_POST['NewStockID'])))
	{
		  	$input_error = 1;
      		display_error( _("This item code is already assigned to stock item or sale kit."));
			set_focus('NewStockID');
	}
	
  if (get_post('fixed_asset')) {
    if ($_POST['depreciation_rate'] > 100) {
      $_POST['depreciation_rate'] = 100;
    }
    elseif ($_POST['depreciation_rate'] < 0) {
      $_POST['depreciation_rate'] = 0;
    }
    $move_row = get_fixed_asset_move($_POST['NewStockID'], ST_SUPPRECEIVE);
    if ($move_row && isset($_POST['depreciation_start']) && strtotime($_POST['depreciation_start']) < strtotime($move_row['tran_date'])) {
      display_warning(_('The depracation cannot start before the fixed asset purchase date'));
    }
  }
	
	if ($input_error != 1)
	{
		if (check_value('del_image'))
		{
			$filename = company_path().'/images/'.item_img_name($_POST['NewStockID']).".jpg";
			if (file_exists($filename))
				unlink($filename);
		}
		
		if (!$new_item) 
		{ /*so its an existing one */
			update_item($_POST['NewStockID'], $_POST['description'],
				$_POST['long_description'], $_POST['category_id'], 
				$_POST['tax_type_id'], get_post('units'),
				get_post('fixed_asset') ? 'F' : get_post('mb_flag'), $_POST['sales_account'],
				$_POST['inventory_account'], $_POST['cogs_account'],
				$_POST['adjustment_account'], $_POST['wip_account'], 
				$_POST['dimension_id'], $_POST['dimension2_id'],
				check_value('no_sale'), check_value('editable'), check_value('no_purchase'),
				get_post('depreciation_method'), input_num('depreciation_rate'), input_num('depreciation_factor'), get_post('depreciation_start', null),
				get_post('fa_class_id'));

			update_record_status($_POST['NewStockID'], $_POST['inactive'],
				'stock_master', 'stock_id');
			update_record_status($_POST['NewStockID'], $_POST['inactive'],
				'item_codes', 'item_code');
			set_focus('stock_id');
			$Ajax->activate('stock_id'); // in case of status change
			display_notification(_("Item has been updated."));
		} 
		else 
		{ //it is a NEW part

			add_item($_POST['NewStockID'], $_POST['description'],
				$_POST['long_description'], $_POST['category_id'], $_POST['tax_type_id'],
				$_POST['units'], get_post('fixed_asset') ? 'F' : get_post('mb_flag'), $_POST['sales_account'],
				$_POST['inventory_account'], $_POST['cogs_account'],
				$_POST['adjustment_account'], $_POST['wip_account'], 
				$_POST['dimension_id'], $_POST['dimension2_id'],
				check_value('no_sale'), check_value('editable'), check_value('no_purchase'),
				get_post('depreciation_method'), input_num('depreciation_rate'), input_num('depreciation_factor'), get_post('depreciation_start', null),
				get_post('fa_class_id'));

			display_notification(_("A new item has been added."));
			$_POST['stock_id'] = $_POST['NewStockID'] = 
			$_POST['description'] = $_POST['long_description'] = '';
			$_POST['no_sale'] = $_POST['editable'] = $_POST['no_purchase'] =0;
			set_focus('NewStockID');
		}
		$Ajax->activate('_page_body');
	}
}
if (get_post('clone')) {
	set_edit($_POST['stock_id']); // restores data for disabled inputs too
	unset($_POST['stock_id']);
	$stock_id = '';
	unset($_POST['inactive']);
	set_focus('NewStockID');
	$Ajax->activate('_page_body');
}

//------------------------------------------------------------------------------------

function check_usage($stock_id, $dispmsg=true)
{
	$msg = item_in_foreign_codes($stock_id);

	if ($msg != '')	{
		if($dispmsg) display_error($msg);
		return false;
	}
	return true;
}

//------------------------------------------------------------------------------------

if (isset($_POST['delete']) && strlen($_POST['delete']) > 1) 
{

	if (check_usage($_POST['NewStockID'])) {

		$stock_id = $_POST['NewStockID'];
		delete_item($stock_id);
		$filename = company_path().'/images/'.item_img_name($stock_id).".jpg";
		if (file_exists($filename))
			unlink($filename);
		display_notification(_("Selected item has been deleted."));
		$_POST['stock_id'] = '';
		clear_data();
		set_focus('stock_id');
		$new_item = true;
		$Ajax->activate('_page_body');
	}
}

function item_settings(&$stock_id, $new_item) 
{
	global $SysPrefs, $path_to_root, $page_nested, $depreciation_methods;

	start_outer_table(TABLESTYLE2);

	table_section(1);

	table_section_title(_("General Settings"));

	//------------------------------------------------------------------------------------
	if ($new_item) 
	{
		$tmpCodeID=null;
		$post_label = null;
		if (!empty($SysPrefs->prefs['barcodes_on_stock']))
		{
			$post_label = '<button class="ajaxsubmit" type="submit" aspect=\'default\'  name="generateBarcode"  id="generateBarcode" value="Generate Barcode EAN8"> '._("Generate EAN-8 Barcode").' </button>';
			if (isset($_POST['generateBarcode']))
			{
				$tmpCodeID=generateBarcode();
				$_POST['NewStockID'] = $tmpCodeID;
			}
		}	
		text_row(_("Item Code:"), 'NewStockID', $tmpCodeID, 21, 20, null, "", $post_label);
		$_POST['inactive'] = 0;
	} 
	else 
	{ // Must be modifying an existing item
		if (get_post('NewStockID') != get_post('stock_id') || get_post('addupdate')) { // first item display

			$_POST['NewStockID'] = $_POST['stock_id'];
			set_edit($_POST['stock_id']);
		}
		label_row(_("Item Code:"),$_POST['NewStockID']);
		hidden('NewStockID', $_POST['NewStockID']);
		set_focus('description');
	}
	$fixed_asset = get_post('fixed_asset');

	text_row(_("Name:"), 'description', null, 52, 200);

	textarea_row(_('Description:'), 'long_description', null, 42, 3);

	stock_categories_list_row(_("Category:"), 'category_id', null, false, $new_item, $fixed_asset);

	if ($new_item && (list_updated('category_id') || !isset($_POST['sales_account']))) { // changed category for new item or first page view

		$category_record = get_item_category($_POST['category_id']);

		$_POST['tax_type_id'] = $category_record["dflt_tax_type"];
		$_POST['units'] = $category_record["dflt_units"];
		$_POST['mb_flag'] = $category_record["dflt_mb_flag"];
		$_POST['inventory_account'] = $category_record["dflt_inventory_act"];
		$_POST['cogs_account'] = $category_record["dflt_cogs_act"];
		$_POST['sales_account'] = $category_record["dflt_sales_act"];
		$_POST['adjustment_account'] = $category_record["dflt_adjustment_act"];
		$_POST['wip_account'] = $category_record["dflt_wip_act"];
		$_POST['dimension_id'] = $category_record["dflt_dim1"];
		$_POST['dimension2_id'] = $category_record["dflt_dim2"];
		$_POST['no_sale'] = $category_record["dflt_no_sale"];
		$_POST['no_purchase'] = $category_record["dflt_no_purchase"];
		$_POST['editable'] = 0;

	}
	$fresh_item = !isset($_POST['NewStockID']) || $new_item 
		|| check_usage($_POST['stock_id'],false);

	// show inactive item tax type in selector only if already set.
  item_tax_types_list_row(_("Item Tax Type:"), 'tax_type_id', null, !$new_item && item_type_inactive(get_post('tax_type_id')));

	if (!get_post('fixed_asset'))
		stock_item_types_list_row(_("Item Type:"), 'mb_flag', null, $fresh_item);

	stock_units_list_row(_('Units of Measure:'), 'units', null, $fresh_item);


	if (!get_post('fixed_asset')) {
		check_row(_("Editable description:"), 'editable');
		check_row(_("Exclude from sales:"), 'no_sale');
		check_row(_("Exclude from purchases:"), 'no_purchase');
	}

	if (get_post('fixed_asset')) {
		table_section_title(_("Depreciation"));

		fixed_asset_classes_list_row(_("Fixed Asset Class").':', 'fa_class_id', null, false, true);

		array_selector_row(_("Depreciation Method").":", "depreciation_method", null, $depreciation_methods, array('select_submit'=> true));

		if (!isset($_POST['depreciation_rate']) || (list_updated('fa_class_id') || list_updated('depreciation_method'))) {
			$class_row = get_fixed_asset_class($_POST['fa_class_id']);
			$_POST['depreciation_rate'] = get_post('depreciation_method') == 'N' ? ceil(100/$class_row['depreciation_rate'])
				: $class_row['depreciation_rate'];
		}

		if ($_POST['depreciation_method'] == 'O')
		{
			hidden('depreciation_rate', 100);
			label_row(_("Depreciation Rate").':', "100 %");
		}
		elseif ($_POST['depreciation_method'] == 'N')
		{
			small_amount_row(_("Depreciation Years").':', 'depreciation_rate', null, null, _('years'), 0);
		}
		elseif ($_POST['depreciation_method'] == 'D')
			small_amount_row(_("Base Rate").':', 'depreciation_rate', null, null, '%', user_percent_dec());
		else
			small_amount_row(_("Depreciation Rate").':', 'depreciation_rate', null, null, '%', user_percent_dec());

		if ($_POST['depreciation_method'] == 'D')
			small_amount_row(_("Rate multiplier").':', 'depreciation_factor', null, null, '', 2);

		// do not allow to change the depreciation start after this item has been depreciated
		if ($new_item || $_POST['depreciation_start'] == $_POST['depreciation_date'])
			date_row(_("Depreciation Start").':', 'depreciation_start', null, null, 1 - date('j'));
		else {
			hidden('depreciation_start');
			label_row(_("Depreciation Start").':', $_POST['depreciation_start']);
			label_row(_("Last Depreciation").':', $_POST['depreciation_date']==$_POST['depreciation_start'] ? _("None") :  $_POST['depreciation_date']);
		}
		hidden('depreciation_date');
	}
	table_section(2);

	$dim = get_company_pref('use_dimension');
	if ($dim >= 1)
	{
		table_section_title(_("Dimensions"));

		dimensions_list_row(_("Dimension")." 1", 'dimension_id', null, true, " ", false, 1);
		if ($dim > 1)
			dimensions_list_row(_("Dimension")." 2", 'dimension2_id', null, true, " ", false, 2);
	}
	if ($dim < 1)
		hidden('dimension_id', 0);
	if ($dim < 2)
		hidden('dimension2_id', 0);

	table_section_title(_("GL Accounts"));

	gl_all_accounts_list_row(_("Sales Account:"), 'sales_account', $_POST['sales_account']);

	if (get_post('fixed_asset')) {
		gl_all_accounts_list_row(_("Asset account:"), 'inventory_account', $_POST['inventory_account']);
		gl_all_accounts_list_row(_("Depreciation cost account:"), 'cogs_account', $_POST['cogs_account']);
		gl_all_accounts_list_row(_("Depreciation/Disposal account:"), 'adjustment_account', $_POST['adjustment_account']);
	}
	elseif (!is_service(get_post('mb_flag')))
	{
		gl_all_accounts_list_row(_("Inventory Account:"), 'inventory_account', $_POST['inventory_account']);
		gl_all_accounts_list_row(_("C.O.G.S. Account:"), 'cogs_account', $_POST['cogs_account']);
		gl_all_accounts_list_row(_("Inventory Adjustments Account:"), 'adjustment_account', $_POST['adjustment_account']);
	}
	else 
	{
		gl_all_accounts_list_row(_("C.O.G.S. Account:"), 'cogs_account', $_POST['cogs_account']);
		hidden('inventory_account', $_POST['inventory_account']);
		hidden('adjustment_account', $_POST['adjustment_account']);
	}


	if (is_manufactured(get_post('mb_flag')))
		gl_all_accounts_list_row(_("WIP Account:"), 'wip_account', $_POST['wip_account']);
	else
		hidden('wip_account', $_POST['wip_account']);

	table_section_title(_("Other"));

	// Add image upload for New Item  - by Joe
	file_row(_("Image File (.jpg)") . ":", 'pic', 'pic');
	// Add Image upload for New Item  - by Joe
	$stock_img_link = "";
	$check_remove_image = false;

	if (@$_POST['NewStockID'] && file_exists(company_path().'/images/'
		.item_img_name($_POST['NewStockID']).".jpg")) 
	{
	 // 31/08/08 - rand() call is necessary here to avoid caching problems.
		$stock_img_link .= "<img id='item_img' alt = '[".$_POST['NewStockID'].".jpg".
			"]' src='".company_path().'/images/'.item_img_name($_POST['NewStockID']).
			".jpg?nocache=".rand()."'"." height='".$SysPrefs->pic_height."' border='0'>";
		$check_remove_image = true;
	} 
	else 
	{
		$stock_img_link .= _("No image");
	}

	label_row("&nbsp;", $stock_img_link);
	if ($check_remove_image)
		check_row(_("Delete Image:"), 'del_image');

	record_status_list_row(_("Item status:"), 'inactive');
	if (get_post('fixed_asset')) {
		table_section_title(_("Values"));
		if (!$new_item) {
			hidden('material_cost');
			hidden('purchase_cost');
			label_row(_("Initial Value").":", price_format($_POST['purchase_cost']), "", "align='right'");
			label_row(_("Depreciations").":", price_format($_POST['purchase_cost'] - $_POST['material_cost']), "", "align='right'");
			label_row(_("Current Value").':', price_format($_POST['material_cost']), "", "align='right'");
		}
	}
	end_outer_table(1);

	div_start('controls');
	if (@$_REQUEST['popup']) hidden('popup', 1);
	if (!isset($_POST['NewStockID']) || $new_item) 
	{
		submit_center('addupdate', _("Insert New Item"), true, '', 'default');
	} 
	else 
	{
		submit_center_first('addupdate', _("Update Item"), '', 
			$page_nested ? true : 'default');
		submit_return('select', get_post('stock_id'), 
			_("Select this items and return to document entry."));
		submit('clone', _("Clone This Item"), true, '', true);
		submit('delete', _("Delete This Item"), true, '', true);
		submit_center_last('cancel', _("Cancel"), _("Cancel Edition"), 'cancel');
	}

	div_end();
}

function item_search_form() {
    start_outer_table(TABLESTYLE2);
    
    table_section(1);
    
    table_section_title(_("Search"));
    text_row(_("Text:"), 'string_term', null, 52, 200, null, "class=searchbox");
    
    start_row();
    echo "<td>";
    echo _("Empty Address Only");
    echo "</td>";
    echo "<td>"; 
    echo checkbox("","empty_address_only");
    echo "</td>";
    end_row();
    start_row();
    echo "<td>";
    echo _("On Order Only");
    echo "</td>";
    echo "<td>";
    echo checkbox("","on_order_only");
    echo "</td>";
    end_row();
    stock_item_types_list_row(_("Item Type:"), 'mb_flag', null, true, true);
    end_outer_table(1);
    hidden("search_submit",true);
    submit_center('search_results', _("Search"), true, '', 'selector');
}

//-------------------------------------------------------------------------------------------- 

start_form(true);
div_start('search');
item_search_form();

$tabs = [
    'list' => array(_('&List'), $stock_id),
];

tabbed_content_start('tabs', $tabs);

switch (get_post('_tabs_sel')) {
    default:
    case 'list':
        $_GET['search_term'] = $stock_id;
        $_GET['page_level'] = 1;
        if(get_post('search_submit')) {
            include_once($path_to_root."/inventory/items_list.php");
        }
        break;
};

br();
tabbed_content_end();


div_end();

hidden('fixed_asset', get_post('fixed_asset'));

if (get_post('fixed_asset'))
	hidden('mb_flag', 'F');

end_form();

//------------------------------------------------------------------------------------

end_page(@$_REQUEST['popup']);
