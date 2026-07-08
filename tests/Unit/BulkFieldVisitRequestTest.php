<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Modules\PPUDS\Http\Requests\BulkFieldVisitRequest;
use Tests\TestCase;

class BulkFieldVisitRequestTest extends TestCase
{
    public function test_bulk_field_visit_request_collects_all_attachment_inputs(): void
    {
        $attachment = UploadedFile::fake()->create('visit-report.pdf', 20, 'application/pdf');
        $image = UploadedFile::fake()->image('visit-photo.jpg');
        $attachmentListFile = UploadedFile::fake()->create('extra.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $imageListFile = UploadedFile::fake()->image('extra-image.png');

        $request = BulkFieldVisitRequest::create('/api/v1/ppuds/field-visits/bulk', 'POST');
        $request->files->set('attachment', $attachment);
        $request->files->set('image', $image);
        $request->files->set('attachments', [$attachmentListFile]);
        $request->files->set('images', [$imageListFile]);

        $files = $request->attachmentFiles();

        $this->assertSame(
            [$attachment, $image, $attachmentListFile, $imageListFile],
            $files
        );
    }

    public function test_bulk_field_visit_request_accepts_file_validation_keys(): void
    {
        $rules = (new BulkFieldVisitRequest())->rules();

        $this->assertArrayHasKey('attachment', $rules);
        $this->assertArrayHasKey('image', $rules);
        $this->assertArrayHasKey('attachments', $rules);
        $this->assertArrayHasKey('attachments.*', $rules);
        $this->assertArrayHasKey('images', $rules);
        $this->assertArrayHasKey('images.*', $rules);
    }
}
