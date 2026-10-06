/**
 * Admin page of Rotation Tournaments (wp-admin > Rotation Tournaments).
 *
 * One React page with the tabs Overview, Settings and Help. Everything it shows comes
 * from GET doroto/v1/admin-settings (includes/doroto-admin.php): the settings schema,
 * the stored values, the overview and the help texts. The settings are saved together by
 * POST doroto/v1/admin-settings; the server checks every value and answers with a message
 * per field when one is out of range.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import './admin.scss';

/**
 * Message of a failed request.
 *
 * @param {Object} error Error thrown by apiFetch.
 * @return {string} Text for the user.
 */
const errorText = ( error ) =>
	error?.message ||
	__(
		'The server could not be reached. Please try again.',
		'doubles-rotation-tournament'
	);

/**
 * Map of the club location (Leaflet, loaded on demand).
 *
 * @param {Object}                             props
 * @param {number}                             props.latitude  Latitude.
 * @param {number}                             props.longitude Longitude.
 * @param {string}                             props.css       Address of the Leaflet stylesheet.
 * @param {(lat: number, lng: number) => void} props.onChange  Called with (latitude, longitude).
 */
function LocationMap( { latitude, longitude, css, onChange } ) {
	const element = useRef( null );
	const leaflet = useRef( null );
	const change = useRef( onChange );
	change.current = onChange;

	useEffect( () => {
		let cancelled = false;
		if ( css && ! document.querySelector( 'link[data-doroto-leaflet]' ) ) {
			const link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = css;
			link.dataset.dorotoLeaflet = '1';
			document.head.appendChild( link );
		}
		import( /* webpackChunkName: "leaflet" */ 'leaflet' ).then( ( L ) => {
			if ( cancelled || ! element.current ) {
				return;
			}
			const center = [ latitude || 50, longitude || 15 ];
			const map = L.map( element.current ).setView( center, 13 );
			L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors',
			} ).addTo( map );
			const icon = L.divIcon( {
				className: 'doroto-admin-map__pin',
				iconSize: [ 22, 22 ],
				iconAnchor: [ 11, 22 ],
			} );
			const marker = L.marker( center, { draggable: true, icon } ).addTo(
				map
			);
			const report = ( latlng ) =>
				change.current(
					Math.round( latlng.lat * 1e6 ) / 1e6,
					Math.round( latlng.lng * 1e6 ) / 1e6
				);
			map.on( 'click', ( event ) => {
				marker.setLatLng( event.latlng );
				report( event.latlng );
			} );
			marker.on( 'dragend', () => report( marker.getLatLng() ) );
			leaflet.current = { map, marker };
		} );
		return () => {
			cancelled = true;
			leaflet.current?.map.remove();
			leaflet.current = null;
		};
		// The map is created once; later changes move the marker below.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		const current = leaflet.current;
		if ( ! current || ( ! latitude && ! longitude ) ) {
			return;
		}
		const position = current.marker.getLatLng();
		if ( position.lat !== latitude || position.lng !== longitude ) {
			current.marker.setLatLng( [ latitude, longitude ] );
			current.map.panTo( [ latitude, longitude ] );
		}
	}, [ latitude, longitude ] );

	return <div ref={ element } className="doroto-admin-map" />;
}

/**
 * One field of the settings schema.
 *
 * @param {Object}                                        props
 * @param {Object}                                        props.field    Field from the schema.
 * @param {Object}                                        props.values   All values.
 * @param {Object}                                        props.errors   Messages of the server, key => text.
 * @param {(key: string, value: (string|number)) => void} props.setValue Called with (key, value).
 * @param {Object}                                        props.config   Page configuration (Leaflet stylesheet).
 */
