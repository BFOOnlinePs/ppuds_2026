@if (!empty($stats))
    <div class="mt-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">ملخص التطور</h3>
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
            <table class="w-full text-center">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-400 uppercase">
                <tr>
                    <th class="px-4 py-3">المقياس</th>
                    <th class="px-4 py-3">الوزن (كجم)</th>
                    <th class="px-4 py-3">الدهون (%)</th>
                    <th class="px-4 py-3">العضلات (%)</th>
                    <th class="px-4 py-3">الأملاح (%)</th>
                    <th class="px-4 py-3">السوائل (%)</th>
                    <th class="px-4 py-3">Bmi (%)</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <tr class="text-gray-900 dark:text-white font-semibold">
                    <td class="px-4 py-3 text-right">الزيارة الحالية (الأخيرة)</td>
                    <td>{{ $stats['weight']['current'] ?? 'N/A' }}</td>
                    <td>{{ $stats['fats']['current'] ?? 'N/A' }}</td>
                    <td>{{ $stats['muscles']['current'] ?? 'N/A' }}</td>
                    <td>{{ $stats['salts']['current'] ?? 'N/A' }}</td>
                    <td>{{ $stats['water']['current'] ?? 'N/A' }}</td>
                    <td>{{ $stats['bmi']['current'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-right">الزيارة السابقة</td>
                    <td>{{ $stats['weight']['previous'] ?? 'N/A' }}</td>
                    <td>{{ $stats['fats']['previous'] ?? 'N/A' }}</td>
                    <td>{{ $stats['muscles']['previous'] ?? 'N/A' }}</td>
                    <td>{{ $stats['salts']['previous'] ?? 'N/A' }}</td>
                    <td>{{ $stats['water']['previous'] ?? 'N/A' }}</td>
                    <td>{{ $stats['bmi']['previous'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-right">الزيارة الأولى</td>
                    <td>{{ $stats['weight']['first'] ?? 'N/A' }}</td>
                    <td>{{ $stats['fats']['first'] ?? 'N/A' }}</td>
                    <td>{{ $stats['muscles']['first'] ?? 'N/A' }}</td>
                    <td>{{ $stats['salts']['first'] ?? 'N/A' }}</td>
                    <td>{{ $stats['water']['first'] ?? 'N/A' }}</td>
                    <td>{{ $stats['bmi']['first'] ?? 'N/A' }}</td>
                </tr>
                <tr class="bg-gray-50 dark:bg-gray-900/50 font-bold">
                    <td class="px-4 py-3 text-right">الفرق الكلي</td>
                    <td class="{{ $stats['weight']['total_diff'] > 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['weight']['total_diff']) }}
                    </td>
                    <td class="{{ $stats['fats']['total_diff'] > 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['fats']['total_diff']) }}
                    </td>
                    <td class="{{ $stats['muscles']['total_diff'] < 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['muscles']['total_diff']) }}
                    </td>
                    <td class="{{ $stats['salts']['total_diff'] < 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['salts']['total_diff']) }}
                    </td>
                    <td class="{{ $stats['water']['total_diff'] < 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['water']['total_diff']) }}
                    </td>
                    <td class="{{ $stats['bmi']['total_diff'] < 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ sprintf('%+0.2f', $stats['bmi']['total_diff']) }}
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
