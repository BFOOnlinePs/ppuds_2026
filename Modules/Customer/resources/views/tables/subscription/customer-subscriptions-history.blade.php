@if ($subscriptions->count() > 0)
    <div class="mt-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">سجل القراءات</h3>
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">تاريخ القراءة</th>
                    <th scope="col" class="px-6 py-3">الوزن</th>
                    <th scope="col" class="px-6 py-3">الدهون</th>
                    <th scope="col" class="px-6 py-3">العضلات</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($subscriptions as $subscription)
                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <td class="px-6 py-4">{{ $subscription->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4"></td>
                        <td class="px-6 py-4"></td>
                        <td class="px-6 py-4"></td>
                        <td wire:click="removeReading({{ $subscription->id }})">
                            @svg('heroicon-s-trash', 'w-5 h-5 cursor-pointer text-danger')
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
