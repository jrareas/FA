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
function update_item($stock_id, $description, $long_description, $category_id, 
	$tax_type_id, $units='', $mb_flag='', $sales_account, $inventory_account, 
	$cogs_account, 	$adjustment_account, $wip_account, $dimension_id, 
	$dimension2_id, $no_sale, $editable, $no_purchase,
	$depreciation_method = 'D', $depreciation_rate=100, $depreciation_factor=1,
    $depreciation_start=null, $fa_class_id=null, $inventory_address=null, $datasheet_url=null,
    $barcode=null, $supplier_part_number)
{
	$sql = "UPDATE ".TB_PREF."stock_master SET long_description=".db_escape($long_description).",
		description=".db_escape($description).",
		category_id=".db_escape($category_id).",
		sales_account=".db_escape($sales_account).",
		inventory_account=".db_escape($inventory_account).",
		cogs_account=".db_escape($cogs_account).",
		adjustment_account=".db_escape($adjustment_account).",
		wip_account=".db_escape($wip_account).",
		dimension_id=".db_escape($dimension_id).",
		dimension2_id=".db_escape($dimension2_id).",
		tax_type_id=".db_escape($tax_type_id).",
		no_sale=".db_escape($no_sale).",
		no_purchase=".db_escape($no_purchase).",
		editable=".db_escape($editable).",
		depreciation_method=".db_escape($depreciation_method).",
		depreciation_rate=".db_escape($depreciation_rate).",
		depreciation_factor=".db_escape($depreciation_factor).",
		fa_class_id=".db_escape($fa_class_id).",
        stock_address=".db_escape($inventory_address).",
        datasheet_url=".db_escape($datasheet_url). ",
        supplier_part_number=" .db_escape($supplier_part_number);
	if ($barcode) {
	    $sql .= ",barcode=".db_escape($barcode);
	    
	}
	

	if ($units != '')
		$sql .= ", units=".db_escape($units);

	if ($mb_flag != '')
		$sql .= ", mb_flag=".db_escape($mb_flag);

	if (isset($depreciation_start)) {
		$sql .= ", depreciation_start='".date2sql($depreciation_start)."'"
			.", depreciation_date='".date2sql($depreciation_start)."'";
	}

	$sql .= " WHERE stock_id=".db_escape($stock_id);

	db_query($sql, "The item could not be updated");

	update_item_code(-1, $stock_id, $stock_id, $description, $category_id, 1, 0);
}

function add_item($stock_id, $description, $long_description, $category_id, 
	$tax_type_id, $units, $mb_flag,	$sales_account, $inventory_account, 
	$cogs_account, $adjustment_account,	$wip_account, $dimension_id, 
	$dimension2_id, $no_sale, $editable, $no_purchase,
	$depreciation_method='D', $depreciation_rate=100,  $depreciation_factor=1, $depreciation_start=null,
    $fa_class_id=null, $inventory_address=null, $datasheet_url=null,
    $barcode=null, $supplier_part_number)
{
	$sql = "INSERT INTO ".TB_PREF."stock_master (stock_id, description, long_description, category_id,
		tax_type_id, units, mb_flag, sales_account, inventory_account, cogs_account,
		adjustment_account, wip_account, dimension_id, dimension2_id, no_sale, no_purchase, editable,
		depreciation_method, depreciation_rate, depreciation_factor, stock_address, datasheet_url, barcode, supplier_part_number"
		.(isset($depreciation_start) ? ", depreciation_start, depreciation_date, fa_class_id" : "")
		.") VALUES (".db_escape($stock_id).", ".db_escape($description).", ".db_escape($long_description).",
		".db_escape($category_id).", ".db_escape($tax_type_id).", "
		.db_escape($units).", ".db_escape($mb_flag).",
		".db_escape($sales_account).", ".db_escape($inventory_account)
		.", ".db_escape($cogs_account).",".db_escape($adjustment_account)
		.", ".db_escape($wip_account).", "
		.db_escape($dimension_id).", ".db_escape($dimension2_id).","
		.db_escape($no_sale).","
		.db_escape($no_purchase).","
		.db_escape($editable).","
		.db_escape($depreciation_method).",".db_escape($depreciation_rate).",".db_escape($depreciation_factor).","
		    .db_escape($inventory_address).",".db_escape($datasheet_url).",".($barcode != "" ? db_escape($barcode) : "null") .",".db_escape($supplier_part_number)
		.(isset($depreciation_start) ? ",'".date2sql($depreciation_start)."','".date2sql($depreciation_start)."',".db_escape($fa_class_id) : "")
		.")";

	db_query($sql, "The item could not be added");

	$sql = "INSERT INTO ".TB_PREF."loc_stock (loc_code, stock_id)
		SELECT loc_code, ".db_escape($stock_id)
		." FROM ".TB_PREF."locations";

	db_query($sql, "The item locstock could not be added");

	add_item_code($stock_id, $stock_id, $description, $category_id, 1, 0);
}

