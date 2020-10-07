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


if ($action=="getCBServiceTrees") {
        if (empty($accessToken))
        {
            $result["status"]="ko";
            $result['msg'] = "Access Token not present";
            $result["error_msg"] .= "Problem getting all the device information (Access Token not present). ";
            $result["log"]= "action=getCBServiceTrees - error AccessToken not present\r\n";
            my_log($result);
        }
        else
        {

            $username = mysqli_real_escape_string($link, $_REQUEST['username']);
            $organization = mysqli_real_escape_string($link, $_REQUEST['organization']);
            $loggedrole= mysqli_real_escape_string($link, $_REQUEST['loggedrole']);

            $res=array();

            $q = "SELECT DISTINCT d.contextBroker, c.ip, c.port, c.accesslink, c.accessport, c.path, c.login, c.password, d.service, d.servicePath
                  FROM devices d JOIN contextbroker c on d.contextBroker = c.name
                  WHERE d.protocol LIKE 'ngsi w/MultiService'
                    AND c.kind LIKE 'external'
                  UNION
                  SELECT DISTINCT dd.contextBroker, c.ip, c.port, c.accesslink, c.accessport, c.path, c.login, c.password, dd.service, dd.servicePath
                  FROM deleted_devices dd JOIN contextbroker c on dd.contextBroker = c.name
                  WHERE dd.protocol LIKE 'ngsi w/MultiService'
                    AND c.kind LIKE 'external'
                  UNION
                  SELECT DISTINCT m.contextBroker, c.ip, c.port, c.accesslink, c.accessport, c.path, c.login, c.password, m.service, m.servicePath
                  FROM model m JOIN contextbroker c on m.contextBroker = c.name
                  WHERE m.protocol LIKE 'ngsi w/MultiService'
                    AND c.kind LIKE 'external'
                  UNION
                  SELECT DISTINCT c.name as contextBroker, c.ip, c.port, c.accesslink, c.accessport, c.path, c.login, c.password, s.name as service, '/' as servicePath
                  FROM contextbroker c LEFT JOIN services s ON c.name = s.broker_name
                  WHERE c.protocol LIKE 'ngsi w/MultiService'
                    AND c.kind LIKE 'external'
                  UNION
                    SELECT DISTINCT c.name as contextBroker, c.ip, c.port, c.accesslink, c.accessport, c.path, c.login, c.password, '' as service, '/' as servicePath
                    FROM contextbroker c
                    WHERE c.protocol LIKE 'ngsi w/MultiService'
                      AND c.kind LIKE 'external'
                  ORDER BY contextBroker, service, servicePath";
            $r = mysqli_query($link, $q);

            if($r)
            {
                    while($row = mysqli_fetch_assoc($r))
                    {
                        array_push($res, $row);

                    }
                    $result["status"]="ok";
                    $result["content"]=$res;
                    $result["log"]= "action=getCBServiceTrees \r\n";
            }
            else
            {
                    $result["status"]="ko";
                    $result['msg'] = mysqli_error($link);
                    $result["log"]= "action=getCBServiceTrees -" . " error " .  mysqli_error($link)  . "\r\n";
            }

            my_log($result);
        }
    mysqli_close($link);
}
else if ($action == "get_all_ext_devices_in_iot_dir")
{
	if (empty($accessToken))
        {
            $result["status"]="ko";
            $result['msg'] = "Access Token not present";
            $result["error_msg"] .= "Problem getting all the device information (Access Token not present). ";
            $result["log"]= "action=get_all_ext_devices_in_iot_dir - error AccessToken not present\r\n";
            my_log($result);
        }
        else
        {

            $username = mysqli_real_escape_string($link, $_REQUEST['username']);
            $organization = mysqli_real_escape_string($link, $_REQUEST['organization']);
            $loggedrole= mysqli_real_escape_string($link, $_REQUEST['loggedrole']);

            $res=array();

            $q = "SELECT d.id, d.contextBroker, d.devicetype as type, d.service, d.servicePath FROM devices d
            JOIN contextbroker c on d.contextBroker = c.name WHERE c.protocol LIKE 'ngsi w/MultiService' AND c.kind LIKE 'external'";

            $r = mysqli_query($link, $q);

            if($r)
            {
                    while($row = mysqli_fetch_assoc($r))
                    {
                        array_push($res, $row);

                    }
                    $result["status"]="ok";
                    $result["content"]=$res;
                    $result["log"]= "action=get_all_ext_devices_in_iot_dir \r\n";
            }
            else
            {
                    $result["status"]="ko";
                    $result['msg'] = mysqli_error($link);
                    $result["log"]= "action=get_all_ext_devices_in_iot_dir -" . " error " .  mysqli_error($link)  . "\r\n";
            }

            my_log($result);
        }
	mysqli_close($link);
}
else if ($action=="getCBServiceTree") {

         $contextbroker = mysqli_real_escape_string($link, $_REQUEST['contextbroker']);

         $res=array();

         $q = "SELECT DISTINCT d.service, d.servicePath
               FROM devices d JOIN contextbroker c on d.contextBroker = c.name
               WHERE c.name LIKE '$contextbroker'
               UNION
               SELECT DISTINCT dd.service, dd.servicePath
               FROM deleted_devices dd JOIN contextbroker c on dd.contextBroker = c.name
               WHERE c.name LIKE '$contextbroker'
               UNION
               SELECT DISTINCT m.service, m.servicePath
               FROM model m JOIN contextbroker c on m.contextBroker = c.name
               WHERE c.name LIKE '$contextbroker'
               UNION
               SELECT DISTINCT s.name as service, '/' as servicePath
               FROM contextbroker c LEFT JOIN services s ON c.name = s.broker_name
               WHERE c.name LIKE '$contextbroker'
               UNION
                  SELECT DISTINCT '' as service, '/' as servicePath
                  FROM contextbroker c
                  WHERE c.name LIKE '$contextbroker'
               ORDER BY service, servicePath";
         $r = mysqli_query($link, $q);

         if($r)
         {
                 while($row = mysqli_fetch_assoc($r))
                 {
                     array_push($res, $row);

                 }
                 $result["status"]="ok";
                 $result["content"]=$res;
                 $result["log"]= "action=getCBServiceTree \r\n";
         }
         else
         {
                 $result["status"]="ko";
                 $result['msg'] = mysqli_error($link);
                 $result["log"]= "action=getCBServiceTree -" . " error " .  mysqli_error($link)  . "\r\n";
         }

         my_log($result);

         mysqli_close($link);
     }


else
{
	$result['status'] = 'ko';
	$result['msg'] = 'invalid action ' . $action;
	$result['log'] = 'invalid action ' . $action;
	my_log($result);
}
