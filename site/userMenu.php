<?php

/**
 *
 * Display usermenu on top right
 *
 */

/* get username */
$ipamusername = $_SESSION['ipamusername'];
$userDetails = getActiveUserDetails ();
?>

<div class="container-fluid">

	<div class="input-group" id="searchForm">
		<form id="userMenuSearch">
		<input type="text" class="form-control searchInput input-sm" name='ip' placeholder='<?php print _('Search string'); ?>' type='text' value='<?php print $_GET['ip']; ?>'>
		</form>
		<span class="input-group-btn">
        	<button class="btn btn-default btn-sm searchSubmit" type="button"><?php print _('Search'); ?></button>
		</span>
	
	</div>

	<!-- settings -->
	<?php if(isset($userDetails['username'])) { ?>
	<a href="<?php print create_link("tools","userMenu"); ?>"><?php print _('Hi'); ?>,    <?php print $userDetails['real_name'];  ?></a><br>
	<span class="info"><?php print _('Logged in as'); ?>  <?php print "&nbsp;"._("$userDetails[role]"); ?></span><br>
	
	<!-- logout -->
	<a  href="<?php print create_link("login"); ?>"><?php print _('Logout'); ?>  <i class="fa fa-pad-left fa-sign-out"></i></a>
	<?php } ?>
</div>