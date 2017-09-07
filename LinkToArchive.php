<?php

namespace LinkToArchive;

class LinkToArchive
{
    public static function onLinkerMakeExternalLink($url, $text, &$link, array &$attribs, $linktype)
    {
        if ($linktype
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])
        ) {
            $attribs['href'] = $url;
            $archiveAttribs = [
                'rel'  => $attribs['rel'],
                'href' => 'https://web.archive.org/web/'.$url,
            ];
            if (isset($attribs['target'])) {
                $archiveAttribs['target'] = $attribs['target'];
            }
            $link = \Html::rawElement('a', $attribs, $text).' <sup>'.\Html::rawElement('a', $archiveAttribs, '['.wfMessage('archive')->parse().']').'</sup>';

            //We need to return false if we want to modify the HTML of external links
            return false;
        }
    }
}
