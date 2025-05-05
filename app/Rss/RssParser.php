<?php

namespace App\Rss;

use App\Models\Post;
use DateTime;
use DateTimeZone;
use Exception;
use SimpleXMLElement;

class RssParser
{
    /**
     * @return Post[]
     */
    public function rssDataToPosts(string $rssData): array
    {
        try {
            return $this->parseRssDataToPosts($rssData);
        } catch (Exception $exception) {
            return [];
        }
    }

    /**
     * @return Post[]
     * @throws Exception
     */
    public function parseRssDataToPosts(string $rssData): array
    {
        $rssData = trim($rssData);

        $rssXml = new SimpleXMLElement($rssData);
        $items = [];
        if (is_iterable($rssXml->channel->item ?? null)) {
            $items = iterator_to_array($rssXml->channel->item, false);
        } else if  (is_iterable($rssXml->item ?? null)) {
            $items = iterator_to_array($rssXml->item, false);
        }

        $isAtom = false;
        if (empty($items)) {
            $items = is_iterable($rssXml->entry ?? null) ? iterator_to_array($rssXml->entry, false) : [];
            $isAtom = true;
        }

        $posts = [];

        foreach ($items as $item) {
            $postData = $isAtom ? $this->getPostDataForAtomItem($item) : $this->getPostDataForRssItem($item);

            if (!$this->isValidPostData($postData)) {
                continue;
            }

            $posts[] = (new Post())->forceFill($postData);
        }

        return $posts;
    }

    protected function getPostDataForRssItem(SimpleXMLElement $item): array
    {
        $date = DateTime::createFromFormat(DateTime::RSS, $item->pubDate ?? '');
        if ($date === false) {
            $dcDate = strval($item->children('dc', true)->date ?? '');
            if ($dcDate) {
                if (!str_contains($dcDate, 'T')) {
                    $dcDate .= 'T00:00:00Z';
                }
                $date = DateTime::createFromFormat(DateTime::ATOM, $dcDate);
            }
        }

        $item = [
            'title' => mb_substr(strval($item->title ?? ''), 0, 250),
            'description' => $this->formatDescription(strval($item->description) ?: ''),
            'url' => strval($item->link ?? ''),
            'guid' => strval($item->guid ?? ''),
            'published_at' => $date ? $date->getTimestamp() : 0,
        ];

        if (empty($item['guid'])) {
            $item['guid'] = $item['url'];
        }

        return $item;
    }

    protected function formatDescription(string $description): string
    {
        $decoded = trim(html_entity_decode(strip_tags($description)));
        $decoded = preg_replace('/\s+/', ' ', $decoded);

        if (strlen($decoded) > 200) {
            return mb_substr($decoded, 0, 200) . '...';
        }

        return $decoded;
    }

    protected function getPostDataForAtomItem(SimpleXMLElement $item): array
    {
        $date = new DateTime(strval($item->published ?? $item->updated ?? ''));
        return [
            'title' => html_entity_decode(mb_substr(strval($item->title ?? ''), 0, 250)),
            'description' => $this->formatDescription(strval($item->summary) ?: strval($item->content) ?: ''),
            'url' => $item->link ? strval($item->link->attributes()['href']) : '',
            'guid' => strval($item->id ?? ''),
            'published_at' => $date ? $date->getTimestamp() : 0,
        ];
    }

    /**
     * @param array{title: string, description: string, url: string, published_at: int} $item
     */
    protected function isValidPostData(array $item): bool
    {
        if (empty($item['title']) || empty($item['url']) || empty($item['guid'])) {
            return false;
        }

        if (!str_starts_with($item['url'], 'https://') && !str_starts_with($item['url'], 'http://')) {
            return false;
        }

        if ($item['published_at'] <= 1000) {
            return false;
        }

        return true;
    }
}
