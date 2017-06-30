<?php

namespace Samwilson\MediaWikiFeeds;

use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Item;
use Suin\RSSWriter\Feed;

class RssBuilder extends FeedBuilder
{
    
    public function getFileExtension()
    {
        return 'rss';
    }

    public function getContentType()
    {
        return 'application/rss+xml; charset=utf-8';
    }

    protected function getFeedContents($items)
    {
        // Select only the most recent top N items.
        krsort($items);
        $items = array_slice($items, 0, $this->numItems);

        // Build the channel.
        $channel = new Channel();
        $channel->title($this->title);
        $channel->url($this->scriptUrl.'index.php?title='.urlencode($this->category));
        foreach ($items as $info) {
            $item = new Item();
            $item->title($info['title'])
                ->description($info['description'])
                ->contentEncoded($info['content'])
                ->url($info['url'])
                ->author(join(', ', $info['authors']))
                ->pubDate($info['pubdate']->format('r'))
                ->guid($info['guid'], true)
                ->appendTo($channel);
            continue;
        }
        $feed = new Feed();
        $feed->addChannel($channel);

        // Return the XML.
        return $feed->render();
    }
}
