<?php
namespace EventFlow\Application\Import;
final readonly class UploadedFile{public function __construct(public string$clientFilename,public string$temporaryPath,public int$reportedSize,public int$errorCode){}}
