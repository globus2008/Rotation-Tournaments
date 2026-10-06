/**
 * Colour settings shared by the blocks (Styles tab of the block). Empty values keep the
 * highlight colours of the theme; render.php turns the attributes into custom properties
 * (doroto_block_color_style()).
 */
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function ColorPanel( { attributes, setAttributes } ) {
	const color = ( key, label ) => ( {
		value: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value || '' } ),
		label,
	} );
	return (
		<InspectorControls group="styles">
			<PanelColorSettings
				title={ __(
					'Colours of the tournament',
					'doubles-rotation-tournament'
				) }
				colorSettings={ [
					color(
						'accentColor',
						__(
							'Accent (buttons, active tab, progress)',
							'doubles-rotation-tournament'
						)
					),
					color(
						'tabsBackground',
						__(
							'Tab bar and table headers',
							'doubles-rotation-tournament'
						)
					),
					color(
						'tabsTextColor',
						__(
							'Text of the tab bar and table headers',
							'doubles-rotation-tournament'
						)
					),
					color(
						'specialColor',
						__( 'Special group', 'doubles-rotation-tournament' )
					),
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
	);
}
