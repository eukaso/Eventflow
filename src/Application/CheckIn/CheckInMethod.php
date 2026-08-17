<?php
namespace EventFlow\Application\CheckIn;
enum CheckInMethod: string { case MANUAL='manual'; case SEARCH='search'; case GUEST_LIST='guest_list'; case QR_CODE='qr_code'; }
