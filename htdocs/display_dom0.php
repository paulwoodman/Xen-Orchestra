<?php
require_once dirname (__FILE__) . '/../includes/prepend.php';

//$id = isset($_POST['id']) ? $_POST['id'] : false;
//$id = 'xena1:9363';

//list($address, $port) = explode(':', $id, 2);

//$dom0 = Model::get_dom0($id,true);
//var_dump($dom0);
$result = array();

foreach (Model::get_dom0s() as $dom0)
{
	$result[] = $dom0->display_frame_all_vm();
}
echo json_encode($result);
