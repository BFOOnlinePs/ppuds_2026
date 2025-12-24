<?php

namespace Modules\Customer\Livewire\Pages\Customer;

use App\View\Components\AppLayout;
use Carbon\Carbon;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Enums\Status;
use Modules\Items\Entities\Brand;
use Filament\Tables\Actions\Action;
use Livewire\Livewire;
use Modules\Clinic\Enums\PaymentMethod;
use Modules\Core\Enums\TransactionFlow;
use Modules\Core\Enums\TransactionType;
use Modules\Core\Interfaces\TransactionLoggerInterface;
use Modules\Customer\Livewire\Pages\Customer\Details\Tables\Transaction;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Customer::query()->with('createdBy'))
            ->heading(__('Customers'))
            ->emptyStateHeading(__('No customers found'))
            ->emptyStateDescription(__('Create a new customer by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Customer'))
                    ->url(route('customers.add'))
                    ->visible(fn() => auth()->user()->can('Customer Create'))
            ])
            ->columns(components: [
                ImageColumn::make('image')
                    ->label(__('Image'))
                    ->getStateUsing(function ($record) {
                        return $record->user->getProfileImageUrlAttribute();
                    }),
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->searchable(
                        query: function (Builder $query, string $search) {
                            $query->whereHas('user', function (Builder $query) use ($search) {
                                $query->where('name', 'like', "%{$search}%");
                            });
                        }
                    )
                    ->state(function ($record) {
                        return $record->getNameAttribute();
                    })
                    ->url(function ($record) {
                        return route('customers.edit', $record);
                    }),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable(),
                TextColumn::make('user.phone')
                    ->label(__('Phone'))
                    ->searchable(),
                TextColumn::make('gender')
                    ->label(__('Gender')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn(Status $state): string => $state->getColor()),
                IconColumn::make('subscription_status')
                    ->label('حالة الاشتراك')
                    ->state(function ($record): bool|string {
                        if ($record->subscriptions()->where('status', \Modules\Subscription\Enums\Status::ACTIVE)->exists()) {
                            return true;
                        }
                        if ($record->subscriptions()->where('status', \Modules\Subscription\Enums\Status::FROZEN)->exists()) {
                            return 'frozen';
                        }
                        return false;
                    })
                    ->icon(fn(string|bool $state): string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        'frozen' => 'solar-snowflake-bold-duotone',
                        false => 'heroicon-o-x-circle',
                    })
                    ->color(fn(string|bool $state): string => match ($state) {
                        true => 'success',
                        'frozen' => 'primary',
                        false => 'danger',
                    })
                    ->tooltip(function ($record): string {
                        $latestSubscription = $record->subscriptions()
                            ->whereIn('status', [\Modules\Subscription\Enums\Status::ACTIVE, \Modules\Subscription\Enums\Status::FROZEN])
                            ->orderBy('end_date', 'desc')
                            ->first();

                        if ($latestSubscription) {
                            $statusLabel = $latestSubscription->status->getLabel();
                            $endDate = $latestSubscription->end_date;
                            $dateFormatted = $endDate->format('Y-m-d');

                            if ($latestSubscription->status === \Modules\Subscription\Enums\Status::ACTIVE) {
                                $remainingDays = now()->diffInDays($endDate, false);
                                return "{$statusLabel} | ينتهي في: {$dateFormatted} (باقي {$remainingDays} يوم)";
                            }

                            return "{$statusLabel} | ينتهي في: {$dateFormatted}";
                        }

                        return 'لا يوجد اشتراك فعال أو مجمد';
                    }),
                TextColumn::make('language')
                    ->label(__('Language'))
                //                    ->state(function ($state) {
                //                        return $state->label();
                //                    })
                //                TextColumn::make('locale')
                //                    ->label(__('Locale'))
                //                    ->getStateUsing(function ($record) {
                //                        return $record->translations->pluck('locale')->join(', ');
                //                    })
                //                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Customer'))
                    ->url(route('customers.add'))
                    ->visible(fn() => auth()->user()->can('Customer Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * The table filters.
     *
     * @return array
     */
    /*******  923c272c-8fa7-4881-8754-4bd584d02cc1  *******/
    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder(__('Search by name'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['name'])) {
                        $query->whereHas('user', function (Builder $query) use ($data) {
                            $query->where('name', 'like', "%{$data['name']}%");
                        });
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ActionGroup::make([
                InfoAction::make('info')
                    ->label(__('Info'))
                    ->visible(fn() => auth()->user()->can('Customer Info')),
                ViewAction::make('view')
                    ->label(__('View'))
                    ->form(function (Forms\Form $form, $record) {
                        return $form->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Name'))
                                        ->default($record->name)
                                        ->disabled(),

                                    TextInput::make('slug')
                                        ->label(__('Slug'))
                                        ->default($record->slug)
                                        ->disabled()
                                ]),
                            Grid::make(1)
                                ->schema([
                                    Textarea::make('description')
                                        ->label(__('Description'))
                                        ->default($record->description)
                                        ->disabled(),
                                ]),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->visible(fn() => auth()->user()->can('Customer View')),

                Action::make('details')
                    ->label('Details')
                    ->size('xl')
                    ->tooltip(__('Details'))
                    ->color('warning')
                    ->icon('solar-map-point-search-bold')
                    ->url(fn($record) => route('customers.details', $record))
                    ->visible(fn() => Module::isEnabled('subscription')),

                Action::make('add_deposit')
                    ->label(__('Add Deposit'))
                    ->size(ActionSize::Small)
                    ->tooltip(__('Add Prepayment Deposit'))
                    ->color('success')
                    ->form(function (Forms\Form $form, $record) {
                        return $form->schema([
                            Grid::make(3)
                                ->schema([
                                    TextInput::make('amount')
                                ->label(__('Deposit Amount'))
                                ->required()
                                ->numeric()
                                ->prefix('₪'),
                            Select::make('payment_method')
                                ->label(__('Payment Method'))
                                ->required()
                                ->options(PaymentMethod::options())
                                ->live()
                                ->searchable(),
                            DatePicker::make('date')
                                ->label(__('Payment Date'))
                                ->default(now()->format('Y-m-d'))
                                ->required(),
                            TextInput::make('reference_no')
                                ->label(__('Reference Number'))
                                ->columnSpanFull()
                                ->nullable()
                                ->visible(fn(Get $get) => $get('payment_method') == PaymentMethod::BANK_TRANSFER->value || $get('payment_method') == PaymentMethod::CREDIT_CARD->value),
                            Textarea::make('notes')
                                ->columnSpanFull()
                                ->label(__('Notes')),

                            ViewField::make('transactions_view')
                                ->view('customer::components.transactions', ['customer' => $record])
                                ->columnSpanFull(),
                                ]),
                        ]);
                    })
                    ->action(function (array $data, $record) {
                        $amount = $data['amount'];
                        $paymentMethod = $data['payment_method'];
                        $referenceNo = $data['reference_no'] ?? null;
                        $notes = $data['notes'] ?? '';

                        app(TransactionLoggerInterface::class)->log(
                          sourceDocument: $record,
                          flow: TransactionFlow::INCOME->value,
                          amount:$amount,
                          paymentMethod: $paymentMethod,
                          referenceNo: $referenceNo,
                          sourceTypeEnum: TransactionType::PrepaymentDeposit,
                          description: __('Customer added a prepayment deposit.') . " " . $notes,
                          relatedEntity: $record,
                        );
                    })
                ->visible(fn() => auth()->user()->can('Customer Deposit Create')),

                Action::make('freeze_subscription')
                    ->label(__('Freeze Subscription'))
                    ->size(ActionSize::ExtraLarge)
                    ->tooltip('Freeze subscription')
                    ->color('primary')
                    ->icon('solar-snowflake-bold')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label('سبب التجميد')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('duration_days')
                            ->label('مدة التجميد بالأيام (اختياري)')
                            ->helperText('اتركه فارغاً ليكون التجميد مفتوحاً حتى إعادة التفعيل اليدوي.')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->action(function (array $data, $record) {
                        // $record هو العميل (Customer)

                        // 1. ابحث عن كل الاشتراكات النشطة فقط لهذا العميل
                        $activeSubscriptions = $record->subscriptions()
                            ->where('status', \Modules\Subscription\Enums\Status::ACTIVE)
                            ->get();

                        if ($activeSubscriptions->isEmpty()) {
                            Toaster::warning(__('This customer has no active subscriptions to freeze'));
                            return;
                        }

                        // 3. استخدم Transaction لضمان تنفيذ العملية كلها أو عدم تنفيذها إطلاقاً
                        \Illuminate\Support\Facades\DB::transaction(function () use ($activeSubscriptions, $data, $record) {

                            $duration = isset($data['duration_days']) ? (int)$data['duration_days'] : null;

                            foreach ($activeSubscriptions as $subscription) {

                                $newEndDate = $subscription->end_date;
                                $freezeEndDate = null;

                                if ($duration) {
                                    $newEndDate = Carbon::parse($subscription->end_date)->addDays($duration);
                                    $freezeEndDate = now()->addDays($duration);
                                }

                                // تحديث بيانات الاشتراك الحالي في اللوب
                                $subscription->status = \Modules\Subscription\Enums\Status::FROZEN;
                                $subscription->end_date = $newEndDate;
                                $subscription->save();

                                // إنشاء سجل تجميد مرتبط بالاشتراك الحالي
                                $subscription->freezes()->create([
                                    'start_date' => now(),
                                    'end_date' => $freezeEndDate,
                                    'reason' => $data['reason'],
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        });

                        Toaster::success(__('All active subscriptions have been successfully frozen'));
                    })
                    ->visible(fn() => Module::isEnabled('subscription')),

                // In: Modules/Customer/Livewire/Pages/Customer/Index.php
                // Inside getTableActions() array

                // In: Modules/Customer/Livewire/Pages/Customer/Index.php
                // In: Modules/Customer/Livewire/Pages/Customer/Index.php
                Action::make('unfreeze_subscription')
                    ->label('Unfreeze Subscription')
                    ->tooltip('إلغاء تجميد الاشتراكات')
                    ->size(ActionSize::Small)
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) { // $record هو العميل
                        $frozenSubscriptions = $record->subscriptions()
                            ->where('status', \Modules\Subscription\Enums\Status::FROZEN)
                            ->get();

                        if ($frozenSubscriptions->isEmpty()) {
                            Toaster::warning('لا يملك هذا العميل أي اشتراكات مجمدة.');
                            return;
                        }

                        \Illuminate\Support\Facades\DB::transaction(function () use ($frozenSubscriptions) {
                            foreach ($frozenSubscriptions as $subscription) {

                                // ================== 👇 هذا هو السطر الذي تم إصلاحه 👇 ==================
                                // بدلاً من البحث عن سجل نهايته NULL، نبحث عن آخر سجل تم إنشاؤه
                                $activeFreeze = $subscription->freezes()->latest()->first();
                                // ================== 👆 نهاية الإصلاح 👆 ==================

                                if ($activeFreeze) {
                                    // الآن سيدخل هذا الشرط ويعمل بشكل صحيح دائماً

                                    $actualDaysFrozen = $activeFreeze->start_date->diffInDays(now());
                                    $newEndDate = $subscription->end_date;

                                    // هذا الشرط سيعمل الآن بشكل صحيح ليفرق بين الحالتين
                                    if ($activeFreeze->end_date) {
                                        // حالة التجميد المحدد المدة
                                        $plannedDuration = $activeFreeze->start_date->diffInDays($activeFreeze->end_date);
                                        $originalEndDate = $subscription->end_date->subDays($plannedDuration);
                                        $newEndDate = $originalEndDate->addDays($actualDaysFrozen);
                                    } else {
                                        // حالة التجميد المفتوح
                                        $newEndDate = $subscription->end_date->addDays($actualDaysFrozen);
                                    }

                                    $subscription->status = \Modules\Subscription\Enums\Status::ACTIVE;
                                    $subscription->end_date = $newEndDate;
                                    $subscription->save();

                                    // هنا نقوم بتحديث تاريخ نهاية التجميد إلى "الآن" لتمييزه كمغلق
                                    $activeFreeze->end_date = now();
                                    $activeFreeze->save();
                                }
                            }
                        });

                        Toaster::success('تم إلغاء تجميد جميع الاشتراكات بنجاح.');
                    })
                    ->visible(fn($record): bool => $record->subscriptions()->where('status', \Modules\Subscription\Enums\Status::FROZEN)->exists()),

                EditAction::make('edit')
                    ->label('Edit')
                    ->url(fn($record) => route('customers.edit', $record))
                    ->visible(fn() => auth()->user()->can('Currency Update')),

                DeleteAction::make('delete')
                    ->label(__('Delete'))
                    ->action(function ($record) {
                        $record->forceDelete();

                        Toaster::success(__('Customer deleted successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Customer Delete')),
            ])
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('Customer Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Customers List'), 'url' => route('customers.index')],
            ]
        ]);
    }
}
