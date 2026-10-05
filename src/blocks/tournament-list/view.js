/**
 * Front-end behaviour of the tournament list block (store "doroto/list").
 * Filters, search and paging reload the rows from GET doroto/v1/view-list;
 * "Create" uses the block action of the tournament block and opens the new tournament.
 */
import { store, getContext, getConfig } from '@wordpress/interactivity';

/**
 * REST address of a route, for both pretty and plain permalinks.
 *
 * @param {string} route  Route without the leading slash.
 * @param {Object} params Query parameters.
 * @return {string} URL.
 */
function restUrl( route, params = {} ) {
	const base = getConfig( 'doroto' ).restUrl;
	const url = base.includes( '?' )
		? base + route
		: base.replace( /\/?$/, '/' ) + route;
	const query = new URLSearchParams( params ).toString();
	return query ? url + ( url.includes( '?' ) ? '&' : '?' ) + query : url;
}

const { actions } = store( 'doroto/list', {
	state: {
		get isClosed() {
			return getContext().item.state === 'closed';
		},
	},
	actions: {
		*load( offset = 0 ) {
			const ctx = getContext();
			ctx.busy = true;
			try {
				const response = yield fetch(
					restUrl( 'doroto/v1/view-list', {
						filter: ctx.filter,
						search: ctx.search,
						offset,
						limit: ctx.perPage,
						page: ctx.pageUrl,
					} ),
					{
						credentials: 'same-origin',
						headers: { 'X-WP-Nonce': getConfig( 'doroto' ).nonce },
					}
				);
				if ( response.ok ) {
					ctx.list = yield response.json();
					ctx.message = '';
				} else {
					ctx.message = getConfig( 'doroto' ).i18n.networkError;
				}
			} catch {
				ctx.message = getConfig( 'doroto' ).i18n.networkError;
			} finally {
				ctx.busy = false;
			}
		},
		*setFilter( event ) {
			getContext().filter = parseInt( event.target.value, 10 ) || 0;
			yield actions.load( 0 );
		},
		setSearch( event ) {
			getContext().search = event.target.value;
		},
		*search( event ) {
			event.preventDefault();
			yield actions.load( 0 );
		},
		*prev() {
			const ctx = getContext();
			yield actions.load(
				Math.max( 0, ctx.list.offset - ctx.list.limit )
			);
		},
		*next() {
			const ctx = getContext();
			yield actions.load( ctx.list.offset + ctx.list.limit );
		},
		setType( event ) {
			getContext().newType = parseInt( event.target.value, 10 ) || 0;
		},
		*create() {
			const ctx = getContext();
			ctx.busy = true;
			try {
				const response = yield fetch(
					restUrl( 'doroto/v1/block-action' ),
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': getConfig( 'doroto' ).nonce,
						},
						body: JSON.stringify( {
							action: 'add_tournament',
							args: { type: ctx.newType },
						} ),
					}
				);
				const data = yield response.json();
				if ( data.success && data.tournament_id ) {
					const url = new URL( ctx.pageUrl );
					url.searchParams.set( 'tournament_id', data.tournament_id );
					window.location.href = url.toString();
					return;
				}
				ctx.message =
					data.message || getConfig( 'doroto' ).i18n.networkError;
			} catch {
				ctx.message = getConfig( 'doroto' ).i18n.networkError;
			} finally {
				ctx.busy = false;
			}
		},
	},
} );
