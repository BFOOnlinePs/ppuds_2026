<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Services\StudentCompanyPlacementImporter;
use Modules\PPUDS\Settings\GeneralSettings;

class ImportPlacements extends Component
{
    use WithFileUploads;

    public $placementImportFile = null;

    public string $academicYear;

    public string $semester;

    public bool $previewOnly = true;

    public bool $updateExisting = false;

    public bool $useLatestRegistration = false;

    public bool $resultWasPreview = false;

    public array $result = [];

    public function mount(GeneralSettings $settings): void
    {
        $this->academicYear = (string) ($settings->year ?? date('Y'));
        $this->semester = (string) ($settings->semester_type?->value ?? SemesterType::FIRST->value);
    }

    public function importPlacements(StudentCompanyPlacementImporter $importer): void
    {
        $this->authorize('StudentCompany Create');

        $this->validate([
            'placementImportFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'academicYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
        ]);

        try {
            $this->result = $importer->import($this->placementImportFile->getRealPath(), [
                'year' => (int) $this->academicYear,
                'semester' => (int) $this->semester,
                'created_by' => auth()->id(),
                'update_existing' => $this->updateExisting,
                'use_latest_registration' => $this->useLatestRegistration,
                'dry_run' => $this->previewOnly,
            ]);

            $this->resultWasPreview = $this->previewOnly;

            if (! $this->previewOnly) {
                $this->placementImportFile = null;
            }

            Toaster::success($this->previewOnly
                ? __('Placement file preview completed successfully')
                : __('Placement file imported successfully'));
        } catch (\Throwable $e) {
            $this->addError('placementImportFile', $e->getMessage());
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-company.import-placements', [
            'semesterOptions' => SemesterType::options(),
        ])->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
                ['title' => __('Import Placements'), 'url' => route('student-companies.import')],
            ],
        ]);
    }
}
