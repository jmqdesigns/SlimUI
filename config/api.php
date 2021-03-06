<?php
 
session_start();
if(isset($_GET['session_stat'])){
	if(!isset($_SESSION['uqid'])){		
		exit;
	}
}

/*************************************************************************************************************/
require("config.php");
header('Content-type: application/json; charset=utf-8');

if(@$_SESSION[$UQID.'uid']){
  /*************************************************************************************************************/
  if(isset($_GET['cookiedata'])){
  	$data = array();	
	for($i = 0; $i < count($cookie_data); $i++){
		$data[$i] = $cookie_data[$i];
	}
		
	$out = array_values($data);
	echo json_encode($out);
  }
  /*************************************************************************************************************/
  if(isset($_GET['userinfo'])){
	$data['userinfo'] = array();
	$data['userinfo']['user_id'] 		  = @$_SESSION[$UQID.'uid'];	
	$data['userinfo']['user_name'] 		  = @$_SESSION[$UQID.'username'];	
	$data['userinfo']['user_displayname'] = @$_SESSION[$UQID.'user_displayname'];
	$data['userinfo']['user_department']  = @$_SESSION[$UQID.'user_department'];
	$data['userinfo']['user_email']		  = @$_SESSION[$UQID.'user_email'];
	$data['userinfo']['user_title']		  = @$_SESSION[$UQID.'user_title'];
	if($_SESSION[$UQID.'DEV_PRESENT']){ $data['userinfo']['dev_user'] = true; }else{ $data['userinfo']['dev_user'] = false; }	
	$out = array_values($data);
	echo json_encode($out);
  }
  /*************************************************************************************************************/    
  if(isset($_GET['dmusers']) AND LDAPAUTH=='true'){    
	ldap_set_option(@$ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option(@$ds, LDAP_OPT_REFERRALS, 0);
    
    $ldap_server = AD_SERVER_ADDRESS;
    $auth_user = $_SESSION[$UQID.'username']."@".LDAP_DN;
    $auth_pass = $_SESSION[$UQID.'ldappass'];
    $dc = explode('.', LDAP_DN); $base_dn = "dc=".$dc[0].",dc=".$dc[1];
    $filter = "(&(givenName=".@$_GET['q']."*)(objectClass=user)(objectCategory=person)(cn=*))";
    
    if (!($connect=ldap_connect($ldap_server))) {
         die("Error al conectar al servidor ldap");
    }
    
    if (!($bind=ldap_bind($connect, $auth_user, $auth_pass))) {
         die("Error al autenticar en ldap");
    }
    
    if (!($search=ldap_search($connect, $base_dn, $filter))) {
         die("Error al buscar en servidor ldap");
    }
    
    $number_returned = ldap_count_entries($connect,$search);
    ldap_sort($connect, $search, 'displayname');
    
    $info = ldap_get_entries($connect, $search);
    
    $array = array();
    for ($i=0; $i<$info["count"]; $i++) {
       if($info[$i]["displayname"][0]){   
         array_push($array, array('value' => @$info[$i]["displayname"][0], 'mail' => @$info[$i]["mail"][0], 'uid' => @$info[$i]["samaccountname"][0], 'department' => @$info[$i]["department"][0]));		     
       }      
    }
    
    $out = array_values($array);
    echo json_encode($out);
  }
  /*************************************************************************************************************/  
  if(isset($_POST['usertasks'])){
  	$con=odbc_connect('planner','sa','22197926');
	$q = "SELECT row_number() over (ORDER BY (SELECT 0)) as item, * FROM ( SELECT tickets.id, fecha, nombre, departamento, asunto, descripcion, correo, prioridad, estado, uid, project_id, evento, allday, (select tickets_meta_data.value from tickets_meta_data join meta_names on tickets_meta_data.meta_id = meta_names.id where tickets_meta_data.ticket_id = tickets.id and meta_id = 8) as fecha_inicio, (select tickets_meta_data.value from tickets_meta_data join meta_names on tickets_meta_data.meta_id = meta_names.id where tickets_meta_data.ticket_id = tickets.id and meta_id = 9) as fecha_vence FROM TICKETS left join project_meta_data on tickets.id = project_meta_data.ticket_id ) as tasks WHERE ( uid = '".$_SESSION[$UQID.'uid']."' OR correo LIKE '%".$_SESSION[$UQID.'user_email']."%')";
  	$e = odbc_exec($con, $q);
	$array = array();    	
	while($task = odbc_fetch_array($e)){		
		if($task['uid'] != $_SESSION[$UQID.'uid'] && !$task['evento']){ $eventColor = "#888888"; }else{ $eventColor = "#3A87AD"; }
		if($task['evento']){ $eventColor = "#4F9E48"; }		
		if($task['allday'] == 0){ 
			$allDay = false; 
			$endDate = date("Y-m-d H:i:s", strtotime($task['fecha_vence']));			
		}else{ 
			$allDay = true;
			$endDate = date("Y-m-d H:i:s", strtotime("+1 day", strtotime($task['fecha_vence']))); 
		}		
		array_push($array, 
			array(
			   'item' 	   	  	  => $task['item'],
			   'id' 	   	  	  => $task['id'],
			   'taskdate' 	   	  => $task['fecha'],
			   'taskname' 	   	  => $task['nombre'],
			   'taskdepartment'   => $task['departamento'],						   						   
			   'title'			  => $task['asunto'],
			   'description'  	  => $task['descripcion'],
			   'taskinvites' 	  => $task['correo'],
			   'taskpriority' 	  => $task['prioridad'],
			   'taskstat' 		  => $task['estado'],
			   'taskowner' 		  => $task['uid'],
			   'projectid' 		  => $task['project_id'],
			   'start' 		  	  => date("Y-m-d H:i:s", strtotime($task['fecha_inicio'])),
			   'end' 		  	  => $endDate,
			   'rawstart'		  => $task['fecha_inicio'],
			   'rawend'		  	  => $task['fecha_vence'],
			   'startdate'		  => date("Y-m-d", strtotime($task['fecha_inicio'])),
			   'enddate'		  => date("Y-m-d", strtotime($task['fecha_vence'])),
			   'starttime'		  => date("H:i a", strtotime($task['fecha_inicio'])),
			   'endtime'		  => date("H:i a", strtotime($task['fecha_vence'])),
			   'isevent'		  => $task['evento'],
			   'allDay'		  	  => $allDay,
			   'color'			  => $eventColor,			   	   		     
			)
		);
	}
	
	$out = array_values($array);
	echo json_encode($out);
  }
  /*************************************************************************************************************/
  if(isset($_POST['updateusertask'])){
  	$con=odbc_connect('planner','sa','22197926');
	switch($_POST['col']){
		case "taskname":
			 $q = "UPDATE tasks SET nombre = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "taskdepartment":
			 $q = "UPDATE tasks SET departamento = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "title":
			 $q = "UPDATE tasks SET asunto = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "taskdepartment":
			 $q = "UPDATE tasks SET departamento = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "description":
			 $q = "UPDATE tasks SET descripcion = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "taskinvites":
			 $q = "UPDATE tasks SET correo = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "taskpriority":
			 $q = "UPDATE tasks SET prioridad = ".$_POST['val']." WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "taskstat":
			 $q = "UPDATE tasks SET estado = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "start":
			 $q = "UPDATE tickets_meta_data SET value = '".$_POST['val']."' WHERE ticket_id = ".$_POST['id']." AND meta_id = 8";
			 break;
		case "end":
			 $q = "UPDATE tickets_meta_data SET value = '".$_POST['val']."' WHERE ticket_id = ".$_POST['id']." AND meta_id = 9";
			 break;
		case "isevent":
			 $q = "UPDATE tasks SET evento = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
		case "allday":
			 $q = "UPDATE tasks SET allday = '".$_POST['val']."' WHERE id = ".$_POST['id']." AND uid = '".$_SESSION[$UQID.'uid']."'";
			 break;
	}
  	if( !odbc_exec($con, $q) ){ echo "Error: ".mysql_error(); }
  }
}
?>