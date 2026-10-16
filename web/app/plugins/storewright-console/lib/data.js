const element = document.getElementById( 'wp-script-module-data-storewright' );

export const consoleData = element ? JSON.parse( element.textContent ) : {};

export const adminLink = ( path ) => `${ consoleData.adminUrl ?? '/wp/wp-admin/' }${ path }`;

export const consoleLink = ( path, query = '' ) => adminLink( `admin.php?page=storewright&p=${ encodeURIComponent( path ) }${ query ? `&${ query }` : '' }` );
