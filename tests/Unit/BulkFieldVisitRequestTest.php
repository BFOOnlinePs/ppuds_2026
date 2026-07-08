<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Modules\PPUDS\Http\Requests\BulkFieldVisitRequest;
use Tests\TestCase;

class BulkFieldVisitRequestTest extends TestCase
{
    public function test_bulk_field_visit_request_collects_images_and_files_from_attachments(): void
    {
        $document = UploadedFile::fake()->create('visit-report.pdf', 20, 'application/pdf');
        $image = UploadedFile::fake()->image('visit-photo.jpg');

        $request = BulkFieldVisitRequest::create('/api/v1/ppuds/field-visits/bulk', 'POST');
        $request->files->set('attachments', [$document, $image]);

        $files = $request->attachmentFiles();

        $this->assertSame(
            [$document, $image],
            $files
        );
    }

    public function test_bulk_field_visit_request_keeps_legacy_attachment_aliases(): void
    {
        $attachment = UploadedFile::fake()->create('visit-report.pdf', 20, 'application/pdf');
        $misspelledAttachment = UploadedFile::fake()->create('typo-name.pdf', 20, 'application/pdf');
        $image = UploadedFile::fake()->image('visit-photo.jpg');
        $imageListFile = UploadedFile::fake()->image('extra-image.png');

        $request = BulkFieldVisitRequest::create('/api/v1/ppuds/field-visits/bulk', 'POST');
        $request->files->set('attachment', $attachment);
        $request->files->set('attachemnts', [$misspelledAttachment]);
        $request->files->set('image', $image);
        $request->files->set('images', [$imageListFile]);

        $files = $request->attachmentFiles();

        $this->assertSame(
            [$attachment, $misspelledAttachment, $image, $imageListFile],
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
        $this->assertArrayHasKey('attachemnts', $rules);
        $this->assertArrayHasKey('attachemnts.*', $rules);
        $this->assertArrayHasKey('images', $rules);
        $this->assertArrayHasKey('images.*', $rules);
    }
}
