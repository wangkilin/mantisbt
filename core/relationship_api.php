<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Relationship API
 *
 * RELATIONSHIP DEFINITIONS
 * * Child/parent relationship:
 *    the child bug is generated by the parent bug or is directly linked with the parent with the following meaning
 *    the child bug has to be resolved before resolving the parent bug (the child bug "blocks" the parent bug)
 *    example: bug A is child bug of bug B. It means: A blocks B and B is blocked by A
 * * General relationship:
 *    two bugs related each other without any hierarchy dependence
 *    bugs A and B are related
 * * Duplicates:
 *    it's used to mark a bug as duplicate of an other bug already stored in the database
 *    bug A is marked as duplicate of B. It means: A duplicates B, B has duplicates
 *
 * Relations are always visible in the email body
 * --------------------------------------------------------------------
 * ADD NEW RELATIONSHIP
 * - Permission: user can update the source bug and at least view the destination bug
 * - Action recorded in the history of both the bugs
 * - Email notification sent to the users of both the bugs based based on the 'updated' bug notify type.
 * --------------------------------------------------------
 * DELETE RELATIONSHIP
 * - Permission: user can update the source bug and at least view the destination bug
 * - Action recorded in the history of both the bugs
 * - Email notification sent to the users of both the bugs based based on the 'updated' bug notify type.
 * --------------------------------------------------------
 * RESOLVE/CLOSE BUGS WITH BLOCKING CHILD BUGS STILL OPEN
 * Just a warning is print out on the form when an user attempts to resolve or close a bug with
 * related bugs in relation BUG_DEPENDANT still not resolved.
 * Anyway the user can force the resolving/closing action.
 * --------------------------------------------------------
 * EMAIL NOTIFICATION TO PARENT BUGS WHEN CHILDREN BUGS ARE RESOLVED/CLOSED
 * Every time a child bug is resolved or closed, an email notification is sent directly to all the handlers
 * of the parent bugs. The notification is sent to bugs not already marked as resolved or closed.
 * --------------------------------------------------------
 * ADD CHILD
 * This function gives the opportunity to generate a child bug. In details the function:
 * - create a new bug with the same basic information of the parent bug (plus the custom fields)
 * - copy all the attachment of the parent bug to the child
 * - not copy history, bugnotes, monitoring users
 * - set a relationship between parent and child
 *
 * @package CoreAPI
 * @subpackage RelationshipAPI
 * @author Marcello Scata' <marcelloscata at users.sourceforge.net> ITALY
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses bug_api.php
 * @uses collapse_api.php
 * @uses config_api.php
 * @uses constant_api.php
 * @uses current_user_api.php
 * @uses database_api.php
 * @uses form_api.php
 * @uses helper_api.php
 * @uses lang_api.php
 * @uses prepare_api.php
 * @uses print_api.php
 * @uses project_api.php
 * @uses string_api.php
 * @uses utility_api.php
 */

