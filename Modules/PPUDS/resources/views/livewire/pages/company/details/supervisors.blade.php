@php
    $company = $company ?? null;
    $supervisors = collect($supervisors ?? []);
@endphp

<div class="space-y-4">
    @if ($company)
        <div class="flex justify-end">
            @can('Company Update')
                <a
                    href="{{ route('companies.edit', $company) }}"
                    class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    {{ __('Edit Company') }}
                </a>
            @endcan
        </div>
    @endif

    @if ($supervisors->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm font-medium text-gray-500">
            {{ __('No supervisors found') }}
        </div>
    @else
        <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white md:block">
            <table class="min-w-full divide-y divide-gray-200 text-right">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">{{ __('Phone') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">{{ __('Branches') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">{{ __('Departments') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @foreach ($supervisors as $row)
                        @php
                            $user = $row['user'];
                            $avatarUrl = $user->getFirstMediaUrl('avatar');
                            $initial = mb_substr($user->name ?: $user->email ?: '-', 0, 1);
                        @endphp

                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($avatarUrl)
                                        <img
                                            src="{{ $avatarUrl }}"
                                            alt="{{ $user->name }}"
                                            class="h-10 w-10 rounded-full object-cover ring-1 ring-gray-200"
                                        >
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 ring-1 ring-primary-100">
                                            {{ $initial }}
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                                        @if ($user->name_en)
                                            <div class="text-sm text-gray-500">{{ $user->name_en }}</div>
                                        @endif
                                        @can('User Update')
                                            <a
                                                href="{{ route('users.edit', $user) }}"
                                                class="mt-1 inline-flex text-xs font-semibold text-primary-600 hover:text-primary-700"
                                            >
                                                {{ __('Open User') }}
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700">
                                @if ($user->email)
                                    <a class="text-primary-600 hover:text-primary-700" href="mailto:{{ $user->email }}">
                                        {{ $user->email }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700" dir="ltr">
                                {{ $user->phone ?: '-' }}
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($row['branches'] as $branch)
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                                            {{ $branch }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="space-y-2">
                                    @foreach ($row['departments'] as $department)
                                        <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-gray-900">{{ $department['department'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $department['branch'] }}</div>
                                            </div>
                                            @can('Company Update')
                                                <button
                                                    type="button"
                                                    wire:click="mountAction('editDepartmentSupervisor', { branchId: {{ $department['branch_id'] }}, departmentId: {{ $department['department_id'] }}, userId: {{ $user->id }} })"
                                                    class="shrink-0 inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                                >
                                                    {{ __('Edit') }}
                                                </button>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden">
            @foreach ($supervisors as $row)
                @php
                    $user = $row['user'];
                    $avatarUrl = $user->getFirstMediaUrl('avatar');
                    $initial = mb_substr($user->name ?: $user->email ?: '-', 0, 1);
                @endphp

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-start gap-3">
                        @if ($avatarUrl)
                            <img
                                src="{{ $avatarUrl }}"
                                alt="{{ $user->name }}"
                                class="h-11 w-11 rounded-full object-cover ring-1 ring-gray-200"
                            >
                        @else
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 ring-1 ring-primary-100">
                                {{ $initial }}
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                            @if ($user->name_en)
                                <div class="text-sm text-gray-500">{{ $user->name_en }}</div>
                            @endif
                            @can('User Update')
                                <a
                                    href="{{ route('users.edit', $user) }}"
                                    class="mt-1 inline-flex text-xs font-semibold text-primary-600 hover:text-primary-700"
                                >
                                    {{ __('Open User') }}
                                </a>
                            @endcan
                            <div class="mt-2 space-y-1 text-sm text-gray-600">
                                <div class="break-all">{{ $user->email ?: '-' }}</div>
                                <div dir="ltr">{{ $user->phone ?: '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($row['branches'] as $branch)
                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                                {{ $branch }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($row['departments'] as $department)
                            <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900">{{ $department['department'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $department['branch'] }}</div>
                                </div>
                                @can('Company Update')
                                    <button
                                        type="button"
                                        wire:click="mountAction('editDepartmentSupervisor', { branchId: {{ $department['branch_id'] }}, departmentId: {{ $department['department_id'] }}, userId: {{ $user->id }} })"
                                        class="shrink-0 inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    >
                                        {{ __('Edit') }}
                                    </button>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
