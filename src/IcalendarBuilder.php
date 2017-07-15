<?php

namespace Samwilson\MediaWikiFeeds;

use DateTime;

class IcalendarBuilder extends FeedBuilder
{

    public function getFileExtension()
    {
        return 'ical';
    }

    public function getContentType()
    {
        return 'text/calendar; charset=utf-8';
    }

    public function getFeedContents($items)
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\n\r";
        foreach ($items as $item) {
            // Only use events that have a start date.
            if (!$item['startdate']) {
                continue;
            }

            // Construct the VEVENT item.
            $ical .= "BEGIN:VEVENT\r\nUID:".$item['guid']."\r\n";
            if ($item['startdate'] instanceof DateTime) {
                $ical .= "DTSTART:".$item['startdate']->format('YmdTHis')."Z\r\n";
            }
            if ($item['enddate'] instanceof DateTime) {
                $ical .= "DTEND:".$item['enddate']->format('YmdTHis')."Z\r\n";
            }
            $ical .= "SUMMARY:".$item['title']."\r\n"
                ."DESCRIPTION:".$this->wrap($item['description'])."\r\n"
                ."URL:".$item['url']."\r\n"
                ."END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR\r\n";
        return $ical;
    }

    protected function wrap($str)
    {
        return wordwrap($str, 73, "\r\n ", true);
    }
}