function Field( { field, values, errors, setValue, config } ) {
	const error = errors[ field.key ];
	const help = error ? (
		<span className="doroto-admin-error">{ error }</span>
	) : (
		field.help
	);

	switch ( field.type ) {
		case 'number':
			return (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					label={ field.label }
					help={ help }
					min={ field.min }
					max={ field.max }
					step={ field.step || 1 }
					value={ String( values[ field.key ] ?? '' ) }
					onChange={ ( value ) => setValue( field.key, value ) }
					className={ error ? 'has-error' : undefined }
				/>
			);
		case 'select':
			return (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ field.label }
					help={ help }
					value={ String( values[ field.key ] ?? '' ) }
					options={ field.options.map( ( option ) => ( {
						value: String( option.value ),
						label: option.label,
					} ) ) }
					onChange={ ( value ) =>
						setValue( field.key, parseInt( value, 10 ) )
					}
				/>
			);
		case 'toggle':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ field.label }
					help={ help }
					checked={ !! values[ field.key ] }
					onChange={ ( checked ) =>
						setValue( field.key, checked ? 1 : 0 )
					}
				/>
			);
		case 'text':
		case 'url':
			return (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type={ field.type === 'url' ? 'url' : 'text' }
					label={ field.label }
					help={ help }
					value={ values[ field.key ] ?? '' }
					onChange={ ( value ) => setValue( field.key, value ) }
					className={ error ? 'has-error' : undefined }
				/>
			);
		case 'info':
			return (
				<div className="doroto-admin-info">
					<p className="doroto-admin-info__label">{ field.label }</p>
					<p>
						<strong>{ field.value }</strong>
						{ field.link && (
							<>
								{ ' ' }
								<a href={ field.link }>
									{ __(
										'WordPress settings',
										'doubles-rotation-tournament'
									) }
								</a>
							</>
						) }
					</p>
					<p className="description">{ field.help }</p>
				</div>
			);
		case 'map':
			return (
				<div className="doroto-admin-info">
					<p className="doroto-admin-info__label">{ field.label }</p>
					<LocationMap
						latitude={ Number( values.latitude ) }
						longitude={ Number( values.longitude ) }
						css={ config.leafletCss }
						onChange={ ( lat, lng ) => {
							setValue( 'latitude', lat );
							setValue( 'longitude', lng );
						} }
					/>
					<p className="description">
						{ field.help } ({ values.latitude },{ ' ' }
						{ values.longitude })
					</p>
					{ ( errors.latitude || errors.longitude ) && (
						<p className="doroto-admin-error">
							{ errors.latitude || errors.longitude }
						</p>
					) }
				</div>
			);
		case 'types':
			return (
				<TypesTable
					field={ field }
					values={ values }
					errors={ errors }
					setValue={ setValue }
				/>
			);
		default:
			return null;
	}
}

/**
 * Table of the tournament types: visibility, average points per game and per hour.
 *
 * @param {Object}                                        props
 * @param {Object}                                        props.field    Field "types" of the schema.
 * @param {Object}                                        props.values   All values.
 * @param {Object}                                        props.errors   Messages of the server.
 * @param {(key: string, value: (string|number)) => void} props.setValue Called with (key, value).
 */
