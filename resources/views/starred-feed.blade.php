{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title>{{ $title }}</title>
    <link>{{ url('starred') }}</link>
    <description>Starred posts</description>
    <lastBuildDate>{{ $buildDate }}</lastBuildDate>
    <atom:link href="{{ url('favorites') }}" rel="self" type="application/rss+xml"/>
@foreach($items as $item)
    <item>
        <title>{{ $item['title'] }}</title>
        <link>{{ $item['url'] }}</link>
        <guid isPermaLink="true">{{ $item['url'] }}</guid>
        <description>{{ $item['description'] }}</description>
        <pubDate>{{ $item['date'] }}</pubDate>
@if($item['category'])
        <category>{{ $item['category'] }}</category>
@endif
@if($item['image'])
        <enclosure url="{{ $item['image']['url'] }}" length="{{ $item['image']['length'] }}" type="{{ $item['image']['type'] }}"/>
        <media:content url="{{ $item['image']['url'] }}" medium="image" type="{{ $item['image']['type'] }}"/>
@endif
    </item>
@endforeach
</channel>
</rss>
