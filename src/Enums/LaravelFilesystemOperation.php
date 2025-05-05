<?php

namespace Spatie\LaravelFlare\Enums;

enum LaravelFilesystemOperation: string
{
    case GetVisibility = 'get_visibility';
    case SetVisibility = 'set_visibility';
    case LastModified = 'last_modified';
    case Checksum = 'checksum';
    case MimeType = 'mime_type';
    case TemporaryUrl = 'temporary_url';
    case TemporaryUploadUrl = 'temporary_upload_url';

    case AssertExists = 'assert_exists';
    case AssertMissing = 'assert_missing';
    case AssertEmpty = 'assert_empty';
    case AssertDirectoryEmpty = 'assert_directory_empty';
}