function TypesTable( { field, values, errors, setValue } ) {
	const numberInput = ( key, limits, label ) => (
		<>
			<input
				type="number"
				min={ limits.min }
				max={ limits.max }
				value={ String( values[ key ] ?? '' ) }
				aria-label={ label }
				aria-invalid={ errors[ key ] ? 'true' : undefined }
				onChange={ ( event ) => setValue( key, event.target.value ) }
			/>
			{ errors[ key ] && (
				<span className="doroto-admin-error">{ errors[ key ] }</span>
			) }
		</>
	);
	return (
		<div className="doroto-admin-types">
			<table className="widefat striped">
				<thead>
					<tr>
						<th scope="col">{ field.columns.name }</th>
						<th scope="col" title={ field.help.visible }>
							{ field.columns.visible }
						</th>
						<th scope="col" title={ field.help.score }>
							{ field.columns.score }
						</th>
						<th scope="col" title={ field.help.hour }>
							{ field.columns.hour }
						</th>
					</tr>
				</thead>
				<tbody>
					{ field.rows.map( ( row ) => (
						<tr key={ row.type }>
							<th scope="row">{ row.name }</th>
							<td>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ field.columns.visible }
									hideLabelFromVision
									checked={ !! values[ row.visible ] }
									onChange={ ( checked ) =>
										setValue( row.visible, checked ? 1 : 0 )
									}
								/>
							</td>
							<td>
								{ numberInput(
									row.score,
									field.score,
									row.name + ': ' + field.columns.score
								) }
							</td>
							<td>
								{ numberInput(
									row.hour,
									field.hour,
									row.name + ': ' + field.columns.hour
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<ul className="description">
				<li>{ field.help.visible }</li>
				<li>{ field.help.score }</li>
				<li>{ field.help.hour }</li>
			</ul>
		</div>
	);
}

/**
 * Settings tab: one collapsible panel per section and a save bar.
 *
 * @param {Object}                                        props
 * @param {Object[]}                                      props.schema   Sections.
 * @param {Object}                                        props.values   Values being edited.
 * @param {Object}                                        props.errors   Messages of the server.
 * @param {(key: string, value: (string|number)) => void} props.setValue Called with (key, value).
 * @param {Object}                                        props.config   Page configuration.
 */
function SettingsTab( { schema, values, errors, setValue, config } ) {
	const [ open, setOpen ] = useState( () => {
		const first = schema.some( ( s ) => s.id === config.panel )
			? config.panel
			: schema[ 0 ]?.id;
		return { [ first ]: true };
	} );

	const sectionHasError = ( section ) =>
		section.fields.some(
			( f ) =>
				errors[ f.key ] ||
				( f.type === 'map' &&
					( errors.latitude || errors.longitude ) ) ||
				( f.type === 'types' &&
					f.rows.some(
						( r ) => errors[ r.score ] || errors[ r.hour ]
					) )
		);

	// Open the sections the server found errors in; nothing closes by itself.
	useEffect( () => {
		const withErrors = schema.filter( sectionHasError );
		if ( withErrors.length ) {
			setOpen( ( current ) => {
				const next = { ...current };
				withErrors.forEach(
					( section ) => ( next[ section.id ] = true )
				);
				return next;
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ errors ] );

	return (
		<Panel>
			{ schema.map( ( section ) => (
				<PanelBody
					key={ section.id }
					title={ section.title }
					opened={ !! open[ section.id ] }
					onToggle={ () =>
						setOpen( ( current ) => ( {
							...current,
							[ section.id ]: ! current[ section.id ],
						} ) )
					}
				>
					<p className="doroto-admin-section-text">
						{ section.description }
					</p>
					<div className="doroto-admin-fields">
						{ section.fields.map( ( field ) => (
							<Field
								key={ field.key }
								field={ field }
								values={ values }
								errors={ errors }
								setValue={ setValue }
								config={ config }
							/>
						) ) }
					</div>
				</PanelBody>
			) ) }
		</Panel>
	);
}

/**
 * Overview tab.
 *
 * @param {Object}                     props
 * @param {Object}                     props.overview Data of the overview.
 * @param {(overview: Object) => void} props.update   Called with a new overview.
 */
function OverviewTab( { overview, update } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( null );

	const post = async ( path, question, done ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( question ) ) {
			return;
		}
		setBusy( path );
		setMessage( null );
		try {
			const data = await apiFetch( { path, method: 'POST' } );
			update( data.overview );
			setMessage( { status: 'success', text: done } );
		} catch ( error ) {
			setMessage( { status: 'error', text: errorText( error ) } );
		} finally {
			setBusy( '' );
		}
	};

	return (
		<div className="doroto-admin-overview">
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ overview.notices.map( ( notice, index ) => (
				<Notice key={ index } status="warning" isDismissible={ false }>
					{ notice.text }{ ' ' }
					{ notice.link && (
						<a href={ notice.link }>{ notice.linkText }</a>
					) }
				</Notice>
			) ) }

			<div className="doroto-admin-stats">
				{ overview.stats.map( ( stat ) => (
					<Card key={ stat.label } size="small">
						<CardBody>
							<span className="doroto-admin-stats__value">
								{ stat.value }
							</span>
							<span className="doroto-admin-stats__label">
								{ stat.label.replace( /:$/, '' ) }
							</span>
						</CardBody>
					</Card>
				) ) }
			</div>

			<div className="doroto-admin-cards">
				<Card>
					<CardHeader>
						<h2>
							{ __( 'Pages', 'doubles-rotation-tournament' ) }
						</h2>
					</CardHeader>
					<CardBody>
						<ul>
							{ overview.pages.map( ( page ) => (
								<li key={ page.url }>
									<a href={ page.url }>{ page.label }</a>
									{ page.edit && (
										<>
											{ ' · ' }
											<a href={ page.edit }>
												{ __(
													'Edit',
													'doubles-rotation-tournament'
												) }
											</a>
										</>
									) }
								</li>
							) ) }
						</ul>
						{ overview.mainPage.exists &&
							( overview.mainPage.usesBlocks ? (
								<p>
									{ __(
										'The main page uses the tournament blocks.',
										'doubles-rotation-tournament'
									) }
								</p>
							) : (
								<>
									<p>
										{ __(
											'The main page still uses the shortcodes. The tournament blocks show the same tournament on one page with tabs and save every change without reloading the page.',
											'doubles-rotation-tournament'
										) }
									</p>
									<Button
										variant="primary"
										isBusy={
											busy ===
											'doroto/v1/admin-convert-main-page'
										}
										disabled={ !! busy }
										onClick={ () =>
											post(
												'doroto/v1/admin-convert-main-page',
												__(
													'Replace the content of the main page with the blocks? The previous content stays in the page revisions.',
													'doubles-rotation-tournament'
												),
												__(
													'The main page now uses the blocks. The previous content is kept in the page revisions.',
													'doubles-rotation-tournament'
												)
											)
										}
									>
										{ __(
											'Convert the main page to blocks',
											'doubles-rotation-tournament'
										) }
									</Button>
								</>
							) ) }
					</CardBody>
				</Card>

				<Card>
					<CardHeader>
						<h2>
							{ __(
								'Example tournaments',
								'doubles-rotation-tournament'
							) }
						</h2>
					</CardHeader>
					<CardBody>
						<p>
							{ __(
								'The guided tours of the tournament block explain the plugin on these tournaments.',
								'doubles-rotation-tournament'
							) }
						</p>
						<ul>
							{ overview.examples.map( ( example ) => (
								<li key={ example.url }>
									<a href={ example.url }>
										{ example.label }
									</a>
								</li>
							) ) }
						</ul>
						<Button
							variant="secondary"
							isBusy={ busy === 'doroto/v1/admin-examples' }
							disabled={ !! busy }
							onClick={ () =>
								post(
									'doroto/v1/admin-examples',
									__(
										'Create the example tournaments again? Their results are drawn anew.',
										'doubles-rotation-tournament'
									),
									__(
										'The example tournaments were created again.',
										'doubles-rotation-tournament'
									)
								)
							}
						>
							{ __(
								'Create the example tournaments again',
								'doubles-rotation-tournament'
							) }
						</Button>
					</CardBody>
				</Card>

				<Card>
					<CardHeader>
						<h2>
							{ __(
								'Mobile app',
								'doubles-rotation-tournament'
							) }
						</h2>
					</CardHeader>
					<CardBody>
						<p>
							{ __(
								'Complete Your Tournament Experience with the Android App!',
								'doubles-rotation-tournament'
							) }
						</p>
						<ExternalLink href={ overview.storeUrl }>
							{ __(
								'Get it on Google Play',
								'doubles-rotation-tournament'
							) }
						</ExternalLink>
					</CardBody>
				</Card>
			</div>

			<Card>
				<CardHeader>
					<h2>
						{ __( 'Terms used:', 'doubles-rotation-tournament' ) }
					</h2>
				</CardHeader>
				<CardBody>
					{ overview.terms.map( ( term ) => (
						<p key={ term.term }>
							<strong>{ term.term }</strong> { term.text }
						</p>
					) ) }
				</CardBody>
			</Card>
		</div>
	);
}

/**
 * Help tab: the blocks, how to use them, and the shortcodes.
 *
 * @param {Object} props
 * @param {Object} props.help Help texts from the server.
 */
function HelpTab( { help } ) {
	return (
		<div className="doroto-admin-help">
			<Card>
				<CardHeader>
					<h2>{ __( 'Blocks', 'doubles-rotation-tournament' ) }</h2>
				</CardHeader>
				<CardBody>
					<dl>
						{ help.blocks.map( ( block ) => (
							<div key={ block.name }>
								<dt>{ block.name }</dt>
								<dd>{ block.text }</dd>
							</div>
						) ) }
					</dl>
					<h3>
						{ __( 'How to start', 'doubles-rotation-tournament' ) }
					</h3>
					<ol>
						{ help.steps.map( ( step ) => (
							<li key={ step }>{ step }</li>
						) ) }
					</ol>
				</CardBody>
			</Card>
			<Card>
				<CardHeader>
					<h2>
						{ __(
							'Classic editor',
							'doubles-rotation-tournament'
						) }
					</h2>
				</CardHeader>
				<CardBody>
					{ help.classic.map( ( row ) => (
						<p key={ row.code }>
							<code>{ row.code }</code>
							<br />
							{ row.text }
						</p>
					) ) }
				</CardBody>
			</Card>
			<Panel>
				<PanelBody
					title={ __(
						'Shortcodes of the pages made before version 2.0',
						'doubles-rotation-tournament'
					) }
					initialOpen={ false }
				>
					<p>
						{ __(
							'They keep working, so older pages do not need any change.',
							'doubles-rotation-tournament'
						) }
					</p>
					<table className="widefat striped">
						<tbody>
							{ help.legacy.map( ( row ) => (
								<tr key={ row.code }>
									<td>
										<code>{ row.code }</code>
									</td>
									<td>{ row.text }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</PanelBody>
			</Panel>
		</div>
	);
}

/**
 * The whole page.
 *
 * @param {Object} props
 * @param {Object} props.config Data of the root element (Leaflet stylesheet, panel).
 */
function App( { config } ) {
	const [ data, setData ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ saved, setSaved ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'doroto/v1/admin-settings' } )
			.then( ( response ) => {
				setData( response );
				setValues( response.values );
				setSaved( response.values );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: errorText( error ) } )
			);
	}, [] );

	const dirty = useMemo(
		() =>
			Object.keys( values ).some(
				( key ) => String( values[ key ] ) !== String( saved[ key ] )
			),
		[ values, saved ]
	);

	// Warn before leaving the page with unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const setValue = ( key, value ) => {
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
		setErrors( ( current ) => {
			if ( ! current[ key ] ) {
				return current;
			}
			const next = { ...current };
			delete next[ key ];
			return next;
		} );
	};

	const save = async () => {
		setSaving( true );
		setNotice( null );
		try {
			const response = await apiFetch( {
				path: 'doroto/v1/admin-settings',
				method: 'POST',
				data: { values },
			} );
			setValues( response.values );
			setSaved( response.values );
			setErrors( {} );
			setNotice( {
				status: 'success',
				text: __(
					'Settings saved successfully!',
					'doubles-rotation-tournament'
				),
			} );
		} catch ( error ) {
			if ( error?.fields ) {
				setErrors( error.fields );
				setNotice( {
					status: 'error',
					text: __(
						'Some values are not allowed. Correct the marked fields.',
						'doubles-rotation-tournament'
					),
				} );
			} else {
				setNotice( { status: 'error', text: errorText( error ) } );
			}
		} finally {
			setSaving( false );
		}
	};

	if ( ! data ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const sectionIds = data.schema.map( ( section ) => section.id );
	let initialTab = 'overview';
	if (
		sectionIds.includes( config.panel ) ||
		config.panel === 'parameters' ||
		config.panel === 'environment'
	) {
		initialTab = 'settings';
	} else if ( config.panel === 'shortcode' || config.panel === 'help' ) {
		initialTab = 'help';
	}

	return (
		<div className="doroto-admin">
			<TabPanel
				className="doroto-admin-tabs"
				initialTabName={ initialTab }
				tabs={ [
					{
						name: 'overview',
						title: __( 'Overview', 'doubles-rotation-tournament' ),
					},
					{
						name: 'settings',
						title: __( 'Settings', 'doubles-rotation-tournament' ),
					},
					{
						name: 'help',
						title: __( 'Help', 'doubles-rotation-tournament' ),
					},
				] }
			>
				{ ( tab ) => (
					<div className="doroto-admin-tab">
						{ tab.name === 'overview' && (
							<OverviewTab
								overview={ data.overview }
								update={ ( overview ) =>
									setData( { ...data, overview } )
								}
							/>
						) }
						{ tab.name === 'settings' && (
							<>
								<SettingsTab
									schema={ data.schema }
									values={ values }
									errors={ errors }
									setValue={ setValue }
									config={ {
										...config,
										panel:
											config.panel === 'parameters'
												? 'types'
												: config.panel,
									} }
								/>
								<div className="doroto-admin-savebar">
									{ notice && (
										<Notice
											status={ notice.status }
											onRemove={ () => setNotice( null ) }
										>
											{ notice.text }
										</Notice>
									) }
									<Button
										variant="primary"
										isBusy={ saving }
										disabled={ saving || ! dirty }
										onClick={ save }
									>
										{ __(
											'Save Settings',
											'doubles-rotation-tournament'
										) }
									</Button>
									<Button
										variant="tertiary"
										disabled={ saving || ! dirty }
										onClick={ () => {
											setValues( saved );
											setErrors( {} );
										} }
									>
										{ __(
											'Discard changes',
											'doubles-rotation-tournament'
										) }
									</Button>
								</div>
							</>
						) }
						{ tab.name === 'help' && (
							<HelpTab help={ data.help } />
						) }
					</div>
				) }
			</TabPanel>
		</div>
	);
}

domReady( () => {
	const root = document.getElementById( 'doroto-admin-root' );
	if ( ! root ) {
		return;
	}
	let config = {};
	try {
		config = JSON.parse( root.dataset.config || '{}' );
	} catch {
		config = {};
	}
	createRoot( root ).render( <App config={ config } /> );
} );
