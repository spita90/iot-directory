<?php
   /* Snap4City: IoT-Directory
   Copyright (C) 2017 DISIT Lab https://www.disit.org - University of Florence

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA. */


$result=array("status"=>"","msg"=>"","content"=>"","log"=>"");	
/* all the primitives return an array "result" with the following structure

result["status"] = ok/ko; reports the status of the operation (mandatory)
result["msg"] a message related to the execution of the operation (optional)
result["content"] in case of positive execution of the operation the content extracted from the db (optional)
result["log"] keep trace of the operations executed on the db

This array should be encoded in json
*/	



header("Content-type: application/json");
header("Access-Control-Allow-Origin: *\r\n");
include ('../config.php');
include ('common.php');

//session_start();
$link = mysqli_connect($host, $username, $password) or die("failed to connect to server !!");
mysqli_select_db($link, $dbname);

//Altrimenti restituisce in output le warning
error_reporting(E_ERROR | E_NOTICE);


function compare_values($obj_a, $obj_b) {
  return  strcasecmp($obj_a->value_name,$obj_b->value_name);
}


if(!$link->set_charset("utf8")) 
{
    exit();
}
$node_data = json_decode(file_get_contents("php://input"));
if($node_data != null){
//if(isset($_POST['data']) && !empty($_POST['data']) && isset($_POST['data_from_nodeJs']) && !empty($_POST['data_from_nodeJs'])) 
//{
// $node_data=$_POST['data'];
$data_parallel=$node_data->data_parallel;
$action= $node_data->action;
}
else if(isset($_REQUEST['action']) && !empty($_REQUEST['action'])) 
    {
        $action = $_REQUEST['action'];
    }
else
{
    exit();
}

require '../sso/autoload.php';
use Jumbojett\OpenIDConnectClient;


if (isset($_REQUEST['nodered']))
{
   if ($_REQUEST['token']!='undefined')
      $accessToken = $_REQUEST['token'];
   else $accessToken = "";
} 
else
{
if (isset($_REQUEST['token'])) {
	  $oidc = new OpenIDConnectClient($keycloakHostUri, $clientId, $clientSecret);
          $oidc->providerConfigParam(array('token_endpoint' => $keycloakHostUri.'/auth/realms/master/protocol/openid-connect/token'));
          $tkn = $oidc->refreshToken($_REQUEST['token']);
          $accessToken = $tkn->access_token;
}
else $accessToken ="";
}

if (isset($_REQUEST['username'])) {
	$currentUser = $_REQUEST['username'];
}


