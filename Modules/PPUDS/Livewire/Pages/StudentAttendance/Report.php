<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendance;

use App\View\Components\AppLayout;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Report extends Component implements HasForms
{
    use InteractsWithForms;
    use ScopesStudentCompanyVisibility;

    public ?array $data = [];
    public StudentAttendance $attendance;
    public ?StudentReport $report = null;

    public function mount(StudentAttendance $studentAttendance)
    {
        abort_unless($this->canAccessStudentCompanyRecord($studentAttendance->studentCompany), 403);

        $this->attendance = $studentAttendance;

        $this->report = $studentAttendance->studentReport;

        $this->form->fill(array_merge(
            ['student_attendance_id' => $this->attendance->id],
            $this->report?->toArray() ?? [],
        ));
    }



    public function form(Form $form): Form
    {
        return $form
            ->model($this->report)
            ->schema([
                Grid::make(3)
                ->schema([
                    Grid::make(1)
                        ->columnSpan(2)
                        ->schema([
                            Section::make(__('Report Details'))
                                ->description(__('Write your daily report and attach files.'))
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    Hidden::make('student_attendance_id')
                                        ->default(fn (): int => $this->attendance->id)
                                        ->required(),

                                    RichEditor::make('report_text')
                                        ->label(__('Report Content'))
                                        ->required()
                                        ->toolbarButtons([
                                            'bold', 'italic', 'bulletList', 'orderedList', 'link', 'redo', 'undo',
                                        ])
                                        ->columnSpanFull(),

                                    FileUpload::make('file_report')
                                        ->label(__('Attachment (Image/File)'))
                                        ->storeFiles(false)
                                        ->acceptedFileTypes([
                                            'application/pdf',
                                            'application/msword',
                                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'image/gif',
                                        ])
                                        ->rules(['mimes:pdf,doc,docx,jpg,jpeg,png,webp,gif'])
                                        ->multiple()
                                        ->maxFiles(5)
                                        ->reorderable()
                                        ->panelLayout('grid')
                                        ->maxSize(5120) // 5MB
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Grid::make(1)
                        ->columnSpan(1)
                        ->schema([
                            Section::make(__('Attendance Info'))
                                ->icon('heroicon-o-information-circle')
                                ->schema([
                                    Placeholder::make('student_name')
                                        ->label(__('Student Name'))
                                        ->content($this->attendance->studentCompany->student->name ?? '-'),

                                    Placeholder::make('date')
                                        ->label(__('Date'))
                                        ->content($this->attendance->attendance_date),

                                    Placeholder::make('current_images')
                                        ->label(__('Current Uploaded Files'))
                                        ->visible(fn () => $this->report && $this->report->getMedia('file_report')->count() > 0)
                                        ->content(fn () => new HtmlString(
                                            Blade::render(<<<'HTML'
                                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px;">
                                                        @foreach($attachments as $attachment)
                                                            <a href="{{ $attachment['url'] }}" target="_blank" style="display: block; text-decoration: none;">
                                                                @if($attachment['is_image'])
                                                                    <img
                                                                        src="{{ $attachment['url'] }}"
                                                                        style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;"
                                                                        alt="{{ $attachment['name'] }}"
                                                                    >
                                                                @else
                                                                    <div style="height: 100px; border-radius: 8px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; padding: 8px; background: #f9fafb; color: #374151; text-align: center; font-size: 12px;">
                                                                        {{ $attachment['name'] }}
                                                                    </div>
                                                                @endif
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                HTML,
                                                ['attachments' => $this->report?->getFileReportItems() ?? []]
                                            )
                                        )),
                                ]),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {

            $report = $this->attendance->studentReport()->updateOrCreate(
                ['student_attendance_id' => $this->attendance->id],
                [
                    'report_text' => $data['report_text'],
                    'created_by'  => auth()->id(),
                ]
            );

            if (!empty($data['file_report'])) {
                foreach ($data['file_report'] as $file)
                {
                    $report->addImage($file);
                }
            }

            Toaster::success(__('Report saved successfully'));
        });

        // إعادة التوجيه بعد الحفظ
        return redirect()->route('student-attendances.index');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-attendance.report')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Attendance List'), 'url' => route('student-attendances.index')],
                ['title' => __('Submit Report'), 'url' => '#'],
            ]
        ]);
    }
}