function delete_item($stock_id)
{
	$sql="DELETE FROM ".TB_PREF."stock_master WHERE stock_id=".db_escape($stock_id);
	db_query($sql, "could not delete stock item");

	/*and cascade deletes in loc_stock */
	$sql ="DELETE FROM ".TB_PREF."loc_stock WHERE stock_id=".db_escape($stock_id);
	db_query($sql, "could not delete stock item loc stock");

	/*and cascade deletes in purch_data */
	$sql ="DELETE FROM ".TB_PREF."purch_data WHERE stock_id=".db_escape($stock_id);
	db_query($sql, "could not delete stock item purch data");

	/*and cascade deletes in prices */
	$sql ="DELETE FROM ".TB_PREF."prices WHERE stock_id=".db_escape($stock_id);
	db_query($sql, "could not delete stock item prices");

	/*and cascade delete the bill of material if any */
	$sql = "DELETE FROM ".TB_PREF."bom WHERE parent=".db_escape($stock_id);
	db_query($sql, "could not delete stock item bom");

	delete_item_kit($stock_id);
}

function get_item($stock_id)
{
	$sql = "SELECT item.*, taxtype.name AS tax_type_name
		FROM ".TB_PREF."stock_master item,"
			.TB_PREF."item_tax_types taxtype
		WHERE taxtype.id=item.tax_type_id
		AND stock_id=".db_escape($stock_id);
	$result = db_query($sql,"an item could not be retreived");

	return db_fetch_assoc($result);
}

function get_items($fixed_asset = 0)
{
	$sql = "SELECT * FROM ".TB_PREF."stock_master WHERE fixed_asset=".db_escape($fixed_asset);
	return db_query($sql,"items could not be retreived");
}

function get_all_items($filters)
{   
    $where = [];
    if ($filters) {
        if($filters['string_term'] && $filters['string_term'] != "") {
            $term_items = [
                's.description',
                's.stock_id',
                's.long_description',
                's.stock_address'
            ];
            foreach ($term_items as $term_item) {
                $where[] = $term_item . " like '%" . $filters['string_term'] . "%'";
            }
        }
    }
    if (count($where) > 0) {
        $where = ["(" . implode(" OR ", $where) . ")"];
    }
    
    if ($filters['category_id'] != -1 ) {
        $where[] = "s.category_id = " . $filters['category_id'];
    }

    if ($filters['barcode'] && $filters['barcode'] != "") {
        $where[] = "barcode = '" . $filters['barcode'] . "'";
    }
    if ($filters['empty_address_only'] && $filters['empty_address_only'] == true) {
        $where[] = "stock_address is null";
    }
    
    if ($filters['mb_flag'] && $filters['mb_flag'] != "") {
        $where[] = "mb_flag = '" . $filters['mb_flag'] . "'";
    }
    
    if($filters['order_no'] && $filters['order_no'] != "") {
        $where[] = "pod.order_no = ". $filters['order_no'];
    }   
    
    if (count($where) > 0) {
        $wherestr = 'where ' . implode(" AND ", $where);
    }
    $having="";
    if ($filters['on_order_only'] && $filters['on_order_only'] == true) {
        $having = "having on_order > 0";
    }
    
    
    $sql = "SELECT s.stock_id, s.description, mb_flag, m.qty on_hand, sum(pod.quantity_ordered - pod.quantity_received) on_order ,
            fba.qty as on_fba
            , stock_address, datasheet_url, barcode, supplier_part_number, GROUP_CONCAT(distinct(pod.order_no))  orders
            , sales 
            FROM " . TB_PREF . "stock_master s
            left join (select stock_id, sum(qty) qty from " . TB_PREF . "stock_moves group by stock_id ) m
            on m.stock_id = s.stock_id
            left join (select stock_id, sum(qty) qty from &TB_PREF&stock_moves where loc_code = 'YYZ4' group by stock_id ) fba
            on fba.stock_id = s.stock_id
            left join " . TB_PREF . "purch_order_details pod 
            on pod.item_code = s.stock_id
            left join (select stk_code, GROUP_CONCAT(distinct(order_no)) sales from " . TB_PREF . "sales_order_details group by stk_code) sales_order
            on sales_order.stk_code = s.stock_id
            $wherestr 
            group by s.stock_id, s.description, mb_flag,  stock_address, datasheet_url, m.qty
            $having";
    
    return db_query($sql,"items could not be retreived");
}