require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'collapse_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'helper_api.php' );
require_api( 'lang_api.php' );
require_api( 'prepare_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );
require_api( 'utility_api.php' );

require_css( 'status_config.php' );

/**
 * RelationshipData Structure Definition
 */
class BugRelationshipData {
	/**
	 * Relationship id
	 */
	public $id;

	/**
	 * Source Bug id
	 */
	public $src_bug_id;

	/**
	 * Source project id
	 */
	public $src_project_id;

	/**
	 * Destination Bug id
	 */
	public $dest_bug_id;

	/**
	 * Destination project id
	 */
	public $dest_project_id;

	/**
	 * Type
	 */
	public $type;
}

$g_relationships = array();
$g_relationships[BUG_DEPENDANT] = array(
	'#forward' => true,
	'#complementary' => BUG_BLOCKS,
	'#name' => 'parent-of',
	'#description' => 'dependant_on',
	'#notify_added' => 'email_notification_title_for_action_dependant_on_relationship_added',
	'#notify_deleted' => 'email_notification_title_for_action_dependant_on_relationship_deleted',
	'#edge_style' => array(
		'color' => '#C00000',
		'dir' => 'back',
	),
);
$g_relationships[BUG_BLOCKS] = array(
	'#forward' => false,
	'#complementary' => BUG_DEPENDANT,
	'#name' => 'child-of',
	'#description' => 'blocks',
	'#notify_added' => 'email_notification_title_for_action_blocks_relationship_added',
	'#notify_deleted' => 'email_notification_title_for_action_blocks_relationship_deleted',
	'#edge_style' => array(
		'color' => '#C00000',
		'dir' => 'forward',
	),
);
$g_relationships[BUG_DUPLICATE] = array(
	'#forward' => true,
	'#complementary' => BUG_HAS_DUPLICATE,
	'#name' => 'duplicate-of',
	'#description' => 'duplicate_of',
	'#notify_added' => 'email_notification_title_for_action_duplicate_of_relationship_added',
	'#notify_deleted' => 'email_notification_title_for_action_duplicate_of_relationship_deleted',
	'#edge_style' => array(
		'style' => 'dashed',
		'color' => '#808080',
	),
);
$g_relationships[BUG_HAS_DUPLICATE] = array(
	'#forward' => false,
	'#complementary' => BUG_DUPLICATE,
	'#name' => 'has-duplicate',
	'#description' => 'has_duplicate',
	'#notify_added' => 'email_notification_title_for_action_has_duplicate_relationship_added',
	'#notify_deleted' => 'email_notification_title_for_action_has_duplicate_relationship_deleted',
);
$g_relationships[BUG_RELATED] = array(
	'#forward' => true,
	'#name' => 'related-to',
	'#complementary' => BUG_RELATED,
	'#description' => 'related_to',
	'#notify_added' => 'email_notification_title_for_action_related_to_relationship_added',
	'#notify_deleted' => 'email_notification_title_for_action_related_to_relationship_deleted',
);

if( file_exists( config_get_global( 'config_path' ) . 'custom_relationships_inc.php' ) ) {
	include_once( config_get_global( 'config_path' ) . 'custom_relationships_inc.php' );
}

/**
 * Return the complementary type of the provided relationship
 * @param integer $p_relationship_type A Relationship type.
 * @return integer Complementary type
 */
function relationship_get_complementary_type( $p_relationship_type ) {
	global $g_relationships;
	if( !isset( $g_relationships[$p_relationship_type] ) ) {
		trigger_error( ERROR_GENERIC, ERROR );
	}
	return $g_relationships[$p_relationship_type]['#complementary'];
}

/**
 * Add a new relationship
 * @param integer $p_src_bug_id        Source Bug Id.
 * @param integer $p_dest_bug_id       Destination Bug Id.
 * @param integer $p_relationship_type Relationship type.
 * @param bool $p_email_for_source     Should an email be triggered for source issue?
 * @return integer The new bug relationship id.
 */
function relationship_add( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source = true ) {
	global $g_relationships;
	if( $g_relationships[$p_relationship_type]['#forward'] === false ) {
		$c_src_bug_id = (int)$p_dest_bug_id;
		$c_dest_bug_id = (int)$p_src_bug_id;
		$c_relationship_type = (int)relationship_get_complementary_type( $p_relationship_type );
	} else {
		$c_src_bug_id = (int)$p_src_bug_id;
		$c_dest_bug_id = (int)$p_dest_bug_id;
		$c_relationship_type = (int)$p_relationship_type;
	}

	db_param_push();
	$t_query = 'INSERT INTO {bug_relationship}
				( source_bug_id, destination_bug_id, relationship_type )
				VALUES
				( ' . db_param() . ',' . db_param() . ',' . db_param() . ')';
	db_query( $t_query, array( $c_src_bug_id, $c_dest_bug_id, $c_relationship_type ) );

	$t_relationship_id = db_insert_id( db_get_table( 'bug_relationship' ) );

	history_log_event_special( $p_src_bug_id, BUG_ADD_RELATIONSHIP, $p_relationship_type, $p_dest_bug_id );
	history_log_event_special( $p_dest_bug_id, BUG_ADD_RELATIONSHIP, relationship_get_complementary_type( $p_relationship_type ), $p_src_bug_id );

	bug_update_date( $p_src_bug_id );
	bug_update_date( $p_dest_bug_id );

	email_relationship_added( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source );

	return $t_relationship_id;
}

/**
 * Update a relationship
 * @param integer $p_relationship_id   Relationship Id to update.
 * @param integer $p_src_bug_id        Source Bug Id.
 * @param integer $p_dest_bug_id       Destination Bug Id.
 * @param integer $p_relationship_type Relationship type.
 * @param bool $p_email_for_source     Should an email be triggered for source issue?
 * @return void
 */
function relationship_update( $p_relationship_id, $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source = true ) {
	global $g_relationships;
	if( $g_relationships[$p_relationship_type]['#forward'] === false ) {
		$c_src_bug_id = (int)$p_dest_bug_id;
		$c_dest_bug_id = (int)$p_src_bug_id;
		$c_relationship_type = (int)relationship_get_complementary_type( $p_relationship_type );
	} else {
		$c_src_bug_id = (int)$p_src_bug_id;
		$c_dest_bug_id = (int)$p_dest_bug_id;
		$c_relationship_type = (int)$p_relationship_type;
	}

	db_param_push();
	$t_query = 'UPDATE {bug_relationship}
				SET source_bug_id=' . db_param() . ',
					destination_bug_id=' . db_param() . ',
					relationship_type=' . db_param() . '
				WHERE id=' . db_param();
	db_query( $t_query, array( $c_src_bug_id, $c_dest_bug_id, $c_relationship_type, (int)$p_relationship_id ) );

	history_log_event_special( $p_src_bug_id, BUG_REPLACE_RELATIONSHIP, $p_relationship_type, $p_dest_bug_id );
	history_log_event_special( $p_dest_bug_id, BUG_REPLACE_RELATIONSHIP, relationship_get_complementary_type( $p_relationship_type ), $p_src_bug_id );

	bug_update_date( $p_src_bug_id );
	bug_update_date( $p_dest_bug_id );

	email_relationship_added( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source );
}

/**
 * Add/Update relationship based on whether the relationship already exists or not.
 *
 * @param integer $p_src_bug_id        Source Bug Id.
 * @param integer $p_dest_bug_id       Destination Bug Id.
 * @param integer $p_relationship_type Relationship type.
 * @param bool $p_email_for_source     Should an email be triggered for source issue?
 * @return integer The new bug relationship id.
 */
function relationship_upsert( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source = true ) {
	# Check if there is other relationship between the bugs.
	$t_id_relationship = relationship_same_type_exists( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type );

	if( $t_id_relationship > 0 ) {
		relationship_update( $t_id_relationship, $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source );
		$t_relationship_id = $t_id_relationship;
	} else if( $t_id_relationship != -1 ) {
		$t_relationship_id = relationship_add( $p_src_bug_id, $p_dest_bug_id, $p_relationship_type, $p_email_for_source );
	} else {
		# else relationship is -1 - same type exists
		$t_relationship_id = relationship_exists( $p_src_bug_id, $p_dest_bug_id );
	}

	return $t_relationship_id;
}

/**
 * Delete a relationship
 * @param integer $p_relationship_id Relationship Id to update.
 * @param bool $p_send_email Send email?
 * @return void
 */
function relationship_delete( $p_relationship_id, $p_send_email = true ) {
	$t_relationship = relationship_get( $p_relationship_id );

	db_param_push();
	$t_query = 'DELETE FROM {bug_relationship} WHERE id=' . db_param();
	db_query( $t_query, array( (int)$p_relationship_id ) );

	$t_src_bug_id = $t_relationship->src_bug_id;
	$t_dest_bug_id = $t_relationship->dest_bug_id;
	$t_rel_type = $t_relationship->type;

	bug_update_date( $t_src_bug_id );
	bug_update_date( $t_dest_bug_id );

	history_log_event_special( $t_src_bug_id, BUG_DEL_RELATIONSHIP, $t_rel_type, $t_dest_bug_id );

	if( bug_exists( $t_dest_bug_id ) ) {
		history_log_event_special(
			$t_dest_bug_id,
			BUG_DEL_RELATIONSHIP,
			relationship_get_complementary_type( $t_rel_type ),
			$t_src_bug_id );
	}

	if( $p_send_email ) {
		email_relationship_deleted( $t_src_bug_id, $t_dest_bug_id, $t_rel_type );
	}
}

/**
 * Deletes all the relationships related to a specific bug (both source and destination)
 * @param integer $p_bug_id A bug Identifier.
 * @return void
 */
function relationship_delete_all( $p_bug_id ) {
	$t_is_different_projects = false;
	$t_relationships = relationship_get_all( $p_bug_id, $t_is_different_projects );
	foreach( $t_relationships as $t_relationship ) {
		relationship_delete( $t_relationship->id, /* send_email */ false );
	}
}

/**
 * copy all the relationships related to a specific bug to a new bug
 * @param integer $p_bug_id     Source bug identifier.
 * @param integer $p_new_bug_id Destination bug identifier.
 * @return void
 */
function relationship_copy_all( $p_bug_id, $p_new_bug_id ) {
	$t_relationships = relationship_get_all_src( $p_bug_id );
	foreach( $t_relationships as $t_relationship ) {
		relationship_add(
			$p_new_bug_id,
			$t_relationship->dest_bug_id,
			$t_relationship->type,
			/* email_for_source */ false );
	}

	$t_relationships = relationship_get_all_dest( $p_bug_id );
	foreach( $t_relationships as $t_relationship ) {
		relationship_add(
			$p_new_bug_id,
			$t_relationship->src_bug_id,
			relationship_get_complementary_type( $t_relationship->type ),
			/* email_for_source */ false );
	}
}

/**
 * get a relationship from id
 * @param integer $p_relationship_id Relationship Identifier.
 * @return null|BugRelationshipData BugRelationshipData object
 */
function relationship_get( $p_relationship_id ) {
	db_param_push();
	$t_query = 'SELECT * FROM {bug_relationship} WHERE id=' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_relationship_id ) );

	$t_relationship = db_fetch_array( $t_result );

	if( $t_relationship ) {
		$t_bug_relationship_data = new BugRelationshipData;
		$t_bug_relationship_data->id = $t_relationship['id'];
		$t_bug_relationship_data->src_bug_id = $t_relationship['source_bug_id'];
		$t_bug_relationship_data->dest_bug_id = $t_relationship['destination_bug_id'];
		$t_bug_relationship_data->type = $t_relationship['relationship_type'];
	} else {
		$t_bug_relationship_data = null;
	}

	return $t_bug_relationship_data;
}

