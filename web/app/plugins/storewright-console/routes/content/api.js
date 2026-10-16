import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const fields = [ 'id', 'title', 'link', 'status', 'modified_gmt', 'slug', 'date_gmt' ];

export const loadPages = () => apiFetch( { path: addQueryArgs( '/wp/v2/pages', { per_page: 100, status: 'publish,draft,pending,private', context: 'view', orderby: 'title', order: 'asc', _fields: fields.join( ',' ) } ) } );

export const loadEntries = () => apiFetch( { path: addQueryArgs( '/wp/v2/posts', { per_page: 20, status: 'publish,draft,pending,private', context: 'view', orderby: 'date', order: 'desc', _fields: fields.join( ',' ) } ) } );
