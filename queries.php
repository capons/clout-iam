<?php
# This file defines the queries used by this server system functions.
global $sysQuery;

$sysQuery['check_user_by_username'] = "SELECT * FROM `clout_v1_3iam`.user_access WHERE user_name='_USER_NAME_' AND password='_PASSWORD_'";

$sysQuery['get_users_in_group_type'] = "SELECT A.user_id
FROM`clout_v1_3iam`.user_access A 
LEFT JOIN `clout_v1_3iam`.permission_groups G ON (G.id=A.permission_group_id)
WHERE G.group_type IN ('_GROUP_TYPE_') _LIMIT_TEXT_";

$sysQuery['save_access_details'] = "INSERT INTO `clout_v1_3iam`.user_access (user_id, permission_group_id, user_name, password, last_updated) VALUES ('_USER_ID_', '_PERMISSION_GROUP_ID_', '_USER_NAME_', '_PASSWORD_', NOW())";


$sysQuery['update_user_password'] = "UPDATE `clout_v1_3iam`.user_access DEST, 
(SELECT '_PASSWORD_' AS password, password AS old_password_1, old_password_1 AS old_password_2, NOW() AS last_updated FROM `clout_v1_3iam`.user_access WHERE user_id='_USER_ID_') SRC 
SET DEST.password=SRC.password, DEST.old_password_1=SRC.old_password_1, DEST.old_password_2=SRC.old_password_2, DEST.last_updated=SRC.last_updated 
WHERE user_id='_USER_ID_'";


$sysQuery['update_user_group_mapping'] = "UPDATE `clout_v1_3iam`.user_access SET permission_group_id='_NEW_GROUP_ID_', last_updated=NOW() WHERE user_id IN ('_USER_ID_LIST_')";




