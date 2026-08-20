<?php
namespace EventFlow\Application\Import;
interface ImportUploadGuard{/** @param callable(string,string):mixed $operation */public function withTrustedUpload(UploadedFile$file,callable$operation):mixed;}
