<?php

namespace Modules\PPUDS\Support;

/**
 * قيود ملفات إجابات الاستبيان، مشتركة بين نموذج الويب والـ API حتى لا يختلفا.
 */
final class SurveyAnswerFiles
{
    /** قرص خاص: الملف لا يُفتح برابط عام، بل عبر surveys.answer-file بعد التحقق من الصلاحية. */
    public const DISK = 'local';

    /** كل ملفات الإجابات تحت هذا المجلد، ولا يُفتح من خارجه شيء. */
    public const DIRECTORY = 'surveys';

    public const MAX_KB = 20480;

    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'heic', 'webp', 'zip'];

    public const MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/webp',
        'application/zip',
        'application/x-zip-compressed',
    ];

    /** @return list<string> */
    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::EXTENSIONS)];
    }
}
