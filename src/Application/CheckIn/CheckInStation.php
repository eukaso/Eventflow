<?php
namespace EventFlow\Application\CheckIn;
final readonly class CheckInStation { public function __construct(public int $stationId, public string $name, public ?string $code) { if ($stationId < 1 || trim($name)==='') throw new CheckInException('checkin_station_invalid'); } }
