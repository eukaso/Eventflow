<?php
namespace EventFlow\Application\Export;
enum ExportType:string{case ATTENDEES='attendees';case INVITATIONS='invitations';case CHECK_INS='check_ins';case EVENT_SUMMARY='event_summary';public function containsPii():bool{return $this!==self::EVENT_SUMMARY;}}
