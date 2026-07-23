@props([
    'name',
    'label'      => null,
    'required'   => false,
    'hint'       => null,
    'disabled'   => false,
    'searchable' => true,
    'placeholder'=> '- เลือก -',
])

@php
    $hasError = $errors->has($name);
    $uid      = 'sel_' . $name . '_' . Str::random(4);
    $ring     = $hasError
        ? 'border-red-400 bg-red-50'
        : 'border-gray-300';
@endphp

<div>
    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    @if($searchable)
        {{-- Searchable custom dropdown --}}
        <div class="relative" id="{{ $uid }}_wrap" x-data="selectSearch('{{ $uid }}')">
            <input type="hidden" name="{{ $name }}" id="{{ $uid }}" :value="selected">

            {{-- Trigger button --}}
            <button type="button"
                    @click="toggle()"
                    @keydown.escape="close()"
                    class="w-full flex items-center justify-between px-4 py-2.5 border {{ $ring }} rounded-xl text-sm bg-white transition-all focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 text-left
                           {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                    :class="open ? 'border-brand-500 ring-2 ring-brand-500/20' : ''"
                    @disabled($disabled)>
                <span :class="selected ? 'text-gray-800' : 'text-gray-400'" x-text="selectedLabel || placeholder"></span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown panel --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 @click.outside="close()"
                 class="absolute z-50 w-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                 style="display:none">

                {{-- Search box --}}
                <div class="p-2 border-b border-gray-100">
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               x-model="search"
                               placeholder="ค้นหา..."
                               class="flex-1 bg-transparent text-sm focus:outline-none text-gray-700 placeholder-gray-400"
                               @keydown.escape="close()"
                               x-ref="searchInput">
                        <button type="button" x-show="search" @click="search=''" class="text-gray-300 hover:text-gray-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Options list --}}
                <ul class="max-h-52 overflow-y-auto py-1">
                    <template x-for="opt in filteredOptions" :key="opt.value">
                        <li>
                            <button type="button"
                                    @click="select(opt)"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors flex items-center justify-between gap-2"
                                    :class="opt.value === selected
                                        ? 'bg-brand-50 text-brand-700 font-medium'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <span x-text="opt.label"></span>
                                <svg x-show="opt.value === selected" class="w-4 h-4 text-brand-600 flex-shrink-0"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </li>
                    </template>
                    <li x-show="filteredOptions.length === 0"
                        class="px-4 py-6 text-sm text-gray-400 text-center">ไม่พบรายการที่ค้นหา</li>
                </ul>
            </div>

            {{-- Hidden native select for options data --}}
            <select id="{{ $uid }}_native" class="hidden" data-search-target="{{ $uid }}">
                {{ $slot }}
            </select>
        </div>

    @else
        {{-- Native select with styled wrapper --}}
        <div class="relative"
             x-data="{ sv: '' }"
             x-init="sv = $el.querySelector('select').value"
             @change.capture="sv = $event.target.value">
            <select
                name="{{ $name }}"
                id="{{ $uid }}"
                @disabled($disabled)
                :style="sv === '' ? 'color: var(--color-gray-400)' : ''"
                {{ $attributes->merge(['class' =>
                    'w-full pl-4 pr-10 py-2.5 border ' . $ring . ' rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white transition-all appearance-none text-gray-800'
                    . ($disabled ? ' opacity-50 cursor-not-allowed' : ' cursor-pointer')
                ]) }}>
                {{ $slot }}
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </div>
    @endif

    @if($hint && !$hasError)
        <p class="text-gray-400 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $hint }}
        </p>
    @endif

    @error($name)
        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
