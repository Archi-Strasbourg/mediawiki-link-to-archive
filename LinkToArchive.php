<?php

namespace LinkToArchive;

use Html;

class LinkToArchive
{
    /**
     * @param $url
     * @param $text
     * @param $link
     * @param array $attribs
     * @param $linktype
     * @return bool
     */
    public static function onLinkerMakeExternalLink($url, $text, &$link, array &$attribs, $linktype)
    {
        if (
            $linktype
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])
        ) {
            // Check if the URL ends with "action=edit"
            if (strpos($url, 'action=edit') !== false) {
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
            $link = Html::rawElement('a', $attribs, $text) . ' <sup>' . Html::rawElement('a', $archiveAttribs, '[' . wfMessage('archive')->parse() . ']') . '</sup>';

            // We need to return false if we want to modify the HTML of external links
            return false;
        }

        return null;
    }
}
