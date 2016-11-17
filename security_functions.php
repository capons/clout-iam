<?php
include_once("config_env.php");
/*
 * These are the functions to review and process security requirements for the system.
 */

# check whether a rule applies to a user
function rule_check($code, $parameters)
{
	log_message('debug', 'security_functions/rule_check:: [1]');
	log_message('debug', 'security_functions/rule_check:: [2] code '.$code.', parameters '.json_encode($parameters));
	$rule = get_row_as_array('get_rule_by_code', array('code'=>$code));
	log_message('debug', 'security_functions/rule_check:: [3] '.json_encode($rule));
	
	# custom user rules
	if(!empty($rule) && $rule['user_type'] != 'system' && !empty($parameters['user_id'])) {
		log_message('debug', 'security_functions/rule_check:: [4] custom user rule applies');
		$check = get_row_as_array('is_rule_applied_to_user', array('user_id'=>$parameters['user_id'], 'rule_id'=>$rule['rule_id']));
		log_message('debug', 'security_functions/rule_check:: [5] custom user rule check '.json_encode($check));
		if(!empty($check['is_applied']) && $check['is_applied'] == 'Y') return TRUE;
		else return FALSE;
	}
	
	# system rules
	else if(!empty($rule) && $rule['user_type'] == 'system') return TRUE;
	
	# not a valid rule
	else return FALSE;
}





# apply a rule based on the passed parameters
function apply_rule($code, $parameters=array())
{
	log_message('debug', 'security_functions/apply_rule:: [1]');
	log_message('debug', 'security_functions/apply_rule:: [2] code '.$code.', parameters '.json_encode($parameters));
	# TODO: put in this array rules that do not need a user id to be applied
	$nonUserRules = array('does_not_need_user_id');
	# reject rule without a user id
	if(empty($parameters['user_id']) && !in_array($code, $nonUserRules)) return FALSE;
	# pick the rule details
	$rule = get_row_as_array('get_rule_by_code', array('code'=>$code));
	log_message('debug', 'security_functions/apply_rule:: [3] rule details '.json_encode($rule));
	
	# switch to apply based on the rule
	switch($code){
		case 'new_inclusion_list_user':
			return apply_new_inclusion_list_user($rule, $parameters);
		break;
		
		case 'new_user_referred_by_invited_user':
			return apply_new_user_referred_by_user_type($rule, $parameters, 'invited_shopper');
		break;
		
		case 'new_user_referred_by_random_user':
			return apply_new_user_referred_by_user_type($rule, $parameters, 'random_shopper');
		break;
		
		case 'new_random_user':
			return apply_new_random_user($rule, $parameters);
		break;
		
		case 'permission_update_to_invited_user':
			return apply_permission_update_to_new_group_type($rule, $parameters, 'invited_shopper');
		break;
		
		case 'permission_update_to_random_user':
			return apply_permission_update_to_new_group_type($rule, $parameters, 'random_shopper');
		break;
		
		case 'invite_daily_limit_10':
			return apply_invite_daily_limit($rule, $parameters, 10);
		break;
		
		case 'invite_daily_limit_30':
			return apply_invite_daily_limit($rule, $parameters, 30);
		break;
		
		case 'invite_daily_limit_unlimited':
			return apply_invite_daily_limit($rule, $parameters, '');
		break;
		
		case 'stop_new_invite_sending':
			return apply_stop_invite_sending($rule, $parameters, 'pending');
		break;
		
		case 'stop_all_invite_sending':
			return apply_stop_invite_sending($rule, $parameters, '');
		break;
		
		
		
		default:
			return FALSE;
		break;
	}
}















# apply stop invite sending
function apply_stop_invite_sending($rule, $parameters, $status)
{
	log_message('debug', 'security_functions/apply_stop_invite_sending:: [1]');
	log_message('debug', 'security_functions/apply_stop_invite_sending:: [2] rule='.json_encode($rule).' parameters='.json_encode($parameters).' status='.$status);
	
	$inviteStatus = extract_rule_setting_value($rule['details'], 'invite_status', 'value');
	log_message('debug', 'security_functions/apply_stop_invite_sending:: [3] inviteStatus='.$inviteStatus);
	
	if($inviteStatus == $status || ($inviteStatus == 'any' && $status == '')) {
		$statusCondition = "";
		if($inviteStatus == 'pending') $statusCondition = "pending";
		else if($inviteStatus == 'any') $statusCondition = "pending','paused";
		
		$result = server_curl(MESSAGE_SERVER_URL, array('__action'=>'run', 'return'=>'plain', 
			'query'=>'update_invite_status_with_limit', 
			'variables'=>array(
				'user_id'=>$parameters['user_id'], 
				'limit_text'=>'',
				'status_condition'=>" AND message_status IN ('".$statusCondition."') ",
				'new_status'=>'paused'
			)
		)); 
	}
	log_message('debug', 'security_functions/apply_stop_invite_sending:: [4] result='.$result);
	
	return !empty($result) && $result;
}





