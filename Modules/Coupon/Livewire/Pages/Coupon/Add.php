<?php

namespace Modules\Coupon\Livewire\Pages\Coupon;

use App\View\Components\AppLayout;
use Dom\Text;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Coupon\Entities\Coupon;
use Modules\Coupon\Enums\CouponType;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Coupon::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('code')
                                            ->required()
                                            ->label(__('Code'))
                                            ->columnSpanFull()
                                            ->unique(Coupon::class, 'code'),

                                        Select::make('type')
                                            ->label(__('Type'))
                                            ->options(CouponType::options())
                                            ->searchable()
                                            ->columnSpan(2)
                                            ->required(),

                                        TextInput::make('value')
                                            ->required()
                                            ->label(__('Value'))
                                            ->numeric()
                                            ->columnSpan(2),

                                        TextInput::make('max_uses')
                                            ->required()
                                            ->label(__('Max Uses'))
                                            ->numeric()
                                            ->columnSpan(2),


                                        TextInput::make('min_order_amount')
                                            ->required()
                                            ->label(__('Min Order Amount'))
                                            ->numeric()
                                            ->columnSpan(2),
                                    ])
                            ]),

                        Section::make()
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('starts_at')
                                    ->label(__('Starts At'))
                                    ->type('datetime-local')
                                    ->columnSpan(1)
                                    ->required(),

                                TextInput::make('expires_at')
                                    ->label(__('Expires At'))
                                    ->type('datetime-local')
                                    ->columnSpan(1)
                                    ->required(),
                            ])
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.slug.required' => __('Slug is required'),
            'data.image.required' => __('Image is required'),
            'data.image.image' => __('The file must be a valid image.'),
            'data.image.mimes' => __('Image must be: JPG, PNG, GIF, or WebP.'),
            'data.image.max' => __('Image size must not exceed 3MB.'),
            'data.image.dimensions' => __('Image must be at least 200x200 pixels.'),
        ];
    }


    public function save()
    {
        $this->validate();

        $this->data['created_by'] = auth()->user()->id;

        $coupon = Coupon::create($this->data);

        $this->redirectRoute('coupons.index');
    }

    public function render()
    {
        return view('coupon::livewire.pages.coupon.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Coupons List'), 'url' => route('coupons.index')],
                ['title' => __('Add Coupon'), 'url' => route('coupons.add')],
            ]
        ]);
    }
}
