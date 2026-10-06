/**
 * Front-end behaviour of the tournament block (Interactivity API store "doroto").
 *
 * The server renders the block from the view model and puts it into the block
 * context (context.data.view). Every change goes through POST doroto/v1/block-action,
 * which answers with a message and the fresh view model. A poll of check-update
 * reloads the view model when somebody else (another device, the app) changed the
 * tournament; typed scores live in context.ui.drafts and survive the reload.
 *
 * The QR code, the map of the settings tab and the guided tours load their libraries
 * with dynamic imports, only when they are used.
 */
import {
	store,
	getContext,
	getElement,
	getConfig,
	withScope,
} from '@wordpress/interactivity';

/**
 * REST address of a route, for both pretty and plain permalinks.
 *
 * @param {string} route  Route without the leading slash, e.g. "doroto/v1/view/5".
 * @param {Object} params Query parameters.
 * @return {string} URL.
 */
function restUrl( route, params = {} ) {
	const base = getConfig( 'doroto' ).restUrl;
	const url = base.includes( '?' )
		? base + route
		: base.replace( /\/?$/, '/' ) + route;
	const query = new URLSearchParams( params ).toString();
	if ( ! query ) {
		return url;
	}
	return url + ( url.includes( '?' ) ? '&' : '?' ) + query;
}

/**
 * Call the REST API with the website login (cookie + nonce).
 *
 * @param {string} route    Route.
 * @param {Object} [body]   JSON body; without it the request is a GET.
 * @param {Object} [params] Query parameters of a GET.
 * @return {Promise<{ok: boolean, data: Object}>} Parsed answer.
 */
async function api( route, body, params = {} ) {
	const options = {
		method: body ? 'POST' : 'GET',
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': getConfig( 'doroto' ).nonce },
	};
	if ( body ) {
		options.headers[ 'Content-Type' ] = 'application/json';
		options.body = JSON.stringify( body );
	}
	const response = await fetch(
		restUrl( route, body ? {} : { ...params, ts: Date.now() } ),
		options
	);
	let data = {};
	try {
		data = await response.json();
	} catch {
		data = {};
	}
	return { ok: response.ok, data };
}

/** @param {string} key Key of a text from doroto_block_config(). */
const t = ( key ) => getConfig( 'doroto' ).i18n?.[ key ] || '';

/**
 * Show a message in the live region of the block.
 *
 * @param {Object}  ctx   Block context.
 * @param {string}  text  Message.
 * @param {boolean} error Whether it is an error.
 */
function notify( ctx, text, error = false ) {
	ctx.ui.message = text;
	ctx.ui.error = error;
	clearTimeout( ctx.ui.messageTimer );
	if ( text ) {
		ctx.ui.messageTimer = setTimeout(
			withScope( () => {
				ctx.ui.message = '';
			} ),
			error ? 10000 : 5000
		);
	}
}

/**
 * Put a fresh view model into the block, keeping what the user is typing.
 *
 * @param {Object} ctx  Block context.
 * @param {Object} view View model from the server.
 */
function applyView( ctx, view ) {
	ctx.data.view = view;
	if ( view.settings && ! ctx.ui.settingsDirty ) {
		ctx.ui.settings = { ...view.settings };
	}
	// Drafts of matches that are no longer ongoing are not needed any more.
	const ongoing = new Set( view.ongoing.map( ( m ) => String( m.number ) ) );
	Object.keys( ctx.ui.drafts ).forEach( ( key ) => {
		if ( ! ongoing.has( key ) ) {
			delete ctx.ui.drafts[ key ];
		}
	} );
	ctx.ui.skip = ctx.ui.skip.filter( ( n ) => ongoing.has( String( n ) ) );
}

/**
 * This page showing the given tournament (0 = without tournament_id).
 *
 * @param {number} tournamentId Tournament ID.
 * @return {string} URL.
 */
function pageUrl( tournamentId ) {
	const url = new URL( window.location.href );
	url.hash = '';
	if ( tournamentId ) {
		url.searchParams.set( 'tournament_id', tournamentId );
	} else {
		url.searchParams.delete( 'tournament_id' );
	}
	return url.toString();
}

/**
 * Element of the block (the root with data-wp-interactive).
 *
 * @param {HTMLElement} el Any element inside the block.
 * @return {HTMLElement} Root of the block.
 */