/**
 * get all relationships with the given bug as source
 * @param integer $p_src_bug_id Source Bug identifier.
 * @return array Array of BugRelationshipData objects
 */
function relationship_get_all_src( $p_src_bug_id ) {
	db_param_push();
	$t_query = 'SELECT {bug_relationship}.id, {bug_relationship}.relationship_type,
				{bug_relationship}.source_bug_id, {bug_relationship}.destination_bug_id,
				{bug}.project_id
				FROM {bug_relationship}
				INNER JOIN {bug} ON {bug_relationship}.destination_bug_id = {bug}.id
				WHERE source_bug_id=' . db_param() . '
				ORDER BY relationship_type, {bug_relationship}.id';
	$t_result = db_query( $t_query, array( $p_src_bug_id ) );

	$t_src_project_id = bug_get_field( $p_src_bug_id, 'project_id' );

	$t_bug_relationship_data = array();
	$t_bug_array = array();
	$i = 0;

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_bug_relationship_data[$i] = new BugRelationshipData;
		$t_bug_relationship_data[$i]->id = $t_row['id'];
		$t_bug_relationship_data[$i]->src_bug_id = $t_row['source_bug_id'];
		$t_bug_relationship_data[$i]->src_project_id = $t_src_project_id;
		$t_bug_relationship_data[$i]->dest_bug_id = $t_row['destination_bug_id'];
		$t_bug_relationship_data[$i]->dest_project_id = $t_row['project_id'];
		$t_bug_relationship_data[$i]->type = $t_row['relationship_type'];
		$t_bug_array[] = $t_row['destination_bug_id'];
		$i++;
	}

	if( !empty( $t_bug_array ) ) {
		bug_cache_array_rows( $t_bug_array );
	}

	return $t_bug_relationship_data;
}