//sara 1510 start
if ($action=="get_cb_details"){
	$contextbroker = mysqli_real_escape_string($link,$_REQUEST['cb']);
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	
	$q = "SELECT * FROM contextbroker WHERE name= '$contextbroker';";
	$r = mysqli_query($link, $q);
	
	$context = array();
	if($r){
		while($row = mysqli_fetch_assoc($r)) 
        {
			$rec= array();
			$rec["contextbroker"]= $row["name"];
			$rec["protocol"]=$row["protocol"];
			$rec["ip"]=$row["ip"];
			$rec["accessLink"]=$row["accesslink"];
			$rec["port"]=$row["port"];
			$rec["protocol"]=$row["protocol"];
			$rec["username"]=md5($username);
			$rec["apikey"]=$row["apikey"];
			$rec["kind"]=$row["kind"];
			$rec["path"]=$row["path"];

			array_push($context, $rec);         
		}	
		$result['status'] = 'ok';
		$result['content'] = $context;		
	}
	else{
	  $result["status"]='ko';
	  $result["msg"] .= "failure"; 
	  $result["log"] .= "\n Problem in get count temporary devices". generateErrorMessage($link); 
	 }
	 my_log($result);
     mysqli_close($link);
}
/*
*/
else if ($action=="get_count_temporary_devices"){
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$usernameNotHashed = $username;
	$username = md5($username);
	$query = "SELECT count(*) FROM temporary_devices WHERE username = '$username' AND organization='$organization' AND should_be_registered='no' AND deleted IS null";
	$r = mysqli_query($link, $query);

     if($r)
	 {
		 $result["status"]='ok';
		 $result["msg"] = 'anything' ; 
		 $result["log"] .= "\n get count temporary devices";
		 $row = mysqli_fetch_assoc($r);
         $result["content"]=$row["count(*)"];
             
		
		
		//Sara2510
	//	logAction($link,$usernameNotHashed,'temporary_devices','get_count_temporary_devices','','','success');
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "failure"; 
	  $result["log"] .= "\n Problem in get count temporary devices". generateErrorMessage($link); 
	//Sara2510
	//logAction($link,$username,'temporary_devices','get_count_temporary_devices','','','faliure');
	 }
	 my_log($result);
     mysqli_close($link);
}
else if($action=="get_multiple_cb_details"){
	$contextbrokers = json_decode($_REQUEST['cb']);
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);

	$i = 0;
	$context = array();

	while($i < count($contextbrokers)){
		$cb = $contextbrokers[$i];
		$q = "SELECT * FROM contextbroker WHERE name= '$cb';";
		$r = mysqli_query($link, $q);
	
		if($r){
			while($row = mysqli_fetch_assoc($r)) 
			{
				$rec= array();
				$rec["contextbroker"]= $row["name"];
				$rec["protocol"]=$row["protocol"];
				$rec["ip"]=$row["ip"];
				$rec["accessLink"]=$row["accesslink"];
				$rec["port"]=$row["port"];
				$rec["protocol"]=$row["protocol"];
				$rec["username"]=md5($username);
				$rec["apikey"]=$row["apikey"];
				$rec["kind"]=$row["kind"];
				$rec["path"]=$row["path"];

				array_push($context, $rec);         
			}	
			$i++;
		}
		else{
		  $result["status"]='ko';
		  $result["msg"] .= "failure"; 
		  $result["log"] .= "\n Problem in get count temporary devices". generateErrorMessage($link); 
		}
		$result['status'] = 'ok';
		$result['content'] = $context;		
	}
	
	 my_log($result);
     mysqli_close($link);
}
//sara 1510 end
else if ($action=="get_temporary_devices"){
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$username = md5($username);

	$q = "SELECT contextBroker, id, devicetype, model, status, macaddress,frequency,kind, 
	 protocol,format,latitude, longitude, visibility, k1, k2,producer, edge_gateway_type, edge_gateway_uri, validity_msg, should_be_registered
	FROM temporary_devices"; // WHERE username = '$username' AND deleted IS null;";
	//$r = mysqli_query($link, $q);	
	$r=create_datatable_data($link,$_REQUEST,$q, "deleted IS null AND should_be_registered='no' AND username = '$username' AND organization='$organization'");

	$selectedrows=-1;
	if($_REQUEST["length"] != -1)
	{
			$start= $_REQUEST['start'];
			$offset=$_REQUEST['length'];
			$tobelimited=true;
	}
	else
	{
		$tobelimited=false;
	}
     
	if($r) 
	{
      //$result['status'] = 'ok';
	  //$result['content'] = array();
	  $device = array();
	  $result["log"]= "\r\n action=get_temporary_devices \r\n";
      	 
	 while($row = mysqli_fetch_assoc($r)) 
        {	
			$selectedrows++;
			if (!$tobelimited || ($tobelimited && $selectedrows >= $start && $selectedrows < ($start+$offset)))
			{
				$rec= array();
				$rec["contextbroker"]=$row["contextBroker"];
				$rec["name"]=$row["id"];
				$rec["devicetype"]=$row["devicetype"];
				$rec["model"]=$row["model"];
				$rec["status"]=$row["status"];
				$rec["macaddress"]=$row["macaddress"];
				$rec["frequency"]=$row["frequency"];
				$rec["kind"]=$row["kind"];
				$rec["protocol"]=$row["protocol"];
				$rec["format"]=$row["format"];
				$rec["latitude"]=$row["latitude"];
				$rec["longitude"]=$row["longitude"];
				$rec["visibility"]=$row["visibility"];
				$rec["producer"]=$row["producer"];
				$rec["k1"]=$row["k1"];
				$rec["k2"]=$row["k2"];
				$rec["edge_gateway_type"]=$row["edge_gateway_type"];
				$rec["edge_gateway_uri"]=$row["edge_gateway_uri"];
				$rec["validity_msg"]=$row["validity_msg"];
				
				array_push($device, $rec);           
			}
		}
	 $output= format_result($_REQUEST["draw"], $selectedrows+1, $selectedrows+1, $device, "", "\r\n action=get_temporary_devices \r\n", 'ok');	
    }
	else{
	$output= format_result($_REQUEST["draw"], 0, 0, null, 'Error: errors in reading data about devices. <br/>' . generateErrorMessage($link), '\n\r Error: errors in reading data about devices.' . generateErrorMessage($link), 'ko');

	}    
	my_log($output);
	mysqli_close($link);
}
else if($action == "get_temporary_attributes")
{
	
	$id = mysqli_real_escape_string($link, $_REQUEST['id']);
	$cb = mysqli_real_escape_string($link, $_REQUEST['contextbroker']);
	$organization = mysqli_real_escape_string($link, $_REQUEST['organization']);
	
	$q1 = "SELECT * FROM temporary_event_values WHERE device = '$id' AND cb = '$cb'";
	
	$r1 = mysqli_query($link, $q1);
	
     $attributes = array();
	 if($r1){
		 while($row1 = mysqli_fetch_assoc($r1)) 
				{ 
				  $rec1=array();
				  $rec1["value_name"]=$row1["value_name"];
				  $rec1["data_type"]=$row1["data_type"];
				  $rec1["value_type"]=$row1["value_type"];
				  $rec1["editable"]=$row1["editable"];
				  $rec1["value_unit"]=$row1["value_unit"];
				  $rec1["old_value_name"]=$row1["old_value_name"];
				  $rec1["healthiness_criteria"]=$row1["healthiness_criteria"];
				  if($rec1["healthiness_criteria"]=="refresh_rate") 
						  $rec1["healthiness_value"]=$row1["value_refresh_rate"];
				  if($rec1["healthiness_criteria"]=="different_values") 
						  $rec1["healthiness_value"]=$row1["different_values"];
				  if($rec1["healthiness_criteria"]=="within_bounds") 
						  $rec1["healthiness_value"]=$row1["value_bounds"];						  
				  array_push($attributes, $rec1);
		}
		$result['status'] = 'ok';
		$result['content'] = $attributes;
		$result['log'] .= "\n\r action:get_device_attributes. access to " . $q1;
	 }
	 else
	 {
	    $result['status'] = 'ko'; // . $q1 . generateErrorMessage($link);
		$result['msg'] = 'Error: errors in reading data about devices. <br/>' .
						  generateErrorMessage($link);
		$result['log'] .= '\n\naction:get_device_attributes. Error: errors in reading data about devices. ' .
						  generateErrorMessage($link);				  
	}
	my_log($result);
	mysqli_close($link); 
}
else if ($action=="update")
{   
	$result["msg"] .= "update"; 
	//Sara2210 start
	$username = mysqli_real_escape_string($link, $_REQUEST['username']);
	//Sara2210 end
	$id = mysqli_real_escape_string($link, $_REQUEST['id']);
	$old_id = mysqli_real_escape_string($link, $_REQUEST['old_id']);
	$devicetype = mysqli_real_escape_string($link, $_REQUEST['type']); 
	$contextbroker = mysqli_real_escape_string($link, $_REQUEST['contextbroker']);  
	$old_contextbroker = mysqli_real_escape_string($link, $_REQUEST['old_cb']);  
	$kind = mysqli_real_escape_string($link, $_REQUEST['kind']);  
	$protocol = mysqli_real_escape_string($link, $_REQUEST['protocol']);  
	$format = mysqli_real_escape_string($link, $_REQUEST['format']);  
	$macaddress = mysqli_real_escape_string($link, $_REQUEST['mac']);  
	$model = mysqli_real_escape_string($link, $_REQUEST['model']);  
	$producer = mysqli_real_escape_string($link, $_REQUEST['producer']);  
	$latitude= mysqli_real_escape_string($link, $_REQUEST['latitude']);  
	$longitude = mysqli_real_escape_string($link, $_REQUEST['longitude']); 
    $status = mysqli_real_escape_string($link, $_REQUEST['status']);
    $validity_msg = mysqli_real_escape_string($link, $_REQUEST['validity_msg']);
	$uri = mysqli_real_escape_string($link, $_REQUEST['uri']);
	$organization = mysqli_real_escape_string($link, $_REQUEST['organization']);
	$k1= $_REQUEST['k1'];
    $k2= $_REQUEST['k2'];
	if (isset( $_REQUEST['edge_gateway_type']))
		$edge_gateway_type = $_REQUEST['edge_gateway_type'];
	else $edge_gateway_type="";
	if (isset( $_REQUEST['edge_gateway_uri']))
		$edge_gateway_uri = $_REQUEST['edge_gateway_uri'];
	else $edge_gateway_uri="";
	$visibility = mysqli_real_escape_string($link, $_REQUEST['visibility']);  
	$frequency= mysqli_real_escape_string($link, $_REQUEST['frequency']);
	
	$listdeleteAttributes= json_decode($_REQUEST['deleteattributes']);
	$listAttributes= json_decode($_REQUEST['attributes']);
	$listnewAttributes= json_decode($_REQUEST['newattributes']);
	
	if ($listAttributes==null) $merge=$listnewAttributes;
	else if ($listnewAttributes==null) $merge=$listAttributes;
	else $merge=array_merge($listAttributes,$listnewAttributes, 'compare_values');
	
	if ($listdeleteAttributes!=null) $merge= array_udiff($merge, $listdeleteAttributes, 'compare_values');
	
	//Sara2510 - for logging purpose
	$deviceName= $old_id . " ".$old_contextbroker; 
					
	$q = null;
	// The following code has to decide the search key in the tables, then the behavior is analogous.
	
	$s1 = true; $s2 = true; $s3=true;
	$notDuplicate = true;
	if($old_id != $id || $old_contextbroker != $contextbroker){
		$result["msg"] .= "\n old != new"; 
			// look for the presence of other devices with the same identifiers
		$select = "SELECT contextBroker, id
		FROM temporary_devices WHERE  contextBroker = '$contextbroker'
		AND id = '$id' AND deleted IS null;";
		
		$selectDevices = "SELECT contextBroker, id
		FROM devices WHERE contextBroker = '$contextbroker'
		AND id = '$id' AND deleted IS null;";
        
        $selectDevicesDeleted = "SELECT contextBroker, id
		FROM deleted_devices WHERE contextBroker = '$contextbroker'
		AND id = '$id';";
		
		$s1 = mysqli_query($link, $select);
		$s2 = mysqli_query($link, $selectDevices);
		$s3 = mysqli_query($link, $selectDevicesDeleted);
		$notDuplicate = (mysqli_num_rows($s1) == 0 && mysqli_num_rows($s2) == 0 && mysqli_num_rows($s3) == 0);
		$result["msg"] .= "mysql s1 ".mysqli_num_rows($s1)."mysqli s2 ".mysqli_num_rows($s2)."mysqli s3 ".mysqli_num_rows($s3);
	}
    else
	{
		$result["msg"] .= "\n old = new, id ".$id . " old_id ".$old_id . " cb ".$contextbroker . " old cb ".$old_contextbroker ; 
	}		
		$result["msg"] .= "if"; 
		

	$s1 = true; $s2 = true; $s3=true;	
    if($s1 && $s2 && $s3){
			if($notDuplicate){
			$q = "UPDATE temporary_devices SET id='$id', 
			contextBroker='$contextbroker', 
			devicetype='$devicetype', 
			kind= '$kind', 
			protocol='$protocol', 
			format='$format', 
			macaddress='$macaddress', 
			model='$model', 
			producer='$producer', 
			latitude='$latitude', 
			longitude='$longitude', 
			status='$status', 
			validity_msg='$validity_msg' ,
            organization='$organization',
			frequency = '$frequency', 
			visibility = '$visibility',
            k1 = '$k1',
            k2 = '$k2',
			edge_gateway_type = '$edge_gateway_type',
			edge_gateway_uri = '$edge_gateway_uri'
			WHERE id='$old_id' and contextBroker='$old_contextbroker';";
			
			$r = mysqli_query($link, $q);

			if($r) 
			{
				$result["msg"] .= "\n Device $contextbroker/$id correctly updatedd " .count($listAttributes);
				$result["log"] .= "\r\n Device $contextbroker/$id correctly updated log ".count($listAttributes);

				//Sara2510 - for logging purpose
				logAction($link,$username,'temporary_devices','update',$deviceName,$organization,'','success');

				$ok=true;
				$q="";
				$a=0;

				while ($a < count($listAttributes) && $ok)
				{
				   $att=$listAttributes[$a];	
				   if ($att->healthiness_criteria=="refresh_rate")  $hc="value_refresh_rate";
				   else if ($att->healthiness_criteria=="different_values") $hc="different_values";
				   else $hc="value_bounds";					

					 $upquery="UPDATE `temporary_event_values` SET `cb`='$contextbroker', `device` = '$id',
					  `value_name`='". $att->value_name . "',`data_type`='" . $att->data_type . "',`value_type`='". $att->value_type. "', 
					  `editable`='". $att->editable. "', `value_unit`='". $att->value_unit."', `healthiness_criteria`='". $att->healthiness_criteria."',
					  $hc='". $att->healthiness_value ."'  WHERE  `cb`='$old_contextbroker' 
					  AND `device`='$old_id' AND old_value_name='". $att->old_value_name . "';";


					$r1 = mysqli_query($link, $upquery);
					//   echo $upquery;
					  if ($r1) 
					  {
						  $result["msg"] .= "\n attribute $att->value_name with old name $att->old_value_name correctly updated";
						  $result["log"] .= "\n attribute $att->value_name correctly updated";
					  }
					  else 
					  {
						  $result["msg"] .= "<br/> attribute $att->value_name was not updated " .  generateErrorMessage($link); 
						  $result["log"] .= "\r\n attribute $att->value_name was not updated " . $upquery . " " .  generateErrorMessage($link); 
						  $ok=0;
					  } 
					  $a++;
				}
			//echo "valore di ok". $ok;    
				if ($ok==true)
				{
					$result["msg"] .= "\n old attributes correctly updated"; 
					$result["log"] .= "\n old attributes correctly updated" . $q; 
					
					$q="";
					$a=0;
					while ($a < count($listnewAttributes) && $ok)
					{
						$att=$listnewAttributes[$a];
						if ($att->healthiness_criteria=="refresh_rate")  $hc="value_refresh_rate";
						else if ($att->healthiness_criteria=="different_values") $hc="different_values";
						else $hc="value_bounds";
				   
						$insertquery="INSERT INTO `temporary_event_values`(cb, device, old_value_name,value_name, data_type, value_type, editable,value_unit,healthiness_criteria,$hc)
						VALUES('$contextbroker','$id','$att->value_name','$att->value_name','$att->data_type','$att->value_type','$att->editable','$att->value_unit','$att->healthiness_criteria','$att->healthiness_value')";			 	   
			   
						$r1 = mysqli_query($link, $insertquery);
						if ($r1) 
						{
							$result["msg"] .= "\n attribute $att->value_name correctly inserted";
							$result["log"] .= "\n attribute $att->value_name correctly inserted";
						}
						else 
						{
							$result["msg"] .= "\n attribute $att->value_name was not inserted " . generateErrorMessage($link); 
							$result["log"] .= "\n attribute $att->value_name was not inserted " . $insertquery . " " . generateErrorMessage($link); 
							$ok=false;
						} 
						$a++;
					}

				 }
				  if ($ok==true)
				{
					$result["msg"] .= "\n new attributes correctly inserted msg " . $contextbroker .count($listdeleteAttributes) ; 
					$result["log"] .= "\n new attributes correctly inserted log " . $contextbroker .count($listdeleteAttributes) ; 
					$q="";
					$a=0;
					
					while ($a < count($listdeleteAttributes) && $ok)
					{
						$att=$listdeleteAttributes[$a];
						$a++;
						$deletequery="DELETE FROM temporary_event_values WHERE cb='$contextbroker' AND device='$id' AND value_name='". $att->value_name . "';";
						$r1 = mysqli_query($link, $deletequery);
						if ($r1) 
						{
							$result["msg"] .= "\n attribute $att->value_name correctly deleted";
							$result["log"] .= "\n attribute $att->value_name correctly deleted";
						}
						else 
						{
							$result["msg"] .= "\n attribute $att->value_name was not deleted " . generateErrorMessage($link); 
							$result["log"] .= "\n attribute $att->value_name was not deleted " . $deletequery . " " . generateErrorMessage($link); 
							$ok=false;
						} 
					}
									
				if ($ok==true)
				 {
					$result["msg"] .= "\n ok value true ". $ok; 
					 $result["status"]='ok';
				 }
				 else
				 {
				   $result["msg"] .= "\n ok value false ". $ok; 
				   $result["status"]='ko';
				 }
				 my_log($result);
				 mysqli_close($link); 
				}

			}
			else
			{
			  //Sara2510 - for logging purpose
			  logAction($link,$username,'temporary_devices','update',$deviceName,$organization,'problem updating','faliure');
			  $result["status"]='ko';
			  $result["msg"] .= "\n Problem in updating the device $id:" . generateErrorMessage($link); 
			  $result["log"] .= "\n Problem in updating the device $id:" . " " . generateErrorMessage($link); 
			  my_log($result);
			  mysqli_close($link); 
			}  
			}//duplicated values
			else{
				//Sara2510 - for logging purpose
				logAction($link,$username,'temporary_devices','update',$deviceName,$organization,'duplicated','faliure');
				
			   $result["status"]='ko';
			   $result["msg"] .= "\n You alredy have a device named $id for $contextbroker context broker, not inserted."; 
			   $result["log"] .= "\r\n Problem in inserting the device $id:  " .  generateErrorMessage($link); 
			   my_log($result);
			   mysqli_close($link); 
			}	
		}//select failed	
		else{
		   
		   //Sara2510 - for logging purpose
		   logAction($link,$username,'temporary_devices','update',$deviceName,$organization,'','faliure');
		   $result["status"]='ko';
		   $result["log"] .= "\r\n Problem selecting the device $id:  " .  generateErrorMessage($link); 
		//   my_log($result);
		   mysqli_close($link); 
		}		
}  
else if ($action=="bulkload")
{
	$time_start = microtime(true);
			  if (isset($node_data->token)) {
			  $oidc = new OpenIDConnectClient($keycloakHostUri, $clientId, $clientSecret);
			  $oidc->providerConfigParam(array('token_endpoint' => $keycloakHostUri.'/auth/realms/master/protocol/openid-connect/token'));

			  $tkn = $oidc->refreshToken($node_data->token);
			  $accessToken = $tkn->access_token; 
			}
			else $accessToken ="";
			

			if (isset($node_data->username)) {
				$currentUser = $node_data->username;
			}
  
  $username = mysqli_real_escape_string($link,$node_data->username);
  $organization = mysqli_real_escape_string($link,$node_data->organization);
  $kbUrl = mysqli_real_escape_string($link,$node_data->kbUrl);
  $usernameNotHashed = $username;
  $username = md5($username);

  
   //asynchBulk('../api/asynchBulk.php',array('username'=>$username,'link'=>$link, 'pathCertificate'=>$pathCertificate, 'accessToken'=>$accessToken));

    //---find total number of valid divices to be inserted-------
    $totalValid=0;
    $qcount = "SELECT count(*) FROM temporary_devices WHERE username = '$username'AND status='valid' AND organization= '$organization' AND should_be_registered='no' AND deleted IS null;";
	$rcount = mysqli_query($link, $qcount);
    
    if($rcount)
	 {
        $row = mysqli_fetch_assoc($rcount);
        $totalValid=$row["count(*)"];
                 
	 }
    //-----------------------------------------------
    
    try{
    //---update the bulk_status table that the bulk is processing----------
    $qupdate= "INSERT INTO bulkload_status (username, is_bulk_processing, number_processed,totale, is_finished ) VALUES('".$username."', 1, 0,".$totalValid.", 0) ON DUPLICATE KEY UPDATE is_bulk_processing=1, number_processed=0, totale=".$totalValid.", is_finished=0;";
    $b=mysqli_query($link, $qupdate);
    //-----------------------------------------------

	
	$q = "SELECT contextBroker, id, devicetype, model, status, macaddress,frequency,kind, 
	 protocol,format,latitude, longitude, visibility, k1, k2,producer, edge_gateway_type, edge_gateway_uri, 
	 validity_msg, subnature, static_attributes FROM temporary_devices WHERE username = '$username' 
	 AND deleted IS null AND organization='$organization' AND  should_be_registered='no';";
	$r = mysqli_query($link, $q);	
	
    $resultInfo = array();
	$result["content"]=array();
    //$selectedrows=-1;
    $counter=0;
    $numberValidProcessed=0;
    
    $continue_processing=1;

	if($r) 
	{
		 while($row = mysqli_fetch_assoc($r)) 
		{	
				
             if($row['status']=='valid')
				{
					
                    
                    if($counter==50){
			  $oidc = new OpenIDConnectClient($keycloakHostUri, $clientId, $clientSecret);
                          $oidc->providerConfigParam(array('token_endpoint' => $keycloakHostUri.'/auth/realms/master/protocol/openid-connect/token'));

                          $tkn = $oidc->refreshToken($node_data->token);
                          $accessToken = $tkn->access_token;



                         usleep(500000);//sleep for 500 ms after each 50 insertion
                         $counter=0;
                        
                        //---update the bulk_status table----------
                    $qupdate= "UPDATE bulkload_status SET  number_processed=".$numberValidProcessed." WHERE username = '".$username."';";
                        mysqli_query($link, $qupdate);
                        //-----------------------------------------------
                        
                    $qcontinue= "select is_bulk_processing from bulkload_status where username= '".$username."';";
                    $rc= mysqli_query($link, $qcontinue);
                        if($rc){
                           $row_continue = mysqli_fetch_assoc($rc);
                               $continue_processing=intval($row_continue['is_bulk_processing']);
                               if($continue_processing==0)
                                   break;
                               
                        }
                        
                     }
                    $counter=$counter+1;
                    
                    $numberValidProcessed=$numberValidProcessed+1;
                 
                    if($numberValidProcessed>$totalValid){
                        break;
                    }
                    
                    
                    $q1 = "SELECT * FROM temporary_event_values WHERE device = '".$row["id"]."' AND cb = '".$row["contextBroker"]."'";
					$r1 = mysqli_query($link, $q1);
					$deviceattributes = array();
					if($r1)
					{	
						while($row1 = mysqli_fetch_assoc($r1)) 
						{ 
						  $rec1=array();
						  $rec1["value_name"]=$row1["value_name"];
						  $rec1["data_type"]=$row1["data_type"];
						  $rec1["value_type"]=$row1["value_type"];
						  $rec1["editable"]=$row1["editable"];
						  $rec1["value_unit"]=$row1["value_unit"];
						  $rec1["healthiness_criteria"]=$row1["healthiness_criteria"];
						  if($rec1["healthiness_criteria"]=="refresh_rate") 
								  $rec1["healthiness_value"]=$row1["value_refresh_rate"];
						  if($rec1["healthiness_criteria"]=="different_values") 
								  $rec1["healthiness_value"]=$row1["different_values"];
						  if($rec1["healthiness_criteria"]=="within_bounds") 
								  $rec1["healthiness_value"]=$row1["value_bounds"];						  
						  array_push($deviceattributes, $rec1);    
						}

						insert_device($link, $row["id"],$row["devicetype"],$row["contextBroker"],$row["kind"],$row["protocol"],$row["format"],
						$row["macaddress"],$row["model"],$row["producer"],$row["latitude"],$row["longitude"],
						$row["visibility"], $row["frequency"], $row["k1"], $row["k2"], $row["edge_gateway_type"],
						$row["edge_gateway_uri"],json_decode(json_encode($deviceattributes)),$row["subnature"],$row["static_attributes"],$pathCertificate,
						$accessToken,$result,'no',$organization,$kbUrl,$username);
						
					   //Sara2210
						$deviceName = $row["id"] . " ".$row["contextBroker"];
						$rec = array();
						$rec["device"]=$row["id"]; 
						$rec["cb"]=$row["contextBroker"]; 										   

						if($result["status"]=="ok"){ // whenver the previous insert succeded
							//Sara2210
							logAction($link,$usernameNotHashed,'device','bulkload',$deviceName,$organization,$result["msg"],'success');
							$rec["inserted"]="ok";
							
							
                            $qdelete = "DELETE FROM temporary_devices  WHERE username = '$username' AND 
							id = '".$row["id"]."' AND contextBroker = '".$row["contextBroker"]."'
							AND deleted is null AND should_be_registered='no' AND status = 'valid'";
                            
                            //why label them as to be deleted while we can delete immediately...
							/*$qupdate = "UPDATE temporary_devices 
							SET toDelete='yes'
							WHERE username = '$username' AND 
							id = '".$row["id"]."' AND contextBroker = '".$row["contextBroker"]."'
							AND deleted is null AND status = 'valid'";
												
							$rupdate = mysqli_query($link, $qupdate);*/
                            $qdelete = mysqli_query($link, $qdelete);
						 
							if($qdelete)
							{
								logAction($link,$usernameNotHashed,'temporary_devices','bulkload deleted after insertion',$deviceName,$organization,'','success');

								$result["status"]='ok';
								$result["msg"] .= "\n  Device ".$deviceName." deleted from temporary_devices"; 
								$result["log"] .= "\n  Device ".$deviceName." deleted from temporary_devices";
								
								$rec["deleted"]="ok";
								 			
								
							}
							 else
							 {
							 logAction($link,$usernameNotHashed,'temporary_devices','bulkload update deleted',$deviceName,$organization,'Problem in deleting the device','faliure');
							  $result["status"]='ko';
							  $result["msg"] .= "\n Problem in deleting the device $id: " . generateErrorMessage($link); 
							  $result["log"] .= "\n Problem in deleting the device $id: " . generateErrorMessage($link);

							  $rec["deleted"]="ko";
							 }
												 
					   }
					   else if($result["status"]=="ko"){
							//Sara2210
							logAction($link,$usernameNotHashed,'device','bulkload',$deviceName,$organization,$result["msg"],'faliure');							   
							$rec["inserted"]="ko";
						}
					}
					else
					{
						//Sara2210
						logAction($link,$usernameNotHashed,'device','bulkload','',$organization,'Error: errors in reading data about devices.','faliure');
						$result['status'] = 'ko'; // . $q1 . generateErrorMessage($link);
						$result['msg'] .= 'CCC Error: errors in reading data about devices. <br/>' .
										  generateErrorMessage($link);
						$result['log'] .= '\n\naction:bulkload. Error: errors in reading data about devices. ' .
										  generateErrorMessage($link);										   
					 
						$rec["inserted"]="ko";
					}	
					array_push($resultInfo, $rec);	
                 
                 if($numberValidProcessed==$totalValid){
						//---update the bulk_status table----------
						$qupdate= "UPDATE bulkload_status SET  is_bulk_processing=0, number_processed=0, totale=0, is_finished=1 WHERE username = '".$username."';";
						$b=mysqli_query($link, $qupdate);
					}
					
				}
				else{
					$rec = array();
					$rec["device"]=$row["id"]; 
					$rec["cb"]=$row["contextBroker"];
					$rec["inserted"]='ko';
					array_push($resultInfo, $rec);
				}
													 $time_end = microtime(true);
								$time = $time_end - $time_start;
								$ti =  'Execution time : '.$time.' seconds\n\r'; 
								
								$result["msg"] = $ti;
		}
		if(mysqli_num_rows($r)==0){
		  $result["msg"] .= "\n No item to be inserted"; 			
		}
		else{
			$result["content"]=array();
			array_push($result["content"],$resultInfo);		
		}
    }
	else{
		//Sara2210
		logAction($link,$usernameNotHashed,'device','bulkload','',$organization,'','failure');
	   $result['status'] = 'ko';
	   $result['msg'] = 'Error: errors in retrieving the devices from temporary_devices`. <br/>' .
						   generateErrorMessage($link);
	   $result['log'] = '\n\r errors in retrieving the devices from temporary_devices.' .
	   generateErrorMessage($link);
	}    
	my_log($result);
    
    //---update the bulk_status table----------
    $qupdate= "UPDATE bulkload_status SET  is_bulk_processing=0, number_processed=0, totale=0, is_finished=1 WHERE username = '".$username."';";
    $b=mysqli_query($link, $qupdate);
    //-----------------------------------------------

		mysqli_close($link);
    }
    catch (Exception $e) {
        $qupdate= "UPDATE bulkload_status SET  is_bulk_processing=0, number_processed=0, totale=0 WHERE username = '".$username."';";
        $b=mysqli_query($link, $qupdate);
         mysqli_close($link);
    }
}
else if ($action=="get_bulk_status")
{
    $username = mysqli_real_escape_string($link,$_REQUEST['username']);
    $username=md5($username);
    $query = "SELECT is_bulk_processing, number_processed, totale, is_finished FROM bulkload_status  WHERE username = '".$username."';";
    $r = mysqli_query($link, $query);

     if($r)
	 {
		 $result["status"]='ok';
		 $result["msg"] = 'bulk status found' ; 
		 $result["log"] .= "\n bulk status found";
		 $row = mysqli_fetch_assoc($r);
         $result["is_bulk_processing"]=$row["is_bulk_processing"];
         $result["number_processed"]=$row["number_processed"];
         $result["totale"]=$row["totale"];
         $result["is_finished"]=$row["is_finished"];
                      
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "failure"; 
	  $result["log"] .= "\n Problem in get bulk status". generateErrorMessage($link); 
	}

	 my_log($result);
     mysqli_close($link);
}
else if ($action=="stop_bulk")
{
    $username = mysqli_real_escape_string($link,$_REQUEST['username']);
    $username=md5($username);
    
    $query= "UPDATE bulkload_status SET  is_bulk_processing=0, number_processed=0, totale=0 WHERE username = '".$username."';";
    $r=mysqli_query($link, $query);
   

     if($r)
	 {
		 $result["status"]='ok';
		 $result["msg"] = 'bulk will be stoped' ; 
		 $result["log"] .= "\n bulk will be stoped";
		     
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "failure"; 
	  $result["log"] .= "\n Problem in stopping bulk". generateErrorMessage($link); 
	}

	 my_log($result);
     mysqli_close($link);
}
else if ($action=="delete_temporary")
{
     $id = mysqli_real_escape_string($link,$_REQUEST['id']);
	 $cb = mysqli_real_escape_string($link,$_REQUEST['contextbroker']);
	 $url = mysqli_real_escape_string($link,$_REQUEST['uri']);
	 $organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	 
	 //Sara2510
	 $username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$deviceName = $id . " ".$cb;
	
	// if ($result["status"]=='ko') return $result;
			
	//  $query = "UPDATE temporary_devices SET deleted = '". date("Y/m/d") . "' WHERE id = '$id' and contextBroker='$cb'";
     $query = "DELETE FROM temporary_devices  WHERE id = '$id' and contextBroker='$cb' and should_be_registered='no'";
	 $r = mysqli_query($link, $query);

     if($r)
	 {
		 $result["status"]='ok';
		 $result["msg"] .= "\n Device $id/$cb and corresponding values corretly removed from temporary devices"; 
		 $result["log"] .= "\n Device $id/$cb and corresponding values corretly removed from temporary devices";
		
		//Sara2510
		 logAction($link,$username,'temporary_devices','delete',$deviceName,$organization,'','success');
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "\n Problem in deleting the device $id: " . generateErrorMessage($link); 
	  $result["log"] .= "\n Problem in deleting the device $id: " . $query . " " . generateErrorMessage($link); 
	//Sara2510
	logAction($link,$username,'temporary_devices','delete',$deviceName,$organization,'','faliure');
	 }
	 my_log($result);
     mysqli_close($link);
}
else if ($action=="delete_temporary")
{
     $id = mysqli_real_escape_string($link,$_REQUEST['id']);
	 $cb = mysqli_real_escape_string($link,$_REQUEST['contextbroker']);
	 $url = mysqli_real_escape_string($link,$_REQUEST['uri']);
	 $organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	 
	 //Sara2510
	 $username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$deviceName = $id . " ".$cb;
	
	// if ($result["status"]=='ko') return $result;
			
	//  $query = "UPDATE temporary_devices SET deleted = '". date("Y/m/d") . "' WHERE id = '$id' and contextBroker='$cb'";
     $query = "DELETE FROM temporary_devices  WHERE id = '$id' and contextBroker='$cb'";
	 $r = mysqli_query($link, $query);

     if($r)
	 {
		 $result["status"]='ok';
		 $result["msg"] .= "\n Device $id/$cb and corresponding values corretly removed from temporary devices"; 
		 $result["log"] .= "\n Device $id/$cb and corresponding values corretly removed from temporary devices";
		
		//Sara2510
		// logAction($link,$username,'temporary_devices','delete',$deviceName,'','success');
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "\n Problem in deleting the device $id: " . generateErrorMessage($link); 
	  $result["log"] .= "\n Problem in deleting the device $id: " . $query . " " . generateErrorMessage($link); 
	//Sara2510
	//logAction($link,$username,'temporary_devices','delete',$deviceName,'','faliure');
	 }
	 my_log($result);
     mysqli_close($link);
}
else if ($action=="delete_all_temporary")
{
     $username = mysqli_real_escape_string($link,$_REQUEST['username']);
	 $usernameNotHashed = $username;
	 $username = md5($username);
	 
	 
     $query = "DELETE FROM temporary_devices  WHERE username = '$username'";
	 $r = mysqli_query($link, $query);
	 
     if($r)
	 {
		// logAction($link,$usernameNotHashed,'temporary_devices','delete all','','','success');
		 $result["status"]='ok';
		 $result["msg"] .= "\n All devices has been removed"; 
		 $result["log"] .= "\n All devices has been removed";
	 }
	 else
	 {
	  $result["status"]='ko';
	  $result["msg"] .= "\n Problem in deleting all the devices: " . generateErrorMessage($link); 
	  $result["log"] .= "\n Problem in deleting all the devices: " . $query . " " . generateErrorMessage($link);
    //  logAction($link,$usernameNotHashed,'temporary_devices','delete all','','','faliure');
	  
	 }
	 my_log($result);
     mysqli_close($link);
}

