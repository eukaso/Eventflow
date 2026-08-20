<?php
namespace EventFlow\Infrastructure\Import;
use EventFlow\Application\Import\ImportException;
final readonly class NativeUploadedFileRuntime implements UploadedFileRuntime
{public function isUploaded(string$p):bool{return is_uploaded_file($p);}public function size(string$p):?int{$size=filesize($p);return$size===false?null:$size;}public function mime(string$p):?string{if(!class_exists(\finfo::class))return null;$finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($p);return is_string($mime)?strtolower($mime):null;}public function temporaryPath(string$extension):string{$base=tempnam(sys_get_temp_dir(),'eventflow-import-');if($base===false)throw new ImportException('import_upload_staging_failed');if(!unlink($base))throw new ImportException('import_upload_staging_failed');return$base.'.'.$extension;}public function move(string$from,string$to):bool{return move_uploaded_file($from,$to);}public function delete(string$p):void{if(is_file($p))@unlink($p);}}