/**
 * get all relationships with the given bug as destination
 * @param integer $p_dest_bug_id Destination bug identifier.
 * @return array Array of BugRelationshipData objects
 */
function relationship_get_all_dest( $p_dest_bug_id ) {
	db_param_push();
	$t_query = 'SELECT {bug_relationship}.id, {bug_relationship}.relationship_type,
				{bug_relationship}.source_bug_id, {bug_relationship}.destination_bug_id,
				{bug}.project_id
				FROM {bug_relationship}
				INNER JOIN {bug} ON {bug_relationship}.source_bug_id = {bug}.id
				WHERE destination_bug_id=' . db_param() . '
				ORDER BY relationship_type, {bug_relationship}.id';
	$t_result = db_query( $t_query, array( (int)$p_dest_bug_id ) );

	$t_dest_project_id = bug_get_field( $p_dest_bug_id, 'project_id' );

	$t_bug_relationship_data = array();
	$t_bug_array = array();
	$i = 0;

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_bug_relationship_data[$i] = new BugRelationshipData;
		$t_bug_relationship_data[$i]->id = $t_row['id'];
		$t_bug_relationship_data[$i]->src_bug_id = $t_row['source_bug_id'];
		$t_bug_relationship_data[$i]->src_project_id = $t_row['project_id'];
		$t_bug_relationship_data[$i]->dest_bug_id = $t_row['destination_bug_id'];
		$t_bug_relationship_data[$i]->dest_project_id = $t_dest_project_id;
		$t_bug_relationship_data[$i]->type = $t_row['relationship_type'];
		$t_bug_array[] = $t_row['source_bug_id'];
		$i++;
	}

	if( !empty( $t_bug_array ) ) {
		bug_cache_array_rows( $t_bug_array );
	}
	return $t_bug_relationship_data;
}

/**
 * get all relationships associated with the given bug
 * @param integer $p_bug_id                 A bug identifier.
 * @param boolean &$p_is_different_projects Returned Boolean value indicating if some relationships cross project boundaries.
 * @return array Array of BugRelationshipData objects
 */
function relationship_get_all( $p_bug_id, &$p_is_different_projects ) {
	$t_src = relationship_get_all_src( $p_bug_id );
	$t_dest = relationship_get_all_dest( $p_bug_id );
	$t_all = array_merge( $t_src, $t_dest );

	$p_is_different_projects = false;
	$t_count = count( $t_all );
	for( $i = 0;$i < $t_count;$i++ ) {
		$p_is_different_projects |= ( $t_all[$i]->src_project_id != $t_all[$i]->dest_project_id );
	}
	return $t_all;
}

