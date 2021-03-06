<?php

/**
 * Script to add users to group
 *************************************************/

/* required functions */
require_once('../../functions/functions.php'); 

/* verify that user is admin */
checkAdmin();

/* get all settings */
$settings = getAllSettings();

/* filter input */
$_POST = filter_user_input($_POST, true, true, false);

/* must be numeric */
if(!is_numeric($_POST['g_id']))		{ die('<div class="alert alert-danger">'._("Invalid ID").'</div>'); }

# get group details
$group = getGroupById($_POST['g_id']);

# not in group
$missing = getUsersNotInGroup($_POST['g_id']);
?>


<!-- header -->
<div class="pHeader"><?php print _('Add users to group'); ?> <?php print $group['g_name'] ?></div>


<!-- content -->
<div class="pContent">

	<?php if(sizeof($missing) > 0) { ?>

	<form id="groupAddUsers" name="groupAddUsers">
	<table class="groupEdit table table-condensed table-hover table-top">
	
	<tr>
		<th>
			<input type="hidden" name="gid" value="<?php print $_POST['g_id']; ?>">
		</th>
		<th><?php print _('Name'); ?></th>
		<th><?php print _('Username'); ?></th>
		<th><?php print _('Email'); ?></th>
	</tr>

	<?php
	foreach($missing as $m) {
		# get details
		$u = getUserDetailsById($m);
		
		print "<tr>";
		
		print "	<td>";
		print "	<input type='checkbox' name='user$u[id]'>";
		print "	</td>";
		
		print "	<td>$u[real_name]</td>";
		print "	<td>$u[username]</td>";
		print "	<td>$u[email]</td>";
		
		print "</tr>";	
	}
	?>

    </table>
    </form>
    
    <?php } else { print "<div class='alert alert-info'>"._('No available users to add to group')."</div>"; } ?>


</div>


<!-- footer -->
<div class="pFooter">
	<div class="btn-group">
		<button class="btn btn-sm btn-default hidePopups">Cancel</button>
		<?php if(sizeof($missing) > 0) { ?>
		<button class="btn btn-sm btn-default btn-success" id="groupAddUsersSubmit"><i class="fa fa-plus"></i> Add selected users</button>
		<?php } ?>
	</div>
	
	<!-- Result -->
	<div class="groupAddUsersResult"></div>
</div>
