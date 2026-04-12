<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;


class Details extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public function mount($studentCompany)
    {
        dd($studentCompany);
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(StudentCompany::class)
            ->schema([

            ])
            ->statePath('data');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-company.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
                ['title' => __('New Student Company'), 'url' => '#'],
            ]
        ]);
    }
}
