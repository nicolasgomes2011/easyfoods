@props(['status'])

@php
$statusColors = [
    'yellow' => 'bg-yellow-400/10 text-yellow-400',
    'blue'   => 'bg-blue-400/10 text-blue-400',
    'orange' => 'bg-orange-400/10 text-orange-400',
    'purple' => 'bg-purple-400/10 text-purple-400',
    'indigo' => 'bg-indigo-400/10 text-indigo-400',
    'teal'   => 'bg-teal-400/10 text-teal-400',
    'green'  => 'bg-green-400/10 text-green-400',
    'red'    => 'bg-red-400/10 text-red-400',
    'gray'   => 'bg-zinc-700 text-zinc-400',
];
$badgeClass = $statusColors[$status->color()] ?? 'bg-zinc-700 text-zinc-400';
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
    {{ $status->label() }}
</span>
