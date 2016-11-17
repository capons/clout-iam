<?php
/*
 * Include system wide configurations
 */
include_once("config_env.php");
include_once("config.php");
include_once("queries.php");
include_once("query_functions.php");
include_once("security_functions.php");

# if testing API
$_POST = !empty($_POST)? $_POST: array();
if(!empty($_GET) && !empty($_GET['__check'])) $_POST = array_merge($_POST, $_GET);

# If an action is posted for the IAM
if(!empty($_POST['__action']))
{
	# Test IAM DB connection through the API
	if($_POST['__action'] == 'test_db')
	{
		$mysqli = new mysqli(HOSTNAME, USERNAME, PASSWORD, DATABASE, DBPORT);
		echo json_encode(array('IS'=>($mysqli->ping()? 'CONNECTED': 'NO CONNECTION') ));
	}
	
	
	# Run a generic query on the database
	else if($_POST['__action'] == 'run')
	{
		log_message('debug', 'main/run:: [1] post');
		log_message('debug', 'main/run:: [2] post parameters '.json_encode($_POST));
		$result = run($_POST['query'], $_POST['variables'], (!empty($_POST['strict']) && $_POST['strict'] == 'true')); 
		log_message('debug', 'main/run:: [3] result '.json_encode($result));
		# determine what to return
		if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($result); 
		else echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	
	# Run a generic query on the database
	else if($_POST['__action'] == 'add_data')
	{
		log_message('debug', 'main/add_data:: [1] post');
		log_message('debug', 'main/add_data:: [2] post parameters '.json_encode($_POST));
		$id = add_data($_POST['query'], $_POST['variables']); 
		log_message('debug', 'main/add_data:: [3] id '.json_encode($id));
		# determine what to return
		if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($id); 
		else echo json_encode(array('id'=>$id));
	}
	
	
	# Run a generic query on the database
	else if($_POST['__action'] == 'get_list')
	{
		log_message('debug', 'main/get_list:: [1] post');
		log_message('debug', 'main/get_list:: [2] post parameters '.json_encode($_POST));
		$list = get_list($_POST['query'], $_POST['variables']); 
		log_message('debug', 'main/get_list:: [3] result list '.json_encode($list));
		echo json_encode($list);
	}
	
	
	# Run a generic query on the database
	else if($_POST['__action'] == 'get_row_as_array')
	{
		log_message('debug', 'main/get_row_as_array:: [1] post');
		log_message('debug', 'main/get_row_as_array:: [2] post parameters '.json_encode($_POST));
		$row = get_row_as_array($_POST['query'], $_POST['variables']); 
		log_message('debug', 'main/get_row_as_array:: [3] result list '.json_encode($row));
		echo json_encode($row);
	}
	
	
	# Run a generic query on the database
	else if($_POST['__action'] == 'get_single_column_as_array')
	{
		log_message('debug', 'main/get_single_column_as_array:: [1] post');
		log_message('debug', 'main/get_single_column_as_array:: [2] post parameters '.json_encode($_POST));
		$list = get_single_column_as_array($_POST['query'], $_POST['column'], $_POST['variables']); 
		log_message('debug', 'main/get_row_as_array:: [3] result list '.json_encode($list));
		echo json_encode($list);
	}
	
	
	# Login User
	else if($_POST['__action'] == 'login')
	{
		log_message('debug', 'main/login:: [1] post');
		log_message('debug', 'main/login:: [2] post parameters '.json_encode($_POST));
		$row = get_row_as_array('check_user_by_username', array('user_name'=>$_POST['username'], 'password'=>sha1($_POST['password']) )); 
		log_message('debug', 'main/get_row_as_array:: [3] result list '.json_encode($row));
		echo json_encode($row);
	}
		
	# Save access details
	else if($_POST['__action'] == 'save_access_details')
	{
		log_message('debug', 'main/save_access_details:: [1] post');
		log_message('debug', 'main/save_access_details:: [2] post parameters '.json_encode($_POST));
		$result = run('save_access_details', array('user_id'=>$_POST['userId'], 'user_name'=>$_POST['emailAddress'], 'password'=>sha1($_POST['password']), 'permission_group_id'=>get_user_default_group($_POST['userId']) )); 
		log_message('debug', 'main/get_row_as_array:: [3] result list '.json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') )));
		echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	# Update user password
	else if($_POST['__action'] == 'update_user_password')
	{
		log_message('debug', 'main/update_user_password:: [1] post');
		log_message('debug', 'main/update_user_password:: [2] post parameters '.json_encode($_POST));
		$result = run('update_user_password', array('user_id'=>$_POST['userId'], 'password'=>sha1($_POST['password']) )); 
		log_message('debug', 'main/update_user_password:: [3] result list '.json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') )));
		echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	# Get users in the given permission group
	else if($_POST['__action'] == 'get_users_in_group_type')
	{
		log_message('debug', 'main/get_users_in_group_type:: [1] post');
		log_message('debug', 'main/get_users_in_group_type:: [2] post parameters '.json_encode($_POST));
		$list = get_single_column_as_array('get_users_in_group_type', 'user_id', array('group_type'=>$_POST['group_type'], 'limit_text'=>" LIMIT ".$_POST['offset'].",".$_POST['limit'].";")); 
		log_message('debug', 'main/update_user_password:: [3] result list '.json_encode($list));
		echo json_encode($list);
	}
	#Get users in the promotion reservation list
	else if($_POST['__action'] = 'get_users_in_p_reservation')
    {
        log_message('debug', 'main/get_users_in_group_type:: [1] post');
        log_message('debug', 'main/get_users_in_group_type:: [2] post parameters '.json_encode($_POST));
        $list = get_single_column_as_array('get_select_users', 'user_id', array('user_id'=>$_POST['user_id'], 'limit_text'=>" LIMIT ".$_POST['offset'].",".$_POST['limit'].";"));
        log_message('debug', 'main/update_user_password:: [3] result list '.json_encode($list));
        echo json_encode($list);
    }
	
	# Update the user-group mapping
	else if($_POST['__action'] == 'update_user_group_mapping')
	{
		log_message('debug', 'main/update_user_group_mapping:: [1] post');
		log_message('debug', 'main/update_user_group_mapping:: [2] post parameters '.json_encode($_POST));
		$result = run('update_user_group_mapping', array('user_id'=>$_POST['userId'], 'user_id_list'=>implode("','", explode(',',$_POST['userIdList'])), 'new_group_id'=>$_POST['newGroupId'] )); 
		log_message('debug', 'main/update_user_group_mapping:: [3] result list '.json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') )));
		echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	
	# cache the mysql queries for this server
	else if($_POST['__action'] == 'load_queries_into_cache')
	{
		log_message('debug', 'main/load_queries_into_cache:: [1] post');
		log_message('debug', 'main/load_queries_into_cache:: [2] post parameters '.json_encode($_POST));
		if(ENABLE_QUERY_CACHE) load_queries_into_cache();
		log_message('debug', 'main/load_queries_into_cache:: [3] ENABLE_QUERY_CACHE '.ENABLE_QUERY_CACHE);
		echo json_encode(array('result'=>'DONE'));
	}
	
	
	
	# check whether rules apply to a given user
	else if($_POST['__action'] == 'rule_check')
	{
		log_message('debug', 'main/rule_check:: [1] post');
		log_message('debug', 'main/rule_check:: [2] post parameters '.json_encode($_POST));
		$result = rule_check($_POST['code'], $_POST['parameters']); 
		log_message('debug', 'main/rule_check:: [3] result '.json_encode($result));
		# determine what to return
		if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($result); 
		else echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	
	
	# apply a rule to a given user
	else if($_POST['__action'] == 'apply_rule')
	{
		log_message('debug', 'main/apply_rule:: [1] post');
		log_message('debug', 'main/apply_rule:: [2] post parameters '.json_encode($_POST));
		$result = apply_rule($_POST['code'], $_POST['parameters']); 
		log_message('debug', 'main/apply_rule:: [3] result '.json_encode($result));
		# determine what to return
		if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($result); 
		else echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
	}
	
	
	
	
	
}




# Get the default permission group for a given user
function get_user_default_group($userId)
{	
	log_message('debug', 'main/get_user_default_group');
	#STUB: Generate permission group from access rules
	return '2';
}

?>