<?php

namespace Modules\Core\Livewire\Pages\Profile;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentProfile;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];

    public function mount()
    {
        // dd(auth()->user());
        $this->form->fill(array_merge(
            $this->userRecord->toArray(),
            ['attachment_uploads' => []],
        ));
    }

    #[Computed]
    public function userRecord()
    {
        return User::with([
            'studentProfile',
            'studentProfile.media',
            'roles',
            'media',
        ])->findOrFail(auth()->id());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->userRecord)
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->getStateUsing(fn ($record) => $record->getAvatarUrlAttribute()),

                        TextEntry::make('name'),

                        TextEntry::make('email'),

                        TextEntry::make('student_profile.student_number'),
                    ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->userRecord)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Tabs::make('tabs')
                            ->tabs([

                                Tabs\Tab::make('personal-information')
                                    ->label(__('Personal Information'))
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Grid::make(1)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Name'))
                                                            ->disabled()
                                                            ->required(),

                                                        TextInput::make('email')
                                                            ->label(__('Email'))
                                                            ->email()
                                                            ->disabled()
                                                            ->unique(ignoreRecord: true, ignorable: $this->userRecord)
                                                            ->required(),

                                                        TextInput::make('password')
                                                            ->label(__('Password'))
                                                            ->password()
                                                            ->dehydrated(fn ($state) => filled($state))
                                                            ->required(fn (string $context): bool => $context === 'create'),
                                                    ])
                                                    ->columnSpan(2),

                                                Grid::make(1)
                                                    ->schema([
                                                        SpatieMediaLibraryFileUpload::make('cover_photo')
                                                            ->disk('media')
                                                            ->collection('cover_photo')
                                                            ->imageEditor()
                                                            ->alignCenter(),

                                                        SpatieMediaLibraryFileUpload::make('avatar')
                                                            ->disk('media')
                                                            ->collection('avatar')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->avatar()
                                                            ->alignCenter(),
                                                    ])
                                                    ->columnSpan(1),
                                            ]),
                                    ]),

                                Tabs\Tab::make('student-profile')
                                    ->label(__('Student Profile'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Section::make()
                                                    ->columnSpan(2)
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('student_profile.student_number')
                                                                    ->label(__('Student Number'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->required(),

                                                                Select::make('student_profile.major_id')
                                                                    ->label(__('Major'))
                                                                    ->options(\Modules\PPUDS\Entities\Major::get()->pluck('name', 'id'))
                                                                    ->disabled()
                                                                    ->searchable(),

                                                                TextInput::make('student_profile.enrollment_year')
                                                                    ->label(__('Enrollment Year'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->minLength(4)
                                                                    ->maxLength(4),

                                                                TextInput::make('student_profile.semester_level')
                                                                    ->label(__('Semester Level'))
                                                                    ->disabled()
                                                                    ->numeric(),

                                                                TextInput::make('student_profile.tawjihi_gpa')
                                                                    ->label(__('Tawjihi GPA'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->step(0.1),

                                                                DatePicker::make('student_profile.dob')
                                                                    ->label(__('Date of Birth'))
                                                                    ->disabled()
                                                                    ->displayFormat('d/m/Y'),

                                                                Select::make('student_profile.gender')
                                                                    ->label(__('Gender'))
                                                                    ->disabled()
                                                                    ->options([
                                                                        'male' => __('Male'),
                                                                        'female' => __('Female'),
                                                                    ]),

                                                                Select::make('student_profile.cv_status')
                                                                    ->label(__('CV Status'))
                                                                    ->disabled()
                                                                    ->options([
                                                                        'pending' => __('Pending'),
                                                                        'approved' => __('Approved'),
                                                                        'rejected' => __('Rejected'),
                                                                    ]),

                                                                TextInput::make('student_profile.linkedin_url')
                                                                    ->label(__('LinkedIn'))
                                                                    ->url()
                                                                    ->maxLength(255),

                                                                TextInput::make('student_profile.behance_url')
                                                                    ->label(__('Behance'))
                                                                    ->url()
                                                                    ->maxLength(255),

                                                                TextInput::make('student_profile.github_url')
                                                                    ->label(__('GitHub'))
                                                                    ->url()
                                                                    ->maxLength(255),
                                                            ]),
                                                    ]),

                                                Section::make()
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        SpatieMediaLibraryFileUpload::make('cv')
                                                            ->label(__('CV'))
                                                            ->disk('student_profiles')
                                                            ->collection('cv')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->alignCenter(),
                                                    ]),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                                Tabs\Tab::make('attachments')
                                    ->label(__('Attachments'))
                                    ->icon('heroicon-o-paper-clip')
                                    ->schema([
                                        Section::make(__('Student Attachments'))
                                            ->description(__('Optional supporting documents for the student'))
                                            ->schema([
                                                Placeholder::make('current_attachments')
                                                    ->label(__('Current Attachments'))
                                                    ->visible(fn (): bool => $this->studentAttachments()->isNotEmpty())
                                                    ->content(fn (): HtmlString => new HtmlString(
                                                        Blade::render(<<<'HTML'
                                                            <div class="grid gap-2">
                                                                @foreach ($attachments as $attachment)
                                                                    <div
                                                                        class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500"
                                                                    >
                                                                        <div class="min-w-0">
                                                                            <div class="truncate font-medium text-gray-800 dark:text-gray-100">
                                                                                {{ $attachment->name ?: $attachment->file_name }}
                                                                            </div>
                                                                            <div class="truncate text-xs text-gray-500">
                                                                                {{ $attachment->file_name }}
                                                                            </div>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-2">
                                                                            <span class="text-xs text-gray-500">
                                                                                {{ $attachment->human_readable_size }}
                                                                            </span>
                                                                            <button
                                                                                type="button"
                                                                                wire:click="downloadAttachment({{ $attachment->id }})"
                                                                                class="rounded-md px-2 py-1 text-xs font-medium text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
                                                                            >
                                                                                {{ __('Download') }}
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                wire:click="deleteAttachment({{ $attachment->id }})"
                                                                                wire:confirm="{{ __('Are you sure you want to delete this attachment?') }}"
                                                                                class="rounded-md px-2 py-1 text-xs font-medium text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-500/10"
                                                                            >
                                                                                {{ __('Delete') }}
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        HTML, [
                                                            'attachments' => $this->studentAttachments(),
                                                        ])
                                                    ))
                                                    ->columnSpanFull(),

                                                Repeater::make('attachment_uploads')
                                                    ->label(__('Add Attachment'))
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->label(__('Attachment Name'))
                                                                    ->helperText(__('Leave it empty to use the file name'))
                                                                    ->maxLength(255),

                                                                FileUpload::make('file')
                                                                    ->label(__('Attachment File'))
                                                                    ->required()
                                                                    ->storeFiles(false)
                                                                    ->preserveFilenames()
                                                                    ->maxSize(10240),
                                                            ]),
                                                    ])
                                                    ->defaultItems(0)
                                                    ->addActionLabel(__('Add Another Attachment'))
                                                    ->reorderable(false)
                                                    ->collapsible()
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                                Tabs\Tab::make('work-experience')

                                    ->label(__('Work Experience'))
                                    ->icon('heroicon-o-briefcase')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\WorkExperience\Index::class,
                                                    [
                                                        'studentId' => auth()->id(),
                                                    ]
                                                )
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                                Tabs\Tab::make('training-history')
                                    ->label(__('Training History'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\StudentCompany\Index::class,
                                                    [
                                                        'studentId' => auth()->id(),
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('registration')
                                    ->label(__('Registration'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\Registration\Index::class,
                                                    [
                                                        'studentId' => auth()->id(),
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                                Tabs\Tab::make('attendance')
                                    ->label(__('Attendance'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\StudentAttendance\Index::class,
                                                    [
                                                        'studentId' => auth()->id(),
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                                Tabs\Tab::make('payment')
                                    ->label(__('Payment'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\Payment\Index::class,
                                                    [
                                                        'studentId' => auth()->id(),
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ])
                                    ->visible(fn () => auth()->user()->hasRole('Student')),

                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        $data = $this->form->getState();
        $savedAttachments = 0;

        DB::transaction(function () use ($data, &$savedAttachments) { // أضف use ($data) هنا

            $user = $this->userRecord;

            $updateData = [];

            foreach (['name', 'email'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if ($updateData !== []) {
                $user->update($updateData);
            }

            if ($user->hasRole('Student')) {
                $socialLinks = collect($data['student_profile'] ?? [])
                    ->only(['linkedin_url', 'behance_url', 'github_url'])
                    ->toArray();

                if ($socialLinks !== [] && $user->studentProfile) {
                    $user->studentProfile->update($socialLinks);
                }
            }

            $this->form->model($user)->saveRelationships();

            $savedAttachments = $this->saveAttachments($user, $data['attachment_uploads'] ?? []);

            // Notification::make()->title('Saved successfully')->success()->send();
        });

        if ($savedAttachments > 0) {
            $this->data['attachment_uploads'] = [];

            unset($this->userRecord);

            Toaster::success(__('Student attachment uploaded successfully'));
        }

        // إعادة التوجيه بعد الحفظ
        // return redirect()->route('students.index');
    }

    public function downloadAttachment(int $mediaId)
    {
        $media = $this->studentAttachment($mediaId);

        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function deleteAttachment(int $mediaId): void
    {
        $media = $this->studentAttachment($mediaId);

        if (! $media) {
            Toaster::error(__('Attachment not found'));

            return;
        }

        $media->delete();

        unset($this->userRecord);

        Toaster::success(__('Attachment deleted successfully'));
    }

    protected function studentAttachments(): Collection
    {
        $profile = $this->userRecord->studentProfile;

        if (! $profile) {
            return collect();
        }

        return $profile->getMedia(StudentProfile::ATTACHMENTS_COLLECTION);
    }

    protected function studentAttachment(int $mediaId): ?SpatieMedia
    {
        $profile = $this->userRecord->studentProfile;

        if (! $profile) {
            return null;
        }

        return SpatieMedia::query()
            ->whereKey($mediaId)
            ->where('model_type', $profile->getMorphClass())
            ->where('model_id', $profile->getKey())
            ->where('collection_name', StudentProfile::ATTACHMENTS_COLLECTION)
            ->first();
    }

    protected function saveAttachments(User $user, array $attachmentUploads): int
    {
        $profile = $user->studentProfile;

        if (! $profile || $attachmentUploads === []) {
            return 0;
        }

        $saved = 0;

        foreach ($attachmentUploads as $attachmentUpload) {
            $files = Arr::wrap($attachmentUpload['file'] ?? []);
            $name = $attachmentUpload['name'] ?? null;

            foreach (array_filter($files) as $attachmentFile) {
                if ($profile->addAttachment($attachmentFile, $name)) {
                    $saved++;
                }
            }
        }

        return $saved;
    }

    public function render()

    {
        return view('core::livewire.pages.profile.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Profile'), 'url' => route('profile.index')],
            ],
        ]);
    }
}
