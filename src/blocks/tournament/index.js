/**
 * Editor side of the "Rotation tournament" block.
 * The block is rendered by PHP (render.php); the editor shows the server render
 * and lets the author pick the tournament.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
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
						value={ attributes.tournamentId }
						onChange={ ( value ) =>
							setAttributes( {
								tournamentId: parseInt( value, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: the markup comes from render.php.
	save: () => null,
} );
