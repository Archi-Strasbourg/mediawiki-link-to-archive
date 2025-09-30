<?php

namespace LinkToArchive;

use BadMethodCallException;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Utils\UrlUtils;

class LinkToArchive
{
    /**
     * @param $url
     * @param $text
     * @param $link
     * @param array $attribs
     * @param $linktype
     * @return bool|null
     * @noinspection PhpUnused
     */
    public static function onLinkerMakeExternalLink($url, $text, &$link, array &$attribs, $linktype): ?bool
    {
        $urlUtils = MediaWikiServices::getInstance()->getUrlUtils();
        $urlParts = $urlUtils->parse($url);
        $rootUrlParts = ($urlUtils->parse($urlUtils->getServer(PROTO_FALLBACK)));

        if (
            $linktype
            && in_array($urlParts['scheme'], ['http', 'https'])
        ) {
            if ($urlParts['host'] == $rootUrlParts['host']) {
                // This is not really an external URL.
                return null;
            }

            // Check if the URL ends with "action=edit"
            if (str_contains($url, 'action=edit')) {
                return null;
            }
            $attribs['href'] = $url;
            $archiveAttribs = [
                'rel' => $attribs['rel'],
                'href' => 'https://web.archive.org/web/' . $url,
            ];
            if (isset($attribs['target'])) {
                $archiveAttribs['target'] = $attribs['target'];
            }

            try {
                $label = wfMessage('archive')->parse();
            } catch (BadMethodCallException) {
                $label = 'archive';
            }

            $link = Html::rawElement('a', $attribs, $text) . ' <sup>' . Html::rawElement('a', $archiveAttribs, '[' . $label . ']') . '</sup>';

            // We need to return false if we want to modify the HTML of external links
            return false;
        }

        return null;
    }
}