const blockOf = ( el ) => el.closest( '.doroto-block' );

// Only one tour runs at a time, and a tour asked for in the address starts only once.
let tourRunning = false;
let tourFromAddressDone = false;

/**
 * Load the steps of a tour and run it (help.js is loaded on demand).
 *
 * @param {Object}      ctx   Block context.
 * @param {HTMLElement} block Block root.
 * @param {string}      tour  "page" or "example".
 */
async function startTourIn( ctx, block, tour ) {
	if ( tourRunning ) {
		return;
	}
	tourRunning = true;
	try {
		const [ { runTour }, { ok, data } ] = await Promise.all( [
			import( /* webpackChunkName: "help" */ './help.js' ),
			api( 'doroto/v1/help/' + ctx.data.view.id, null, { tour } ),
		] );
		if ( ok && data.steps ) {
			await runTour( block, data.steps, {
				next: t( 'tourNext' ),
				prev: t( 'tourPrev' ),
				done: t( 'tourDone' ),
			} );
		} else {
			notify( ctx, t( 'networkError' ), true );
		}
	} catch {
		notify( ctx, t( 'networkError' ), true );
	} finally {
		tourRunning = false;
	}
}

/**
 * Map of the tournament place in the settings tab (Leaflet, loaded on demand).
 * A click or a dragged marker writes the coordinates into the settings form.
 *
 * @param {Object}      ctx       Block context.
 * @param {HTMLElement} container Map element.
 * @return {Promise<Object>} Leaflet map and marker.
 */
async function createMap( ctx, container ) {
	const css = getConfig( 'doroto' ).leafletCss;
	if ( css && ! document.querySelector( 'link[data-doroto-leaflet]' ) ) {
		const link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = css;
		link.dataset.dorotoLeaflet = '1';
		document.head.appendChild( link );
	}
	const L = await import( /* webpackChunkName: "leaflet" */ 'leaflet' );
	const lat = parseFloat( ctx.ui.settings.latitude ) || 0;
	const lng = parseFloat( ctx.ui.settings.longitude ) || 0;
	const known = lat !== 0 || lng !== 0;
	const center = known
		? [ lat, lng ]
		: getConfig( 'doroto' ).mapCenter || [ 50, 15 ];
	const map = L.map( container ).setView( center, known ? 15 : 6 );
	L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap contributors',
	} ).addTo( map );
	// A CSS marker: the default marker needs images that the plugin does not ship.
	const icon = L.divIcon( {
		className: 'doroto-map__pin',
		iconSize: [ 22, 22 ],
		iconAnchor: [ 11, 22 ],
	} );
	const marker = L.marker( center, { draggable: true, icon } ).addTo( map );
	const save = withScope( ( latlng ) => {
		ctx.ui.settings.latitude = Math.round( latlng.lat * 1e6 ) / 1e6;
		ctx.ui.settings.longitude = Math.round( latlng.lng * 1e6 ) / 1e6;
		ctx.ui.settingsDirty = true;
	} );
	map.on( 'click', ( event ) => {
		marker.setLatLng( event.latlng );
		save( event.latlng );
	} );
	marker.on( 'dragend', () => save( marker.getLatLng() ) );
	return { map, marker };
}

