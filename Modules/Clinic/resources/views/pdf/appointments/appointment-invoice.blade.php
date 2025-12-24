@extends('core::pdf.app')

@section('content')

    <div class="header-title">
        <h2>تقرير المعاملات المالية</h2>
        <p>تاريخ الطباعة: {{ date('Y-m-d H:i') }}</p>
    </div>

    <div class="table-container">
        <table dir="rtl">
            <thead>
                <tr>
                    <th style="width: 15%">التاريخ</th>
                    <th style="width: 25%">الوصف / المصدر</th>
                    <th style="width: 20%">الطرف المرتبط</th>
                    <th style="width: 15%">النوع</th>
                    <th style="width: 20%;" class="amount-col">القيمة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['transactions'] as $transaction)
                    <tr>
                        <td>
                            <div>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('Y-m-d') }}</div>
                            <div class="meta-info">
                                {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('h:i A') }}
                            </div>
                        </td>

                        <td>
                            <div style="font-weight: bold;">
                                {{ $transaction->description ?? '-' }}
                            </div>
                            <div class="meta-info">
                                {{ $transaction->source_module }} | {{ $transaction->source_type }}
                            </div>
                        </td>

                        <td>
                            @if($transaction->related_entity)
                                {{ $transaction->related_entity->name ?? $transaction->related_entity->full_name ?? class_basename($transaction->related_entity) }}
                            @else
                                <span style="color: #999">-</span>
                            @endif
                        </td>

                        <td>
                            @if($transaction->flow === 'INCOME')
                                <span class="badge badge-income">دخل</span>
                            @else
                                <span class="badge badge-expense">مصروف</span>
                            @endif
                        </td>

                        <td class="amount-col {{ $transaction->flow === 'INCOME' ? 'text-success' : 'text-danger' }}">
                            {{ $transaction->flow === 'EXPENSE' ? '-' : '+' }}
                            {{ number_format($transaction->amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            لا توجد معاملات مسجلة في هذا التقرير.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            <tfoot>
                <tr style="background-color: #f3f4f6; font-weight: bold;">
                    <td colspan="4" style="text-align: right; padding-right: 10px;">
                        الإجمالي النهائي:
                    </td>
                    <td class="amount-col" style="border-top: 2px solid #aaa;">
                        {{ number_format(
                            $data['transactions']->where('flow', 'INCOME')->sum('amount')
                            - $data['transactions']->where('flow', 'EXPENSE')->sum('amount'),
                            2
                        ) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