/**
 * check if there is a relationship between two bugs
 * return id if found 0 otherwise
 * @param integer $p_src_bug_id  Source bug identifier.
 * @param integer $p_dest_bug_id Destination bug identifier.
 * @return integer Relationship ID
 */
function relationship_exists( $p_src_bug_id, $p_dest_bug_id ) {
	$c_src_bug_id = (int)$p_src_bug_id;
	$c_dest_bug_id = (int)$p_dest_bug_id;

	db_param_push();
	$t_query = 'SELECT * FROM {bug_relationship}
				WHERE (source_bug_id=' . db_param() . ' AND destination_bug_id=' . db_param() . ')
				OR
				(source_bug_id=' . db_param() . '
				AND destination_bug_id=' . db_param() . ')';
	$t_result = db_query( $t_query, array( $c_src_bug_id, $c_dest_bug_id, $c_dest_bug_id, $c_src_bug_id ), 1 );

	if( $t_row = db_fetch_array( $t_result ) ) {
		# return the first id
		return $t_row['id'];
	} else {
		# no relationship found
		return 0;
	}
}

/**
 * check if there is a relationship between two bugs
 * return:
 *  0 if the relationship is not found
 *  -1 if the relationship is found and it's of the same type $p_rel_type
 *  id if the relationship is found and it's of a different time (this means it can be replaced with the new type $p_rel_type
 * @param integer $p_src_bug_id  Source Bug Id.
 * @param integer $p_dest_bug_id Destination Bug Id.
 * @param integer $p_rel_type    Relationship Type.
 * @return integer 0, -1 or id
 */
function relationship_same_type_exists( $p_src_bug_id, $p_dest_bug_id, $p_rel_type ) {
	# Check if there is already a relationship set between them
	$t_id_relationship = relationship_exists( $p_src_bug_id, $p_dest_bug_id );

	if( $t_id_relationship > 0 ) {
		# if there is...
		# get all the relationship info
		$t_relationship = relationship_get( $t_id_relationship );

		if( $t_relationship->src_bug_id == $p_src_bug_id && $t_relationship->dest_bug_id == $p_dest_bug_id ) {
			if( $t_relationship->type == $p_rel_type ) {
				$t_id_relationship = -1;
			}
		} else {
			if( $t_relationship->type == relationship_get_complementary_type( $p_rel_type ) ) {
				$t_id_relationship = -1;
			}
		}
	}
	return $t_id_relationship;
}

/**
 * retrieve the linked bug id of the relationship: provide source -> return destination; provide destination -> return source
 * @param integer $p_relationship_id Relationship id.
 * @param integer $p_bug_id          A bug identifier.
 * @return int Complementary bug id
 */
function relationship_get_linked_bug_id( $p_relationship_id, $p_bug_id ) {
	$t_bug_relationship_data = relationship_get( $p_relationship_id );

	if( $t_bug_relationship_data->src_bug_id == $p_bug_id ) {
		return $t_bug_relationship_data->dest_bug_id;
	}

	if( $t_bug_relationship_data->dest_bug_id == $p_bug_id ) {
		return $t_bug_relationship_data->src_bug_id;
	}

	trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR );
}

/**
 * get class description of a relationship (source side)
 * @param integer $p_relationship_type Relationship type.
 * @return string Relationship description
 */
function relationship_get_description_src_side( $p_relationship_type ) {
	global $g_relationships;
	if( !isset( $g_relationships[$p_relationship_type] ) ) {
		trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR );
	}
	return lang_get( $g_relationships[$p_relationship_type]['#description'] );
}

/**
 * get class description of a relationship (destination side)
 * @param integer $p_relationship_type Relationship type.
 * @return string Relationship description
 */
function relationship_get_description_dest_side( $p_relationship_type ) {
	global $g_relationships;
	if( !isset( $g_relationships[$p_relationship_type] ) || !isset( $g_relationships[$g_relationships[$p_relationship_type]['#complementary']] ) ) {
		trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR );
	}
	return lang_get( $g_relationships[$g_relationships[$p_relationship_type]['#complementary']]['#description'] );
}

/**
 * get class description of a relationship as it's stored in the history
 * @param integer $p_relationship_code Relationship Type.
 * @return string Relationship description
 */
function relationship_get_description_for_history( $p_relationship_code ) {
	return relationship_get_description_src_side( $p_relationship_code );
}

/**
 * Get class API name of a relationship as it's stored in the history.
 * @param integer $p_relationship_type Relationship Type.
 * @return string Relationship API name
 */
function relationship_get_name_for_api( $p_relationship_type ) {
	global $g_relationships;

	if( !isset( $g_relationships[$p_relationship_type] ) ) {
		switch( $p_relationship_type ) {
			case BUG_REL_NONE:
				return 'none';
			case BUG_REL_ANY:
				return 'any';
			default:
				trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR );
		}
	}

	return $g_relationships[$p_relationship_type]['#name'];
}