const { state, actions } = store( 'doroto', {
	state: {
		get statsHasSpecial() {
			const rows = getContext().ui.stats?.rows || [];
			return rows.some( ( row ) => row.special );
		},
		get organizerNames() {
			return ( getContext().data.view.organizers || [] ).join( ', ' );
		},
		get etaText() {
			const minutes = getContext().data.view.progress.minutes_left;
			if ( ! minutes ) {
				return '';
			}
			const h = Math.floor( minutes / 60 );
			const m = minutes % 60;
			return '≈ ' + ( h ? h + ' h ' : '' ) + m + ' min';
		},
		get isTabSelected() {
			return (
				getContext().ui.tab === getElement().attributes[ 'data-tab' ]
			);
		},
		get tabIndex() {
			return state.isTabSelected ? 0 : -1;
		},
		get isPanelActive() {
			return (
				getContext().ui.tab === getElement().attributes[ 'data-tab' ]
			);
		},
		get scoreId1() {
			return 'doroto-score-' + getContext().match.number + '-1';
		},
		get scoreId2() {
			return 'doroto-score-' + getContext().match.number + '-2';
		},
		get draftScore1() {
			const ctx = getContext();
			return ctx.ui.drafts[ ctx.match.number ]?.r1 ?? '';
		},
		get draftScore2() {
			const ctx = getContext();
			return ctx.ui.drafts[ ctx.match.number ]?.r2 ?? '';
		},
		get isSkipSelected() {
			const ctx = getContext();
			return ctx.ui.skip.includes( ctx.match.number );
		},
		get isFilteredOut() {
			const ctx = getContext();
			return (
				ctx.ui.filter > 0 &&
				! ctx.match.players.includes( ctx.ui.filter )
			);
		},
		get hidePayButton() {
			const ctx = getContext();
			return ! ctx.data.view.payment_display || ctx.player.paid;
		},
		get hideUnpayButton() {
			const ctx = getContext();
			return ! ctx.data.view.payment_display || ! ctx.player.paid;
		},
		get hideRemoveButton() {
			// Only players without a match can be removed.
			return getContext().player.games > 0;
		},
		get addableUsers() {
			const ctx = getContext();
			return ( ctx.ui.candidates || [] ).filter(
				( u ) => ! u.in_tournament
			);
		},
		get adminCandidates() {
			const ctx = getContext();
			return ( ctx.ui.candidates || [] ).filter( ( u ) => ! u.organizer );
		},
		get hideRemoveAdmin() {
			const ctx = getContext();
			return ctx.user.founder || ! ctx.data.view.user.is_founder;
		},
	},

	actions: {
		/**
		 * Run a block action on the server and show its result.
		 *
		 * @param {string} action Action name of doroto_block_action_dispatch().
		 * @param {Object} args   Its arguments.
		 */
		*run( action, args = {} ) {
			const ctx = getContext();
			if ( ctx.ui.busy ) {
				return false;
			}
			ctx.ui.busy = true;
			const currentId = ctx.data.view.id;
			try {
				const { data } = yield api( 'doroto/v1/block-action', {
					tournament_id: currentId,
					action,
					args,
				} );
				if (
					data.success &&
					data.tournament_id &&
					data.tournament_id !== currentId
				) {
					// A new tournament: show it on this page.
					window.location.href = pageUrl( data.tournament_id );
					return true;
				}
				if ( data.view ) {
					applyView( ctx, data.view );
				}
				if ( data.success && data.view && ctx.ui.candidates ) {
					// The "Add a player" box is loaded: players or organizers may
					// have changed, so refresh it now. It stays open, so its
					// toggle event would not reload it.
					const fresh = yield api(
						'doroto/v1/block-candidates/' + currentId
					);
					if ( fresh.ok ) {
						ctx.ui.candidates = fresh.data.users;
						ctx.ui.organizers = fresh.data.organizers;
					}
				}
				if ( data.success && ! data.view ) {
					// The tournament was deleted: show the default one.
					notify( ctx, data.message || t( 'deleted' ) );
					window.location.href = pageUrl( 0 );
				} else {
					notify(
						ctx,
						data.message || t( 'networkError' ),
						! data.success
					);
				}
				return !! data.success;
			} catch {
				notify( ctx, t( 'networkError' ), true );
				return false;
			} finally {
				ctx.ui.busy = false;
			}
		},

		/**
		 * Ask first, then run the action (used for irreversible changes).
		 *
		 * @param {string}      text    Question.
		 * @param {string}      action  Action name.
		 * @param {Object}      args    Arguments.
		 * @param {HTMLElement} element Any element in the block.
		 */
		ask( text, action, args, element ) {
			const ctx = getContext();
			ctx.ui.confirm = { text, action, args };
			blockOf( element )
				.querySelector( '.doroto-dialog--confirm' )
				.showModal();
		},
		*confirmYes( event ) {
			const ctx = getContext();
			event.target.closest( 'dialog' ).close();
			const { action, args } = ctx.ui.confirm;
			if ( action ) {
				yield actions.run( action, args );
			}
		},
		closeDialog( event ) {
			event.target.closest( 'dialog' ).close();
		},

		// Presentation started by the button: full screen, the sections one after another.
		togglePresentation( event ) {
			const ctx = getContext();
			if ( ctx.ui.presenting ) {
				if ( document.fullscreenElement ) {
					document.exitFullscreen();
				}
				actions.stopPresentation();
				return;
			}
			const block = blockOf( event.target );
			ctx.ui.presenting = true;
			ctx.ui.presentationBase = ctx.ui.presentation;
			ctx.ui.presentation =
				getConfig( 'doroto' ).presentationSeconds || 15;
			if ( ! ctx.ui.sections.includes( ctx.ui.tab ) ) {
				ctx.ui.tab = ctx.ui.sections[ 0 ];
			}
			block.requestFullscreen?.().catch( () => {} );
		},
		stopPresentation() {
			const ctx = getContext();
			if ( ! ctx.ui.presenting ) {
				return;
			}
			ctx.ui.presenting = false;
			ctx.ui.presentation = ctx.ui.presentationBase || 0;
		},

		// Help
		openHelp( event ) {
			blockOf( event.target )
				.querySelector( '.doroto-dialog--help' )
				.showModal();
		},
		*startTour( event ) {
			const ctx = getContext();
			const block = blockOf( event.target );
			const tour = event.target.dataset.tour;
			event.target.closest( 'dialog' ).close();
			if ( tour === 'page' ) {
				yield startTourIn( ctx, block, 'page' );
				return;
			}
			const example = parseInt( tour, 10 );
			if ( example === ctx.data.view.example ) {
				yield startTourIn( ctx, block, 'example' );
				return;
			}
			// Prepare the example (and organizer rights), then open it on this page.
			ctx.ui.busy = true;
			try {
				const { data } = yield api( 'doroto/v1/help-example', {
					example,
				} );
				if ( ! data.success ) {
					notify(
						ctx,
						data.error_code === 'help_example_busy'
							? t( 'helpBusy' )
							: t( 'networkError' ),
						true
					);
					return;
				}
				const url = new URL(
					ctx.ui.fixed ? data.url : pageUrl( data.tournament_id )
				);
				url.searchParams.set( 'doroto_tour', 'example' );
				window.location.href = url.toString();
			} catch {
				notify( ctx, t( 'networkError' ), true );
			} finally {
				ctx.ui.busy = false;
			}
		},

		// Tabs
		selectTab( event ) {
			getContext().ui.tab = event.target.dataset.tab;
		},
		tabKeys( event ) {
			if (
				! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes(
					event.key
				)
			) {
				return;
			}
			const tabs = Array.from(
				event.currentTarget.querySelectorAll( '[role="tab"]' )
			);
			const index = tabs.indexOf( event.target );
			if ( index < 0 ) {
				return;
			}
			event.preventDefault();
			let next = index;
			if ( event.key === 'ArrowLeft' ) {
				next = ( index - 1 + tabs.length ) % tabs.length;
			} else if ( event.key === 'ArrowRight' ) {
				next = ( index + 1 ) % tabs.length;
			} else if ( event.key === 'Home' ) {
				next = 0;
			} else {
				next = tabs.length - 1;
			}
			getContext().ui.tab = tabs[ next ].dataset.tab;
			tabs[ next ].focus();
		},

		// Joining and leaving (the links work without JavaScript too)
		*join( event ) {
			const ctx = getContext();
			if ( ! ctx.data.view.user.logged_in ) {
				return; // follow the link: log in first, then join
			}
			event.preventDefault();
			yield actions.run( 'join' );
		},
		*leave( event ) {
			event.preventDefault();
			yield actions.run( 'leave' );
		},
		*openShare( event ) {
			const ctx = getContext();
			const dialog = blockOf( event.target ).querySelector(
				'.doroto-dialog--share'
			);
			dialog.showModal();
			if ( ctx.ui.share.qr ) {
				return;
			}
			try {
				const { default: qrcode } = yield import(
					/* webpackChunkName: "qrcode" */ 'qrcode-generator'
				);
				const qr = qrcode( 0, 'M' );
				qr.addData( ctx.data.view.links.share );
				qr.make();
				// The library writes the SVG markup itself; no user text goes into it.
				dialog.querySelector( '.doroto-qr' ).innerHTML =
					qr.createSvgTag( {
						cellSize: 6,
						margin: 2,
						scalable: true,
					} );
				ctx.ui.share.qr = true;
			} catch {
				notify( ctx, t( 'networkError' ), true );
			}
		},
		copyLink( event ) {
			const ctx = getContext();
			const link = event.target.dataset.link;
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( link ).then(
					withScope( () => notify( ctx, t( 'linkCopied' ) ) ),
					withScope( () => notify( ctx, link ) )
				);
			} else {
				notify( ctx, link );
			}
		},

		// Ongoing matches
		setDraft( event ) {
			const ctx = getContext();
			const number = ctx.match.number;
			const draft = ctx.ui.drafts[ number ] || { r1: '', r2: '' };
			draft[ event.target.dataset.side === '1' ? 'r1' : 'r2' ] =
				event.target.value;
			ctx.ui.drafts[ number ] = draft;
		},
		*enterResult() {
			const ctx = getContext();
			const number = ctx.match.number;
			const draft = ctx.ui.drafts[ number ] || {};
			if (
				draft.r1 === undefined ||
				draft.r1 === '' ||
				draft.r2 === undefined ||
				draft.r2 === ''
			) {
				notify( ctx, t( 'invalidScore' ), true );
				return;
			}
			const ok = yield actions.run( 'enter_result', {
				match: number,
				result_1: parseInt( draft.r1, 10 ),
				result_2: parseInt( draft.r2, 10 ),
			} );
			if ( ok ) {
				delete ctx.ui.drafts[ number ];
			}
		},
		toggleSkipMode() {
			const ctx = getContext();
			ctx.ui.skipMode = ! ctx.ui.skipMode;
			ctx.ui.skip = [];
		},
		toggleSkip() {
			const ctx = getContext();
			const number = ctx.match.number;
			ctx.ui.skip = ctx.ui.skip.includes( number )
				? ctx.ui.skip.filter( ( n ) => n !== number )
				: [ ...ctx.ui.skip, number ];
		},
		skipSelected( event ) {
			const ctx = getContext();
			if ( ! ctx.ui.skip.length ) {
				return;
			}
			actions.ask(
				t( 'confirmSkip' ),
				'skip_matches',
				{ matches: [ ...ctx.ui.skip ] },
				event.target
			);
			ctx.ui.skipMode = false;
		},
		*roundEnd( event ) {
			const choice = event.target.dataset.choice;
			if ( choice === 'end' ) {
				actions.ask(
					t( 'confirmEnd' ),
					'round_end',
					{ choice },
					event.target
				);
				return;
			}
			yield actions.run( 'round_end', { choice } );
		},

		// Final match
		setFinalPick( event ) {
			getContext().ui.final[ event.target.dataset.key ] =
				event.target.value;
		},
		*saveFinalFour() {
			const f = getContext().ui.final;
			yield actions.run( 'final_four', {
				l1: f.l1,
				p1: f.p1,
				l2: f.l2,
				p2: f.p2,
			} );
		},
		*saveFinalResult() {
			const f = getContext().ui.final;
			yield actions.run( 'final_result', {
				result_1: f.result_1,
				result_2: f.result_2,
			} );
		},

		// Results
		setFilter( event ) {
			getContext().ui.filter = parseInt( event.target.value, 10 ) || 0;
		},
		openEdit( event ) {
			const ctx = getContext();
			ctx.ui.edit = {
				number: ctx.match.number,
				sides: [ ...ctx.match.sides ],
				result_1: ctx.match.result_1,
				result_2: ctx.match.result_2,
			};
			blockOf( event.target )
				.querySelector( '.doroto-dialog--edit' )
				.showModal();
		},
		setEdit( event ) {
			getContext().ui.edit[ event.target.dataset.key ] =
				event.target.value;
		},
		*saveEdit( event ) {
			const ctx = getContext();
			const e = ctx.ui.edit;
			yield actions.run( 'change_result', {
				match: e.number,
				result_1: parseInt( e.result_1, 10 ) || 0,
				result_2: parseInt( e.result_2, 10 ) || 0,
			} );
			event.target.closest( 'dialog' )?.close();
		},

		// Players
		*setActive( event ) {
			const ctx = getContext();
			event.target.closest( 'details' ).open = false;
			yield actions.run( 'set_active', {
				player: ctx.player.id,
				active: event.target.dataset.active === '1',
			} );
		},
		*setPaid( event ) {
			const ctx = getContext();
			event.target.closest( 'details' ).open = false;
			yield actions.run( 'payment', {
				player: ctx.player.id,
				paid: event.target.dataset.paid === '1',
			} );
		},
		*setSpecial( event ) {
			const ctx = getContext();
			event.target.closest( 'details' ).open = false;
			yield actions.run( 'special_group', {
				player: ctx.player.id,
				add: event.target.dataset.add === '1',
			} );
		},
		removePlayer( event ) {
			const ctx = getContext();
			event.target.closest( 'details' ).open = false;
			actions.ask(
				t( 'confirmRemovePlayer' ),
				'remove_player',
				{ player: ctx.player.id },
				event.target
			);
		},
		*loadCandidates( event ) {
			if ( ! event.target.open ) {
				return;
			}
			const ctx = getContext();
			const { ok, data } = yield api(
				'doroto/v1/block-candidates/' + ctx.data.view.id
			);
			if ( ok ) {
				ctx.ui.candidates = data.users;
				ctx.ui.organizers = data.organizers;
			}
		},
		setUi( event ) {
			getContext().ui[ event.target.dataset.key ] =
				parseInt( event.target.value, 10 ) || 0;
		},
		*addPlayer() {
			const ctx = getContext();
			if ( ! ctx.ui.addPlayer ) {
				return;
			}
			const ok = yield actions.run( 'add_player', {
				player: ctx.ui.addPlayer,
			} );
			if ( ok ) {
				ctx.ui.addPlayer = 0;
			}
		},
		setNewPlayer( event ) {
			getContext().ui.newPlayer[ event.target.dataset.key ] =
				event.target.value;
		},
		*createPlayer() {
			const ctx = getContext();
			const ok = yield actions.run( 'create_player', {
				...ctx.ui.newPlayer,
			} );
			if ( ok ) {
				ctx.ui.newPlayer = { first_name: '', last_name: '', email: '' };
			}
		},

		// Statistics
		*loadStats( event ) {
			const ctx = getContext();
			const player = parseInt( event.target.value, 10 ) || 0;
			ctx.ui.statsPlayer = player;
			if ( ! player ) {
				ctx.ui.stats = null;
				return;
			}
			const { ok, data } = yield api(
				'doroto/v1/view/' + ctx.data.view.id + '/player/' + player
			);
			ctx.ui.stats = ok ? data : null;
		},

		// Settings
		setSetting( event ) {
			const ctx = getContext();
			ctx.ui.settings[ event.target.dataset.key ] = event.target.value;
			ctx.ui.settingsDirty = true;
		},
		toggleNewPost( event ) {
			getContext().ui.newPost = event.target.checked;
		},
		*saveSettings( event ) {
			event.preventDefault();
			const ctx = getContext();
			const settings = { ...ctx.ui.settings };
			if ( ctx.ui.newPost ) {
				settings.new_post = 1;
			}
			ctx.ui.settingsDirty = false;
			const ok = yield actions.run( 'save_settings', { settings } );
			if ( ok ) {
				ctx.ui.newPost = false;
			} else {
				ctx.ui.settingsDirty = true;
			}
		},
		*toggleRegistration() {
			yield actions.run( 'toggle_registration' );
		},
		toggleTournament( event ) {
			const ctx = getContext();
			if ( ctx.data.view.flags.closed ) {
				return actions.run( 'toggle_tournament' );
			}
			actions.ask(
				t( 'confirmEnd' ),
				'toggle_tournament',
				{},
				event.target
			);
		},
		emptyTournament( event ) {
			actions.ask(
				t( 'confirmEmpty' ),
				'save_settings',
				{ settings: { empty_tournament: 1 } },
				event.target
			);
		},
		deleteTournament( event ) {
			actions.ask(
				t( 'confirmDelete' ),
				'save_settings',
				{ settings: { delete_tournament: 1 } },
				event.target
			);
		},
		*addAdmin() {
			const ctx = getContext();
			if ( ! ctx.ui.addAdmin ) {
				return;
			}
			const ok = yield actions.run( 'add_admin', {
				player: ctx.ui.addAdmin,
			} );
			if ( ok ) {
				ctx.ui.addAdmin = 0;
			}
		},
		removeAdmin( event ) {
			const ctx = getContext();
			actions.ask(
				t( 'confirmRemoveAdmin' ),
				'remove_admin',
				{ player: ctx.user.id },
				event.target
			);
		},
		*addTournament() {
			yield actions.run( 'add_tournament', {
				type: getContext().ui.newType,
			} );
		},

		/**
		 * Reload the view model when the tournament changed on the server.
		 * Skipped while an action runs or a dialog is open.
		 */
		*poll() {
			const ctx = getContext();
			const { ref } = getElement();
			if (
				ctx.ui.busy ||
				document.hidden ||
				ref.querySelector( 'dialog[open]' )
			) {
				return;
			}
			const check = yield api(
				'doroto/v1/check-update/' + ctx.data.view.id
			);
			// check-update answers with the plain last_update number.
			const serverUpdate = parseInt( check.data, 10 ) || 0;
			if ( ! check.ok || serverUpdate <= ctx.data.view.last_update ) {
				return;
			}
			const { ok, data } = yield api(
				'doroto/v1/view/' + ctx.data.view.id
			);
			if ( ok && data && data.id ) {
				applyView( ctx, data );
			}
		},
	},

	callbacks: {
		/**
		 * Map of the settings tab: created the first time the tab is shown, then the
		 * marker follows the coordinates typed into the fields.
		 */
		watchMap() {
			const ctx = getContext();
			if ( ! ctx.ui.ready || ctx.ui.tab !== 'settings' ) {
				return;
			}
			const { ref } = getElement();
			const lat = parseFloat( ctx.ui.settings.latitude ) || 0;
			const lng = parseFloat( ctx.ui.settings.longitude ) || 0;
			if ( ref.dorotoMap ) {
				ref.dorotoMap.then( ( { map, marker } ) => {
					map.invalidateSize();
					if ( lat !== 0 || lng !== 0 ) {
						marker.setLatLng( [ lat, lng ] );
					}
				} );
				return;
			}
			ref.dorotoMap = createMap( ctx, ref );
			ref.dorotoMap.catch( () => {
				ref.hidden = true;
			} );
		},

		/**
		 * The invitation is HTML (wp_kses on the server), which data-wp-text cannot
		 * show; keep it in sync after the organizer edits it in the settings.
		 */
		syncInvitation() {
			const html = getContext().data.view.invitation || '';
			const { ref } = getElement();
			if ( ref.innerHTML !== html ) {
				ref.innerHTML = html;
			}
		},

		init() {
			const ctx = getContext();
			ctx.ui.ready = true;
			const seconds = getConfig( 'doroto' ).pollSeconds || 30;
			let running = false;
			// The poll must not overlap itself on a slow connection.
			const tick = withScope( async () => {
				if ( running ) {
					return;
				}
				running = true;
				try {
					await actions.poll();
				} catch {
					// Network errors are retried on the next tick.
				} finally {
					running = false;
				}
			} );
			const timer = setInterval( tick, seconds * 1000 );

			// Presentation (block attribute or the button): show the sections one after another.
			let shown = 0;
			const rotation = setInterval(
				withScope( () => {
					if (
						ctx.ui.presentation <= 0 ||
						ctx.ui.sections.length < 2
					) {
						shown = 0;
						return;
					}
					shown++;
					if ( shown < ctx.ui.presentation ) {
						return;
					}
					shown = 0;
					const index = ctx.ui.sections.indexOf( ctx.ui.tab );
					ctx.ui.tab =
						ctx.ui.sections[
							( index + 1 ) % ctx.ui.sections.length
						];
				} ),
				1000
			);
			const { ref } = getElement();
			const onFullscreen = withScope( () => {
				if ( document.fullscreenElement !== ref ) {
					actions.stopPresentation();
				}
			} );
			document.addEventListener( 'fullscreenchange', onFullscreen );

			// A tour asked for in the address (the help menu opened an example tournament).
			const params = new URLSearchParams( window.location.search );
			const tour = params.get( 'doroto_tour' );
			if ( tour && ! tourFromAddressDone ) {
				tourFromAddressDone = true;
				params.delete( 'doroto_tour' );
				const url = new URL( window.location.href );
				url.search = params.toString();
				window.history.replaceState( null, '', url.toString() );
				setTimeout(
					withScope( () =>
						startTourIn(
							ctx,
							ref,
							tour === 'example' ? 'example' : 'page'
						)
					),
					300
				);
			}

			return () => {
				clearInterval( timer );
				clearInterval( rotation );
				document.removeEventListener(
					'fullscreenchange',
					onFullscreen
				);
			};
		},
	},
} );
