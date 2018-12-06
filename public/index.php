<?php
// Set up paths
define('SYSTEM_PATH', realpath(dirname(__FILE__) . '/..'));

// Load the Composer data
require_once(SYSTEM_PATH . '/vendor/autoload.php');

// Initialize the Smarty
$smarty = new \Smarty();
$smarty -> setTemplateDir(SYSTEM_PATH . '/templates');
$smarty -> setCompileDir(SYSTEM_PATH . '/cache');
$smarty -> setCacheDir(SYSTEM_PATH . '/cache');

// Initialize the XIVAPI
$api = new \XIVAPI\XIVAPI();
$api -> environment -> key(getenv('XIVAPI_KEY', TRUE));

// Read the variables from user
$server = filter_input(INPUT_GET, 'server', FILTER_SANITIZE_STRING);
$idlist = filter_input(INPUT_GET, 'idlist', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_STRIP_BACKTICK);
$crafterlist = filter_input(INPUT_GET, 'crafterlist', FILTER_SANITIZE_STRING);
$retainerlist = filter_input(INPUT_GET, 'retainerlist', FILTER_SANITIZE_STRING);
$itemnum = filter_input(INPUT_GET, 'itemnum', FILTER_VALIDATE_INT, array('default' => 5, 'min_range' => 0, 'max_range' => 100));

// Set the default server
if (empty($server))
{
	$server = 'Lich';
}

// Set the default item number
if ((empty($itemnum)) || (!is_int($itemnum)))
{
	$itemnum = 0;
}

// Check the page templates
if (!$smarty -> templateExists('head.tpl.html'))
{
	throw new \Exception(sprintf(_('Unable to load the template file %s'), 'head.tpl.html'));
}
if (!$smarty -> templateExists('item.tpl.html'))
{
	throw new \Exception(sprintf(_('Unable to load the template file %s'), 'item.tpl.html'));
}
if (!$smarty -> templateExists('tail.tpl.html'))
{
	throw new \Exception(sprintf(_('Unable to load the template file %s'), 'tail.tpl.html'));
}

// Set the form data
$smarty -> assign('server', $server);
$smarty -> assign('idlist', $idlist);
$smarty -> assign('crafterlist', $crafterlist);
$smarty -> assign('retainerlist', $retainerlist);
$smarty -> assign('itemnum', $itemnum);

// Display the form and other top part of the page
$head = $smarty -> createTemplate('head.tpl.html', $smarty);
$head -> display();
flush();

// Fix the crafters
$crafters = explode(',', $crafterlist);
foreach ($crafters as &$crafter)
{
	$crafter = trim($crafter);
}

// Fix the retainers
$retainers = explode(',', $retainerlist);
foreach ($retainers as &$retainer)
{
	$retainer = trim($retainer);
}

// Go through the list
$items = explode(',', $idlist);
foreach ($items as $item)
{
	// Validate the item data
	if (empty(trim($item)))
	{
		continue;
	}
	$item_id = intval(trim($item));
	if ($item_id === 0)
	{
		throw new \Exception(sprintf(_('Invalid item ID %s'), $item));
	}
	
	// Get the item data
	$item = $api -> content -> item() -> one($item_id);
	$category_id = $item -> ItemSearchCategoryTargetID;
	sleep(1);
	
	// Get the market data via the API
	$res = $api -> market -> price($server, $item_id);
	$res -> Item -> Category = $category_id;
	
	// Limit the item prices if need to
	if ($itemnum)
	{
		$res -> Prices = array_slice($res -> Prices, 0, $itemnum);
	}
	
	// Go through the price data and add some display classes
	$first = TRUE;
	foreach ($res -> Prices as &$price)
	{
		$classes = array();
		
		if ($first)
		{
			$classes[] = 'item-first';
			$first = FALSE;
		}
		
		if ((count($crafters)) && ($price -> IsCrafted) && (!in_array($price -> CraftSignature, $crafters)))
		{
			$classes[] = 'crafter-mismatch';
		}
		
		if ((count($retainers)) && (!in_array($price -> RetainerName, $retainers)))
		{
			$classes[] = 'retainer-mismatch';
		}
		
		$price -> Classes = implode(' ', $classes);
	}
	
	// Set data and output the template
	$item = $smarty -> createTemplate('item.tpl.html', $smarty);
	$item -> assign('data', $res);
	$item -> display();
	flush();
	
	// Sleep 1 second to avoid bombing the API too much
	sleep(1);
}

// Output rest of the HTML
$tail = $smarty -> createTemplate('tail.tpl.html', $smarty);
$tail -> display();
flush();