else if($action=='delete_after_insert')
{
    $username = mysqli_real_escape_string($link,$_REQUEST['username']);
    $organization = mysqli_real_escape_string($link,$_REQUEST['$organization']);
    $usernameNotHashed = $username;
    $username = md5($username);
    
	
    $qdel = "DELETE FROM temporary_devices 
    WHERE username = '$username' AND 	
    toDelete = 'yes' AND deleted is null AND status = 'valid'";

    $rdel = mysqli_query($link, $qdel);
    if($rdel)
    {
       // logAction($link,$usernameNotHashed,'temporary_devices','bulkload delete after insert','','','success');
        $result["status"]='ok';
        $result["msg"] .= "\n  Valid devices correctly removed from temporary devices"; 
		$result["log"] .= "\n  Valid devices correctly removed from temporary devices";	
    }
    else
    {
        logAction($link,$usernameNotHashed,'temporary_devices','bulkload delete after insert','',$organization,'','faliure');
        $result["status"]='ko';
        $result["msg"] .= "\n Problem in deleting the valid devices inserted " . generateErrorMessage($link);
        $result["log"] .= "\n Problem in deleting the valid devices inserted " . generateErrorMessage($link); 	
    }
}
else if($action == 'suggest_associations'){
	$value = mysqli_real_escape_string($link,$_REQUEST['value']);
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$username = md5($username);
    $organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$value2 = $value;
	$value = 'output_'.$value;
	$query = "SELECT * FROM association_rules  WHERE $value <> '' AND $value IS NOT NULL AND lift>1 ORDER BY lift DESC";
	$r = mysqli_query($link, $query);

	$rules = array();
	$eraseDuplicate = array();
	$sameDevice = array();
	
	if($r){
		while($row = mysqli_fetch_assoc($r)){
			$associations = array();

			$associations['input_data_type'] = $row['input_data_type'];
			$associations['input_value_type'] = $row['input_value_type'];
			$associations['input_value_unit'] = $row['input_value_unit'];
			$associations['input_context_broker'] = $row['input_context_broker'];
			$associations['input_device_type'] = $row['input_device_type'];
			$associations['input_model'] = $row['input_model'];
			$associations['input_protocol'] = $row['input_protocol'];
			$associations['input_format'] = $row['input_format'];
			$associations['output_data_type'] = $row['output_data_type'];
			$associations['output_value_type'] = $row['output_value_type'];
			$associations['output_value_unit'] = $row['output_value_unit'];
			$associations['output_context_broker'] = $row['output_context_broker'];
			$associations['output_device_type'] = $row['output_device_type'];
			$associations['output_model'] = $row['output_model'];
			$associations['output_protocol'] = $row['output_protocol'];
			$associations['output_format'] = $row['output_format'];
			$associations['support'] = $row['support'];
			$associations['lift'] = $row['lift'];
			array_push($eraseDuplicate,$associations);
		}


		$associations = array_unique($eraseDuplicate,SORT_REGULAR);

		foreach($associations as $a){

			$listAffected = "SELECT count(*) as tot, td.id , te.value_name  FROM temporary_event_values te, temporary_devices td WHERE td.contextbroker = te.cb AND td.id = te.device AND ($value2 IS NULL OR $value2 = '')";
		
			$query_where="";
			foreach($a as $key=>$q){
				$q = strtolower($q);
				if($q != '' OR !strcmp($q,'null')===0){
					$key2= $key;


					if( (strcmp(substr($key, 0, 7),"output_") === 0) or (strcmp($key,"support")===0) or (strcmp($key,"lift")===0)){
						continue;
					}
					$query_where .= " AND " . substr($key, 6, strlen($key)) . " = '".$q . "' ";
				}
			}

			$listAffected .= $query_where. " AND td.username = '$username' AND td.organization = '$organization' GROUP BY td.id, te.value_name;";
			//echo($listAffected . "\n");
		//	echo($listAffected);
			/*
			AND (te.data_type = '".$associations['input_data_type']."' OR te.data_type IS NULL) AND (te.value_type ='". $associations['input_value_type'] ."' OR te.value_type IS NULL)AND (te.value_unit ='". $associations['input_value_unit']."'
			OR te.value_unit IS NULL) AND	(te.cb = '".$associations['input_context_broker']."' OR te.cb IS NULL) AND (td.device_type ='". $associations['input_device_type'] ."' OR te.device_type IS NULL)
			AND td.model = '".$associations['input_model']."' OR td.model IS NULL) AND (td.protocol ='". $associations['input_protocol']."' OR td.protocol IS NULL) AND (td.format = '".$arrociations['input_format']. "'OR td.format IS NULL);";
			*/
			$numberRowsAffected = mysqli_query($link, $listAffected);
			if($numberRowsAffected){
				while($res = mysqli_fetch_assoc($numberRowsAffected)){
					$strDevValue = $res['id'] . $res['value_name'];
					if($res['tot'] > 0 && !in_array($strDevValue,$sameDevice)){
						//	echo($listAffected ."\n");
						array_push($sameDevice,$strDevValue);
						array_push($rules, $a);
					}
					/*
					print_r($sameDevice);
					Array
					(
						[0] => 373773207E330100valueNameDeviceApriori
						[1] => fmi-106948valueApriori
					)
					*/
				}


			}

		}
		$rules = array_unique($rules);
	//	print_r($rules);
		$result["status"]='ok';
		$result['content'] = $rules;
		$result["msg"] .= "Association rules correctly retrieved"; 
	}
	else{
		$result["status"]='ko';
		$result["msg"] .= "\n Problems during the retrieval of the association rules"; 
		$result["log"] .= "\r\n Problem in retrieving the rules" .  generateErrorMessage($link); 
	}
	my_log($result);
	mysqli_close($link); 
}
else if ($action=="get_rules_affecting_count"){
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$attributesIf = json_decode($_REQUEST['attributesIf']);
	$value = mysqli_real_escape_string($link,$_REQUEST['value']);

	$usernameNotHashed = $username;
	$username = md5($username);

/*	if ($listAttributes==null) $merge=$listnewAttributes;
	else if ($listnewAttributes==null) $merge=$listAttributes;
	else $merge=array_merge($listAttributes,$listnewAttributes, 'compare_values');
	
*/	
if(count($attributesIf)!=0){
	$query="SELECT count(*) as tot FROM temporary_event_values te, temporary_devices td  WHERE te.cb = td.contextbroker AND te.device = td.id";


	//for healthiness value
		$hv= 0;
		$hcriteria="";
		while ($hv < count($attributesIf))
		{
			$attHv=$attributesIf[$hv];	
			if($attHv->field== "healthiness_criteria"){
				if($attHv->value == "refresh_rate"){
					$hcriteria = "value_refresh_rate";
				}
				else if($attHv->value=="within_bounds"){
					$hcriteria = "value_bounds";
				}
				else{
					$hcriteria = $attHv->value;
				}
				break;
			}
			$hv++;
		}
	//end


		$a=0;
		while ($a < count($attributesIf))
		{
			$attIf=$attributesIf[$a];	
			if($attIf->field == "empty"){
				$a++;
				break;
			}
			if($attIf->field == "healthiness_value"){
				$query .= " AND " . $hcriteria;
			}
			else
			{
				$query .= " AND " . $attIf->field;
			}
			if($attIf->operator == "IsNull"){
				$query .= " IS NULL";
			}
			else{

				if($attIf->operator == "IsEqual"){
					if($attIf->value == "Empty" ||empty($attIf->value)){
						$query .= " = ''";
					}
					else{
						$query .= " LIKE  '%" . $attIf->value . "%'";
					}			
				}
				else if($attIf->operator == "IsNotEqual"){
					$query .=  "<> '" . $attIf->value . "'";
				}
			}
			
			$a++;
		}

		$query .= " AND username =  '$username' AND organization = '$organization' AND ($value = '' OR $value IS NULL);";

		if(count($attributesIf) > 0){
		
			$r = mysqli_query($link, $query);
				//   echo $upquery;
			if ($r) 
			{
				
				$row = mysqli_fetch_assoc($r);

				$result['status'] = 'ok';
				$result['content'] = $row['tot'];
				$result["msg"] .= "Selected ok " . $query; 
			}
			else 
			{
				$result['status'] = 'ko';
				$result["msg"] .= $query . " error".  generateErrorMessage($link); 
				$result["log"] .= "\r\n Error during selection" ; 

			} 
		}	else{
			$result['status'] = 'ok';
			$result['content'] = 0;
		}
	}
	else{
		$result['status'] = 'ok';
		$result['content'] = 0;
	}

	my_log($result);
	mysqli_close($link); 		
}
else if ($action=="get_rules_affecting_data"){
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$attributesIf = json_decode($_REQUEST['attributes']);
	$value = mysqli_real_escape_string($link,$_REQUEST['value']);

	$usernameNotHashed = $username;
	$username = md5($username);


		$hv= 0;
		$hcriteria="";
		while ($hv < count($attributesIf))
		{
			$attHv=$attributesIf[$hv];	
			if($attHv->field== "healthiness_criteria"){
				if($attHv->value == "refresh_rate"){
					$hcriteria = "value_refresh_rate";
				}
				else if($attHv->value=="within_bounds"){
					$hcriteria = "value_bounds";
				}
				else{
					$hcriteria = $attHv->value;
				}
				break;
			}
			$hv++;
		}

		$a=0;
	//	$query = "te.cb = td.contextbroker AND te.device = td.id AND deleted IS null AND username = '$username' AND organization='$organization'";
		$query = "te.cb = td.contextbroker AND te.device = td.id AND deleted IS null AND organization = '$organization' AND username = '$username' AND ($value = '' OR $value IS NULL) ";

		while ($a < count($attributesIf))
		{
			$attIf=$attributesIf[$a];	
			if($attIf->field == "empty"){
				$a++;
				break;
			}
			if($attIf->field == "healthiness_value"){
				$query .= " AND " . $hcriteria;
			}
			else
			{
				$query .= " AND " . $attIf->field;
			}
			//$attThen = $attributesThen[$a];
			if($attIf->operator == "IsNull"){
				$query .= " IS NULL";
			}
			else{
				if($attIf->operator == "IsEqual"){
					if($attIf->value == "Empty" ||empty($attIf->value)){
						$query .= " = ''";
					}
					else{
						$query .= " LIKE  '%" . $attIf->value . "%'";
					}	
				}
				else if($attIf->operator == "IsNotEqual"){
					$query .=  "<> '" . $attIf->value . "'";
				}
			}
			$a++;
		}
		if(count($attributesIf) > 0){			

			$q = "SELECT * FROM temporary_event_values te, temporary_devices td";
			//echo("get affected prova ".$q . $query);
			$r=create_datatable_data($link,$_REQUEST,$q, $query);

			$selectedrows=-1;
			if($_REQUEST["length"] != -1)
			{
					$start= $_REQUEST['start'];
					$offset=$_REQUEST['length'];
					$tobelimited=true;
			}
			else
			{
				$tobelimited=false;
			}
			
			if($r) 
			{
				//$result['status'] = 'ok';
				//$result['content'] = array();
				$value = array();
				$result["log"]= "\r\n action=get_preview \r\n";
					//Sara711 - for logging purpose
				while($row = mysqli_fetch_assoc($r)) 
				{
					$selectedrows++;
					if (!$tobelimited || ($tobelimited && $selectedrows >= $start && $selectedrows < ($start+$offset)))
					{
					$rec= array();
					$rec["contextbroker"]=$row["cb"];
					$rec["name"]=$row["device"];
					$rec["value_name"]=$row["value_name"];
					$rec["data_type"]=$row["data_type"];
					$rec["value_type"]=$row["value_type"];
					$rec["value_unit"]=$row["value_unit"];
					$rec["healthiness_criteria"]=$row["healthiness_criteria"];

					if($rec["healthiness_criteria"]== "refresh_rate"){
						$rec["healthiness_value"] = $row["value_refresh_rate"];
					}
					else if($rec["healthiness_criteria"] == "within_bounds"){
						$rec["healthiness_value"] = $row["value_bounds"];
					}
					else{
						$rec["healthiness_value"] = $row["different_values"];
					}
					array_push($value, $rec);          
					}
				}
				$output= format_result($_REQUEST["draw"], $selectedrows+1, $selectedrows+1, $value, "", "\r\n action=get_preview \r\n", 'ok');	
			}
			else
			{
				$output= format_result($_REQUEST["draw"], 0, 0, null, 'Error: errors in reading data about devices. <br/>' . generateErrorMessage($link), '\n\r Error: errors in reading data about devices.' . generateErrorMessage($link), 'ko');
			}	
			$result = $output;
		}


	my_log($result);
	mysqli_close($link); 		
}
else if($action == 'apply_rules'){
	$username = mysqli_real_escape_string($link,$_REQUEST['username']);
	$organization = mysqli_real_escape_string($link,$_REQUEST['organization']);
	$attributesThen = json_decode($_REQUEST['attributesThen']);
	$attributesIf = json_decode($_REQUEST['attributesIf']);

	$deviceElements = array("device_type", "protocol", "model", "format","contextbroker");
	$valueElements = array("data_type","value_type", "value_unit","contextbroker");


	$usernameNotHashed = $username;
	$username = md5($username);
	

	$modelsdata = array();
	$queryModel  = "SELECT * FROM model";
	$resModel = mysqli_query($link, $queryModel);
	if($resModel){
		while($rowModel = mysqli_fetch_assoc($resModel)){
			array_push($modelsdata, $rowModel);
		}
	}

	$datatypes=array();

	$queryDatatype = "SELECT * FROM data_types";
	$resData = mysqli_query($link, $queryDatatype);
	if($resData){
		while($row = mysqli_fetch_assoc($resData)){
			array_push($datatypes, $row["data_type"]);
		}
	}
	$valuetypes= array();
	$valueunits= array();

	$queryDatatype = "SELECT * FROM value_types";
	$resData = mysqli_query($link, $queryDatatype);
	if($resData){
		while($resData = mysqli_fetch_assoc($resData)){
			array_push($valuetypes, $row["value_type"]);
			array_push($valueunits, $row["value_unit_default"]);

		}
	}

	$contextbroker="";

/*	if ($listAttributes==null) $merge=$listnewAttributes;
	else if ($listnewAttributes==null) $merge=$listAttributes;
	else $merge=array_merge($listAttributes,$listnewAttributes, 'compare_values');
	
*/	$queryDevices="UPDATE temporary_devices";
	$queryValues= "UPDATE temporary_event_values";
	$a=0;
	$setDev = 0;
	$setVal= 0;
	$logFields = "";

	while ($a < count($attributesThen))
	{
		$attThen=$attributesThen[$a];	

		if(in_array($attThen->field,$deviceElements)){
			$logFields .= $attThen->field.", ";

			if($attThen->field == "contextbroker"){
				$contextbroker = $attThen->valueThen;
			}

			if($setDev == 0){
				$queryDevices .= " SET " ;
				if( strtoupper($attThen->valueThen) == "NULL"){
					$queryDevices .=  $attThen->field . " = NULL";				
				}
				else{
					$queryDevices .=  $attThen->field . " = '"  . $attThen->valueThen. "'";
				}
				$setDev = 1;
			}
			else{
				if( strtoupper($attThen->valueThen) == "NULL"){
					$queryDevices .=  $attThen->field . " = NULL";				
				}
				else{
					$queryDevices .= ", ".$attThen->field . " = '"   . $attThen->valueThen. "'";
				}
			}
		}
		else if(in_array($attThen->field,$valueElements)){
			$logFields .= $attThen->field.", ";

			if($attThen->field == "contextbroker"){
				$contextbroker = $attThen->valueThen;
			}

			if($setVal == 0){
				$queryValues .= " SET " ;

				if(strcmp($attThen->field,"contextbroker") == 0){
					$attThen->field = "cb";
				}
				if( strtoupper($attThen->valueThen) == "NULL"){
					$queryValues .=  $attThen->field . " = NULL";				
				}
				else{
					$queryValues .=  $attThen->field . " = '"  . $attThen->valueThen. "'";
				}
				$setVal = 1;
			}
			else{
				if( strtoupper($attThen->valueThen) == "NULL"){
					$queryValues .=  $attThen->field . " = NULL";				
				}
				else{
					$queryValues .= ", ".$attThen->field . " = '"   . $attThen->valueThen. "'";
				}
			}
		}
		$a++;
	}
	$logFields = substr($logFields, 0, strlen($logFields)-2);

	$b=0;
	$whereDev = 0;
	$whereVal = 0;

	while ($b < count($attributesIf))
	{

		$attIf=$attributesIf[$b];	

		if(in_array($attIf->field,$deviceElements)){
			if($whereDev == 0){
				$queryDevices .= " WHERE " ;
				$queryDevices .=  $attIf->field ;
				$whereDev = 1;
			}
			else{
				$queryDevices .= " AND " . $attIf->field;
			}
			//$attThen = $attributesThen[$a];
			if($attIf->operator == "IsNull"){
				$queryDevices .= " IS NULL";
			}
			else{
				if($attIf->operator == "IsEqual"){
					$queryDevices .= "=  '" . $attIf->value . "'";
				}
				else if($attIf->operator == "IsNotEqual"){
					$queryDevices .=  "<> '" . $attIf->value . "'";
				}
			}	
		}
		else if(in_array($attIf, $valueElements)){
			if($whereVal == 0){
				$queryValues .= " WHERE " ;
				$queryValues .=  $attIf->field ;
				$whereVal = 1;
			}
			else{
				$queryValues .= " AND " . $attIf->field;
			}
			//$attThen = $attributesThen[$a];
			if($attIf->operator == "IsNull"){
				$queryValues .= " IS NULL";
			}
			else{
				if($attIf->operator == "IsEqual"){
					$queryValues .= "=  '" . $attIf->value . "'";
				}
				else if($attIf->operator == "IsNotEqual"){
					$queryValues .=  "<> '" . $attIf->value . "'";
				}
			}
		}
		$b++;

	}
	if($setVal == 0){
		$queryValues = "";
	}
	else{
		if($whereVal == 0){
			$queryValues .= " WHERE username =  '$username' AND organization = '$organization';";
		}
		else{
			$queryValues .= " AND username =  '$username' AND organization = '$organization';";
		}
	}


	if($setDev == 0){
		$queryDevices = "";
	}
	else{
		if($whereDev==0){
			$queryDevices .= " WHERE username =  '$username' AND organization = '$organization';";
		}
		else{
			$queryDevices .= " AND username =  '$username' AND organization = '$organization';";
		}
	}
	if($queryValues != ""){
		$exec = mysqli_query($link, $queryValues);
	}
	if($queryDevices != ""){
		$r = mysqli_query($link, $queryDevices);
		
		if ($r) 
		{
			$q1 = "SELECT * FROM temporary_devices"; 

			$a1=0;
			$where=0;
			//acquisition of data for validate device function and color hilight
			while ($a1 < count($attributesIf))
			{
				$attIf=$attributesIf[$a1];	
				if($where == 0){
					$q1 .= " WHERE " ;
					$q1 .=  $attIf->field ;
					$where = 1;
				}
				else{
					$q1 .= " AND " . $attIf->field;
				}
				//$attThen = $attributesThen[$a];
				if($attIf->operator == "IsNull"){
					$q1 .= " IS NULL";
				}
				else{

					if($attIf->field == "contextBroker" & $contextbroker != ""){
						if($attIf->operator == "IsEqual"){
							$q1 .= "=  '" . $contextbroker . "'";
						}
						else if($attIf->operator == "IsNotEqual"){
							$q1 .=  "<> '" . $contextbroker . "'";
						}
					}
					else{
						if($attIf->operator == "IsEqual"){
							$q1 .= "=  '" . $attIf->value . "'";
						}
						else if($attIf->operator == "IsNotEqual"){
							$q1 .=  "<> '" . $attIf->value . "'";
						}
					}
				}
				
				$a1++;
			}
			
			$q1 .= " AND username =  '$username' AND organization = '$organization';";
			$result["msg"] .= $q1;

			$r1 = mysqli_query($link, $q1);

			$resultDevices = array();

			if($r1){

				while($row1 = mysqli_fetch_assoc($r1)) 
				{ 
					if($contextbroker == ""){
						$contextbroker =  $row1["contextBroker"];
					}
					$q2 = 'SELECT * FROM temporary_event_values WHERE device="'.$row1["id"]. '" AND cb = "'.$contextbroker . '"';

					$r2 = mysqli_query($link, $q2);
					if($r2){
						//$devValues ="[";
						$devValues = array();
						$values = array();

						while($row2 = mysqli_fetch_assoc($r2)){
							$hvalue="";
							if($row2["healthiness_criteria"]=="refresh_rate") 
								$hvalue =$row2["value_refresh_rate"];
							if($row2["healthiness_criteria"]=="different_values")
								$hvalue = $row2["different_values"];
							if($row2["healthiness_criteria"]=="within_bounds")
								$hvalue = $row2["value_bounds"];	

							$devValues["value_name"] = $row2["value_name"];
							$devValues["data_type"] = $row2["data_type"];
							$devValues["value_type"] = $row2["value_type"];
							$devValues["editable"] = $row2["editable"];
							$devValues["value_unit"] = $row2["value_unit"];
							$devValues["healthiness_criteria"] = $row2["healthiness_criteria"];
							$devValues["healthiness_value"]= $hvalue;
							array_push($values,$devValues);
						}
					//	$devValues= rtrim($devValues,',');
					//	$devValues .= "]";
					//	$updatedDevice='{"contextbroker": "'.$row1["contextBroker"].'", "name": "'.$row1["id"].'", "devicetype": "'.$row1["devicetype"].'", "model": "'.$row1["model"].'","macaddress": "'.$row1["macaddress"].'", "frequency": "'.$row1["frequency"].'", "kind": "'.$row1["kind"].'", "protocol": "'.$row1["protocol"]. '", "format": "'.$row1["format"].'", "latitude": "' .$row1["latitude"].'", "longitude":"'.$row1["longitude"].'", "visibility":"'.$row1["visibility"].'", "k1":"'.$row1["k1"].'", "k2": "'.$row1["k2"].'", "producer":"'.$row1["producer"].'", "edge_gateway_type": "' .$row1["edge_gateway_type"].'", "edge_gateway_uri":"'.$row1["edge_gateway_uri"].'", "deviceValues": '. $devValues.'}';
					$updatedDevice["contextbroker"] = $row1["contextBroker"];
					$updatedDevice["name"] = $row1["id"];
					$updatedDevice["devicetype"] = $row1["devicetype"];
					$updatedDevice["model"] = $row1["model"];
					$updatedDevice["macaddress"] = $row1["macaddress"];
					$updatedDevice["frequency"] = $row1["frequency"];
					$updatedDevice["kind"] = $row1["kind"];
					$updatedDevice["protocol"] = $row1["protocol"];
					$updatedDevice["format"]= $row1["format"];
					$updatedDevice["latitude"] = $row1["latitude"];
					$updatedDevice["longitude"]= $row1["longitude"];
					$updatedDevice["visibility"]= $row1["visibility"];
					$updatedDevice["k1"]= $row1["k1"];
					$updatedDevice["k2"]= $row1["k2"];
					$updatedDevice["producer"]= $row1["producer"];
					$updatedDevice["edge_gateway_type"]= $row1["edge_gateway_type"];
					$updatedDevice["edge_gateway_uri"]= $row1["edge_gateway_uri"];
					$updatedDevice["deviceValues"] = array();
					$updatedDevice["deviceValues"]=$values;
					array_push($resultDevices, $updatedDevice);
					}
				}

				while(!empty($resultDevices)){
						
					$device = array_pop($resultDevices);
					$verification = verifyDevice($device,$modelsdata, $datatypes,$valuetypes,$valueunits);
					$validity = "valid";
					if($verification["isvalid"]==0){
						$validity= "invalid";
					}
					$sql = 'UPDATE temporary_devices SET validity_msg =\''. trim($verification["message"]).'\', status = "'.$validity. '" WHERE contextbroker = "'. $device["contextbroker"].'" AND id ="'. $device["name"].'";';

					$r = mysqli_query($link, $sql);
					if($r){
						$result['status'] = 'ok';
						$result["msg"] .= "temporary update done";
						$result["content"] .= $sql;
					}
					else{
						$result['status'] = 'ko';
						$result["msg"] .= "Error during the update 4".  generateErrorMessage($link); 
						$result["log"] .= "\r\n Error during update" ;
					}
				
				}
			}
			else 
			{
				$result['status'] = 'ko';
				$result["msg"] .= "Error during the update 5".  generateErrorMessage($link); 
				$result["log"] .= "\r\n Error during update" ; 
			} 
		
		}
		else{
			$result['status']='ko';
			$result['msg'].="Error during the update 6".generateErrorMessage($link);

		}
	}
	my_log($result);
	mysqli_close($link); 
}
else if($action == 'get_param_values')
{	
	// $result = array();
	$result['status'] = 'ok'; 
	$result['value_type'] = generatelabels($link);
	$result['data_type'] = generatedatatypes($link);
	$result['value_unit'] = generateunits($link);
	//$result['log'] .= '\n\naction:get_param_values';
	my_log($result);
	mysqli_close($link); 

}

//----Sara end---
else 
{
	$result['status'] = 'ko';
	$result['msg'] = 'invalid action ' . $action;
	$result['log'] = 'invalid action ' . $action;
	my_log($result);
}
