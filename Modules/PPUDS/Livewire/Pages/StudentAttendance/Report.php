<?php

namespace Modules\PPUDS\Livewire\Pages\StudentAttendance;

use App\View\Components\AppLayout;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentReport;

class Report extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public StudentAttendance $attendance;
    public ?StudentReport $report = null;

    public function mount(StudentAttendance $studentAttendance)
    {
        $this->attendance = $studentAttendance;

        $this->report = $studentAttendance->studentReport;

        $this->form->fill($this->report->toArray());
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

                                    RichEditor::make('report_text')
                                        ->label(__('Report Content'))
                                        ->required()
                                        ->toolbarButtons([
                                            'bold', 'italic', 'bulletList', 'orderedList', 'link', 'redo', 'undo',
                                        ])
                                        ->columnSpanFull(),

                                    SpatieMediaLibraryFileUpload::make('file_report')
                                        ->label(__('Attachment (Image/File)'))
                                        ->disk('student_reports')
                                        ->collection('file_report')
                                        ->image()
                                        ->imageEditor()
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
                                    // عرض معلومات فقط (Read-only) للسياق
                                    Placeholder::make('student_name')
                                        ->label(__('Student Name'))
                                        ->content($this->attendance->studentCompany->student->name ?? '-'),

                                    Placeholder::make('date')
                                        ->label(__('Date'))
                                        ->content($this->attendance->attendance_date),

                                    // عرض الصورة الحالية إن وجدت
                                    Placeholder::make('current_image')
                                        ->label(__('Current Uploaded Image'))
                                        ->visible(fn () => $this->attendance->studentReport?->getFirstMediaUrl('file_report'))
                                        ->content(fn () => new HtmlString(
                                            '<img src="' . $this->attendance->studentReport->getFirstMediaUrl('file_report') . '" style="width: 100%; border-radius: 8px; border: 1px solid #eee;" />'
                                        )),
                                ]),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {

            $report = $this->attendance->studentReport()->updateOrCreate(
                ['student_attendance_id' => $this->attendance->id],
                [
                    'report_text' => $this->data['report_text'],
                    'created_by'  => auth()->id(),
                ]
            );

            if (!empty($this->data['report_file'])) {
                $report->addImage($this->data['report_file']);
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
