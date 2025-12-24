@if((empty($stats)) || (empty($readings)))
    <p>لا يوجد قراءات</p>
@else
    <div class="p-4 rounded-lg bg-white dark:bg-gray-800/50 mt-6 border border-gray-200 dark:border-gray-700">
        @include('customer::tables.clinic.customer-readings-stats', ['stats' => $stats])
        @include('customer::tables.clinic.customer-readings-history', ['readings' => $readings])
    </div>
@endif
