<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentProfile;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public User $user;

    public ?array $data = [];

    public function mount(User $user)
    {
        $this->user = $user;

        $profile = $this->user->studentProfile;

        $this->form->fill(array_merge(
            $this->user->attributesToArray(),
            $profile ? $profile->attributesToArray() : [],
            ['attachment_uploads' => []]
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make(__('Account Information'))
                                    ->description(__('Manage user login details'))
                                    ->icon('heroicon-o-user')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('Name (Arabic)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_en')
                                            ->label(__('Name (English)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label(__('Email Address'))
                                            ->email()
                                            ->required()
                                            ->unique('users', 'email', ignoreRecord: true),

                                        TextInput::make('phone')
                                            ->label(__('Phone'))
                                            ->numeric()
                                            ->unique('users', 'phone', ignorable: $this->user)
                                            ->required(),

                                        TextInput::make('password')
                                            ->label(__('Password'))
                                            ->password()
                                            ->revealable()
                                            // التعديلات الخاصة بصفحة التعديل:
                                            ->nullable() // غير إجباري
                                            ->dehydrated(fn ($state) => filled($state)) // لا يرسل للسيرفر إلا إذا تم تعبئته
                                            ->confirmed(),

                                        TextInput::make('password_confirmation')
                                            ->label(__('Confirm Password'))
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => filled($get('password'))) // يظهر فقط إذا كتب باسورد
                                            ->required(fn ($get) => filled($get('password'))),
                                    ]),

                                Section::make(__('Academic Information'))
                                    ->description(__('Student university details'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('student_number')
                                            ->label(__('Student Number'))
                                            ->required()
                                            ->numeric()
                                            // استثناء الرقم الجامعي الحالي (نستثني الـ ID الخاص بالبروفايل)
                                            ->rule(function () {
                                                $profileId = $this->user->studentProfile?->id;

                                                return Rule::unique(config('ppuds.table_prefix').'student_profiles', 'student_number')
                                                    ->ignore($profileId);
                                            }),

                                        Select::make('major_id')
                                            ->label(__('Major'))
                                            ->options(function () {
                                                return Major::get()->pluck('name', 'id');
                                            })
                                            // خيار إضافة تخصص جديد سريعاً (اختياري في التعديل، لكن مفيد)
                                            ->createOptionForm([
                                                TextInput::make('reference_code')->label(__('Reference code'))->required(),
                                                TextInput::make('name')->label(__('Name'))->required(),
                                                Textarea::make('description')->label(__('Description')),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $data['created_by'] = auth()->id();

                                                return Major::create($data)->getKey();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        TextInput::make('enrollment_year')
                                            ->label(__('Enrollment Year'))
                                            ->numeric()
                                            ->minValue(2000)
                                            ->maxValue(date('Y') + 1),

                                        Select::make('semester_level')
                                            ->label(__('Semester Level'))
                                            ->options(array_combine(range(1, 10), range(1, 10)))
                                            ->nullable(),
                                    ]),

                                Section::make(__('Student Attachments'))
                                    ->description(__('Optional supporting documents for the student'))
                                    ->icon('heroicon-o-paper-clip')
                                    ->collapsible()
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
                            ]),

                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('Personal Details'))
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        DatePicker::make('dob')
                                            ->label(__('Date of Birth'))
                                            ->maxDate(now()),

                                        Select::make('gender')
                                            ->label(__('Gender'))
                                            ->options([
                                                'male' => __('Male'),
                                                'female' => __('Female'),
                                            ]),

                                        TextInput::make('tawjihi_gpa')
                                            ->label(__('Tawjihi GPA'))
                                            ->numeric()
                                            ->maxValue(100)
                                            ->suffix('%'),
                                    ]),

                                Section::make(__('Social Links'))
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        TextInput::make('linkedin_url')
                                            ->label(__('LinkedIn'))
                                            ->url()
                                            ->maxLength(255),

                                        TextInput::make('behance_url')
                                            ->label(__('Behance'))
                                            ->url()
                                            ->maxLength(255),

                                        TextInput::make('github_url')
                                            ->label(__('GitHub'))
                                            ->url()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data')
            ->model($this->user); // ربط الموديل بالفورم للمساعدة في الـ Validation
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {

            // 1. تحديث بيانات المستخدم الأساسية
            $userData = [
                'name' => $this->data['name'],
                'name_en' => $this->data['name_en'],
                'email' => $this->data['email'],
                'phone' => $this->data['phone'],
            ];

            // تحديث كلمة المرور فقط إذا تم إدخالها
            if (! empty($this->data['password'])) {
                $userData['password'] = Hash::make($this->data['password']);
            }

            $this->user->update($userData);

            // 2. تحديث أو إنشاء بيانات البروفايل
            $attachmentUploads = $this->data['attachment_uploads'] ?? [];

            $profileData = collect($this->data)
                ->except(['name', 'name_en', 'email', 'password', 'password_confirmation', 'roles', 'attachment_uploads'])
                ->toArray();

            // استخدام updateOrCreate لضمان عدم حدوث أخطاء
            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $this->user->id], // شرط البحث
                $profileData // البيانات للتحديث
            );

            // 3. حفظ مرفقات الطالب الاختيارية
            $this->saveAttachments($profile, $attachmentUploads);

            // تحديث الأدوار إذا لزم الأمر (في Add كان يتم إسناد student فقط)
            // إذا كنت تريد السماح بتعديل الأدوار هنا، يجب إضافة حقل Select للأدوار في الفورم
            // حالياً هو طالب، لذا لا داعي لتغيير دوره
        });

        // إعادة التوجيه
        return redirect()->route('students.index');
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

        $profile = $this->user->studentProfile;

        $profile?->unsetRelation('media');
        $profile?->load('media');

        Toaster::success(__('Attachment deleted successfully'));
    }

    protected function studentAttachments(): Collection
    {
        $profile = $this->user->studentProfile;

        if (! $profile) {
            return collect();
        }

        return $profile->getMedia(StudentProfile::ATTACHMENTS_COLLECTION);
    }

    protected function studentAttachment(int $mediaId): ?SpatieMedia
    {
        $profile = $this->user->studentProfile;

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

    protected function saveAttachments(StudentProfile $profile, array $attachmentUploads): void
    {
        foreach ($attachmentUploads as $attachmentUpload) {
            $files = Arr::wrap($attachmentUpload['file'] ?? []);
            $name = $attachmentUpload['name'] ?? null;

            foreach (array_filter($files) as $attachmentFile) {
                $profile->addAttachment($attachmentFile, $name);
            }
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Edit Student'), 'url' => '#'], // الرابط الحالي
            ],
        ]);
    }
}
