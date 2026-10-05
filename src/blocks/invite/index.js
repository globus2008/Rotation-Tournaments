/**
 * Editor side of the "Rotation tournament invitation" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
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
					title={ __( 'Invitation', 'doubles-rotation-tournament' ) }
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
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the invitation text',
							'doubles-rotation-tournament'
						) }
						checked={ attributes.showInvitation }
						onChange={ ( value ) =>
							setAttributes( { showInvitation: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the number of players',
							'doubles-rotation-tournament'
						) }
						checked={ attributes.showPlayerCount }
						onChange={ ( value ) =>
							setAttributes( { showPlayerCount: value } )
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
