<?php

namespace Samwilson\MediaWikiFeeds;

use DateTime;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;

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
        $calendar = new Calendar($this->scriptUrl);
        $calendar->setName($this->title);
        foreach ($items as $item) {
            $event = new Event();
            $event->setSummary($item['title']);
            $event->setUrl($item['url']);
            $event->setUniqueId($item['guid']);
            if (!empty($item['description'])) {
                $event->setDescription($item['description']);
            }
            if ($item['startdate'] instanceof DateTime) {
                $event->setDtStart($item['startdate']);
            }
            if ($item['enddate'] instanceof DateTime) {
                $event->setDtEnd($item['enddate']);
            }
            $calendar->addComponent($event);
        }
        return $calendar->render();
    }
}
