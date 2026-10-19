( function () {
	var data = window.storewrightShell;
	var menu = document.getElementById( 'adminmenuwrap' );
	if ( ! data || ! menu ) {
		return;
	}
	var brand = document.createElement( 'a' );
	brand.className = 'sw-shell__brand';
	brand.href = data.home;
	if ( data.logo ) {
		var logo = document.createElement( 'img' );
		logo.className = 'sw-shell__logo';
		logo.src = data.logo;
		logo.alt = data.name;
		brand.appendChild( logo );
	} else {
		var name = document.createElement( 'span' );
		name.className = 'sw-shell__name';
		name.textContent = data.name;
		brand.appendChild( name );
	}
	var role = document.createElement( 'span' );
	role.className = 'sw-shell__role';
	role.textContent = data.label;
	brand.appendChild( role );
	menu.insertBefore( brand, menu.firstChild );

	var foot = document.createElement( 'div' );
	foot.className = 'sw-shell__foot';
	var user = document.createElement( 'span' );
	user.className = 'sw-shell__user';
	user.textContent = data.user;
	var out = document.createElement( 'a' );
	out.href = data.logout;
	out.textContent = data.signOut;
	foot.appendChild( user );
	foot.appendChild( out );
	menu.appendChild( foot );
} )();