# apply invite daily limit
function apply_invite_daily_limit($rule, $parameters, $limit)
{
	log_message('debug', 'security_functions/apply_invite_daily_limit:: [1]');
	log_message('debug', 'security_functions/apply_invite_daily_limit:: [2] rule='.json_encode($rule).' parameters='.json_encode($parameters).' limit='.$limit);
	
	$dailyLimit = extract_rule_setting_value($rule['details'], 'daily_limit', 'value');
	log_message('debug', 'security_functions/apply_invite_daily_limit:: [3] dailyLimit='.$dailyLimit);
	
	if($dailyLimit == $limit || ($dailyLimit == 'unlimited' && $limit == '')) 
	{
		$result = server_curl(MESSAGE_SERVER_URL, array('__action'=>'run', 'return'=>'plain', 
			'query'=>'update_invite_status_with_limit', 
			'variables'=>array(
				'user_id'=>$parameters['user_id'], 
				# select any more user invites not the first [$limit] and mark them as paused
				'limit_text'=>(!empty($limit)? " LIMIT ".$limit.",1000000000": ''), 
				'status_condition'=>" AND message_status = 'pending' ",
				'new_status'=>'paused' 
			)
		)); 
	}
	log_message('debug', 'security_functions/apply_invite_daily_limit:: [4] result='.$result);
	
	return !empty($result) && $result;
}









# update permission group of referred users based on group change for referrer user
function apply_permission_update_to_new_group_type($rule, $parameters, $groupType)
{
	log_message('debug', 'security_functions/apply_permission_update_to_new_group_type:: [1]');
	log_message('debug', 'security_functions/apply_permission_update_to_new_group_type:: [2] rule='.json_encode($rule).' parameters='.json_encode($parameters).' groupType='.$groupType);
	
	$permissionGroup = extract_rule_setting_value($rule['details'], 'permission_group', 'value');
	$permissionType = get_row_as_array('get_user_permission_types', array('user_ids'=>$parameters['user_id'], 'order_condition'=>""));
	log_message('debug', 'security_functions/apply_permission_update_to_new_group_type:: [3] permissionGroupe='.json_encode($permissionGroup));
	log_message('debug', 'security_functions/apply_permission_update_to_new_group_type:: [4] permissionType='.json_encode($permissionType));
	
	# proceed if the new user permission group type matches the rule instruction group type
	if($groupType == $permissionType['type']) {
		$referrals = server_curl(BACKEND_SERVER_URL, array('__action'=>'get_single_column_as_array', 'column'=>'user_id', 'query'=>'get_user_network_referral_ids', 'variables'=>array('user_id'=>$parameters['user_id']) ));
		
		$result = TRUE;
		# apply this group type on the referred users
		foreach($referrals AS $referral){
			if($result) $result = update_user_group_by_name($referral, $permissionGroup);
		}
	}
	log_message('debug', 'security_functions/apply_permission_update_to_new_group_type:: [5] result='.$result);
	
	return !empty($result) && $result;
}





# apply new random user group permission
function apply_new_random_user($rule, $parameters)
{
	log_message('debug', 'security_functions/apply_new_random_user:: [1]');
	log_message('debug', 'security_functions/apply_new_random_user:: [2] rule='.json_encode($rule).' parameters='.json_encode($parameters));
	
	$permissionGroup = extract_rule_setting_value($rule['details'], 'permission_group', 'value');
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [3] permissionGroupe='.json_encode($permissionGroup));
	
	if(!empty($permissionGroup)) $group = get_row_as_array('get_group_by_name', array('group_name'=>$permissionGroup));
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [4] group='.json_encode($group));
	
	if(!empty($group['group_type']) && $group['group_type'] == 'random_shopper'){
		return update_user_group_by_name($parameters['user_id'], $permissionGroup);
	} 
	else return FALSE;
}





