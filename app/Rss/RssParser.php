<?php

namespace App\Rss;

use App\Models\Post;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
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
        if (!in_array(strtolower($rssXml->getName()), ['rss', 'feed', 'rdf'])) {
            throw new Exception('Document is not an RSS or Atom feed');
        }
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

    protected function getPostDataForRssItem(SimpleXMLElement $rssItem): array
    {
        $publishedDate = $this->getPublishedDateFromItem($rssItem);
        $source = mb_substr(trim(html_entity_decode(strval($rssItem->source ?? ''))), 0, 250);

        $item = [
            'title' => $this->removeSourceSuffix($this->formatTitle(strval($rssItem->title ?? '')), $source),
            'source' => $source,
            'description' => $this->formatDescription(strval($rssItem->description) ?: ''),
            'url' => strval($rssItem->link ?? ''),
            'guid' => strval($rssItem->guid ?? ''),
            'published_at' =>  $publishedDate ? $publishedDate->getTimestamp() : 0,
        ];

        if (empty($item['guid'])) {
            $item['guid'] = $item['url'];
        }

        return $item;
    }

    /**
     * Remove a trailing " - <source>" from the title, as aggregators like Google News add.
     */
    protected function removeSourceSuffix(string $title, string $source): string
    {
        $suffix = " - {$source}";

        if ($source && str_ends_with($title, $suffix) && mb_strlen($title) > mb_strlen($suffix)) {
            return trim(mb_substr($title, 0, -mb_strlen($suffix)));
        }

        return $title;
    }

    /**
     * Titles are shown as plain text, but some feeds put (escaped) HTML in them,
     * so decode entities, then drop any tags and collapse the leftover whitespace.
     */
    protected function formatTitle(string $title): string
    {
        $plain = strip_tags(html_entity_decode($title));
        $plain = trim(preg_replace('/\s+/', ' ', $plain));

        return mb_substr($plain, 0, 250);
    }

    protected function getPublishedDateFromItem(SimpleXMLElement $item): ?DateTimeImmutable
    {
        // Try to parse from standard RSS format
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $item->pubDate ?? '');
        if ($date) {
            return $date;
        }

        // Try to parse via the old RSS 1.0 Dublin Core format
        $dcDate = strval($item->children('dc', true)->date ?? '');
        if ($dcDate) {
            if (!str_contains($dcDate, 'T')) {
                $dcDate .= 'T00:00:00Z';
            }
            return DateTimeImmutable::createFromFormat(DateTime::ATOM, $dcDate) ?: null;
        }

        // Otherwise, if it still looks like some kind of date, try to parse whatever we have
        $pubDate = strval($item->pubDate ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $pubDate)) {
            try {
                return new DateTimeImmutable($item->pubDate ?? '', new DateTimeZone('UTC'));
            } catch (Exception $exception) {
            }
        }

        return null;
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
        try {
            $date = new DateTime(strval($item->published ?? $item->updated ?? ''));
        } catch (Exception) {
            $date = null;
        }

        return [
            'title' => $this->formatTitle(strval($item->title ?? '')),
            'source' => '',
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
