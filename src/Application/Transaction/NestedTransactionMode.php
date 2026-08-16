<?php

namespace EventFlow\Application\Transaction;

enum NestedTransactionMode: string
{
    case JOIN = 'join';
    case SAVEPOINT = 'savepoint';
}