# apply new user referred by specified user type
function apply_new_user_referred_by_user_type($rule, $parameters, $groupType)
{
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [1]');
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [2] rule='.json_encode($rule).' parameters='.json_encode($parameters).' groupType='.$groupType);
	
	$user = get_row_as_array('get_user_email', array('user_id'=>$parameters['user_id']));
	$permissionGroup = extract_rule_setting_value($rule['details'], 'permission_group', 'value');
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [3] user='.json_encode($user));
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [4] permissionGroupe='.json_encode($permissionGroup));
	
	if(!empty($user['email_address']) && !empty($permissionGroup)){
		$userReferrers = server_curl(MESSAGE_SERVER_URL, array('__action'=>'get_single_column_as_array', 'column'=>'user_id', 'query'=>'get_list_of_inviters_by_email', 'variables'=>array('email_address'=>$user['email_address'], 'inviter_condition'=>"" ) )); 
		
		if(!empty($userReferrers)) $referrers = get_single_column_as_array('filter_users_by_group_name', 'user_id', array('group_name'=>htmlentities($permissionGroup, ENT_QUOTES), 'id_list'=>implode("','",$userReferrers) ));
		
		if(!empty($referrers)) $permissionTypes = get_list('get_user_permission_types', array('user_ids'=>implode("','", $referrers), 'order_condition'=>" ORDER BY  field(A.user_id, ".implode(",", $referrers).") "));
		
		if(!empty($permissionTypes)) {
			foreach($permissionTypes AS $permissionRow){
				if($permissionRow['type'] == $groupType) {
					$result = update_user_group_by_name($parameters['user_id'], $permissionGroup);
					break;
				}
			}
		}
	}
	log_message('debug', 'security_functions/apply_new_user_referred_by_user_type:: [5] result='.$result);
	
	return !empty($result) && $result;
}








# apply inclusion list referrer permission and to the inclusion list user's network
function apply_new_inclusion_list_user($rule, $parameters)
{
	log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [1]');
	log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [2] rule '.$rule.', parameters '.json_encode($parameters));
	$inclusion = get_row_as_array('get_rule_by_code', array('code'=>'the_inclusion_list'));
	log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [2] inclusion '.json_encode($inclusion));
	
	
	# if inclusion list rule is active
	if(!empty($inclusion['details'])){
		$list = explode(',',str_replace(' ','',extract_rule_setting_value($inclusion['details'], 'inclusion_list', 'value')));
		log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [2] list '.json_encode($list));
		$user = get_row_as_array('get_user_email', array('user_id'=>$parameters['user_id']));
		
		# get users who invited this user
		if(!empty($list)){ 
			$referrers = server_curl(MESSAGE_SERVER_URL, array('__action'=>'get_single_column_as_array', 'column'=>'user_id', 'query'=>'get_list_of_inviters_by_email', 'variables'=>array('email_address'=>$user['email_address'], 'inviter_condition'=>" AND (U.email_address LIKE '".str_replace('*','%',implode("' OR U.email_address LIKE '",$list))."')" ) ));
			
			$referrer = server_curl(BACKEND_SERVER_URL, array('__action'=>'get_single_column_as_array', 'column'=>'referrer_id', 'query'=>'get_network_referrer', 'variables'=>array('user_id'=>$parameters['user_id']) ));
			if(!empty($referrer)) array_push($referrers, current($referrer));
			log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [3] referrer '.json_encode($referrer));
		}
		
		
		
		
		if(!empty($referrers)){
			$permissionGroup = extract_rule_setting_value($rule['details'], 'permission_group', 'value');
			log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [4] permissionGroup '.json_encode($permissionGroup));
			# determine which one to assign the referral if there are more than one
			if(count($referrers) > 1){
				$permissionTypes = get_list('get_user_permission_types', array('user_ids'=>implode("','", $referrers), 'order_condition'=>" ORDER BY  field(A.user_id, ".implode(",", $referrers).") "));
				log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [5] permissionTypes '.json_encode($permissionTypes));
				
				$typeOrder = array('clout_owner', 'clout_admin_user', 'store_owner_owner', 'store_owner_admin_user', 'invited_shopper', 'random_shopper');
				
				$assignTo = '';
				foreach($typeOrder AS $i=>$type){
					foreach($permissionTypes AS $permissionRow){
						if($permissionRow['type'] == $type) {
							$assignTo = $permissionRow['user_id'];
							break 2;
						}
					}
				}
						
				if(!empty($assignTo)) $result = server_curl(BACKEND_SERVER_URL, array('__action'=>'run', 'return'=>'plain', 'query'=>'add_user_referral', 'variables'=>array('user_id'=>$parameters['user_id'], 'referred_by'=>$assignTo, 'referrer_type'=>'normal','sent_referral_by'=>'email') )); 
			
			}
			# assign to this referrer
			else {
				$result = server_curl(BACKEND_SERVER_URL, array('__action'=>'run', 'return'=>'plain', 'query'=>'add_user_referral', 'variables'=>array('user_id'=>$parameters['user_id'], 'referred_by'=>$referrers[0],'referrer_type'=>'normal','sent_referral_by'=>'email')));
			}
				
			log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [6] permissionGroup '.$permissionGroup . ' result ' .$result);
			
			# apply permission group
			if(!empty($result) && $result && !empty($permissionGroup)) {
				log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [7] result ' .$result);
				$result = update_user_group_by_name($parameters['user_id'], $permissionGroup);
				log_message('debug', 'security_functions/apply_new_inclusion_list_user:: [8] result ' .$result);
			}
		}
	}
	
	return !empty($result) && $result;
}





