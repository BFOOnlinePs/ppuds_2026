<div>
    <ol class="flex text-gray-500 font-semibold dark:text-white-dark">
        @foreach($items as $item)
            <li class="@if(!$loop->first) before:content-['/'] @endif before:px-1.5">
                @php
                    // اعتبر الصفحة الحالية هي التي url خاصتها مساوي للعنوان الحالي أو url()->current()
                    $isCurrent = empty($item['url']) || $item['url'] === url()->current();
                @endphp

                @if($isCurrent)
                    {{-- العنصر الحالي: نص فقط ولون أزرق --}}
                    <span class="text-blue-600 dark:text-blue-400 font-bold cursor-default select-none">{{ $item['title'] }}</span>
                @else
                    {{-- روابط عادية --}}
                    <a href="{{ $item['url'] }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">{{ $item['title'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</div>
