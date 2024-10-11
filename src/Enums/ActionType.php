<?php

namespace Crehler\EdroneCrm\Enums;

enum ActionType: string
{
    case ORDER = 'order';
    case ORDER_CANCEL = 'order_cancel';
    case OTHER = 'other';
}
