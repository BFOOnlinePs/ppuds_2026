<?php

namespace Modules\Customer\Livewire\Pages\Customer\Details\Views;

use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\Response;
use Modules\Clinic\Entities\Answer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Entities\Customer;

class Surveys extends Component
{
    public $surveys;
    public $answers = [];
    public $customer;

    public function mount($surveys, $customer = null)
    {
        $this->surveys = $surveys;
        $this->customer = $customer;

        if ($this->customer) {
            if($this->surveys)
            {
                $existingResponse = Response::where('customer_id', $this->customer->id)
                    ->where('survey_id', $this->surveys->id)
                    ->with('answers')
                    ->first();

                if ($existingResponse) {
                    foreach ($existingResponse->answers as $answer) {
                        $questionKey = "question-{$answer->question_id}";
                        $answerText = $answer->answer_text;

                        if ($this->surveys->questions->find($answer->question_id) && $this->surveys->questions->find($answer->question_id)->type === \Modules\Clinic\Enums\QuestionType::CHECKBOX) {
                            $this->answers[$questionKey] = json_decode($answerText, true);
                        } else {
                            $this->answers[$questionKey] = $answerText;
                        }
                    }
                }

                foreach ($this->surveys->questions as $question) {
                    if ($question->type === \Modules\Clinic\Enums\QuestionType::CHECKBOX && !isset($this->answers["question-{$question->id}"])) {
                        $this->answers["question-{$question->id}"] = [];
                    }
                }
            }
        }

    }

    // في Surveys.php
    public function save()
    {
        try {
            DB::beginTransaction();

            $response = $this->customer->responses()->firstOrCreate(
                ['survey_id' => $this->surveys->id],
                ['created_by' => Auth::id()]
            );

            foreach ($this->surveys->questions as $question) {
                $questionKey = "question-{$question->id}";
                if (isset($this->answers[$questionKey])) {
                    $answerValue = is_array($this->answers[$questionKey])
                        ? json_encode($this->answers[$questionKey])
                        : $this->answers[$questionKey];

                    $response->answers()->updateOrCreate(
                        ['question_id' => $question->id],
                        ['answer_text' => $answerValue, 'created_by' => Auth::id()]
                    );
                }
            }

            DB::commit();
            Toaster::success('تم حفظ الإجابات بنجاح!');
        } catch (\Exception $e) {
            DB::rollBack();
            Toaster::error('حدث خطأ أثناء حفظ الإجابات: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.details.views.surveys');
    }
}
