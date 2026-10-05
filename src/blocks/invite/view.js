/**
 * Invitation block (store "doroto/invite"): copy the invitation link.
 */
import {
	store,
	getContext,
	getConfig,
	withScope,
} from '@wordpress/interactivity';

store( 'doroto/invite', {
	actions: {
		copy() {
			const ctx = getContext();
			const done = withScope( ( text ) => {
				ctx.message = text;
			} );
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( ctx.link ).then(
					() => done( getConfig( 'doroto' ).i18n.linkCopied ),
					() => done( ctx.link )
				);
			} else {
				done( ctx.link );
			}
		},
	},
} );
