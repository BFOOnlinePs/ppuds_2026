<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\CompanyStatus;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table)
    {
        return $table
            ->query(fn () => Company::query()
                ->with(['category.translations', 'media', 'branches.supervisors'])
                ->withCurrentStudentsCount()
            )
            ->columns([
                ImageColumn::make('logo')
                    ->label(__('Logo'))
                    ->getStateUsing(function ($record) {
                        return $record->getImageAttribute();
                    }),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereTranslationLike('name', "%{$search}%")
                    )
                    ->url(fn (Company $record) => route('companies.details', $record))
                    ->sortable(),

                TextColumn::make('category.name'),

                TextColumn::make('supervisors')
                    ->label(__('Supervisors'))
                    ->getStateUsing(fn (Company $record): array => $record->companySupervisorNames())
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder(__('No supervisors found')),

                TextColumn::make('current_students_count')
                    ->label(__('Current Semester Students'))
                    ->alignCenter()
                    ->sortable()
                    ->color('primary')
                    ->action(
                        Action::make('viewCurrentStudents')
                            ->label(__('Current Semester Students'))
                            ->modalHeading(fn (Company $record): string => __('Current Semester Students').' - '.$record->name)
                            ->modalContent(fn (Company $record) => view('ppuds::livewire.pages.company.partials.current-students-modal', [
                                'students' => $this->currentStudentRows($record),
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel(__('Close'))
                            ->modalWidth('6xl')
                    ),

                ToggleColumn::make('status')
                    ->label(__('Status'))
                    ->getStateUsing(fn (Company $record): bool => $record->status === CompanyStatus::ACTIVE)
                    ->updateStateUsing(function (Company $record, mixed $state): bool {
                        $record->update([
                            'status' => $state ? CompanyStatus::ACTIVE->value : CompanyStatus::INACTIVE->value,
                        ]);

                        Toaster::success(__('Updated successfully'));

                        return (bool) $state;
                    })
                    ->disabled(fn (): bool => ! auth()->user()->can('Company Update'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),
            ])
            ->filters(
                filters: $this->getTableFilters(),
                layout: FiltersLayout::AboveContent,
            )
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Company'))
                    ->url(route('companies.add'))
                    ->visible(fn () => auth()->user()->can('Company Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('company_category_id')
                ->label(__('Company Category'))
                ->options(fn () => CompanyCategory::with('translations')->get()->pluck('name', 'id'))
                ->searchable(),

            SelectFilter::make('status')
                ->label(__('Company Status'))
                ->options(CompanyStatus::options())
                ->searchable(),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        abort_unless(auth()->user()?->can('Company Delete'), 403);

                        $records->each->delete();
                    })
                    ->visible(fn () => auth()->user()->can('Company Delete')),
            ]),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Company Info')),
            // ViewAction::make('view')
            //     ->form(function (Forms\Form $form, $record) {
            //         return $form->schema([
            //             TextInput::make('name')
            //                 ->label(__('Name'))
            //                 ->default($record->name)
            //                 ->disabled(),
            //             TextInput::make('website')
            //                 ->label(__('Website'))
            //                 ->default($record->website)
            //                 ->disabled(),
            //             TextInput::make('category.name')
            //                 ->label(__('Category'))
            //                 ->default($record->category->name)
            //                 ->disabled(),
            //             Textarea::make('description')
            //                 ->default($record->description)
            //                 ->disabled(),
            //         ]);
            //     })
            //     ->modalSubmitAction(false)
            //     ->visible(fn () => auth()->user()->can('Company View')),

            // Action::make('details')
            //     ->label('')
            //     ->size('xl')
            //     ->icon('heroicon-o-user')
            //     ->url(fn ($record) => route('companies.details', $record))
            //     ->visible(fn () => auth()->user()->can('Company Details List')),

            EditAction::make('edit')
                ->url(fn (Company $record) => route('companies.edit', $record->id))
                ->visible(fn () => auth()->user()->can('Company Update')),

            // DeleteAction::make('delete')
            //     ->action(function ($record) {
            //         $this->authorize('Company Delete');
            //         $record->delete();
            //         Toaster::success(__('Company deleted successfully'));
            //     })
            //     ->visible(fn () => auth()->user()->can('Company Delete')),
        ];
    }

    protected function currentStudentRows(Company $company)
    {
        return $company->currentStudentCompanies()
            ->with([
                'student.studentProfile.major.translations',
                'registration.course.translations',
                'branch.translations',
                'department.translations',
            ])
            ->latest()
            ->get()
            ->unique('student_id')
            ->values()
            ->map(function (StudentCompany $studentCompany): array {
                $student = $studentCompany->student;
                $registration = $studentCompany->registration;

                return [
                    'student_name' => $student?->name ?? '-',
                    'student_number' => $student?->studentProfile?->student_number ?? '-',
                    'email' => $student?->email ?? '-',
                    'course' => $registration?->course?->name ?? '-',
                    'semester' => $registration?->semester?->getLabel() ?? '-',
                    'year' => $registration?->year ?? '-',
                    'branch' => $studentCompany->branch?->name ?? '-',
                    'department' => $studentCompany->department?->name ?? '-',
                    'student_url' => ($student && auth()->user()->can('Student Details List'))
                        ? route('students.details', $student->id)
                        : null,
                    'record_url' => auth()->user()->can('StudentCompany Details')
                        ? route('student-companies.details', $studentCompany)
                        : null,
                ];
            });
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
            ],
        ]);
    }
}
