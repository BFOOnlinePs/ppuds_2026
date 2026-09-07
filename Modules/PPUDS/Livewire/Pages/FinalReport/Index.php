<?php

namespace Modules\PPUDS\Livewire\Pages\FinalReport;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\FinalReport;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\FinalReportItemType;
use Modules\PPUDS\Services\FinalReportService;

class Index extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public ?Registration $registration = null;

    public ?FinalReport $report = null;

    public function mount(FinalReportService $finalReports): void
    {
        $this->authorize('FinalReport View');

        // إخفاء العنصر من القائمة الجانبية وحده ليس حماية، فنمنع الوصول المباشر أيضاً.
        abort_unless($finalReports->submissionIsOpen(), 403);

        $this->registration = $finalReports->currentRegistrationFor(auth()->id());
        $this->report = $this->registration
            ? $finalReports->reportForRegistration($this->registration)
            : null;

        $this->form->fill($this->reportFormState());
    }

    /**
     * @return array<string, mixed>
     */
    protected function reportFormState(): array
    {
        if (! $this->report) {
            return [
                'role_description' => null,
                'summary' => null,
                'tasks' => [],
                'skills' => [],
                'contributions' => [],
                'difficulties' => [],
                'final_file' => null,
            ];
        }

        return [
            'final_file' => null,
            'role_description' => $this->report->role_description,
            'summary' => $this->report->summary,
            'tasks' => $this->report->tasks
                ->map(fn ($task) => [
                    'task_name' => $task->task_name,
                    'task_details' => $task->task_details,
                    'work_duration' => $task->work_duration,
                    'notes' => $task->notes,
                ])
                ->all(),
            'skills' => $this->report->skills
                ->map(fn ($skill) => [
                    'skill' => $skill->skill,
                    'mastery_percentage' => $skill->mastery_percentage,
                    'notes' => $skill->notes,
                ])
                ->all(),
            'contributions' => $this->itemsState(FinalReportItemType::CONTRIBUTION),
            'difficulties' => $this->itemsState(FinalReportItemType::DIFFICULTY),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function itemsState(FinalReportItemType $type): array
    {
        return $this->report->items
            ->where('type', $type)
            ->map(fn ($item) => ['content' => $item->content])
            ->values()
            ->all();
    }

    public function isLocked(): bool
    {
        return (bool) $this->report?->isSubmitted();
    }

    /**
     * رابط المرفق الحالي إن وُجد، والمرفق يبقى اختيارياً.
     */
    protected function attachmentLink(): HtmlString
    {
        $url = app(FinalReportService::class)->attachmentUrl($this->registration);

        if ($url === null) {
            return new HtmlString('<span class="text-sm text-gray-500">'.e(__('No attachment')).'</span>');
        }

        return new HtmlString(
            '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600">'
            .e(__('View File')).'</a>'
        );
    }

    public function hasRegistration(): bool
    {
        return $this->registration !== null;
    }

    public function form(Form $form): Form
    {
        $locked = $this->isLocked();

        return $form
            ->schema([
                Section::make(__('Training Role Description'))
                    ->description(__('The student explains their role during the training period and the department they worked in, in addition to the most important responsibilities and duties they carried out.'))
                    ->icon('solar-user-speak-rounded-bold-duotone')
                    ->schema([
                        RichEditor::make('role_description')
                            ->label(__('Training Role Description'))
                            ->placeholder(__('Write about your role and the department you worked in...'))
                            ->disabled($locked)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Training Tasks'))
                    ->icon('solar-clipboard-list-bold-duotone')
                    ->schema([
                        Repeater::make('tasks')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add Task'))
                            ->schema([
                                TextInput::make('task_name')
                                    ->label(__('Training Task'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('task_details')
                                    ->label(__('Task Details'))
                                    ->rows(2)
                                    ->maxLength(2000),

                                TextInput::make('work_duration')
                                    ->label(__('Work Duration In Days And Hours'))
                                    ->placeholder(__('For example: 10 days - 60 hours'))
                                    ->maxLength(255),

                                Textarea::make('notes')
                                    ->label(__('Notes'))
                                    ->rows(2)
                                    ->maxLength(2000),
                            ])
                            ->columns(['default' => 1, 'lg' => 2])
                            ->itemLabel(fn (array $state): ?string => $state['task_name'] ?? null)
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->disabled($locked)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Skills Gained From Training'))
                    ->icon('solar-medal-star-bold-duotone')
                    ->schema([
                        Repeater::make('skills')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add Skill'))
                            ->schema([
                                TextInput::make('skill')
                                    ->label(__('Skill'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('mastery_percentage')
                                    ->label(__('Mastery Percentage'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),

                                Textarea::make('notes')
                                    ->label(__('Notes'))
                                    ->rows(2)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'lg' => 2])
                            ->itemLabel(fn (array $state): ?string => $state['skill'] ?? null)
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->disabled($locked)
                            ->columnSpanFull(),
                    ]),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make(__('Key Contributions'))
                            ->description(__('The student explains the most prominent contributions they achieved during the training period.'))
                            ->icon('solar-star-bold-duotone')
                            ->schema([
                                Repeater::make('contributions')
                                    ->hiddenLabel()
                                    ->addActionLabel(__('Add Contribution'))
                                    ->schema([
                                        Textarea::make('content')
                                            ->label(__('Contribution'))
                                            ->required()
                                            ->rows(2)
                                            ->maxLength(2000),
                                    ])
                                    ->reorderable()
                                    ->disabled($locked)
                                    ->columnSpanFull(),
                            ]),

                        Section::make(__('Training Difficulties'))
                            ->description(__('The most important difficulties faced during the training period that affected the execution of some tasks.'))
                            ->icon('solar-danger-triangle-bold-duotone')
                            ->schema([
                                Repeater::make('difficulties')
                                    ->hiddenLabel()
                                    ->addActionLabel(__('Add Difficulty'))
                                    ->schema([
                                        Textarea::make('content')
                                            ->label(__('Difficulty'))
                                            ->required()
                                            ->rows(2)
                                            ->maxLength(2000),
                                    ])
                                    ->reorderable()
                                    ->disabled($locked)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make(__('Summary'))
                    ->description(__('Write a summary of the field training explaining how much you benefited from the training.'))
                    ->icon('solar-document-text-bold-duotone')
                    ->schema([
                        RichEditor::make('summary')
                            ->label(__('Summary'))
                            ->placeholder(__('Write the training summary here...'))
                            ->disabled($locked)
                            ->columnSpanFull(),

                        Placeholder::make('submitted_at')
                            ->label(__('Submitted At'))
                            ->content(fn (): HtmlString => new HtmlString(
                                e(optional($this->report?->submitted_at)->format('Y-m-d H:i') ?? '---')
                            ))
                            ->visible(fn (): bool => $this->isLocked()),
                    ]),

                Section::make(__('Attachment'))
                    ->description(__('Optional: attach a copy of the report or any supporting file.'))
                    ->icon('solar-paperclip-2-bold-duotone')
                    ->schema([
                        Placeholder::make('current_attachment')
                            ->label(__('Current Attachment'))
                            ->content(fn (): HtmlString => $this->attachmentLink())
                            ->columnSpanFull(),

                        FileUpload::make('final_file')
                            ->label(__('Attachment'))
                            ->helperText(__('Optional. Uploading a new file replaces the current one.'))
                            ->storeFiles(false)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->rules(['mimes:jpeg,png,jpg,pdf'])
                            ->maxSize(2048)
                            ->visible(fn (): bool => ! $locked)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(FinalReportService $finalReports): void
    {
        $this->authorize('FinalReport Create');

        if (! $this->guardEditable($finalReports)) {
            return;
        }

        $this->validate();

        if (! $this->storeAttachment($finalReports)) {
            return;
        }

        $this->report = $finalReports->save($this->registration, $this->data, auth()->user());

        $this->form->fill($this->reportFormState());

        Toaster::success(__('Final report saved successfully'));
    }

    public function submit(FinalReportService $finalReports): void
    {
        $this->authorize('FinalReport Submit');

        if (! $this->guardEditable($finalReports)) {
            return;
        }

        $this->validate();

        if (! $this->storeAttachment($finalReports)) {
            return;
        }

        $report = $finalReports->save($this->registration, $this->data, auth()->user());

        if ($report->tasks->isEmpty() || blank($report->summary)) {
            $this->report = $report;

            Toaster::error(__('Please add at least one training task and write the summary before submitting.'));

            return;
        }

        $this->report = $finalReports->submit($report);

        $this->form->fill($this->reportFormState());

        Toaster::success(__('Final report submitted successfully'));
    }

    /**
     * المرفق اختياري: نسحبه من حالة النموذج ونرفعه فقط إذا اختار الطالب ملفاً،
     * حتى لا يمسح الحفظُ العاديُّ المرفقَ الموجود.
     */
    protected function storeAttachment(FinalReportService $finalReports): bool
    {
        $file = $this->data['final_file'] ?? null;

        unset($this->data['final_file']);

        if (is_array($file)) {
            $file = reset($file) ?: null;
        }

        if (blank($file)) {
            return true;
        }

        if ($finalReports->saveAttachment($this->registration, $file)) {
            return true;
        }

        Toaster::error(__('Failed to upload the final report file. Please try again.'));

        return false;
    }

    /**
     * التسليم النهائي يقفل التقرير، والحارس هنا هو الحارس الفعلي وليس إخفاء الزر.
     */
    protected function guardEditable(FinalReportService $finalReports): bool
    {
        // القائمة قد تكون مفتوحة في المتصفح لحظة إغلاق التقارير من الإعدادات.
        if (! $finalReports->submissionIsOpen()) {
            Toaster::error(__('Final report submission is currently closed.'));

            return false;
        }

        if (! $this->registration) {
            Toaster::error(__('You do not have a registration in the current semester.'));

            return false;
        }

        if ($this->isLocked()) {
            Toaster::error(__('The final report has already been submitted and can no longer be edited.'));

            return false;
        }

        return true;
    }

    public function render()
    {
        return view('ppuds::livewire.pages.final-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Final Report Submission'), 'url' => route('final-reports.index')],
            ],
        ]);
    }
}
