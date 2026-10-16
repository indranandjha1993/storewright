<?php
/**
 * Title: Announcement
 * Slug: storewright/announcement
 * Categories: storewright
 * Description: One line above the header, from the announcement setting. Hidden when empty.
 * Inserter: no
 */

$storewright_message = storewright_announcement();
if ( $storewright_message === '' ) {
	return;
}
?>
<div class="sw-announcement"><p class="sw-announcement__text"><?php echo esc_html( $storewright_message ); ?></p></div>
