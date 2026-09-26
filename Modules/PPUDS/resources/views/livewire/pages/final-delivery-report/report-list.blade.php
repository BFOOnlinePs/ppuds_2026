@php
    use Modules\PPUDS\Entities\Registration;
@endphp

{{-- The final report exactly as the student submitted it, one per registration.
     The including component provides printFinalReport() for the PDF button. --}}
@forelse ($registrations as $registration)
    @php
        $report = $registration->finalReport;
        $placement = $registration->studentCompany;
        $attachmentUrl = $registration->getFirstMediaUrl('final_file') ?: null;
        $presentationUrl = $registration->getFirstMediaUrl(Registration::PRESENTATION_COLLECTION) ?: null;
    @endphp

    <div class="mb-4 space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $placement?->company?->name ?: __('Final Report') }}
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                      style="color: {{ $report->isSubmitted() ? '#10b981' : '#f59e0b' }}; background-color: {{ $report->isSubmitted() ? '#10b981' : '#f59e0b' }}1f;">
                    {{ $report->isSubmitted() ? __('Submitted') : __('Not Submitted') }}
                </span>

                <button type="button"
                        wire:click="printFinalReport({{ $registration->id }})"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-1 text-sm text-primary-600 hover:underline">
                    @svg('solar-printer-bold', 'h-4 w-4')
                    <span>{{ __('Print PDF') }}</span>
                </button>
            </div>
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ __('Submission Details') }}
            </div>

            @if ($placement)
                @include('ppuds::livewire.pages.final-delivery-report.report-summary', [
                    'record' => $placement,
                    'report' => $report,
                    'attachmentUrl' => $attachmentUrl,
                    'presentationUrl' => $presentationUrl,
                ])
            @else
                {{-- A registration with no placement row still has a report to read --}}
                <div class="overflow-x-auto text-sm">
                    <table class="w-full border-collapse text-start">
                        <tbody>
                            @foreach ([
                                __('Course') => $registration->course?->name,
                                __('Practical Training Supervisor') => $registration->supervisor?->name,
                                __('Semester') => $registration->semester?->getLabel(),
                                __('Year') => $registration->year,
                                __('Delivery Status') => $report->status?->getLabel(),
                                __('Submitted At') => $report->submitted_at?->format('Y-m-d H:i'),
                            ] as $label => $value)
                                <tr>
                                    <td class="border px-3 py-2 text-xs font-semibold">{{ $label }}</td>
                                    <td class="border px-3 py-2">{{ filled($value) ? $value : '---' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ __('Training Role Description') }}
            </div>
            <div class="prose max-w-none text-sm dark:prose-invert">
                {!! filled($report->role_description) ? $report->role_description : '---' !!}
            </div>
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ __('Training Tasks And Skills') }}
            </div>
            @include('ppuds::livewire.pages.final-delivery-report.report-details', ['report' => $report])
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ __('Summary') }}
            </div>
            <div class="prose max-w-none text-sm dark:prose-invert">
                {!! filled($report->summary) ? $report->summary : '---' !!}
            </div>
        </div>
    </div>
@empty
    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ __('No final report submitted yet') }}
    </div>
@endforelse
