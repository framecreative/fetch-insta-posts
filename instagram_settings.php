
<div class="wrap">

<h1>Instagram Settings</h1>

<h2 class="title">Account</h2>

<?php if ( $this->token ) : ?>

	<p>
		Current Account: <strong><?php echo $this->account ? '@' . $this->account->username : 'Your account could not be accessed. Please remove and reconnect.' ?></strong>

		<?php if ( isset($_GET['debug']) ) : ?>
			<br>Token: <?php echo $this->token ?>
			<br>Token Expires: <?php echo date( 'jS F Y, g:i:s a (e)', $this->tokenExpires ) ?>
		<?php endif ?>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo $this->get_login_url() ?>">Replace Account</a>
		<a href="<?php echo add_query_arg( 'remove_insta_account', true, $this->settingsPage ) ?>" class="button">Remove Account</a>
	</p>

	<p><br></p>

	<h2 class="title">Fetched Posts</h2>

	<?php if ( $fetched->found_posts == 0 ) : ?>

		<p>You have not fetched any posts</p>

	<?php else : ?>

		<p>You have <?php echo $fetched->found_posts ?> fetched posts</p>

	<?php endif ?>

	<p>
		<a href="<?php echo add_query_arg( 'fetch_insta_posts', true, $this->settingsPage ) ?>" class="button button-primary" >Fetch Posts</a>
		<a href="<?php echo add_query_arg( ['fetch_insta_posts' => true, 'force_update' => true], $this->settingsPage ) ?>" class="button button-warning">Fetch and Force Update</a>
		<a class="button" href="<?php echo $this->get_feed_url() ?>" target="_blank" >View feed</a>
	</p>

	<p><em>* Forcing an update will result in all recent instagram being replaced.<br />Only use this if you know what you're doing!</em></p>


<?php else : ?>

	<p>There is currently no account set</p>
	<p><a class="button button-primary" href="<?php echo $this->get_login_url() ?>">Set Account</a></p>

<?php endif ?>

<style type="text/css">
	.button.button-warning {
		color: #fff;
		border-color: #d63638; 
		background-color: #d63638;
	}
	.button.button-warning:active, 
	.button.button-warning:hover {
		color: #fff;
		border-color: #d63638; 
		background-color: #a82527;
	}
</style>

</div>