/**
 * return false if there are child bugs not resolved/closed
 * N.B. we don't check if the parent bug is read-only. This is because the answer of this function is indepedent from
 * the state of the parent bug itself.
 * @param integer $p_bug_id A bug identifier.
 * @return boolean
 */
function relationship_can_resolve_bug( $p_bug_id ) {
	# retrieve all the relationships in which the bug is the source bug
	$t_relationship = relationship_get_all_src( $p_bug_id );
	$t_relationship_count = count( $t_relationship );
	if( $t_relationship_count == 0 ) {
		return true;
	}

	for( $i = 0;$i < $t_relationship_count;$i++ ) {
		# verify if each bug in relation BUG_DEPENDANT is already marked as resolved
		if( $t_relationship[$i]->type == BUG_DEPENDANT ) {
			$t_dest_bug_id = $t_relationship[$i]->dest_bug_id;
			$t_status = bug_get_field( $t_dest_bug_id, 'status' );

			if( $t_status < config_get( 'bug_resolved_status_threshold' ) ) {
				# the bug is NOT marked as resolved/closed
				return false;
			}
		}
	}

	return true;
}

/**
 * return formatted string with all the details on the requested relationship
 * @param integer             $p_bug_id       A bug identifier.
 * @param BugRelationshipData $p_relationship A bug relationship object.
 * @param boolean             $p_html         Whether to return html or text output.
 * @param boolean             $p_html_preview Whether to include style/hyperlinks - if preview is false, we prettify the output.
 * @param boolean             $p_show_project Show Project details.
 * @return string
 */
