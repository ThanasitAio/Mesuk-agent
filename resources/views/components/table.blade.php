{{--
  x-table — Desktop table wrapper (hidden on mobile, visible md+)
  Props:
    card  (bool, default true) — wrap in white rounded-xl card
  Slots:
    $head  — <th> cells inside the <thead><tr>
    $slot  — <tr> rows inside <tbody>  (plus @empty row)
  Usage with card:
    <x-table>
      <x-slot:head><th>…</th></x-slot:head>
      @foreach … <tr>…</tr> @endforeach
    </x-table>
  Usage without card (already inside a card):
    <x-table :card="false">…</x-table>
--}}

@props(['card' => true])

@if($card)
<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @isset($head)
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>{{ $head }}</tr>
            </thead>
            @endisset
            <tbody class="divide-y divide-gray-50">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
@else
<div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm">
        @isset($head)
        <thead class="bg-gray-50">
            <tr>{{ $head }}</tr>
        </thead>
        @endisset
        <tbody class="divide-y divide-gray-50">
            {{ $slot }}
        </tbody>
    </table>
</div>
@endif
