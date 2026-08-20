<?php
namespace EventFlow\Infrastructure\Import;
interface UploadedFileRuntime{public function isUploaded(string$path):bool;public function size(string$path):?int;public function mime(string$path):?string;public function temporaryPath(string$extension):string;public function move(string$from,string$to):bool;public function delete(string$path):void;}
