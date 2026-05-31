<?php

namespace Modules\Core\Livewire;

use App\View\Components\GuestLayout;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Core\Filament\Forms\Components\TextInput;
use Modules\Core\Services\ModulePackageService;
use Nwidart\Modules\Facades\Module;

class ModuleSelector extends Component
{

    public $search = '';
    public $selectedFeature = null;
    public $showModal = false;

    protected ModulePackageService $packageService;

    public function boot(ModulePackageService $packageService)
    {
        $this->packageService = $packageService;
    }

    public function mount()
    {
//        dd($this->packageService->getAvailableFeatures());
    }

    #[Computed()]
    public function getAvailableFeatures()
    {
        return $this->packageService->getAvailableFeatures();
    }

    public function selectFeature($feature)
    {
        $modules = Module::all();
        foreach ($modules as $module) {
            if ($module->isStatus(true)) {
                $module->disable();
//                $this->info("Module '{$module->getName()}' has been disabled.");
            } else {
//                $this->comment("Module '{$module->getName()}' is already disabled.");
            }
        }

        $packageModules = collect($this->packageService->getFeature($feature)['modules']);
        $packageModules->each(function ($moduleName) {
            $module = Module::findOrFail($moduleName);
            if ($module) {
                $module->enable();
//                $this->info("Module '{$moduleName}' has been enabled.");
            } else {
//                $this->comment("Module '{$moduleName}' not found.");
            }
        });

        session()->put('selected_module', $feature);
        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('core::livewire.module-selector')->layout(GuestLayout::class);
    }
}
