<?php
namespace EventFlow\Infrastructure\Import;
use EventFlow\Application\Import\{ImportException,ImportUploadGuard,UploadedFile};
final readonly class HardenedImportUploadGuard implements ImportUploadGuard
{
 private const MAX_BYTES=26214400;private const MIME=['csv'=>['text/plain','text/csv','application/csv','application/vnd.ms-excel'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip']];
 public function __construct(private UploadedFileRuntime$runtime=new NativeUploadedFileRuntime()){}
 public function withTrustedUpload(UploadedFile$file,callable$operation):mixed{$name=basename(str_replace('\\','/',$file->clientFilename));$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));if($file->errorCode!==UPLOAD_ERR_OK||$name!==$file->clientFilename||$name===''||strlen($name)>255||preg_match('/[\x00-\x1F\x7F]/',$name)||!isset(self::MIME[$extension])||!$this->runtime->isUploaded($file->temporaryPath))throw new ImportException('import_upload_invalid');$actual=$this->runtime->size($file->temporaryPath);$mime=$this->runtime->mime($file->temporaryPath);if($actual===null||$actual<1||$actual>self::MAX_BYTES||$actual!==$file->reportedSize||$mime===null||!in_array($mime,self::MIME[$extension],true))throw new ImportException('import_upload_invalid');$trusted=$this->runtime->temporaryPath($extension);if(!$this->runtime->move($file->temporaryPath,$trusted))throw new ImportException('import_upload_staging_failed');try{return$operation($trusted,$name);}finally{$this->runtime->delete($trusted);}}
}
