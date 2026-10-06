/**
 * Guided tours of the tournament block (driver.js).
 *
 * Loaded by view.js with a dynamic import only when a tour starts. The steps come from
 * GET doroto/v1/help/<id> (doroto_help_page_steps() / doroto_help_example_steps() in
 * includes/doroto-help.php): every step names an element of the block by its data-help
 * anchor, the tab that shows it, and optionally a select to set first (filter, statistics).
 * The tour clicks the tab and sets the select like the user would, so the block's own
 * actions keep its state; when the tour ends, the tab and the selects are restored.
 */
import { driver } from 'driver.js';

/** Wait until the Interactivity API has rendered a change (two animation frames). */
const nextFrame = () =>
	new Promise( ( resolve ) =>
		window.requestAnimationFrame( () =>
			window.requestAnimationFrame( resolve )
		)
	);

/**
 * Wait until a condition is true, at most `timeout` ms.
 *
 * @param {() => boolean} condition Test.
 * @param {number}        timeout   Milliseconds.
 */
async function waitFor( condition, timeout = 3000 ) {
	const end = Date.now() + timeout;
	while ( ! condition() && Date.now() < end ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 100 ) );
	}
}

/**
 * Can the step be shown in this block? Its element must exist and must not be hidden by
 * the data (a hidden column, a missing tag). The statistics table is hidden until a player
 * is chosen, which a previous step does, so it is not tested here.
 *
 * @param {HTMLElement} block Block root.
 * @param {Object}      step  Step from the server.
 * @return {boolean} Whether to keep the step.
 */
function available( block, step ) {
	if ( step.tab && ! block.querySelector( `[data-tab="${ step.tab }"]` ) ) {
		return false;
	}
	if ( ! step.el ) {
		return true;
	}
	const el = block.querySelector( step.el );
	if ( ! el ) {
		return false;
	}
	return (
		!! el.closest( '[data-help="stats-table"]' ) ||
		! el.closest( '[hidden]' )
	);
}

/**
 * Open the tab of a step and set its select, then wait for the block to render.
 *
 * @param {HTMLElement} block Block root.
 * @param {Object}      step  Step.
 */
async function prepare( block, step ) {
	if ( step.tab ) {
		const tab = block.querySelector(
			`[role="tab"][data-tab="${ step.tab }"]`
		);
		if ( tab && tab.getAttribute( 'aria-selected' ) !== 'true' ) {
			tab.click();
		}
	}
	if ( step.select ) {
		const [ selector, value ] = step.select;
		const select = block.querySelector( selector );
		if ( select && select.value !== String( value ) ) {
			select.value = String( value );
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}
	await nextFrame();
	if ( step.el && /stats-(table|left|right|opponent|total)/.test( step.el ) ) {
		// The statistics of the chosen player come from the server.
		await waitFor( () => {
			const table = block.querySelector( '[data-help="stats-table"]' );
			return table && ! table.hidden;
		} );
		await nextFrame();
	}
}

/**
 * Run a tour.
 *
 * @param {HTMLElement} block Block root.
 * @param {Object[]}    steps Steps from the server.
 * @param {Object}      texts Button texts: next, prev, done.
 */
export async function runTour( block, steps, texts ) {
	const kept = steps.filter( ( step ) => available( block, step ) );
	if ( ! kept.length ) {
		return;
	}
	const startTab = block
		.querySelector( '[role="tab"][aria-selected="true"]' )
		?.getAttribute( 'data-tab' );
	const changedSelects = new Set();
	kept.forEach(
		( step ) => step.select && changedSelects.add( step.select[ 0 ] )
	);

	let tour = null;
	// Clicks while a step is being prepared (a tab renders, statistics load) are ignored.
	let moving = false;
	const go = async ( index ) => {
		if ( moving ) {
			return;
		}
		moving = true;
		try {
			await prepare( block, kept[ index ] );
			tour.moveTo( index );
		} finally {
			moving = false;
		}
	};

	tour = driver( {
		animate: true,
		smoothScroll: true,
		allowClose: true,
		stagePadding: 6,
		stageRadius: 6,
		popoverClass: 'doroto-tour',
		showProgress: kept.length > 1,
		progressText: '{{current}} / {{total}}',
		nextBtnText: texts.next,
		prevBtnText: texts.prev,
		doneBtnText: texts.done,
		steps: kept.map( ( step ) => ( {
			element: step.el ? () => block.querySelector( step.el ) : undefined,
			popover: {
				title: step.title,
				description: step.text,
			},
		} ) ),
		onNextClick: () => {
			const index = tour.getActiveIndex() ?? 0;
			if ( index >= kept.length - 1 ) {
				tour.destroy();
				return;
			}
			go( index + 1 );
		},
		onPrevClick: () => {
			const index = tour.getActiveIndex() ?? 0;
			if ( index > 0 ) {
				go( index - 1 );
			}
		},
		onDestroyed: () => {
			// Leave the block as the user had it.
			changedSelects.forEach( ( selector ) => {
				const select = block.querySelector( selector );
				if ( select && select.value !== '0' ) {
					select.value = '0';
					select.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
				}
			} );
			if ( startTab ) {
				block
					.querySelector( `[role="tab"][data-tab="${ startTab }"]` )
					?.click();
			}
		},
	} );

	await prepare( block, kept[ 0 ] );
	tour.drive( 0 );
}
