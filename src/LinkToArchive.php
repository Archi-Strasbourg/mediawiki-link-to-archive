<?php
namespace LinkToArchive;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use ParserOutput;

class LinkToArchive implements OutputPageParserOutputHook {
	// Define constants for link types for clarity and to avoid magic strings.
	private const LINK_TYPE_REGULAR = 'regular';
	private const LINK_TYPE_WEB_ARCHIVE = 'web.archive';
	private const LINK_TYPE_ARCHIVE_TODAY = 'archive.today';
	private const LINK_TYPE_ONION = 'onion';

	/**
	 * Hook handler for BeforePageDisplay.
	 * Adds the necessary modules to the output page.
	 *
	 * @param OutputPage $out The output page object.
	 */
	public static function onBeforePageDisplay( OutputPage $out ): void {
		$out->addModules( [ 'ext.linkToArchive' ] );
		$out->addModuleStyles( [ 'ext.linkToArchive.styles' ] );
	}

	/**
	 * Hook handler for OutputPageParserOutput.
	 * This is the main entry point. It parses the generated HTML to find and modify external links.
	 * This approach is more performant and reliable than the LinkerMakeExternalLink hook
	 * as it runs once per page when the i18n system is fully initialized.
	 *
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		$html = $parserOutput->getRawText();

		// Avoid processing empty content or content that is not HTML.
		if ( !$html || !str_contains($html, '<')) {
			return;
		}

		$dom = new DOMDocument();
		// Suppress warnings from invalid HTML, which can be present in wikitext.
		// Use LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD to prevent DOMDocument
		// from adding implicit <html> and <body> tags, which would break the page structure.
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xpath = new DOMXPath( $dom );

		// Find all external links that are not part of an image map or already processed.
		$links = $xpath->query( '//a[contains(concat(" ", normalize-space(@class), " "), " external ")]' );

		foreach ( $links as $linkNode ) {
			$url = $linkNode->getAttribute( 'href' );

			// Skip links that are invalid, not http/https, or should otherwise be ignored.
			if ( !$url || !in_array( parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
				continue;
			}

			// Check if the link should be skipped based on custom rules.
			if ( self::shouldSkipLink( $url, $linkNode ) ) {
				continue;
			}

			$urlType = self::getUrlType( $url );

			// Create a DocumentFragment to hold the new archive links.
			$fragment = $dom->createDocumentFragment();
			// Add a leading space.
			$fragment->appendChild( $dom->createTextNode( ' ' ) );

			self::addArchiveLinkNodes( $dom, $fragment, $url, $urlType, $linkNode );

			// Insert the fragment containing the new links immediately after the original link.
			if ( $linkNode->parentNode ) {
				$linkNode->parentNode->insertBefore( $fragment, $linkNode->nextSibling );
			}
		}

		// Save the modified HTML back to the parser output.
		$parserOutput->setRawText( $dom->saveHTML() );
	}

	/**
	 * Determines if a URL or link node should be skipped.
	 *
	 * @param string $url The URL to check.
	 * @param DOMElement $node The DOM node of the link.
	 * @return bool True if the link should be skipped.
	 */
	private static function shouldSkipLink( string $url, DOMElement $node ): bool {
		// Skip internal actions like editing.
		if ( str_contains( $url, 'action=edit' ) ) {
			return true;
		}

		// Skip links that are inside an image.
		if ( $node->getElementsByTagName( 'img' )->length > 0 ) {
			return true;
		}

		// Check if archive links have already been added by this extension.
		$nextSibling = $node->nextSibling;
		if ( $nextSibling && $nextSibling->nodeType === XML_ELEMENT_NODE ) {
			$class = $nextSibling->getAttribute( 'class' );
			if ( str_contains( $class, 'mw-archive-link' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines the type of a given URL.
	 *
	 * @param string $url The URL to classify.
	 * @return string The type of the URL.
	 */
	private static function getUrlType( string $url ): string {
		if ( preg_match( '/^https?:\/\/[^\.\/]+\.?[^\.\/]+\.onion(\/|$)/', $url ) ) {
			return self::LINK_TYPE_ONION;
		}
		if ( preg_match( '/^https:\/\/web\.archive\.org\/web/', $url ) ) {
			return self::LINK_TYPE_WEB_ARCHIVE;
		}
		if ( preg_match( '/^https:\/\/archive\.(today|fo|is|li|md|ph|vn)/', $url ) ) {
			return self::LINK_TYPE_ARCHIVE_TODAY;
		}
		return self::LINK_TYPE_REGULAR;
	}

	/**
	 * Appends the appropriate archive link nodes to a DOM fragment.
	 *
	 * @param DOMDocument $dom The main DOM document.
	 * @param \DOMDocumentFragment $fragment The fragment to append links to.
	 * @param string $url The original URL being linked to.
	 * @param string $urlType The classified type of the URL.
	 * @param DOMElement $originalLinkNode The original link node to copy attributes from.
	 */
	private static function addArchiveLinkNodes( DOMDocument $dom, \DOMDocumentFragment $fragment, string $url, string $urlType, DOMElement $originalLinkNode ): void {
		$baseAttribs = [
			'rel' => $originalLinkNode->getAttribute( 'rel' ) ?: 'noopener noreferrer',
			'target' => $originalLinkNode->getAttribute( 'target' ) ?: '_blank'
		];

		switch ( $urlType ) {
			case self::LINK_TYPE_ONION:
				$fragment->appendChild( self::createArchiveLinkNode( $dom, $url, 'linktoarchive-onion-label', 'linktoarchive-onion-link', 'archive-onion', $baseAttribs ) );
				break;
			case self::LINK_TYPE_WEB_ARCHIVE:
				$fragment->appendChild( self::createArchiveLinkNode( $dom, $url, 'linktoarchive-archive-label', 'linktoarchive-archive-link', 'archive-web', $baseAttribs ) );
				break;
			case self::LINK_TYPE_ARCHIVE_TODAY:
				$fragment->appendChild( self::createArchiveLinkNode( $dom, $url, 'linktoarchive-archivetoday-label', 'linktoarchive-archivetoday-link', 'archive-today', $baseAttribs ) );
				break;
			case self::LINK_TYPE_REGULAR:
			default:
				$fragment->appendChild( self::createArchiveLinkNode( $dom, "https://web.archive.org/web/$url", 'linktoarchive-archive-label', 'linktoarchive-archive-link-desc', 'archive-web', $baseAttribs ) );
				$fragment->appendChild( $dom->createTextNode( ' ' ) );
				$fragment->appendChild( self::createArchiveLinkNode( $dom, "https://archive.today/$url", 'linktoarchive-archivetoday-label', 'linktoarchive-archivetoday-link-desc', 'archive-today', $baseAttribs ) );
				break;
		}
	}

	/**
	 * Creates a single DOM node for an archive link.
	 *
	 * @param DOMDocument $dom The main DOM document.
	 * @param string $href The URL for the link.
	 * @param string $labelKey The i18n message key for the link text.
	 * @param string $titleKey The i18n message key for the link title attribute.
	 * @param string $extraClass An additional CSS class to add to the link.
	 * @param array $baseAttribs Base attributes ('rel', 'target') for the link.
	 * @return DOMElement The generated anchor element.
	 */
	private static function createArchiveLinkNode( DOMDocument $dom, string $href, string $labelKey, string $titleKey, string $extraClass, array $baseAttribs ): DOMElement {
		$lang = MediaWikiServices::getInstance()->getContentLanguage();
		$label = '[' . wfMessage( $labelKey )->inLanguage( $lang )->escaped() . ']';
		$title = wfMessage( $titleKey )->inLanguage( $lang )->escaped();

		$a = $dom->createElement( 'a', $label );
		$a->setAttribute( 'href', $href );
		$a->setAttribute( 'title', $title );
		$a->setAttribute( 'class', 'mw-archive-link ' . $extraClass );
		$a->setAttribute( 'rel', $baseAttribs['rel'] );
		$a->setAttribute( 'target', $baseAttribs['target'] );
		return $a;
	}
}
