/**
 * Editor side of the "Rotation tournaments list" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	RangeControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'List', 'doubles-rotation-tournament' ) }
				>
					<TextControl
						__nextHasNoMarginBottom
						type="number"
						min={ 0 }
						label={ __(
							'ID of the page that shows a tournament',
							'doubles-rotation-tournament'
						) }
						help={ __(
							'0 = this page when it contains the tournament block, otherwise the main tournament page.',
							'doubles-rotation-tournament'
						) }
						value={ attributes.targetPage }
						onChange={ ( value ) =>
							setAttributes( {
								targetPage: parseInt( value, 10 ) || 0,
							} )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __(
							'Tournaments per page',
							'doubles-rotation-tournament'
						) }
						min={ 1 }
						max={ 50 }
						value={ attributes.perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value || 10 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Offer creating a tournament',
							'doubles-rotation-tournament'
						) }
						checked={ attributes.showCreate }
						onChange={ ( value ) =>
							setAttributes( { showCreate: value } )
						}
					/>
				</PanelBody>
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
	save: () => null,
} );
