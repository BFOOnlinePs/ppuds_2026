<?php

namespace Modules\Core\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Closure;

class FileUploadWithPreview extends FileUpload
{
    protected string $view = 'core::filament.forms.components.file-upload';
}