# update the user permission group given their id and permission group name
function update_user_group_by_name($userId, $groupName)
{
	log_message('debug', 'security_functions/update_user_group_by_name:: [1]');
	log_message('debug', 'security_functions/update_user_group_by_name:: [2] userId='.$userId.' groupName='.$groupName);
	
	$result = run('update_user_access_by_group_name', array('group_name'=>$groupName, 'user_id'=>$userId));
	$group = get_row_as_array('get_group_by_name', array('group_name'=>$groupName));
	log_message('debug', 'security_functions/update_user_group_by_name:: [3] group='.json_encode($group));
	
	if($result && !empty($group['group_type'])) {	
		$result = server_curl(BACKEND_SERVER_URL, array('__action'=>'run', 'return'=>'plain', 'query'=>'update_user_security_settings', 'variables'=>array('user_id'=>$userId, 'user_type'=>$group['group_type'], 'user_type_level'=>'level 1') ));
	}
	log_message('debug', 'security_functions/update_user_group_by_name:: [4] result='.$result);
	return $result;
}






# Extract a value of a setting 
function extract_rule_setting_value($string, $setting, $settingKey='') 
{
	log_message('debug', 'security_functions/extract_rule_setting_value:: [1]');
	log_message('debug', 'security_functions/extract_rule_setting_value:: [2] string '.$string.' '.$setting.' '.$settingKey);
	
	$settingStart = strpos($string,$setting);
			
	# a) extract the value of the setting
	$equalPos = strpos($string, '=', $settingStart);
	$closeBracePos = strpos($string, ']', $equalPos);
	#+1 to remove = and -1 to remove ]
	$rawValueString = substr($string, ($equalPos+1), ($closeBracePos - $equalPos - 1)); 
	$valueStringArray = explode('|', $rawValueString);
	$valueString = $valueStringArray[0];
			
	# b) extract the setting itself i.e., setting_variable=Setting Value|setting_id
	$settingString = substr($string, $settingStart, (strlen($setting) + 1 + strlen($rawValueString)) );
	
	$settings = array('setting_string'=>$settingString, 'setting'=>$setting, 'value'=>$valueString, 'value_id'=>(!empty($valueStringArray[1])? $valueStringArray[1]: ''));
	log_message('debug', 'security_functions/extract_rule_setting_value:: [3] settings '.json_encode($settings));
	
	return array_key_exists($settingKey, $settings)? $settings[$settingKey]: $settings;
}


	
	
	



########################################################
# UTILITY FUNCTIONS 								   #
########################################################



# Post data to the server URL
function server_curl($url, $data)
{
	# Connect and post to URL
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	if(!empty($data['__check'])) echo PHP_EOL.PHP_EOL.PHP_EOL.$url."?".http_build_query($data);

	$response = curl_exec($ch);
	$responseArray = json_decode($response, TRUE);

	curl_close($ch);
	return $responseArray;
}







function log_message($level, $msg, $php_error = FALSE)
{
	$level = strtoupper($level);
	if (!file_exists(HOME_URL.'logs')) mkdir(HOME_URL.'logs', 0777, true);
	$filepath = HOME_URL.'logs/log-'.date('Y-m-d').'.php';
	$message  = '';

	if (!file_exists($filepath)) {
		$message .= "<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ".PHP_EOL;
		if (!$fp = @fopen($filepath, 'w')) return FALSE;
	}
	else $fp = @fopen($filepath, 'a');

	if(LOG_ERROR_LEVEL > 0) {
		if(LOG_ERROR_LEVEL == 1 && $level == 'ERROR') $message .= PHP_EOL.$level.' - '.date('Y-m-d H:i:s'). ' --> '.$msg;
		else if(LOG_ERROR_LEVEL == 2 && in_array($level, array('DEBUG', 'ERROR'))) $message .= PHP_EOL.$level.' - '.date('Y-m-d H:i:s'). ' --> '.$msg;
		else if(LOG_ERROR_LEVEL == 3 && in_array($level, array('INFO', 'DEBUG', 'ERROR'))) $message .= PHP_EOL.$level.' - '.date('Y-m-d H:i:s'). ' --> '.$msg;
		else $message .= PHP_EOL.$level.' - '.date('Y-m-d H:i:s'). ' --> '.$msg;
	}
	
	flock($fp, LOCK_EX);
	fwrite($fp, $message);
	flock($fp, LOCK_UN);
	fclose($fp);
	@chmod($filepath, FILE_WRITE_MODE);

	return TRUE;
}


?>