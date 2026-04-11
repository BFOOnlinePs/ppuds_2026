{{-- <div>
    @if ($this->getStudentCompanies()->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($this->getStudentCompanies() as $studentCompany)
                <a href="#"
                    class="flex flex-col bg-white border border-gray-200 rounded-xl shadow-sm hover:ring-2 hover:ring-primary-500 dark:bg-gray-900 dark:border-gray-800 transition duration-200 overflow-hidden group">

                    <div class="w-full h-48 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
                        <img class="w-full h-full object-cover"
                             src="{{ $studentCompany->company->getImageAttribute() }}"
                             alt="{{ $studentCompany->company->name }}">
                    </div>

                    <div class="p-5 flex flex-col flex-grow">
                        <h5 class="mb-2 text-lg font-bold tracking-tight text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                            {{ $studentCompany->company->name }}
                        </h5>

                        <p class="font-normal text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                            {{ $studentCompany->description ?? 'اضغط هنا لعرض تفاصيل الشركة' }}
                        </p>

                        <div class="mt-auto text-primary-600 dark:text-primary-400 text-sm font-medium inline-flex items-center">
                            عرض التفاصيل
                            <svg class="w-4 h-4 mr-1 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="p-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            لا يوجد شركات مسجلة حتى الآن.
        </div>
    @endif
</div> --}}


<div>
    @if ($this->getStudentCompanies()->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach ($this->getStudentCompanies() as $studentCompany)
                <a href="#"
                    class="flex flex-col items-center w-full bg-neutral-primary-soft border border-default rounded-base shadow-xs md:flex-row">

                    <img class="object-cover w-32 h-32 p-5 mb-4 rounded-base md:h-auto md:w-48 md:mb-0"
                        src="{{ $studentCompany->company->getImageAttribute() }}" alt="">

                    <div class="flex flex-col justify-between flex-1 p-4 leading-normal text-right">
                        <h5 class="mb-2 text-2xl font-bold tracking-tight text-heading">{{ $studentCompany->company->name }}</h5>
                        <p class="mb-6 text-body">
                            {{ $studentCompany->description ?? 'اضغط هنا لعرض تفاصيل الشركة' }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div
            class="p-6 text-center text-gray-500 border border-gray-200 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            {{ __('No Company Registered') }}
        </div>
    @endif
</div>
