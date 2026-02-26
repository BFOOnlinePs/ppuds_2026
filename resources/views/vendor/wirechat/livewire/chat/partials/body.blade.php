<main
    wire:key="chat-conversation-{{ $conversation->id }}"
    x-data="chatScroll()"
    x-init="init()"
    @scroll="handleScroll"
    @update-height.window="preserveScroll"
    @scroll-bottom.window="scrollToBottom"
    x-cloak
    class="flex flex-col h-full relative gap-2 p-4 md:p-5 lg:p-8 grow overscroll-contain overflow-x-hidden overflow-y-auto w-full my-auto"
    style="contain: content"
>

    {{-- Loading spinner --}}
    <div wire:loading.delay.class.remove="invisible"
         wire:target="loadMore"
         class="invisible transition-all duration-300 flex justify-center pb-2">
        <x-wirechat::loading-spin />
    </div>

    @php $previousMessage = null; @endphp

    @if ($loadedMessages)
        @foreach ($loadedMessages as $date => $messageGroup)

            {{-- التاريخ --}}
            <div class="sticky top-0 uppercase p-2 shadow-xs px-2.5 z-50 rounded-xl border dark:border-[var(--wc-dark-primary)] border-[var(--wc-light-primary)] text-sm flex text-center justify-center bg-[var(--wc-light-secondary)] dark:bg-[var(--wc-dark-secondary)] dark:text-white w-28 mx-auto my-4">
                {{ $date }}
            </div>

            @foreach ($messageGroup as $key => $message)
                @php
                    $belongsToAuth = $message->belongsToAuth();
                    $parent = $message->parent ?? null;
                    $attachment = $message->attachment ?? null;
                    $isEmoji = $message->isEmoji();

                    if ($key > 0) {
                        $previousMessage = $messageGroup->get($key - 1);
                    }
                @endphp

                {{-- حاوية الرسالة الرئيسية - استخدام flex-row-reverse لعكس الاتجاه إذا كانت الرسالة لك --}}
                <div @class(['flex w-full gap-2 mb-2', 'flex-row-reverse' => $belongsToAuth]) wire:key="message-{{ $key }}">

                    {{-- صورة المستخدم (تظهر فقط للطرف الآخر) --}}
                    @if (!$belongsToAuth)
                        <div @class([
                            'shrink-0 self-end mb-1', // لجعل الصورة في أسفل الرسالة
                            'invisible' => $previousMessage && $message?->sendable?->is($previousMessage?->sendable)
                        ])>
                            <x-wirechat::avatar src="{{ $message->sendable?->wirechat_avatar_url ?? null }}" class="h-8 w-8 rounded-full" />
                        </div>
                    @endif

                    {{-- محتوى الرسالة --}}
                    <div @class([
                        'flex flex-col max-w-[85%] md:max-w-[75%]',
                        'items-end' => $belongsToAuth,   // محاذاة النص لليمين إذا كانت رسالتك
                        'items-start' => !$belongsToAuth, // محاذاة النص لليسار إذا كانت من الطرف الآخر
                    ])>
                        
                        {{-- النص أو الإيموجي --}}
                        @if ($isEmoji)
                            <p class="text-5xl dark:text-white">{{ $message->body }}</p>
                        @else
                            <div @class([
                                'px-4 py-2 shadow-sm break-words',
                                'bg-blue-500 text-white rounded-2xl rounded-br-none' => $belongsToAuth, // ألوان وشكل حواف رسائلك (اليمين)
                                'bg-[var(--wc-light-secondary)] dark:bg-[var(--wc-dark-secondary)] dark:text-white text-black rounded-2xl rounded-bl-none' => !$belongsToAuth, // ألوان وشكل حواف الطرف الآخر (اليسار)
                            ])>
                                {{ $message->body }}
                            </div>
                        @endif

                        {{-- المرفقات (مثال الصور) --}}
                        @if ($attachment && str()->startsWith($attachment->mime_type, 'image/'))
                            <img src="{{ $attachment->url }}" alt="attachment" class="max-h-60 rounded-xl mt-2 object-cover border dark:border-gray-700">
                        @endif

                    </div>
                </div>
            @endforeach
        @endforeach
    @endif

</main>

<script>
function chatScroll() {
    return {
        height: 0,

        init() {
            this.$nextTick(() => {
                this.height = this.$el.scrollHeight;
                this.$el.scrollTop = this.height;
            });
        },

        handleScroll() {
            if (this.$el.scrollTop <= 0 && this.$wire.canLoadMore) {
                this.height = this.$el.scrollHeight;
                this.$wire.loadMore().then(() => {
                    this.$dispatch('update-height');
                });
            }
        },

        preserveScroll() {
            this.$nextTick(() => {
                let newHeight = this.$el.scrollHeight;
                let diff = newHeight - this.height;

                this.$el.scrollTop += diff;
                this.height = newHeight;
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                this.$el.scrollTop = this.$el.scrollHeight;
                this.height = this.$el.scrollHeight;
            });
        }
    }
}
</script>