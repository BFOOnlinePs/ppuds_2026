<div>
    <li>
        <div
            class="flex items-center justify-between px-4 py-2 font-semibold hover:!bg-transparent">
            <h4 class="text-lg">{{ __('Notification') }}</h4>
            <span class="badge bg-primary/80">{{ $notifications->count() }}</span>
        </div>
    </li>

    @forelse($notifications as $notification)
        <li class="dark:text-white-light/90 border-b border-[#e0e6ed] dark:border-[#1b2e4b]">
            <div class="group flex items-center px-4 py-2">

                {{-- 1. بداية الرابط: يغلف الصورة والنص --}}
                <a href="{{ $notification->data['url'] ?? '#' }}"
                   class="flex flex-1 items-center"
                   @click="toggle">

                    <div class="grid place-content-center rounded">
                        <div class="relative h-12 w-12">
                            {{-- تم تعديل مصدر الصورة ليتناسب مع Blade --}}
                            <img class="h-12 w-12 rounded-full object-cover"
                                 src="{{ data_get($notification->data, 'image') ?: $defaultNotificationImage }}"
                                 alt="image" />
                            <span class="absolute right-[6px] bottom-0 block h-2 w-2 rounded-full bg-success"></span>
                        </div>
                    </div>

                    <div class="flex flex-auto ltr:pl-3 rtl:pr-3">
                        <div class="ltr:pr-3 rtl:pl-3">
                            {{-- اسم المرسل --}}
                            <h6 class="font-semibold">{{ $notification->data['title'] ?? '' }}</h6>

                            {{-- نص الرسالة --}}
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $notification->data['message'] ?? '' }}</p>

                            {{-- عرض الوقت --}}
                            <span class="block text-xs font-normal dark:text-gray-500">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                        </div>
                    </div>
                </a>
                {{-- نهاية الرابط --}}

                {{-- 2. زر الحذف: يبقى خارج الرابط --}}
                <button type="button"
                        class="text-neutral-300 hover:text-danger ltr:ml-auto rtl:mr-auto"
                        {{-- هنا يجب استدعاء دالة Livewire للحذف وليس Alpine --}}
                        wire:click="markAsRead('{{ $notification->id }}')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <circle opacity="0.5" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="1.5" />
                        <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5"
                              stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </li>
    @empty
        <li class="p-4 text-center text-gray-500">لا توجد إشعارات</li>
    @endforelse
</div>
