( function () {
	'use strict';

	/**
	 * Add archive links to dynamically loaded content
	 */
	function addArchiveLinksToNewContent() {
		// Use MutationObserver to detect when new content is added to the page
		const observer = new MutationObserver( ( mutations ) => {
			mutations.forEach( ( mutation ) => {
				if ( mutation.addedNodes && mutation.addedNodes.length > 0 ) {
					// Check for new external links in the added content
					mutation.addedNodes.forEach( ( node ) => {
						if ( node.nodeType === Node.ELEMENT_NODE ) {
							const newLinks = node.querySelectorAll( '.external:not(.archive-processed)' );
							processExternalLinks( newLinks );
						}
					} );
				}
			} );
		} );

		// Start observing the document body for changes
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	/**
	 * Process external links to add archive.today and web.archive.org links if they don't exist
	 *
	 * @param {NodeList} links - The external links to process
	 */
	function processExternalLinks( links ) {
		links.forEach( ( link ) => {
			// Skip if already processed or is a special link
			if ( link.classList.contains( 'archive-processed' ) ||
				link.classList.contains( 'image' ) ) {
				return;
			}

			const href = link.getAttribute( 'href' );
			if ( !href ||
				href.startsWith( 'mailto:' ) ||
				href.startsWith( 'tel:' ) ||
				href.includes( 'action=edit' ) ||
				href.startsWith( 'https://web.archive.org/web/' ) ||
				href.match( /^https:\/\/archive\.(today|fo|is|li|md|ph|vn)/ ) ||
				href.match( /^https?:\/\/[^\.\/]+\.?[^\.\/]+\.onion(\/|$)/ ) ) {
				// Skip special links, already archived links, or onion links
				return;
			}

			// Mark as processed
			link.classList.add( 'archive-processed' );

			// Check if PHP-generated archive links are already present
			const nextSibling = link.nextSibling;
			if ( nextSibling &&
				( ( nextSibling.nodeType === Node.ELEMENT_NODE &&
						nextSibling.classList.contains( 'mw-archive-link' ) ) ||
					( nextSibling.nodeType === Node.TEXT_NODE &&
						nextSibling.nextSibling &&
						nextSibling.nextSibling.classList.contains( 'mw-archive-link' ) ) ) ) {
				// Archive links already exist, skip
				return;
			}

			// Add space after the link
			link.insertAdjacentText( 'afterend', ' ' );

			// Create and add the web.archive.org link
			const webArchiveLink = document.createElement( 'a' );
			webArchiveLink.href = 'https://web.archive.org/web/' + href;
			webArchiveLink.className = 'mw-archive-link';
			webArchiveLink.target = '_blank';
			webArchiveLink.rel = 'noopener noreferrer';
			webArchiveLink.textContent = '[' + mw.msg( 'linktoarchive-archive-label' ) + ']';
			webArchiveLink.title = mw.msg( 'linktoarchive-archive-link-desc' );
			link.parentNode.insertBefore( webArchiveLink, link.nextSibling.nextSibling || null );

			// Add space after the first archive link
			webArchiveLink.insertAdjacentText( 'afterend', ' ' );

			// Create and add the archive.today link
			const archiveTodayLink = document.createElement( 'a' );
			archiveTodayLink.href = 'https://archive.today/' + href;
			archiveTodayLink.className = 'mw-archive-link';
			archiveTodayLink.target = '_blank';
			archiveTodayLink.rel = 'noopener noreferrer';
			archiveTodayLink.textContent = '[' + mw.msg( 'linktoarchive-archivetoday-label' ) + ']';
			archiveTodayLink.title = mw.msg( 'linktoarchive-archivetoday-link-desc' );
			webArchiveLink.parentNode.insertBefore(
				archiveTodayLink, webArchiveLink.nextSibling.nextSibling || null
			);
		} );
	}

	// Run when document is ready
	$( () => {
		// Process any external links that might not have been processed by PHP
		const externalLinks = document.querySelectorAll( '.external:not(.archive-processed)' );
		processExternalLinks( externalLinks );

		// Set up observer for dynamically loaded content
		addArchiveLinksToNewContent();
	} );

}() );
