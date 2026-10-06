/**
 * Editor side of the "Rotation tournament" block and its variations
 * (standings, matches, presentation; defined in block.json).
 * The block is rendered by PHP (render.php); the editor shows the server render
 * and lets the author pick the tournament, the sections and the presentation mode.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	CheckboxControl,
	ToggleControl,
	RangeControl,
	Disabled,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

const SECTIONS = [
	[ 'matches', __( 'Matches', 'doubles-rotation-tournament' ) ],
	[ 'results', __( 'Results', 'doubles-rotation-tournament' ) ],
	[ 'players', __( 'Players', 'doubles-rotation-tournament' ) ],
	[ 'stats', __( 'Statistics', 'doubles-rotation-tournament' ) ],
	[ 'settings', __( 'Settings', 'doubles-rotation-tournament' ) ],
];

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const {
		tournamentId,
		sections,
		presentation,
		presentationSeconds,
		accentColor,
		tabsBackground,
		tabsTextColor,
		specialColor,
	} = attributes;

	const toggleSection = ( key, checked ) => {
		const next = checked
			? SECTIONS.map( ( [ k ] ) => k ).filter(
					( k ) => k === key || sections.includes( k )
				)
			: sections.filter( ( k ) => k !== key );
		if ( next.length ) {
			setAttributes( { sections: next } );
		}
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Tournament', 'doubles-rotation-tournament' ) }
				>
					<TextControl
						__nextHasNoMarginBottom
						type="number"
						min={ 0 }
						label={ __(
							'Tournament ID',
							'doubles-rotation-tournament'
						) }
						help={ __(
							'0 = the tournament from the address (?tournament_id=) or the latest one.',
							'doubles-rotation-tournament'
						) }
						value={ tournamentId }
						onChange={ ( value ) =>
							setAttributes( {
								tournamentId: parseInt( value, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Sections', 'doubles-rotation-tournament' ) }
				>
					{ SECTIONS.map( ( [ key, label ] ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ key }
							label={ label }
							checked={ sections.includes( key ) }
							onChange={ ( checked ) =>
								toggleSection( key, checked )
							}
						/>
					) ) }
				</PanelBody>
				<PanelBody
					title={ __(
						'Presentation',
						'doubles-rotation-tournament'
					) }
					initialOpen={ presentation }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Rotate the sections (read only)',
							'doubles-rotation-tournament'
						) }
						checked={ presentation }
						onChange={ ( value ) =>
							setAttributes( { presentation: value } )
						}
					/>
					{ presentation && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __(
								'Seconds per section',
								'doubles-rotation-tournament'
							) }
							min={ 5 }
							max={ 120 }
							value={ presentationSeconds }
							onChange={ ( value ) =>
								setAttributes( {
									presentationSeconds: value || 15,
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<PanelColorSettings
					title={ __(
						'Colours of the tournament',
						'doubles-rotation-tournament'
					) }
					colorSettings={ [
						{
							value: accentColor,
							onChange: ( value ) =>
								setAttributes( { accentColor: value || '' } ),
							label: __(
								'Accent (buttons, active tab, progress)',
								'doubles-rotation-tournament'
							),
						},
						{
							value: tabsBackground,
							onChange: ( value ) =>
								setAttributes( {
									tabsBackground: value || '',
								} ),
							label: __(
								'Tab bar and table headers',
								'doubles-rotation-tournament'
							),
						},
						{
							value: tabsTextColor,
							onChange: ( value ) =>
								setAttributes( {
									tabsTextColor: value || '',
								} ),
							label: __(
								'Text of the tab bar and table headers',
								'doubles-rotation-tournament'
							),
						},
						{
							value: specialColor,
							onChange: ( value ) =>
								setAttributes( { specialColor: value || '' } ),
							label: __(
								'Special group',
								'doubles-rotation-tournament'
							),
						},
					] }
				>
					<p className="components-base-control__help">
						{ __(
							'Without a choice the block uses the highlight colours of the theme (its buttons and palette).',
							'doubles-rotation-tournament'
						) }
					</p>
				</PanelColorSettings>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</Disabled>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: the markup comes from render.php.
	save: () => null,
} );
