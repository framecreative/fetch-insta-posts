
<div class="wrap">

	<h1>Instagram Settings</h1>

	<h2 class="title">Account</h2>

	<?php if ( !$this->instagram ) : ?>

		<p>Client settings must be set before the account can be set</p>

	<?php elseif ( $this->account ) : ?>

		<p>
			Current Account: <strong><?php echo $this->account->full_name ?> (@<?php echo $this->account->username ?>)</strong>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo $this->instagram->getLoginUrl() ?>">Replace Account</a> 
			<a href="<?php echo add_query_arg( 'remove_insta_account', true, $this->settingsPage ) ?>" class="button">Remove Account</a>
		</p>

	<?php else : ?>

		<p>There is currently no account set</p>
		<p><a class="button button-primary" href="<?php echo $this->instagram->getLoginUrl() ?>">Set Account</a></p>

	<?php endif ?>

	<p><br></p>

	<h2 class="title">Fetched Posts</h2>

	<?php if ( $fetched->found_posts == 0 ) : ?>

		<p>You have not fetched any posts</p>

	<?php else : ?>

		<p>You have <?php echo $fetched->found_posts ?> fetched posts</p>

	<?php endif ?>

	<p>
		<a href="<?php echo add_query_arg( 'fetch_insta_posts', true, $this->settingsPage ) ?>" class="button button-primary" >Fetch Posts</a>
		<a class="button" href="<?php echo $this->fetchUrl ?>" target="_blank" >View feed</a>
	</p>


</div>