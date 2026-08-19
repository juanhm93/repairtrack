<?php

namespace App\Enums;

enum TicketNotificationType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
}
