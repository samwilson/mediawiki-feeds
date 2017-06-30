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
        foreach ($items as $item) {
            //echo "<pre>";print_r($item['enddate']);exit();
            $event = new Event();
            $event->setSummary($item['title']);
            $event->setDescription($item['description']);
            $event->setUrl($item['url']);
            if ($item['startdate'] instanceof DateTime) {
                $event->setDtStart($item['startdate']);
            }
            if ($item['enddate'] instanceof DateTime) {
                $event->setDtEnd($item['enddate']);
            }
            $event->setUniqueId($item['guid']);
            $calendar->addComponent($event);
        }
        return $calendar->render();
    }
}
