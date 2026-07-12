<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Modules\PPUDS\Http\Requests\StudentAttendanceReportRequest;
use Tests\TestCase;

class StudentAttendanceReportRequestTest extends TestCase
{
    public function test_pdf_image_doc_and_docx_are_accepted(): void
    {
        foreach (['pdf', 'jpg', 'png', 'doc', 'docx'] as $extension) {
            $file = UploadedFile::fake()->create("report.{$extension}", 100);

            $errors = $this->validate(['file_report' => $file]);

            $this->assertArrayNotHasKey('file_report', $errors, "Expected .{$extension} to be accepted");
        }
    }

    public function test_disallowed_file_type_fails_with_arabic_reason(): void
    {
        App::setLocale('ar');

        $file = UploadedFile::fake()->create('malware.exe', 100);

        $errors = $this->validate(['file_report' => $file]);

        $this->assertArrayHasKey('file_report', $errors);
        $this->assertSame(
            'يجب أن يكون الملف المرفق من نوع PDF أو مستند Word (doc, docx) أو صورة (jpg, jpeg, png, webp, gif).',
            $errors['file_report'][0]
        );
    }

    public function test_oversized_file_fails_with_arabic_reason(): void
    {
        App::setLocale('ar');

        $file = UploadedFile::fake()->create('report.pdf', 20 * 1024); // 20MB > 10MB limit

        $errors = $this->validate(['file_report' => $file]);

        $this->assertArrayHasKey('file_report', $errors);
        $this->assertSame(
            'يجب ألا يتجاوز حجم الملف المرفق 10MB.',
            $errors['file_report'][0]
        );
    }

    public function test_too_many_files_fails_with_arabic_reason(): void
    {
        App::setLocale('ar');

        $files = array_map(
            fn (int $i) => UploadedFile::fake()->create("report{$i}.pdf", 100),
            range(1, 6)
        );

        $errors = $this->validate(['file_report' => $files]);

        $this->assertArrayHasKey('file_report', $errors);
        $this->assertSame('يمكنك إرفاق 5 ملفات كحد أقصى.', $errors['file_report'][0]);
    }

    private function validate(array $data): array
    {
        $request = new StudentAttendanceReportRequest();
        $request->files->set('file_report', $data['file_report']);

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );

        return $validator->errors()->toArray();
    }
}