function relationship_get_details( $p_bug_id, BugRelationshipData $p_relationship, $p_html = false, $p_html_preview = false, $p_show_project = false ) {
	$t_summary_wrap_at = utf8_strlen( config_get( 'email_separator2' ) ) - 28;

	if( $p_bug_id == $p_relationship->src_bug_id ) {
		# root bug is in the source side, related bug in the destination side
		$t_related_project_id = $p_relationship->dest_bug_id;
		$t_related_bug_id = $p_relationship->dest_bug_id;
		$t_related_project_name = project_get_name( $p_relationship->dest_project_id );
		$t_relationship_descr = relationship_get_description_src_side( $p_relationship->type );
	} else {
		# root bug is in the dest side, related bug in the source side
		$t_related_project_id = $p_relationship->src_bug_id;
		$t_related_bug_id = $p_relationship->src_bug_id;
		$t_related_project_name = project_get_name( $p_relationship->src_project_id );
		$t_relationship_descr = relationship_get_description_dest_side( $p_relationship->type );
	}

	# related bug not existing...
	if( !bug_exists( $t_related_bug_id ) ) {
		return '';
	}

	# user can access to the related bug at least as a viewer
	if( !access_has_bug_level( config_get( 'view_bug_threshold', null, null, $t_related_project_id ), $t_related_bug_id ) ) {
		return '';
	}

	if( $p_html_preview == false ) {
		$t_td = '<td>';
	} else {
		$t_td = '<td class="print">';
	}

	# get the information from the related bug and prepare the link
	$t_bug = bug_get( $t_related_bug_id, false );
	$t_status_string = get_enum_element( 'status', $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
	$t_resolution_string = get_enum_element( 'resolution', $t_bug->resolution, auth_get_current_user_id(), $t_bug->project_id );

	$t_relationship_info_html = $t_td . string_no_break( $t_relationship_descr ) . '&#160;</td>';
	if( $p_html_preview == false ) {
		# choose color based on status
		$status_label = html_get_status_css_class( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
		$t_relationship_info_html .= '<td><a href="' . string_get_bug_view_url( $t_related_bug_id ) . '">' . string_display_line( bug_format_id( $t_related_bug_id ) ) . '</a></td>';
		$t_relationship_info_html .= '<td><i class="fa fa-square fa-status-box ' . $status_label . '"></i> ';
		$t_relationship_info_html .= '<span class="issue-status" title="' . string_attribute( $t_resolution_string ) . '">' . string_display_line( $t_status_string ) . '</span></td>';
	} else {
		$t_relationship_info_html .= $t_td . string_display_line( bug_format_id( $t_related_bug_id ) ) . '</td>';
		$t_relationship_info_html .= $t_td . string_display_line( $t_status_string ) . '&#160;</td>';
	}

	$t_relationship_info_text = utf8_str_pad( $t_relationship_descr, 20 );
	$t_relationship_info_text .= utf8_str_pad( bug_format_id( $t_related_bug_id ), 8 );

	# get the handler name of the related bug
	$t_relationship_info_html .= $t_td;
	if( $t_bug->handler_id > 0 ) {
		$t_relationship_info_html .= string_no_break( prepare_user_name( $t_bug->handler_id ) );
	}
	$t_relationship_info_html .= '&#160;</td>';

	# add project name
	if( $p_show_project ) {
		$t_relationship_info_html .= $t_td . string_display_line( $t_related_project_name ) . '&#160;</td>';
	}

	# add summary
	if( $p_html == true ) {
		$t_relationship_info_html .= $t_td . string_display_line_links( $t_bug->summary );
		if( VS_PRIVATE == $t_bug->view_state ) {
			$t_relationship_info_html .= sprintf( ' <i class="fa fa-lock" alt="(%s)" title="%s" />', lang_get( 'private' ), lang_get( 'private' ) );
		}
	} else {
		if( utf8_strlen( $t_bug->summary ) <= $t_summary_wrap_at ) {
			$t_relationship_info_text .= string_email_links( $t_bug->summary );
		} else {
			$t_relationship_info_text .= utf8_substr( string_email_links( $t_bug->summary ), 0, $t_summary_wrap_at - 3 ) . '...';
		}
	}

	# add delete link if bug not read only and user has access level
	if( !bug_is_readonly( $p_bug_id ) && !current_user_is_anonymous() && ( $p_html_preview == false ) ) {
		if( access_has_bug_level( config_get( 'update_bug_threshold' ), $p_bug_id ) ) {
			$t_relationship_info_html .= ' <a class="noprint"
			href="bug_relationship_delete.php?bug_id=' . $p_bug_id . '&amp;rel_id=' . $p_relationship->id . htmlspecialchars( form_security_param( 'bug_relationship_delete' ) ) . '"><i class="ace-icon fa fa-trash-o"></i></a>';
		}
	}

	$t_relationship_info_html .= '&#160;</td>';
	$t_relationship_info_text .= "\n";
	$t_relationship_info_html = '<tr>' . $t_relationship_info_html . '</tr>';

	if( $p_html == true ) {
		return $t_relationship_info_html;
	} else {
		return $t_relationship_info_text;
	}
}

/**
 * print ALL the RELATIONSHIPS OF A SPECIFIC BUG
 * @param integer $p_bug_id A bug identifier.
 * @return string
 */
function relationship_get_summary_html( $p_bug_id ) {
	$t_summary = '';
	$t_show_project = false;

	$t_relationship_all = relationship_get_all( $p_bug_id, $t_show_project );
	$t_relationship_all_count = count( $t_relationship_all );

	# prepare the relationships table
	for( $i = 0; $i < $t_relationship_all_count; $i++ ) {
		$t_summary .= relationship_get_details( $p_bug_id, $t_relationship_all[$i], true, false, $t_show_project );
	}

	if( !is_blank( $t_summary ) ) {
		if( relationship_can_resolve_bug( $p_bug_id ) == false ) {
			$t_summary .= '<tr><td colspan="' . ( 5 + $t_show_project ) . '"><strong>' .
				lang_get( 'relationship_warning_blocking_bugs_not_resolved' ) . '</strong></td></tr>';
		}
		$t_summary = '<table class="table table-bordered table-condensed table-hover">' . $t_summary . '</table>';
	}

	return $t_summary;
}

/**
 * print ALL the RELATIONSHIPS OF A SPECIFIC BUG
 * @param integer $p_bug_id A bug identifier.
 * @return string
 */
function relationship_get_summary_html_preview( $p_bug_id ) {
	$t_summary = '';
	$t_show_project = false;

	$t_relationship_all = relationship_get_all( $p_bug_id, $t_show_project );
	$t_relationship_all_count = count( $t_relationship_all );

	# prepare the relationships table
	for( $i = 0;$i < $t_relationship_all_count;$i++ ) {
		$t_summary .= relationship_get_details( $p_bug_id, $t_relationship_all[$i], true, true, $t_show_project );
	}

	if( !is_blank( $t_summary ) ) {
		if( relationship_can_resolve_bug( $p_bug_id ) == false ) {
			$t_summary .= '<tr class="print"><td class="print" colspan=' . ( 5 + $t_show_project ) . '><strong>' . lang_get( 'relationship_warning_blocking_bugs_not_resolved' ) . '</strong></td></tr>';
		}
		$t_summary = '<table width="100%" cellpadding="0" cellspacing="1">' . $t_summary . '</table>';
	}

	return $t_summary;
}

/**
 * print ALL the RELATIONSHIPS OF A SPECIFIC BUG in text format (used by email_api.php
 * @param integer $p_bug_id A bug identifier.
 * @return string
 */
function relationship_get_summary_text( $p_bug_id ) {
	$t_summary = '';
	$t_show_project = false;

	$t_relationship_all = relationship_get_all( $p_bug_id, $t_show_project );
	$t_relationship_all_count = count( $t_relationship_all );

	# prepare the relationships table
	for( $i = 0;$i < $t_relationship_all_count;$i++ ) {
		$t_summary .= relationship_get_details( $p_bug_id, $t_relationship_all[$i], false );
	}

	return $t_summary;
}

/**
 * print HTML relationship listbox
 * @param integer $p_default_rel_type Relationship Type (default -1).
 * @param string  $p_select_name      List box name (default "rel_type").
 * @param boolean $p_include_any      Include an ANY option in list box (default false).
 * @param boolean $p_include_none     Include a NONE option in list box (default false).
 * @param string  $p_input_css        CSS classes to use with input fields
 * @return void
 */
function relationship_list_box( $p_default_rel_type = BUG_REL_ANY, $p_select_name = 'rel_type', $p_include_any = false, $p_include_none = false, $p_input_css = "input-sm" ) {
	global $g_relationships;
	?>
<select class="<?php echo $p_input_css ?>" name="<?php echo $p_select_name?>">
<?php if( $p_include_any ) {?>
<option value="<?php echo BUG_REL_ANY ?>" <?php echo( $p_default_rel_type == BUG_REL_ANY ? ' selected="selected"' : '' )?>>[<?php echo lang_get( 'any' )?>]</option>
<?php
	}

	if( $p_include_none ) {?>
<option value="<?php echo BUG_REL_NONE ?>" <?php echo( $p_default_rel_type == BUG_REL_NONE ? ' selected="selected"' : '' )?>>[<?php echo lang_get( 'none' )?>]</option>
<?php
	}

	foreach( $g_relationships as $t_type => $t_relationship ) {
		?>
<option value="<?php echo $t_type?>"<?php echo( $p_default_rel_type == $t_type ? ' selected="selected"' : '' )?>><?php echo lang_get( $t_relationship['#description'] )?></option>
<?php
	}?>
</select>
<?php
}

/**
 * print HTML relationship form
 * @param integer $p_bug_id A bug identifier.
 * @return void
 */
function relationship_view_box( $p_bug_id ) {
	$t_relationships_html = relationship_get_summary_html( $p_bug_id );
	$t_can_update = !bug_is_readonly( $p_bug_id ) &&
		access_has_bug_level( config_get( 'update_bug_threshold' ), $p_bug_id );

	if( !$t_can_update && empty( $t_relationships_html ) ) {
		return;
	}

	$t_relationship_graph = ON == config_get( 'relationship_graph_enable' );
	$t_show_top_div = $t_can_update || $t_relationship_graph;
	?>
	<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

	<?php
	$t_collapse_block = is_collapsed( 'relationships' );
	$t_block_css = $t_collapse_block ? 'collapsed' : '';
	$t_block_icon = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
	?>
	<div id="relationships" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-sitemap"></i>
			<?php echo lang_get( 'bug_relationships' ) ?>
		</h4>
		<div class="widget-toolbar">
			<a data-action="collapse" href="#">
				<i class="1 ace-icon fa <?php echo $t_block_icon ?> bigger-125"></i>
			</a>
		</div>
	</div>
	<div class="widget-body">
		<?php if( $t_show_top_div ) { ?>
		<div class="widget-toolbox padding-8 clearfix">
		<?php
			if( $t_relationship_graph ) {
		?>
		<div class="btn-group pull-right noprint">
		<span class="small"><?php print_small_button( 'bug_relationship_graph.php?bug_id=' . $p_bug_id . '&graph=relation', lang_get( 'relation_graph' ) )?></span>
		<span class="small"><?php print_small_button( 'bug_relationship_graph.php?bug_id=' . $p_bug_id . '&graph=dependency', lang_get( 'dependency_graph' ) )?></span>
		</div>
		<?php
			} # $t_relationship_graph

			if( $t_can_update ) {
			?>
		<form method="post" action="bug_relationship_add.php" class="form-inline noprint">
		<?php echo form_security_field( 'bug_relationship_add' ) ?>
		<input type="hidden" name="src_bug_id" value="<?php echo $p_bug_id?>" />
		<label class="inline"><?php echo lang_get( 'this_bug' ) ?>&#160;&#160;</label>
		<?php relationship_list_box( config_get( 'default_bug_relationship' ) )?>
		<input type="text" class="input-sm" name="dest_bug_id" value="" />
		<input type="submit" class="btn btn-primary btn-sm btn-white btn-round" name="add_relationship" value="<?php echo lang_get( 'add_new_relationship_button' )?>" />
		</form>
			<?php
			} # can update
			?>
		</div>
		<?php } # show top div ?>

		<div class="widget-main no-padding">
			<div class="table-responsive">
				<?php echo $t_relationships_html; ?>
			</div>
		</div>
	</div>
	</div>
	</div>
<?php
}
