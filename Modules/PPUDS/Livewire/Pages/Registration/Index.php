<?php

namespace Modules\PPUDS\Livewire\Pages\Registration;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\Course; // ✅ تأكد من استدعاء مودل الكورس
use Modules\PPUDS\Enums\Enums\SemesterType;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Registration::query()->with(['student', 'course', 'supervisor']))
            ->columns([
                // 1. عمود الطالب
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->icon('solar-user-id-bold-duotone')
                    ->weight('bold'),

                // 2. عمود المساق
                TextColumn::make('course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('info'),
                // ❌ تم إزالة searchable و sortable لأن العمود غير موجود في الداتابيس (مترجم)

                // 3. عمود الفصل والسنة
                TextColumn::make('semester')
                    ->label(__('Term'))
                    ->formatStateUsing(function ($state, $record) {
                        $semesterName = SemesterType::tryFrom($state)?->getLabel() ??
                            __('Semester') . ' ' . $state;
                        return $semesterName . ' - ' .
                            $record->year;
                    })
                    ->icon('solar-calendar-date-linear'),
                // تم إزالة sortable لتجنب المشاكل حالياً

                // 4. عمود المشرف
                TextColumn::make('supervisor.name')
                    ->label(__('Supervisor'))
                    ->icon('solar-user-speak-rounded-linear')
                    ->toggleable(),

                // 5. عمود العلامة
//                TextColumn::make('grade')
//                    ->label(__('Grade'))
//                    ->placeholder('-')
//                    ->badge()
//                    ->color(fn ($state) => $state >= 60 ? 'success' : ($state === null ? 'gray' : 'danger'))
//                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Registered At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Registration'))
                    ->url(route('registrations.add'))
                    ->visible(fn() => auth()->user()->can('Registration Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            // 1. فلتر المساق (تم الإصلاح)
            SelectFilter::make('course_id')
                ->label(__('Course'))
                // ❌ لا تستخدم relationship هنا لأنها تطلب SQL مباشر
                // ✅ استخدم options مع get() لجلب الترجمات عبر المودل
                ->options(Course::get()->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            // 2. فلتر الفصل الدراسي
            SelectFilter::make('semester')
                ->label(__('Semester'))
                ->options(SemesterType::class)
                ->native(false),

            // 3. فلتر السنة
            Filter::make('year')
                ->form([
                    TextInput::make('year')->label(__('Year'))->numeric()
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $query, $year) => $query->where('year', $year)
                    );
                }),

            // 4. فلتر المشرف
            SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(User::whereHas('roles', fn($q) => $q->where('name', 'Practical Training Supervisor'))->pluck('name', 'id'))
                ->searchable(),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected registrations deleted successfully'))),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Registration Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)->schema([
                            TextInput::make('student.name')
                                ->label(__('Student'))
                                ->default($record->student->name ?? '-')
                                ->disabled(),
                            TextInput::make('course.name')
                                ->label(__('Course'))
                                ->default($record->course->name ?? '-')
                                ->disabled(),
                            TextInput::make('semester')
                                ->label(__('Semester'))
                                ->default(fn() => SemesterType::tryFrom($record->semester)?->getLabel() ?? $record->semester)
                                ->disabled(),
                            TextInput::make('year')
                                ->label(__('Year'))
                                ->default($record->year)
                                ->disabled(),
                            TextInput::make('university_score')
                                ->label(__('University Score'))
                                ->default($record->university_score)
                                ->disabled(),
                            TextInput::make('company_score')
                                ->label(__('Company Score'))
                                ->default($record->company_score)
                                ->disabled(),
                            TextInput::make('grade')
                                ->label(__('Final Grade'))
                                ->default($record->grade)
                                ->disabled()
                                ->extraInputAttributes(['class' => 'text-primary-600 font-bold']),
                        ])
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Registration View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn(Registration $record) => route('registrations.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Registration Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Registration deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Registration Delete'))
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.registration.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Registrations List'), 'url' => route('registrations.index')],
            ]
        ]);
    }
}
