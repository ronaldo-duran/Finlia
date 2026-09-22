@php
    $tour = $finliaTour ?? null;
@endphp
@if ($tour && $tour['payload'])
    <script type="application/json" id="finlia-tour-data">{!! json_encode([
        'start' => $tour['start'],
        'guide' => $tour['payload'],
        'urls' => [
            'seen' => route('tours.store', $tour['payload']['key']),
            'preference' => route('tours.preference'),
        ],
    ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