function item_in_foreign_codes($stock_id)
{
	$sqls=  array(
	"SELECT COUNT(*) FROM "
		.TB_PREF."stock_moves WHERE stock_id=".db_escape($stock_id) =>
	 _('Cannot delete this item because there are stock movements that refer to this item.'),
	"SELECT COUNT(*) FROM "
		.TB_PREF."bom WHERE component=".db_escape($stock_id)=>
	 _('Cannot delete this item record because there are bills of material that require this part as a component.'),
	"SELECT COUNT(*) FROM "
		.TB_PREF."sales_order_details WHERE stk_code=".db_escape($stock_id) =>
	 _('Cannot delete this item because there are existing sales order items for it.'),
	"SELECT COUNT(*) FROM "
		.TB_PREF."purch_order_details WHERE item_code=".db_escape($stock_id)=>
	 _('Cannot delete this item because there are existing purchase order items for it.')
	);

	$msg = '';

	foreach($sqls as $sql=>$err) {
		$result = db_query($sql, "could not query stock usage");
		$myrow = db_fetch_row($result);
		if ($myrow[0] > 0) 
		{
			$msg = $err; break;
		}
	}
	if ($msg == '') {

		$kits = get_where_used($stock_id);
		$num_kits = db_num_rows($kits);
		if ($num_kits) {
			$msg = _("This item cannot be deleted because some code aliases 
				or foreign codes was entered for it, or there are kits defined 
				using this item as component")
				.':<br>';

			while($num_kits--) {
				$kit = db_fetch($kits);
				$msg .= "'".$kit[0]."'";
				if ($num_kits) $msg .= ',';
			}

		}
	}
	return $msg;
}

function get_items_search($description, $type)
{
	global $SysPrefs;

	$sql = "SELECT COUNT(i.item_code) AS kit, i.item_code, i.description, c.description category
		FROM ".TB_PREF."stock_master s, ".TB_PREF."item_codes i
			LEFT JOIN ".TB_PREF."stock_category c ON i.category_id=c.category_id
		WHERE i.stock_id=s.stock_id
			AND !i.inactive AND !s.inactive
			AND (  i.item_code LIKE " . db_escape("%" . $description. "%") . " OR 
				i.description LIKE " . db_escape("%" . get_post("description"). "%") . " OR 
				c.description LIKE " . db_escape("%" . get_post("description"). "%") . ") ";

	switch ($type) {
		case "sales":
			$sql .= " AND !s.no_sale AND mb_flag <> 'F'";
			break;
		case "manufactured":
			$sql .= " AND mb_flag = 'M'";
			break;
    	case "purchasable":
    		$sql .= " AND NOT no_purchase AND mb_flag <> 'F' AND i.item_code=i.stock_id";
    		break;
		case "costable":
			$sql .= " AND mb_flag <> 'D' AND mb_flag <> 'F' AND  i.item_code=i.stock_id";
			break;
		case "component":
			$parent = $_GET['parent'];
			$sql .= " AND  i.item_code=i.stock_id AND i.stock_id <> '$parent' AND mb_flag <> 'F' ";
			break;
		case "kits":
			$sql .= " AND !i.is_foreign AND i.item_code!=i.stock_id AND mb_flag <> 'F'";
			break;
		case "all":
			$sql .= " AND mb_flag <> 'F' AND i.item_code=i.stock_id";
			break;
	}

	if (isset($SysPrefs->max_rows_in_search))
		$limit = $SysPrefs->max_rows_in_search;
	else
		$limit = 10;

	$sql .= " GROUP BY i.item_code ORDER BY i.description LIMIT 0,".(int)($limit);

	return db_query($sql, "Failed in retreiving item list.");
}

