<?php
$storewright_message = storewright_announcement();
if ( $storewright_message === '' ) {
	return;
}
?>
<div class="sw-announcement"><p class="sw-announcement__text"><?php echo esc_html( $storewright_message ); ?></p></div>