$sysQuery['get_permission_group_list'] = 'SELECT A.*, CONCAT(IF(A.permission_string <> \'\', CONCAT(\'PERMISSIONS: \', A.permission_string), \'\'),\' \', IF(A.rule_string <> \'\', CONCAT(\'RULES: \', A.rule_string), \'\')) AS permission_summary 
FROM 
(SELECT G.id AS group_id, G.is_removable, 
G.name AS group_name, 

(SELECT GROUP_CONCAT(DISTINCT CONCAT(REPLACE(P.category, \'_\', \' \'), \' (\',
	(SELECT COUNT(_permission_id) FROM clout_v1_3iam.permission_group_mapping_permissions PM1 
		LEFT JOIN clout_v1_3iam.permissions P1 ON (PM1._permission_id=P1.id) WHERE PM1._group_id=PM._group_id AND P1.category=P.category),
	\' permissions)\') SEPARATOR \', \')
FROM clout_v1_3iam.permission_group_mapping_permissions PM LEFT JOIN clout_v1_3iam.permissions P ON (PM._permission_id=P.id)
WHERE PM._group_id=G.id) AS permission_string, 

(SELECT GROUP_CONCAT(DISTINCT CONCAT(REPLACE(R.category, \'_\', \' \'), \' (\',
	(SELECT COUNT(_rule_id) FROM clout_v1_3iam.permission_group_mapping_rules RM1 
		LEFT JOIN clout_v1_3iam.rules R1 ON (RM1._rule_id=R1.id) WHERE RM1._group_id=RM._group_id AND R1.category=R.category),
	\' rules)\') SEPARATOR \', \')
FROM clout_v1_3iam.permission_group_mapping_rules RM LEFT JOIN clout_v1_3iam.rules R ON (RM._rule_id=R.id)
WHERE RM._group_id=G.id) AS rule_string, 

(SELECT COUNT(DISTINCT user_id) FROM clout_v1_3iam.user_access WHERE permission_group_id=G.id) AS user_count, 

G.`status`
FROM clout_v1_3iam.permission_groups G
WHERE 1=1 _PHRASE_CONDITION_ _CATEGORY_CONDITION_ 
_LIMIT_TEXT_) A
';





$sysQuery['get_permission_list'] = 'SELECT P.id AS permission_id, P.code, P.display AS name, P.details AS description, P.category, P.url, P.status FROM clout_v1_3iam.permissions P 
WHERE 1=1 _PHRASE_CONDITION_ 
_LIMIT_TEXT_ ';


$sysQuery['get_rule_category_list'] = 'SELECT * FROM (
SELECT DISTINCT category, cap_first_letter_in_words(REPLACE(category, \'_\', \' \')) AS category_display FROM clout_v1_3iam.rules WHERE user_type <> \'system\') A WHERE 1=1 _PHRASE_CONDITION_ _LIMIT_TEXT_'; 



$sysQuery['get_rule_name_list'] = 'SELECT id, code, display AS name, category, cap_first_letter_in_words(REPLACE(category, \'_\', \' \')) AS category_display, status FROM clout_v1_3iam.rules WHERE user_type <> \'system\' _CATEGORY_CONDITION_ _PHRASE_CONDITION_ _LIMIT_TEXT_'; 

 


$sysQuery['get_group_by_id'] = 'SELECT id, name, group_type, group_category, is_removable FROM clout_v1_3iam.`permission_groups` WHERE id=\'_GROUP_ID_\'';



$sysQuery['get_group_rules'] = 'SELECT R.id, R.code, R.display AS name, R.category, cap_first_letter_in_words(REPLACE(R.category, \'_\', \' \')) AS category_display, R.status FROM clout_v1_3iam.permission_group_mapping_rules M LEFT JOIN clout_v1_3iam.rules R ON (M._rule_id=R.id) WHERE M._group_id=\'_GROUP_ID_\'';



$sysQuery['get_group_permissions'] = 'SELECT P.id AS permission_id, P.code, P.display AS name, P.details AS description, P.category, P.url, P.status FROM 
clout_v1_3iam.permission_group_mapping_permissions M 
LEFT JOIN clout_v1_3iam.permissions P ON (M._permission_id=P.id)
WHERE M._group_id=\'_GROUP_ID_\'';




$sysQuery['add_permission_group'] = 'INSERT IGNORE INTO clout_v1_3iam.permission_groups (name, notes, group_type, group_category, _default_permission, is_removable, status, date_entered, entered_by, last_updated, last_updated_by) VALUES 

(\'_NAME_\', \'_NAME_\', \'_GROUP_TYPE_\', \'_GROUP_CATEGORY_\', \'1\', \'_IS_REMOVABLE_\', \'_STATUS_\', NOW(), \'_USER_ID_\', NOW(), \'_USER_ID_\')';



$sysQuery['update_permission_group'] = 'UPDATE clout_v1_3iam.permission_groups SET name=\'_NAME_\', group_type=\'_GROUP_TYPE_\', last_updated_by=\'_USER_ID_\', last_updated=NOW() WHERE id=\'_GROUP_ID_\'';




$sysQuery['delete_group_permissions'] = 'DELETE FROM clout_v1_3iam.`permission_group_mapping_permissions` WHERE _group_id=\'_GROUP_ID_\'';



$sysQuery['add_group_permissions'] = 'INSERT IGNORE INTO clout_v1_3iam.`permission_group_mapping_permissions` (_group_id, _permission_id, entered_by, date_entered) 

(SELECT \'_GROUP_ID_\' AS _group_id, P.id AS _permission_id, \'_USER_ID_\' AS entered_by, NOW() AS date_entered FROM clout_v1_3iam.permissions P WHERE P.id IN (\'_PERMISSION_IDS_\'))';





$sysQuery['delete_group_rules'] = 'DELETE FROM clout_v1_3iam.`permission_group_mapping_rules` WHERE _group_id=\'_GROUP_ID_\'';




$sysQuery['add_group_rules'] = 'INSERT IGNORE INTO clout_v1_3iam.`permission_group_mapping_rules` (_group_id, _rule_id, entered_by, date_entered) 

(SELECT \'_GROUP_ID_\' AS _group_id, R.id AS _rule_id, \'_USER_ID_\' AS entered_by, NOW() AS date_entered FROM clout_v1_3iam.rules R WHERE R.id IN (\'_RULE_IDS_\'))';





$sysQuery['update_permission_group_status'] = 'UPDATE clout_v1_3iam.`permission_groups` SET status=\'_STATUS_\', last_updated_by=\'_USER_ID_\', last_updated=NOW() WHERE id=\'_GROUP_ID_\'';




$sysQuery['get_rule_settings_list'] = 'SELECT R.id AS rule_id, R.user_type, R.code, R.display AS name, R.details AS description, R.category,  R.status, 

(SELECT COUNT(DISTINCT UA.user_id) FROM clout_v1_3iam.permission_group_mapping_rules MR 
LEFT JOIN clout_v1_3iam.user_access UA ON (MR._group_id=UA.permission_group_id)
WHERE MR._rule_id=R.id) AS user_count, 

(SELECT cap_first_letter_in_words(REPLACE(GROUP_CONCAT(DISTINCT group_type SEPARATOR \', \'), \'_\', \' \')) FROM clout_v1_3iam.permission_group_mapping_rules MR 
LEFT JOIN clout_v1_3iam.permission_groups G ON (MR._group_id=G.id)
WHERE MR._rule_id=R.id) AS user_groups

FROM clout_v1_3iam.rules R 
WHERE 1=1 _PHRASE_CONDITION_ _LIMIT_TEXT_ ';





$sysQuery['update_rule_setting_status'] = 'UPDATE clout_v1_3iam.rules SET status=\'_STATUS_\' WHERE id=\'_RULE_ID_\'';





$sysQuery['get_rule_setting'] = 'SELECT * FROM clout_v1_3iam.rules WHERE id=\'_RULE_ID_\'';





$sysQuery['update_setting_value'] = 'UPDATE clout_v1_3iam.rules SET details=REPLACE(details, \'_PREVIOUS_VALUE_STRING_\', \'_NEW_VALUE_STRING_\') WHERE id=\'_SETTING_ID_\'';




$sysQuery['get_user_group_types'] = 'SELECT DISTINCT G.group_type FROM clout_v1_3iam.user_access A LEFT JOIN clout_v1_3iam.permission_groups G ON (A.permission_group_id=G.id) WHERE A.user_id=\'_USER_ID_\'';




$sysQuery['get_user_permissions'] = 'SELECT P.code AS permission_code, P.category FROM clout_v1_3iam.user_access A 
LEFT JOIN clout_v1_3iam.permission_group_mapping_permissions PM ON (A.permission_group_id=PM._group_id) 
LEFT JOIN clout_v1_3iam.permissions P ON (PM._permission_id=P.id) 

WHERE A.user_id=\'_USER_ID_\'';




$sysQuery['get_user_rules'] = 'SELECT R.code AS rule_code FROM clout_v1_3iam.user_access A 
LEFT JOIN clout_v1_3iam.permission_group_mapping_rules RM ON (A.permission_group_id=RM._group_id) 
LEFT JOIN clout_v1_3iam.rules R ON (RM._rule_id=R.id) 

WHERE A.user_id=\'_USER_ID_\'';




$sysQuery['get_rule_by_code'] = 'SELECT id AS rule_id, user_type, details FROM clout_v1_3iam.rules WHERE code=\'_CODE_\' AND status=\'active\'';


$sysQuery['add_user_permission_group'] =  'INSERT INTO clout_v1_3iam.user_access (user_id, permission_group_id, user_name, password, last_updated) 
(SELECT \'_USER_ID_\' AS user_id, G.id AS permission_group_id, \'_USER_NAME_\' AS user_name, \'_PASSWORD_\' AS password, NOW() AS last_updated FROM clout_v1_3iam.permission_groups G WHERE LOWER(G.name)=LOWER(\'_GROUP_NAME_\'))

ON DUPLICATE KEY UPDATE password=VALUES(password), last_updated=VALUES(last_updated)';


$sysQuery['get_user_settings'] = 'SELECT _FIELDS_ FROM (
SELECT UNIX_TIMESTAMP(last_updated) AS passwordLastUpdated,

IFNULL((SELECT G.name FROM clout_v1_3iam.permission_groups G WHERE G.id=U.permission_group_id LIMIT 1),\'\') AS groupName,

IFNULL((SELECT G.group_type FROM clout_v1_3iam.permission_groups G WHERE G.id=U.permission_group_id LIMIT 1),\'\') AS groupType,

U.permission_group_id AS groupId

FROM user_access U WHERE U.user_id=\'_USER_ID_\'
) A ';



$sysQuery['is_rule_applied_to_user'] = 'SELECT 
IF((SELECT PR._rule_id FROM clout_v1_3iam.permission_group_mapping_rules PR 
LEFT JOIN clout_v1_3iam.user_access UA ON (UA.permission_group_id=PR._group_id)
WHERE PR._rule_id=\'_RULE_ID_\' AND UA.user_id=\'_USER_ID_\'
) IS NOT NULL, \'Y\', \'N\') AS is_applied';


$sysQuery['remove_user_permission_group'] = 'DELETE FROM clout_v1_3iam.user_access WHERE user_id=\'_USER_ID_\'';



$sysQuery['get_permission_group_types'] = 'SELECT DISTINCT group_type AS type_code, `clout_v1_3`.cap_first_letter_in_words(REPLACE(group_type,\'_\',\' \')) AS type_display

FROM `clout_v1_3iam`.permission_groups WHERE 1=1 _CATEGORY_CONDITION_ 
ORDER BY group_type ';



$sysQuery['delete_permission_group_rules'] = 'DELETE FROM `clout_v1_3iam`.permission_group_mapping_rules WHERE _group_id=\'_GROUP_ID_\'';


$sysQuery['delete_permission_group_permissions'] = 'DELETE FROM `clout_v1_3iam`.permission_group_mapping_permissions WHERE _group_id=\'_GROUP_ID_\'';


$sysQuery['delete_permission_group'] = 'DELETE FROM `clout_v1_3iam`.permission_groups WHERE id=\'_GROUP_ID_\'';


$sysQuery['set_user_access_group_by_field'] = 'UPDATE `clout_v1_3iam`.user_access SET permission_group_id=\'_GROUP_ID_\' WHERE _FIELD_NAME_=\'_FIELD_VALUE_\'';



function get_sys_query($code) { 
	global $sysQuery; 
	return !empty($sysQuery[$code])? $sysQuery[$code]: '';
}


