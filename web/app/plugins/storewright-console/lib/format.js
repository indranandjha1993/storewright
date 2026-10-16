import { consoleData } from './data';

const locale = consoleData.locale || 'en';

export const money = ( value, digits = 0 ) =>
	new Intl.NumberFormat( locale, { style: 'currency', currency: consoleData.currency || 'INR', maximumFractionDigits: digits, minimumFractionDigits: digits } ).format( Number( value ) || 0 );

export const count = ( value ) => new Intl.NumberFormat( locale ).format( Number( value ) || 0 );

export const shortDate = ( value ) => new Intl.DateTimeFormat( locale, { day: 'numeric', month: 'short' } ).format( new Date( value ) );

export const timeAgo = ( value ) => {
	if ( ! value ) {
		return null;
	}
	const minutes = Math.round( ( Date.now() - new Date( value ).getTime() ) / 60000 );
	const relative = new Intl.RelativeTimeFormat( locale, { numeric: 'auto' } );
	if ( minutes < 60 ) {
		return relative.format( -minutes, 'minute' );
	}
	if ( minutes < 60 * 48 ) {
		return relative.format( -Math.round( minutes / 60 ), 'hour' );
	}
	return relative.format( -Math.round( minutes / 1440 ), 'day' );
};

export const pad = ( n ) => String( n ).padStart( 2, '0' );

export const localDate = ( date ) => `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }`;
