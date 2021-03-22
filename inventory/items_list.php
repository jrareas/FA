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
$page_security = 'SA_SALESPRICE';

if (@$_GET['page_level'] == 1)
	$path_to_root = "../..";
else	
	$path_to_root = "..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/sales/includes/items_db.inc");
include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/sales/includes/db/sales_types_db.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/inventory/includes/inventory_db.inc");

$js = "";
if ($SysPrefs->use_popup_windows && $SysPrefs->use_popup_search)
	$js .= get_js_open_window(900, 500);
page(_($help_context = "Inventory Item Sales prices"), false, false, "", $js);

//---------------------------------------------------------------------------------------------------

check_db_has_stock_items(_("There are no items defined in the system."));

check_db_has_sales_types(_("There are no sales types in the system. Please set up sales types befor entering pricing."));

simple_page_mode(true);
//---------------------------------------------------------------------------------------------------
$input_error = 0;

if (isset($_GET['stock_id']))
{
	$_POST['stock_id'] = $_GET['stock_id'];
}
if (isset($_GET['Item']))
{
	$_POST['stock_id'] = $_GET['Item'];
}

if (!isset($_POST['curr_abrev']))
{
	$_POST['curr_abrev'] = get_company_currency();
}

//---------------------------------------------------------------------------------------------------
$action = $_SERVER['PHP_SELF'];
if ($page_nested)
	$action .= "?stock_id=".get_post('stock_id');
start_form(false, false, $action);

if (!isset($_POST['stock_id']))
	$_POST['stock_id'] = get_global_stock_item();

if (!$page_nested)
{
	echo "<center>" . _("Item:"). "&nbsp;";
	echo sales_items_list('stock_id', $_POST['stock_id'], false, true, '', array('editable' => false));
	echo "<hr></center>";
}
else
	br(2);
set_global_stock_item($_POST['stock_id']);

//----------------------------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM') 
{

	if (!check_num('price', 0))
	{
		$input_error = 1;
		display_error( _("The price entered must be numeric."));
		set_focus('price');
	}
   	elseif ($Mode == 'ADD_ITEM' && get_stock_price_type_currency($_POST['stock_id'], $_POST['sales_type_id'], $_POST['curr_abrev']))
   	{
      	$input_error = 1;
      	display_error( _("The sales pricing for this item, sales type and currency has already been added."));
		set_focus('supplier_id');
	}

	if ($input_error != 1)
	{

    	if ($selected_id != -1) 
		{
			//editing an existing price
			update_item_price($selected_id, $_POST['sales_type_id'],
			$_POST['curr_abrev'], input_num('price'));

			$msg = _("This price has been updated.");
		}
		else
		{

			add_item_price($_POST['stock_id'], $_POST['sales_type_id'],
			    $_POST['curr_abrev'], input_num('price'));

			$msg = _("The new price has been added.");
		}
		display_notification($msg);
		$Mode = 'RESET';
	}

}

//------------------------------------------------------------------------------------------------------

if ($Mode == 'Delete')
{
	//the link to delete a selected record was clicked
	delete_item_price($selected_id);
	display_notification(_("The selected price has been deleted."));
	$Mode = 'RESET';
}

if ($Mode == 'RESET')
{
	$selected_id = -1;
}

if (list_updated('stock_id')) {
	$Ajax->activate('price_table');
	$Ajax->activate('price_details');
}
if (list_updated('stock_id') || isset($_POST['_curr_abrev_update']) || isset($_POST['_sales_type_id_update'])) {
	// after change of stock, currency or salestype selector
	// display default calculated price for new settings. 
	// If we have this price already in db it is overwritten later.
	unset($_POST['price']);
	$Ajax->activate('price_details');
}

//---------------------------------------------------------------------------------------------------



$items_list = get_all_items($_POST);

div_start('search_results');

div_start("results_count");
    echo "<center>Found: " . $items_list->num_rows . " Records</center>";
div_end();
start_table(TABLESTYLE, "width='30%'");

// $th = array(_("Stock Id"), _("Description"), _("Type"), _("OnHand"), "Storage", "Address");
$th = [
    _("Stock Id"), 
    _("Description"), 
    _("Type"), 
    _("OnHand"),
    _("Address"),
    _(""),
];
table_header($th);
$k = 0; //row colour counter
$calculated = false;
while ($myrow = db_fetch($items_list))
{

	alt_table_row_color($k);

	label_cell($myrow["stock_id"]);
    label_cell($myrow["description"]);
    label_cell($myrow["mb_flag"]);
    qty_cell($myrow["on_hand"]);
    label_cell($myrow["stock_address"]);
//     label_cell("");
//     label_cell("");
//     amount_cell($myrow["price"]);
    
    link_cell("Edit Product", "/inventory/manage/items.php?stock_id=" . $myrow["stock_id"],null, ICON_EDIT);
//  	delete_button_cell("Delete".$myrow['stock_id'], _("Delete"));
    end_row();

}
end_table();
if (db_num_rows($prices_list) == 0)
{
	if (get_company_pref('add_pct') != -1)
		$calculated = true;
	display_note(_("There are no prices set up for this part."), 1);
}
div_end();
//------------------------------------------------------------------------------------------------

echo "<br>";

hidden('selected_id', $selected_id);


end_form();
end_page();
